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
    private ?array $jsonCached = null;

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
        $this->query = $router->query;
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
     * Retrieves the JSON payload of the request as an array or object.
     *
     * @param bool $associative Whether to return an associative array (true) or an object (false).
     * @return array|object The JSON payload.
     */
    public function json(bool $associative = true): array|object
    {
        if ($this->jsonCached === null) {
            $this->jsonCached = $this->method === 'GET' ? [] : tiny::readJSONBody(true);
            $this->csrf_token = $this->jsonCached[tiny::csrf()->getTokenName()] ?? '';
            unset($this->jsonCached[tiny::csrf()->getTokenName()]);
        }
        return $associative ? $this->jsonCached : (object) $this->jsonCached;
    }
}
