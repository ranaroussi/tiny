<?php

/**
 * Tiny: PHP Framework
 * https://github.com/ranaroussi/tiny
 *
 * Copyright 2013-2024 Ran Aroussi (@aroussi)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

declare(strict_types=1);


class TinyRequest
{
    public object $user;
    public string $method;
    public array $headers;
    public bool $htmx;
    public array $query;
    public object $path;
    public string $csrf_token = '';

    private ?array $bodyCached = null;
    private ?string $jsonCached = null;
    private ?array $markdownNegotiationCached = null;

    private array $req_params;

    /**
     * Initializes the Request object with request information.
     * Sets up user, method, headers, HTMX status, query parameters, and path details.
     */
    public function __construct()
    {
        $router = tiny::router();
        $this->user = tiny::user();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->headers = getallheaders() ?: [];
        $this->htmx = $router->htmx;
        $this->query = $_GET;
        $this->path = $this->buildPath($router);
    }

    /**
     * Builds a path object containing controller, section, slug, and full path information.
     *
     * @param object $router The router object containing URI information.
     * @return object An object with path details.
     */
    private function buildPath(object $router): object
    {
        return (object)[
            'controller' => $router->controller,
            'section' => $router->section,
            'slug' => $router->slug,
            'full' => '/' . substr($router->uri, strpos($router->uri, $router->controller) ?: 0),
        ];
    }

    /**
     * Retrieves a request parameter value by key, with an optional fallback.
     *
     * @param ?string $key The parameter key to look up.
     * @param mixed $fallback The fallback value if the key is not found.
     * @return mixed The parameter value or fallback.
     */
    public function params(?string $key = null, mixed $fallback = null): mixed
    {
        $this->req_params ??= array_change_key_case($_REQUEST, CASE_LOWER);
        if ($key === null) {
            return $this->req_params;
        }
        return $this->req_params[strtolower($key)] ?? $fallback;
    }

    /**
     * Retrieves the request body as an array or object.
     *
     * @param bool $associative Whether to return an associative array (true) or an object (false).
     * @return array|object The request body.
     */
    public function body(bool $associative = false): array|object
    {
        if ($this->bodyCached === null) {
            $rawBody = file_get_contents('php://input') ?: '';
            $parsedBody = [];
            parse_str($rawBody, $parsedBody);
            $jsonBody = json_decode($rawBody, true);
            $body = $jsonBody ?? $parsedBody ?? [];

            if ($this->method === 'POST') {
                $body = [...$_POST, ...$body];
            }

            $this->csrf_token = $body[tiny::csrf()->getTokenName()] ?? '';
            unset($body[tiny::csrf()->getTokenName()]);

            $this->bodyCached = $body;
        }

        return $associative ? $this->bodyCached : (object) $this->bodyCached;
    }

    /**
     * Validates the CSRF token in the request.
     *
     * @param bool $remove Whether to remove the token after validation.
     * @return bool True if the CSRF token is valid, false otherwise.
     */
    public function isValidCSRF($remove = true): bool
    {
        if ($this->csrf_token === '') {
            $this->body();
        }
        return tiny::csrf()->isValid($this->csrf_token, $remove);
    }

    /**
     * Validates the Bearer token in the request.
     *
     * @param string|null $compareToToken The token to compare against.
     * @return bool True if the token is valid, false otherwise.
     */
    public function validateBearerToken(?string $compareToToken = null): bool
    {
        if (
            !is_string($compareToToken) ||
            !isset($this->headers['Authorization']) ||
            !str_starts_with(strtolower($this->headers['Authorization']), 'bearer') ||
            trim(substr($this->headers['Authorization'], 7)) !== $compareToToken
        ) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves the JSON payload of the request as an array or object.
     *
     * @return string The JSON payload.
     */
    public function json(): string
    {
        if ($this->jsonCached === null) {
            $this->jsonCached = @file_get_contents('php://input');
            return $this->jsonCached;
        }
        return $this->jsonCached;
    }

    /**
     * Determines if the current request is asynchronous/AJAX.
     * Checks multiple conditions to determine if request is async:
     * 1. Running under Swoole server
     * 2. Has X-Requested-With header set to AsyncRequest
     * 3. Has async=true query parameter
     *
     * @return bool True if request is async, false otherwise
     */
    public function isAsync(): bool
    {
        // First check: Is this running under Swoole server in CLI mode?
        // This indicates an async server environment
        if (extension_loaded('swoole') && php_sapi_name() === 'cli') {
            return true;
        }

        // Second check: Look for custom X-Requested-With header
        // Modern async requests set this header to 'AsyncRequest'
        if (
            isset($this->headers['X-Requested-With']) &&
            $this->headers['X-Requested-With'] === 'AsyncRequest'
        ) {
            return true;
        }

        // Third check: Check URL query string for async=true parameter
        // Allows forcing async mode via URL parameter
        if (
            isset($this->query['async']) &&
            filter_var($this->query['async'], FILTER_VALIDATE_BOOLEAN)
        ) {
            return true;
        }

        // If none of the above conditions are met, this is not an async request
        return false;
    }

    // ========= Markdown Negotiation =========

    /**
     * Negotiates the preferred page format from the request URI and Accept header.
     *
     * Returns a normalized decision array that indicates whether markdown should
     * be served, whether the request is not acceptable, and whether the response
     * should vary by the Accept header.
     *
     * @return array{markdown: bool, not_acceptable: bool, vary: bool}
     */
    public function getMarkdownNegotiation(): array
    {
        if ($this->markdownNegotiationCached !== null) {
            return $this->markdownNegotiationCached;
        }

        $acceptHeader = trim((string)(array_change_key_case($this->headers, CASE_LOWER)['accept'] ?? ''));
        $matches = [
            'text/markdown' => ['q' => 0.0, 'specificity' => -1, 'index' => PHP_INT_MAX],
            'text/html' => ['q' => 0.0, 'specificity' => -1, 'index' => PHP_INT_MAX],
            'application/xhtml+xml' => ['q' => 0.0, 'specificity' => -1, 'index' => PHP_INT_MAX],
        ];

        foreach (explode(',', $acceptHeader) as $index => $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $segments = array_map('trim', explode(';', strtolower($part)));
            $acceptedType = array_shift($segments) ?? '';

            if ($acceptedType === '') {
                continue;
            }

            $q = 1.0;

            foreach ($segments as $segment) {
                if (!str_starts_with($segment, 'q=')) {
                    continue;
                }

                $q = max(0.0, min(1.0, (float)substr($segment, 2)));
                break;
            }

            [$type, $subtype] = array_pad(explode('/', $acceptedType, 2), 2, '*');

            foreach ($matches as $mimeType => $best) {
                [$targetType, $targetSubtype] = array_pad(explode('/', $mimeType, 2), 2, '*');

                $specificity = match (true) {
                    $type === '*' && $subtype === '*' => 0,
                    $type === $targetType && $subtype === '*' => 1,
                    $type === $targetType && $subtype === $targetSubtype => 2,
                    default => -1,
                };

                if ($specificity < 0) {
                    continue;
                }

                if (
                    $specificity > $best['specificity'] ||
                    ($specificity === $best['specificity'] && $q > $best['q']) ||
                    ($specificity === $best['specificity'] && $q === $best['q'] && $index < $best['index'])
                ) {
                    $matches[$mimeType] = [
                        'q' => $q,
                        'specificity' => $specificity,
                        'index' => $index,
                    ];
                }
            }
        }

        $prefers = static fn(array $left, array $right): bool => (
            $left['q'] > $right['q'] ||
            ($left['q'] === $right['q'] && $left['specificity'] > $right['specificity'])
        );

        if (str_ends_with(tiny::router()->uri, '.md')) {
            return $this->markdownNegotiationCached = [
                'markdown' => true,
                'not_acceptable' => $acceptHeader !== '' && $matches['text/markdown']['q'] <= 0.0,
                'vary' => false,
            ];
        }

        if ($acceptHeader === '') {
            return $this->markdownNegotiationCached = [
                'markdown' => false,
                'not_acceptable' => false,
                'vary' => false,
            ];
        }

        $html = $prefers($matches['application/xhtml+xml'], $matches['text/html'])
            ? $matches['application/xhtml+xml']
            : $matches['text/html'];

        return $this->markdownNegotiationCached = [
            'markdown' => $prefers($matches['text/markdown'], $html),
            'not_acceptable' => $matches['text/markdown']['q'] <= 0.0 && $html['q'] <= 0.0,
            'vary' => true,
        ];
    }

    /**
     * Determines whether the current request should receive markdown content.
     *
     * Applies the response side effects from content negotiation, including a
     * 406 response when the request is not acceptable and a Vary header when the
     * result depends on the Accept header.
     *
     * @return bool True when markdown should be returned, false otherwise.
     */
    public function isMarkdownRequest(): bool
    {
        $decision = $this->getMarkdownNegotiation();

        if ($decision['not_acceptable']) {
            tiny::response()->sendRaw('Not Acceptable', 'text/plain', 406, true, ['Vary' => 'Accept']);
        }

        if (!$decision['markdown'] && $decision['vary']) {
            tiny::header('Vary: Accept');
        }

        return $decision['markdown'];
    }
}
