<?php

declare(strict_types=1);


const ENCHARGE_API_URL = 'https://ingest.encharge.io/v1';

function encharge_send_event(string $event, array $user, array $event_props = []): bool
{
    /*
    example payload:
    {
    "name": "Registered user",
    "user": {
    "email": "somebody@example.com",
    "userId": "1234567890",
    "firstName": "John",
    "lastName": "Snow",
    "name": "Snow"
    },
    "properties": {
    "plan": "Premium",
    "trial": {
    "startDate": "2020-03-06T14:24:03.522Z",
    "length": 14
    }
    }
    }
    */
    $new_user = normalize_user_data($user);

    $payload = json_encode([
        'name' => $event,
        'user' => $new_user,
        'properties' => $event_props,
    ]);

    $res = tiny::http()->post(
        $_SERVER['ENCHARGE_API_URL'] ?? ENCHARGE_API_URL,
        [
            'json' => $payload,
            'headers' => ['X-Encharge-Token: ' . ($_SERVER['ENCHARGE_API_WRITE_KEY'] ?? '')]
        ]
    );

    return $res->status_code >= 200 && $res->status_code < 300;
}

function normalize_user_data(array $user): array
{
    $key_map = [
        'fname' => 'firstName',
        'firstname' => 'firstName',
        'lname' => 'lastName',
        'lastname' => 'lastName',
        'user_id' => 'userId',
    ];

    $new_user = [];
    foreach ($user as $key => $value) {
        $normalized_key = lcfirst(str_replace(' ', '', $key));
        $new_key = $key_map[$normalized_key] ?? $normalized_key;
        $new_user[$new_key] = $value;
    }

    return $new_user;
}
