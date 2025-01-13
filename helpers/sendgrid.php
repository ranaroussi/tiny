<?php

declare(strict_types=1);


class SendGrid
{
    private const API_URL = 'https://api.sendgrid.com/v3';
    private const MAIL_SEND_ENDPOINT = '/mail/send';
    private const MARKETING_CONTACTS_ENDPOINT = '/marketing/contacts';

    private function getApiKey(): string
    {
        return $_SERVER['SENDGRID_API_KEY'] ?? '';
    }

    private function getHeaders(): array
    {
        return [
            'Authorization: Bearer ' . self::getApiKey(),
            'Content-Type: application/json',
        ];
    }

    public function sendTemplate(
        string $to_email,
        string $to_name,
        object|array $data,
        string $template_id,
        ?string $reply_to_email = null,
        ?string $reply_to_name = null,
        int $send_at = 0
    ): bool|string {
        $payload = [
            'template_id' => $template_id,
            'from' => [
                'email' => $_SERVER['SENDGRID_FROM_ADDRESS'] ?? '',
                'name' => $_SERVER['SENDGRID_FROM_NAME'] ?? '',
            ],
            'personalizations' => [[
                'to' => [[
                    'email' => $to_email,
                    'name' => $to_name,
                ]],
                'dynamic_template_data' => (object)$data,
            ]],
        ];

        if ($reply_to_email && $reply_to_name) {
            $payload['reply_to'] = [
                'email' => $reply_to_email,
                'name' => $reply_to_name,
            ];
        }

        if ($send_at > 0) {
            $payload['send_at'] = $send_at;
        }

        return self::sendRequest(self::MAIL_SEND_ENDPOINT, $payload);
    }

    public function addToList(string $list_id, array $contacts): bool
    {
        $payload = [
            'list_ids' => [$list_id],
            'contacts' => $contacts,
        ];

        $response = self::sendRequest(self::MARKETING_CONTACTS_ENDPOINT, $payload, 'PUT');
        return $response !== false && (int)($response->status_code / 100) === 2;
    }

    public function removeFromList(string $list_id, string $email_to_delete): bool
    {
        $query = [
            'query' => sprintf('email LIKE "%s" AND CONTAINS(list_ids, "%s")', $email_to_delete, $list_id),
        ];

        $response = self::sendRequest(self::MARKETING_CONTACTS_ENDPOINT . '/search', $query, 'POST');
        if ($response === false || (int)($response->status_code / 100) !== 2) {
            return false;
        }

        $user = current(array_filter($response->json->result, fn($user) => $user->email === $email_to_delete));
        if (!$user) {
            return false;
        }

        $delete_url = self::MARKETING_CONTACTS_ENDPOINT . '?ids=' . $user->id;
        $response = self::sendRequest($delete_url, null, 'DELETE');
        return $response !== false && (int)($response->status_code / 100) === 2;
    }

    private function sendRequest(string $endpoint, ?array $payload = null, string $method = 'POST'): object|bool
    {
        $url = self::API_URL . $endpoint;
        $options = [
            'headers' => self::getHeaders(),
        ];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        try {
            $response = tiny::http()->$method($url, $options);
            return $response;
        } catch (\Exception $e) {
            error_log('SendGrid API Error: ' . $e->getMessage());
            return false;
        }
    }
}

tiny::registerHelper('sendgrid', function () {
    return new SendGrid();
});
