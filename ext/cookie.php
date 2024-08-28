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


class TinyCookie
{

    public string $name = 'default';
    public string $path = '';
    public string $domain = '';
    public int|null|false $expiry = null;
    public bool $_exists = false;
    public array|object $data = [];

    /**
     * Constructor for the TinyCookie class.
     * Initializes a cookie with the given name and default values.
     *
     * @param string $name The name of the cookie (default: 'default')
     * @param array $values Initial values for the cookie (default: [])
     */
    public function __construct($name = 'default', $values = [])
    {

        // default cookie values
        $this->name = ($name) ? $name : 'default';
        $this->expiry = @$_SERVER['COOKIE_TTL'] ? strtotime($_SERVER['COOKIE_TTL']) : 0;
        $this->domain = @$_SERVER['COOKIE_DOMAIN'] ? $_SERVER['COOKIE_DOMAIN'] : $_SERVER['HTTP_HOST'];
        $this->path = @$_SERVER['COOKIE_PATH'] ? $_SERVER['COOKIE_PATH'] : tiny::config()->url_path;
        $this->_exists = false;

        // set default values
        $cookie = [];
        foreach ($values as $k => $v) {
            $cookie[$k] = $v;
        }

        // cookie exists?
        if (isset($_COOKIE[$name])) {
            $this->_exists = true;
            $_cookie = @unserialize(@$_COOKIE[$name]);
            $_cookie = json_decode(json_encode($_cookie), true);
            foreach ($_cookie as $k => $v) {
                if (!isset($cookie[$k])) {
                    $cookie[$k] = $v;
                }
            }
        }

        $this->data = $cookie;
    }

    /**
     * Reads a value from the cookie or returns the entire cookie data.
     *
     * @param string|null $item The key of the item to read (optional)
     * @return mixed The value of the specified item, or the entire cookie data if no item is specified
     */
    public function read($item = null): mixed
    {
        if ($item != null && array_key_exists($item, $this->data)) {
            return $this->data[$item];
        }
        return $this->data;
    }

    /**
     * Writes a value or an array of values to the cookie data.
     *
     * @param string|array $key_or_data The key to write, or an array of key-value pairs
     * @param mixed|null $value The value to write (if $key_or_data is a string)
     * @return static Returns the current instance for method chaining
     */
    public function write($key_or_data, $value = null): static
    {
        if ($value != null) {
            $this->data[$key_or_data] = $value;
        } else {
            $this->data = $key_or_data;
        }
        return $this;
    }

    /**
     * Saves the cookie data to the browser.
     *
     * @param string|null $expiry The expiration time for the cookie (optional)
     */
    public function save($expiry = null): void
    {
        if ($expiry == null) {
            $expiry = $this->expiry;
        }
        // print_r($this->data);
        setcookie($this->name, serialize($this->data), ($expiry == null) ? 0 : strtotime($expiry), $this->path, $this->domain);
    }

    /**
     * Destroys the cookie by setting its expiration to the past.
     */
    public function destroy(): void
    {
        setcookie($this->name, '', -1, $this->path, $this->domain);
        unset($_COOKIE[$this->name]);
    }
}
