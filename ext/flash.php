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

class TinyFlash
{
    private string $name;

    /**
     * Constructor for the TinyFlash class.
     * Initializes a flash message with the given name.
     *
     * @param string $name The name of the flash message (default: 'flash_msg')
     */
    public function __construct(string $name = 'flash_msg')
    {
        $this->name = $name;
    }

    /**
     * Sets a flash message.
     *
     * @param mixed $value The value to be stored as a flash message
     */
    public function set(mixed $value): void
    {
        tiny::flashSet($value, $this->name);
    }

    /**
     * Retrieves the flash message.
     *
     * @param bool $keep Whether to keep the flash message after retrieval (default: false)
     * @return null|string|array The flash message value or null if not set
     */
    public function get(bool $keep = false): null|string|array
    {
        return tiny::flashGet($this->name, $keep);
    }

    /**
     * Checks if the flash message matches a given value.
     *
     * @param mixed $match The value to compare against the flash message
     * @param bool $keep Whether to keep the flash message after checking (default: false)
     * @return bool True if the flash message matches the given value, false otherwise
     */
    public function is(mixed $match, bool $keep = false): bool
    {
        $val = tiny::flashGet($this->name, $keep);
        return $val == $match;
    }
}
