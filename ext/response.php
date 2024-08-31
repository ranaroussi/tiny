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

class TinyResponse
{
    /**
     * Redirects the user to a specified URL.
     *
     * @param string $goto The URL to redirect to
     * @param string|null $header Additional header to send (optional)
     */
    public function redirect($goto, $header = null): void
    {
        tiny::redirect($goto, $header);
    }

    /**
     * Sends a CSRF error response.
     *
     * This method triggers the display of a CSRF error message, either immediately
     * or on the next page load, depending on the $nextPage parameter.
     *
     * @param string $id An identifier for the CSRF error. Defaults to 'CSRF-VALIDATION-FAILED'.
     * @param bool $nextPage Whether to show the error on the next page load (true) or immediately (false).
     * @return void
     */
    public function hasCSRFError(string $id = 'CSRF-VALIDATION-FAILED', bool $nextPage = false): void
    {
        tiny::csrf()->showError($id, $nextPage);
    }

    /**
     * Renders a view file with optional parameters and updates the browser's URL.
     *
     * @param string $file The view file to render
     * @param array $params Optional parameters to pass to the view
     * @param bool $die Whether to terminate script execution after rendering (default: true)
     */
    public function render($file = '', $params = [], $die = true): void
    {
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                tiny::data()->$key = $value;
            }
        }
        header('HX-Push-Url: ' . tiny::router()->permalink);
        tiny::render($file, $die);
    }

    /**
     * Flushes output buffer and optionally renders a view file.
     * Used for sending partial content in long-running processes.
     *
     * @param string $string The string to send (optional)
     * @param bool $finish_request Whether to finish the request after flushing (default: true)
     */
    public function flush(string $string = '', bool $finish_request = true): void
    {
        echo $string;
        tiny::flush($finish_request);
    }

    /**
     * Sends a plain text response with an optional HTTP status code.
     *
     * @param string $text The text content to send
     * @param int $code The HTTP status code (default: 200)
     * @param bool $die Whether to terminate script execution after sending (default: true)
     */
    public function send($text, $code = 200, $die = true): void
    {
        try {
            http_response_code($code);
        } catch (Exception) {
            // Silently ignore exceptions when setting HTTP response code
        }
        echo json_encode($text);
        if ($die) die();
    }

    /**
     * Sends the contents of a file as the response.
     *
     * @param string $path The path to the file to send
     * @param int $code The HTTP status code (default: 200)
     * @param bool $die Whether to terminate script execution after sending (default: true)
     */
    public function sendFile($path, $code = 200, $die = true): void
    {
        try {
            http_response_code($code);
        } catch (Exception $e) {
            // Silently ignore exceptions when setting HTTP response code
        }
        echo file_get_contents($path);
        if ($die) die();
    }

    /**
     * Sends a JSON response with an optional HTTP status code.
     *
     * @param mixed $data The data to be encoded as JSON
     * @param int $code The HTTP status code (default: 200)
     * @param bool $die Whether to terminate script execution after sending (default: true)
     */
    public function sendJSON($data, $code = 200, $die = true): void
    {
        try {
            header("Content-type: application/json; charset=utf-8", true, $code);
        } catch (Exception $e) {
            // Silently ignore exceptions when setting headers
        }
        if (is_object($data) || is_array($data)) {
            echo json_encode($data, JSON_THROW_ON_ERROR);
        } else {
            echo $data;
        }
        if ($die) die();
    }
}
