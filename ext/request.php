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

    private ?array $bodyCached = null;
    private ?array $jsonCached = null;

    private array $req_params;

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

    private function buildPath(object $router): object
    {
        return (object)[
            'controller' => $router->controller,
            'section' => $router->section,
            'slug' => $router->slug,
            'full' => '/' . substr($router->uri, strpos($router->uri, $router->controller) ?: 0),
        ];
    }

    public function params(string $key, mixed $fallback = null): mixed
    {
        static $req_params = null;
        if ($req_params === null) {
            foreach ($_REQUEST as $k => $v) {
                $req_params[strtolower($k)] = trim($v .'');
            }
        }
        return $req_params[strtolower($key)] ?? $fallback;
    }

    public function body(bool $associative = false): array|object
    {
        if ($this->bodyCached === null) {
            $body = [];
            parse_str(file_get_contents('php://input') ?: '', $body);
            if ($this->method === 'POST') {
                $body = array_merge($_POST, $body);
            }
            $this->bodyCached = $body;
        }
        return $associative ? $this->bodyCached : (object) $this->bodyCached;
    }

    public function json(bool $associative = true): array|object
    {
        if ($this->jsonCached === null) {
            $this->jsonCached = $this->method === 'GET' ? [] : tiny::readJSONBody($associative);
        }
        return $associative ? $this->jsonCached : (object) $this->jsonCached;
    }
}
