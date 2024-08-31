<?php

declare(strict_types=1);

class HubSpotContact
{
    private const API_URL = 'https://api.hubapi.com/crm/v3/objects/contacts';
    private array $headers;

    public function __construct(string $api_key)
    {
        $this->headers = ["Authorization: Bearer $api_key"];
    }

    public function retrieve(string $hsid_or_email): object
    {
        $url = self::API_URL . '/' . $hsid_or_email;
        if (str_contains($hsid_or_email, '@')) {
            $url .= '?idProperty=email';
        }

        $res = tiny::http()->get($url, ['headers' => $this->headers]);
        return $this->formatResponse($res, 404);
    }

    public function update(string $hsid, array $props): object
    {
        $data = ['properties' => $props];
        $res = tiny::http()->patch(self::API_URL . "/$hsid", [
            'json' => $data,
            'headers' => $this->headers,
        ]);
        return $this->formatResponse($res);
    }

    public function updateByEmail(string $email, array $props): object
    {
        $user = $this->retrieve($email);
        if (!$user->success) {
            return $user;
        }
        return $this->update($user->data->id, $props);
    }

    public function create(array $props): object
    {
        $data = ['properties' => $props];
        $res = tiny::http()->post(self::API_URL, [
            'json' => $data,
            'headers' => $this->headers,
        ]);
        return $this->formatResponse($res);
    }

    public function createOrUpdate(array $props): object
    {
        if (!isset($props['email'])) {
            return (object)[
                'success' => false,
                'data' => 'Email is required',
            ];
        }
        $user = $this->retrieve($props['email']);
        return $user->success ? $this->update($user->data->id, $props) : $this->create($props);
    }

    public function delete(string $hsid): object
    {
        $res = tiny::http()->delete(self::API_URL . "/$hsid", [
            'headers' => $this->headers,
        ]);
        return $this->formatResponse($res);
    }

    public function deleteByEmail(string $email): object
    {
        $user = $this->retrieve($email);
        return $user->success ? $this->delete($user->data->id) : $user;
    }

    private function formatResponse(object $res, int $errorCode = 0): object
    {
        $success = $errorCode ? $res->status_code !== $errorCode : $res->status_code >= 200 && $res->status_code < 300;
        return (object)[
            'success' => $success,
            'data' => $success ? $res->json : ($res->json->message ?? null),
        ];
    }
}
