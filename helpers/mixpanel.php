<?php

declare(strict_types=1);


class Mixpanel
{
    private const API_URL = 'https://api.mixpanel.com';
    private const VALID_MIXPANEL_SUBMIT = ['true', true, '1', 1];

    private static function headers(): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
    }

    public static function identify(string $distinct_id, array $props = []): object|false
    {
        if (!self::isMixpanelEnabled()) {
            return false;
        }

        $payload = [
            '$token' => $_SERVER['APP_MIXPANEL_PROJECT_TOKEN'] ?? '',
            '$distinct_id' => $distinct_id,
            '$set' => $props,
        ];

        return self::sendRequest('engage', $payload);
    }

    public static function track(string $distinct_id, string $event, array $props = []): object|false
    {
        if (!self::isMixpanelEnabled()) {
            return false;
        }

        $payload = [
            'event' => $event,
            'properties' => array_merge([
                'token' => $_SERVER['APP_MIXPANEL_PROJECT_TOKEN'] ?? '',
                'distinct_id' => $distinct_id,
            ], $props),
        ];

        return self::sendRequest('track', $payload);
    }

    public static function alias(string $distinct_id, string $alias): object|false
    {
        if (!self::isMixpanelEnabled()) {
            return false;
        }

        $payload = [
            'event' => '$create_alias',
            'properties' => [
                'token' => $_SERVER['APP_MIXPANEL_PROJECT_TOKEN'] ?? '',
                'distinct_id' => $distinct_id,
                'alias' => $alias,
            ],
        ];

        return self::sendRequest('track', $payload);
    }

    private static function isMixpanelEnabled(): bool
    {
        return in_array($_SERVER['APP_MIXPANEL_SUBMIT'] ?? '', self::VALID_MIXPANEL_SUBMIT, true);
    }

    private static function sendRequest(string $endpoint, array $payload): object
    {
        $url = self::API_URL . "/$endpoint?ip=1";
        $data = base64_encode(json_encode($payload));

        return tiny::http()->post($url, [
            'data' => $data,
            'headers' => self::headers()
        ]);
    }
}

tiny::registerHelper('mixpanel', function () {
    return new Mixpanel();
});
