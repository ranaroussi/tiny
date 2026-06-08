<?php

declare(strict_types=1);


const ENCHARGE_API_URL = 'https://ingest.encharge.io/v1';

class Encharge
{
    private const KEY_MAP = [
        'fname' => 'firstName',
        'firstname' => 'firstName',
        'lname' => 'lastName',
        'lastname' => 'lastName',
        'user_id' => 'userId',
    ];

    public function sendEvent(string $event, array $user, array $event_props = []): bool
    {
        $new_user = $this->normalizeUserData($user);

        $payload = json_encode([
            'name' => $event,
            'user' => $new_user,
            'properties' => $event_props,
        ]);

        $res = tiny::http()->post(
            $_SERVER['TINY_ENCHARGE_API_URL'] ?? ENCHARGE_API_URL,
            [
                'json' => $payload,
                'headers' => ['X-Encharge-Token: ' . ($_SERVER['TINY_ENCHARGE_API_WRITE_KEY'] ?? '')]
            ]
        );

        return $res->status_code >= 200 && $res->status_code < 300;
    }

    private function normalizeUserData(array $user): array
    {
        $new_user = [];
        foreach ($user as $key => $value) {
            $normalized_key = lcfirst(str_replace(' ', '', $key));
            $new_key = self::KEY_MAP[$normalized_key] ?? $normalized_key;
            $new_user[$new_key] = $value;
        }

        return $new_user;
    }
}

tiny::registerHelper('encharge', function() {
    return new Encharge();
});
