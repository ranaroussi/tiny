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


/**
 * A test-double response object that captures output instead of
 * terminating the script. Returned by tiny::response() when test mode
 * is active (via tiny::test()).
 */
class TinyTestResponse extends TinyResponse
{
    public ?string $redirectUrl = null;
    public ?string $renderedView = null;
    public array $renderParams = [];
    public ?string $output = null;
    public int $status = 200;
    public ?string $contentType = null;

    public function redirect(?string $goto = null, ?string $header = null): void
    {
        $this->redirectUrl = $goto;
        throw new TinyTestExit("Test redirect captured");
    }

    public function render(string $file = '', array $params = [], bool $die = true): void
    {
        $this->renderedView = $file;
        $this->renderParams = $params;
        if ($die) {
            throw new TinyTestExit("Test render captured");
        }
    }

    public function send(mixed $text, int $code = 200, bool $die = true): void
    {
        $this->output = is_string($text) ? $text : json_encode($text);
        $this->status = $code;
        if ($die) {
            throw new TinyTestExit("Test send captured");
        }
    }

    public function sendJSON(mixed $data, int $code = 200, bool $die = true): void
    {
        $this->output = is_string($data) ? $data : json_encode($data);
        $this->status = $code;
        $this->contentType = 'application/json';
        if ($die) {
            throw new TinyTestExit("Test sendJSON captured");
        }
    }

    public function sendFile(string $path, int $code = 200, bool $die = true): void
    {
        $this->output = file_get_contents($path) ?: null;
        $this->status = $code;
        if ($die) {
            throw new TinyTestExit("Test sendFile captured");
        }
    }

    public function flush(string $string = '', bool $finish_request = true): void
    {
        $this->output .= $string;
    }

    public function hasCSRFError(string $id = 'CSRF-VALIDATION-FAILED', bool $nextPage = false): void
    {
        $this->output = "CSRF error: $id";
        throw new TinyTestExit("Test CSRF error captured");
    }
}
