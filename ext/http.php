<?php

declare(strict_types=1);


class TinyHTTP
{
    private const DEFAULT_CURL_OPTIONS = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36',
    ];

    private static array $defaultHeaders = [];

    /**
     * Set default headers for all HTTP requests.
     *
     * @param array $headers An associative array of headers to set as default
     */
    public static function setDefaultHeaders(array $headers): void
    {
        self::$defaultHeaders = $headers;
    }

    /**
     * Clear the default headers for all HTTP requests.
     */
    public static function clearDefaultHeaders(): void
    {
        self::$defaultHeaders = [];
    }

    /**
     * Get the final URL after following redirects.
     *
     * @param string $url The initial URL to check
     * @return string|false The final URL after redirects, or false on failure
     */
    public static function getRedirectTarget(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);
        curl_exec($ch);
        $target = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $target ?: false;
    }

    /**
     * Send an HTTP request with the specified method and options.
     *
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param string $url The URL to send the request to
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function request(string $method, string $url, array $options = []): object
    {
        $ch = curl_init();
        $curlOptions = self::buildCurlOptions($method, $url, $options);
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        $options['finalUrl'] = $options['finalUrl'] ?? true;
        if ($options['finalUrl']) {
            $info = curl_getinfo($ch);
        } else {
            $info = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        }
        curl_close($ch);

        return self::formatResponse($response, $error, $info, $options['finalUrl'] ?? $url);
    }

    /**
     * Send a GET request.
     *
     * @param string $url The URL to send the GET request to
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function get(string $url, array $options = []): object
    {
        return self::request('GET', $url, $options);
    }

    /**
     * Send a POST request.
     *
     * @param string $url The URL to send the POST request to
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function post(string $url, array $options = []): object
    {
        return self::request('POST', $url, $options);
    }

    /**
     * Send a POST request with JSON data.
     *
     * @param string $url The URL to send the POST request to
     * @param array|object|string $json The JSON data to send
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function postJSON(string $url, array|object|string $json, array $options = []): object
    {
        $options['json'] = $json;
        return self::post($url, $options);
    }

    /**
     * Send a PUT request.
     *
     * @param string $url The URL to send the PUT request to
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function put(string $url, array $options = []): object
    {
        return self::request('PUT', $url, $options);
    }

    /**
     * Send a PUT request with JSON data.
     *
     * @param string $url The URL to send the PUT request to
     * @param array|object|string $json The JSON data to send
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function putJSON(string $url, array|object|string $json, array $options = []): object
    {
        $options['json'] = $json;
        return self::put($url, $options);
    }

    /**
     * Send a PATCH request.
     *
     * @param string $url The URL to send the PATCH request to
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function patch(string $url, array $options = []): object
    {
        return self::request('PATCH', $url, $options);
    }

    /**
     * Send a PATCH request with JSON data.
     *
     * @param string $url The URL to send the PATCH request to
     * @param array|object|string $json The JSON data to send
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function patchJSON(string $url, array|object|string $json, array $options = []): object
    {
        $options['json'] = $json;
        return self::patch($url, $options);
    }

    /**
     * Send a DELETE request.
     *
     * @param string $url The URL to send the DELETE request to
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function delete(string $url, array $options = []): object
    {
        return self::request('DELETE', $url, $options);
    }

    /**
     * Send a DELETE request with JSON data.
     *
     * @param string $url The URL to send the DELETE request to
     * @param array|object|string $json The JSON data to send
     * @param array $options Additional options for the request
     * @return object The response object
     */
    public static function deleteJSON(string $url, array|object|string $json, array $options = []): object
    {
        $options['json'] = $json;
        return self::delete($url, $options);
    }

    /**
     * Build cURL options for the request.
     *
     * @param string $method The HTTP method
     * @param string $url The URL for the request
     * @param array $options Additional options for the request
     * @return array The compiled cURL options
     */
    private static function buildCurlOptions(string $method, string $url, array $options): array
    {
        /*
        options:
        - headers: array - additional headers to add to the request
        - data: array|object - data to send in the request body
        - json: array|object|string - JSON data to send in the request body
        - interface: string - interface to use for the request
        - finalUrl: bool - whether to use the final URL after redirects
        - ssl: array - SSL options
        - timeout: int - timeout for the request
        */
        $curlOptions = self::DEFAULT_CURL_OPTIONS + [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => mb_strtoupper($method),
            CURLOPT_TIMEOUT => $options['timeout'] ?? $_SERVER['CURL_TIMEOUT'] ?? 30,
        ];

        $headers = $options['headers'] ?? [];
        $headers = array_merge(self::$defaultHeaders, $headers);

        if (!empty($options['json'])) {
            if (is_array($options['json']) || is_object($options['json'])) {
                $options['json'] = json_encode($options['json']);
            }
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($options['json']);
        } elseif (!empty($options['data'])) {
            if ($method === 'GET') {
                $curlOptions[CURLOPT_URL] .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($options['data']);
            } else {
                $curlOptions[CURLOPT_POSTFIELDS] = $options['data'];
            }
        }

        if (!empty($headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        }

        if (!empty($options['interface'])) {
            $curlOptions[CURLOPT_INTERFACE] = $options['interface'];
        }

        if (!empty($options['ssl'])) {
            $curlOptions += self::getSslOptions($options['ssl']);
        }

        return $curlOptions;
    }

    /**
     * Get SSL options for the cURL request.
     *
     * @param array $ssl SSL configuration options
     * @return array The compiled SSL options for cURL
     */
    private static function getSslOptions(array $ssl): array
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => $ssl['verify'] ?? true,
        ];

        if (isset($ssl['cert'])) {
            $options[CURLOPT_SSLCERT] = $ssl['cert'];
            $options[CURLOPT_SSLCERTTYPE] = 'PEM';
        }

        if (isset($ssl['key'])) {
            $options[CURLOPT_SSLKEY] = $ssl['key'];
            $options[CURLOPT_SSLKEYTYPE] = 'PEM';
        }

        if (isset($ssl['keypass'])) {
            $options[CURLOPT_SSLKEYPASSWD] = $ssl['keypass'];
        }

        return $options;
    }

    /**
     * Format the cURL response into a standardized object.
     *
     * @param mixed $response The raw cURL response
     * @param string $error Any error message from cURL
     * @param array $info The cURL info array
     * @param bool $finalUrl Whether to include the final URL after redirects
     * @return object The formatted response object
     */
    private static function formatResponse($response, $error, $info, bool $finalUrl): object
    {
        if ($error) {
            return (object) [
                'success' => false,
                'error' => $error,
                'status_code' => 0,
                'headers' => [],
                'body' => null,
                'json' => [],
                'url' => $finalUrl ?: null,
            ];
        }

        $headerSize = $info['header_size'];
        $headers = self::parseHeaders(substr($response, 0, $headerSize));
        $body = substr($response, $headerSize);

        return (object) [
            'success' => true,
            'status_code' => $info['http_code'],
            'headers' => $headers,
            'body' => $body,
            'json' => json_decode($body),
            'url' => $finalUrl ? $info['url'] : null,
        ];
    }

    /**
     * Parse HTTP headers from a string into an associative array.
     *
     * @param string $headerText The raw header text
     * @return array An associative array of parsed headers
     */
    private static function parseHeaders(string $headerText): array
    {
        $headers = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (preg_match('/^([^:]+):(.*)$/', $line, $matches)) {
                $headers[trim($matches[1])] = trim($matches[2]);
            }
        }
        return $headers;
    }
}
