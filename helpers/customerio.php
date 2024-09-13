<?php

declare(strict_types=1);


use Customerio\Client; // printu/customerio
use GuzzleHttp\Exception\GuzzleException;

class CustomerIO
{
    private static ?Client $clientInstance = null;

    public static function client(): Client
    {
        if (self::$clientInstance === null) {
            self::$clientInstance = new Client(
                $_SERVER['CIO_TRACK_API_KEY'] ?? '',
                $_SERVER['CIO_TRACK_SITE_ID'] ?? '',
                ['region' => $_SERVER['CIO_REGION'] ?? '']
            );
            self::$clientInstance->setAppAPIKey($_SERVER['CIO_APP_API_KEY'] ?? '');
        }
        return self::$clientInstance;
    }

    public static function identify(string $emailOrId, array $attributes = []): object
    {
        $client = self::client();
        $updateMode = false;

        if (str_contains($emailOrId, '@')) {
            $attributes['email'] = $attributes['email'] ?? $emailOrId;
            $cio = $client->customers->get(['email' => $emailOrId]);
            if (!empty($cio->results)) {
                $attributes['cio_id'] = $cio->results[0]->cio_id;
                $updateMode = true;
            } else {
                $attributes['created_at'] = $attributes['created_at'] ?? time();
            }
        } else {
            $attributes['id'] = $emailOrId;
            $updateMode = true;
        }

        try {
            $updateMode ? $client->customers->update($attributes) : $client->customers->add($attributes);
        } catch (\Exception $e) {
            return (object)['success' => false, 'data' => 'Failed to add contact'];
        }

        if (!$updateMode && isset($attributes['email'])) {
            $attributes['cio_id'] = self::findCustomerId($attributes['email']);
            if (!$attributes['cio_id']) {
                return (object)['success' => false, 'data' => 'Contact not found'];
            }
        }

        return (object)['success' => true, 'data' => $attributes];
    }

    public static function track(string $emailOrId, string $event, array $attributes = []): object
    {
        $client = self::client();

        $payload = [
            'name' => $event,
            'data' => $attributes,
            str_contains($emailOrId, '@') ? 'email' : 'id' => $emailOrId
        ];

        try {
            $client->customers->event($payload);
            return (object)['success' => true, 'data' => ['event' => ['name' => $event, 'data' => $attributes]]];
        } catch (\Exception | GuzzleException $e) {
            return (object)['success' => false, 'data' => 'Failed to track event'];
        }
    }

    public static function sendTransactional(string $email, string $messageId, array $data = []): bool|string
    {
        $endpoint = 'https://api' . ($_SERVER['CIO_REGION'] === 'eu' ? '-eu' : '') . '.customer.io/v1/send/email';

        $payload = [
            'transactional_message_id' => $messageId,
            'identifiers' => ['email' => $email],
            'to' => $email,
            'message_data' => (object)$data
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . ($_SERVER['CIO_APP_API_KEY'] ?? ''),
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        return $err ? false : $response;
    }

    private static function findCustomerId(string $email): ?string
    {
        $client = self::client();
        for ($tries = 0; $tries < 3; $tries++) {
            sleep(1);
            $cio = $client->customers->get(['email' => $email]);
            if (!empty($cio->results)) {
                return $cio->results[0]->cio_id;
            }
        }
        return null;
    }
}
