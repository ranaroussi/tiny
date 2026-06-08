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
 * A plain data object returned by tiny::testResult() that captures
 * the outcome of a controller invocation in test mode.
 */
class TestResult
{
    public ?string $output = null;
    public ?string $redirect = null;
    public ?string $view = null;
    public array $viewParams = [];
    public int $status = 200;
    public ?string $contentType = null;
    public ?string $error = null;
    public array $headers = [];

    /**
     * Returns true if the test result indicates a successful response
     * (no error and an HTTP status in the 2xx range).
     */
    public function ok(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    /**
     * Returns true if the response captured a redirect.
     */
    public function isRedirect(): bool
    {
        return $this->redirect !== null;
    }

    /**
     * Returns true if the response rendered a view.
     */
    public function isRender(): bool
    {
        return $this->view !== null;
    }
}
