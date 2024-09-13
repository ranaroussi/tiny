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


class TinyController
{
    public mixed $method;
    public array $allowedMethods;

    /**
     * Constructor for the Controller class.
     * Initializes the request method and allowed HTTP methods.
     */
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    }

    /**
     * Handles GET requests.
     * This method should be overridden in child classes to implement specific GET logic.
     *
     * @param mixed $request The request object
     * @param mixed $response The response object
     * @return mixed|void
     */
    public function get($request, $response)
    {
        return 'This method is not implemented yet.';
    }

    /**
     * Handles POST requests.
     * This method should be overridden in child classes to implement specific POST logic.
     *
     * @param mixed $request The request object
     * @param mixed $response The response object
     * @return mixed|void
     */
    public function post($request, $response)
    {
        return 'This method is not implemented yet.';
    }

    /**
     * Handles PUT requests.
     * This method should be overridden in child classes to implement specific PUT logic.
     *
     * @param mixed $request The request object
     * @param mixed $response The response object
     * @return mixed|void
     */
    public function put($request, $response)
    {
        return 'This method is not implemented yet.';
    }

    /**
     * Handles PATCH requests.
     * This method should be overridden in child classes to implement specific PATCH logic.
     *
     * @param mixed $request The request object
     * @param mixed $response The response object
     * @return mixed|void
     */
    public function patch($request, $response)
    {
        return 'This method is not implemented yet.';
    }

    /**
     * Handles OPTIONS requests.
     * Sets the appropriate headers for CORS (Cross-Origin Resource Sharing) requests.
     */
    public function options(): void
    {
        http_response_code(204);
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
    }
}
