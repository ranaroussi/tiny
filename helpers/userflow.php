<?php

declare(strict_types=1);


class UserFlow
{
    private const API_VERSION = '2020-01-03';
    private const BASE_URL = 'https://api.userflow.com';

    private array $headers;

    public function __construct(string $api_key)
    {
        $this->headers = [
            'Userflow-Version: ' . self::API_VERSION,
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ];
    }

    public function identify(string $id, array $traits = []): object
    {
        return $this->sendRequest('users', [
            'id' => $id,
            'attributes' => $traits,
        ]);
    }

    public function track(string $id, string $event, array $properties = []): object
    {
        return $this->sendRequest('events', [
            'user_id' => $id,
            'event' => $event,
            'attributes' => $properties,
        ]);
    }

    private function sendRequest(string $endpoint, array $payload): object
    {
        $url = $this->buildUrl($endpoint);
        return tiny::http()->post($url, [
            'json' => $payload,
            'headers' => $this->headers
        ]);
    }

    private function buildUrl(string $path): string
    {
        return self::BASE_URL . '/' . ltrim($path, '/');
    }
}
