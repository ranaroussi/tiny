<?php

declare(strict_types=1);


class LogSnag
{
    private const API_URL = 'https://api.logsnag.com/v1';
    private const VALID_LOGSNAG_SUBMIT = ['true', true, '1', 1];

    private static function headers(): array
    {
        return [
            'Authorization: Bearer ' . ($_SERVER['LOGSNAG_S2S_TOKEN'] ?? ''),
            'Content-Type: application/json',
        ];
    }

    public static function identify(string $user_id, array $props = []): object|false
    {
        if (!self::isLogSnagEnabled()) {
            return false;
        }

        $payload = [
            'project' => $_SERVER['LOGSNAG_PROJECT'] ?? '',
            'user_id' => $user_id,
            'properties' => $props,
        ];

        return self::sendRequest('insight', $payload);
    }

    public static function log(
        string|array $channels,
        string $event,
        string $description = '',
        array $tags = [],
        string $icon = '🔔',
        bool $notify = false,
        bool $markdown = false
    ): array|false {
        if (!self::isLogSnagEnabled()) {
            return false;
        }

        $channels = is_array($channels) ? $channels : explode(',', $channels);
        $responses = [];

        foreach ($channels as $channel) {
            $payload = [
                'project' => $_SERVER['LOGSNAG_PROJECT'] ?? '',
                'channel' => trim($channel),
                'event' => $event,
                'description' => $description,
                'icon' => $icon,
                'notify' => $notify,
            ];

            if (!empty($tags)) {
                $payload['tags'] = $tags;
            }
            if ($markdown) {
                $payload['parser'] = 'markdown';
            }

            $responses[] = self::sendRequest('log', $payload);
        }

        return $responses;
    }

    public static function mdlog(
        string|array $channels,
        string $event,
        string $description = '',
        array $tags = [],
        string $icon = '🔔',
        bool $notify = false
    ): array|false {
        return self::log($channels, $event, $description, $tags, $icon, $notify, true);
    }

    public static function insight(string $title, string $value, string $icon = '💡'): object|false
    {
        if (!self::isLogSnagEnabled()) {
            return false;
        }

        return self::sendRequest('insight', [
            'project' => $_SERVER['LOGSNAG_PROJECT'] ?? '',
            'title' => $title,
            'value' => $value,
            'icon' => $icon,
        ]);
    }

    private static function isLogSnagEnabled(): bool
    {
        return in_array($_SERVER['LOGSNAG_SUBMIT'] ?? '', self::VALID_LOGSNAG_SUBMIT, true);
    }

    private static function sendRequest(string $endpoint, array $payload): object
    {
        return tiny::http()->post(self::API_URL . '/'. $endpoint, [
            'json' => $payload,
            'headers' => self::headers()
        ]);
    }
}
