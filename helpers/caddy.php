<?php

declare(strict_types=1);


class Caddy
{
    private const DNS_TYPES = [
        DNS_A, DNS_CNAME, DNS_HINFO, DNS_CAA, DNS_MX,
        DNS_NS, DNS_PTR, DNS_SOA, DNS_TXT, DNS_AAAA,
        DNS_SRV, DNS_NAPTR, DNS_A6, DNS_ALL, DNS_ANY
    ];

    private function getSSLConfig(): array
    {
        return [
            'cert' => $_SERVER['APP_CADDY_CERT_FILE'] ?? null,
            'key' => $_SERVER['APP_CADDY_KEY_FILE'] ?? null,
            'verify' => false,
        ];
    }

    private function makeRequest(string $path, string $method = 'GET', ?array $data = null): object
    {
        if (!$_SERVER['APP_CADDY_ADMIN_URL']) {
            return (object) [
                'success' => false,
                'data' => [],
            ];
        }

        $url = ($_SERVER['APP_CADDY_ADMIN_URL'] ?? '') . $path;
        $options = [
            'ssl' => self::getSSLConfig(),
        ];

        if ($data !== null) {
            $options['json'] = $data;
        }

        $res = tiny::http()->$method($url, $options);

        return (object) [
            'success' => $res->status_code === 200,
            'data' => $res->json,
        ];
    }

    public function getDNS(string $domain, int $type = DNS_NS): array
    {
        if (!in_array($type, self::DNS_TYPES, true)) {
            throw new InvalidArgumentException('Invalid DNS type');
        }

        $dns = dns_get_record($domain, $type);
        return array_map(fn($record) => $record['target'] ?? $record['ip'] ?? '', $dns ?: []);
    }

    public function getConfig(string $path = '/'): object
    {
        return self::makeRequest($path);
    }

    public function listCustomDomains(): object
    {
        return self::makeRequest('/apps/http/servers');
    }

    public function getRootDomain(bool $internal = false): object
    {
        $response = self::makeRequest('/apps/http/servers');

        if (!$response->success) {
            return $response;
        }

        $rootKey = array_key_first((array)$response->data);
        $rootData = $response->data->$rootKey;

        return $internal
            ? (object) ['key' => $rootKey, 'json' => $rootData]
            : (object) ['success' => true, 'data' => $rootData];
    }

    public function setCustomDomain(string $domain): object
    {
        $domain = mb_strtolower($domain);
        $root = self::getRootDomain(true);
        if (!$root->success) {
            return $root;
        }

        $domains = $root->json->routes[0]->match[0]->host;
        $domains[] = explode(':', $domain)[0];
        $domains = array_unique($domains);

        if ($domains === $root->json->routes[0]->match[0]->host) {
            return (object) ['success' => true, 'data' => $root->json];
        }

        return self::updateDomains($root->key, $domains);
    }

    public function deleteCustomDomain(string $domain): object
    {
        $domain = mb_strtolower($domain);
        $root = self::getRootDomain(true);

        if (!$root->success) {
            return $root;
        }

        $domains = array_filter(
            $root->json->routes[0]->match[0]->host,
            fn($item) => $item !== $domain
        );

        return self::updateDomains($root->key, $domains);
    }

    private function updateDomains(string $rootKey, array $domains): object
    {
        $path = "/apps/http/servers/{$rootKey}/routes/0/match/0/host";
        return self::makeRequest($path, 'PATCH', $domains);
    }
}

tiny::registerHelper('caddy', function() {
    return new Caddy();
});
