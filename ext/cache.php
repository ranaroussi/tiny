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


class TinyCache
{
    private $memcached;
    private $engine;

    /**
     * Constructor for TinyCache.
     * Initializes the cache engine (APCu or Memcached) based on the provided parameters.
     *
     * @param string $engine The cache engine to use ('apcu' or 'memcached')
     * @param string|null $memcached_host The Memcached host (default: 'localhost')
     * @param int|null $memcached_port The Memcached port (default: 11211)
     * @throws RuntimeException if the specified engine or its extension is not available
     */
    public function __construct(string $engine = 'apcu', ?string $memcached_host = 'localhost', ?int $memcached_port = 11211)
    {
        $this->engine = $engine;

        if ($this->engine === 'apcu') {
            if (!extension_loaded('apcu') || !apcu_enabled()) {
                throw new \RuntimeException('APCu extension is not available or not enabled');
            }
        } elseif ($this->engine === 'memcached') {
            if (class_exists('Memcached')) {
                $this->memcached = new \Memcached();
                $this->memcached->addServer($memcached_host, $memcached_port);
            } else {
                throw new \RuntimeException('Memcached extension is not available');
            }
        } else {
            throw new \RuntimeException('Invalid cache engine specified');
        }
    }

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key The key to retrieve
     * @param callable|null $memcached_cb Callback for Memcached (ignored for APCu)
     * @param int $get_flags Flags for Memcached get operation (ignored for APCu)
     * @return mixed The cached value if found, null otherwise
     */
    public function get(string $key, ?callable $memcached_cb = null, int $get_flags = 0): mixed
    {
        if ($this->engine === 'apcu') {
            $success = false;
            $result = apcu_fetch($key, $success);
            return $success ? $result : null;
        }

        $result = $this->memcached->get($key, $memcached_cb, $get_flags);
        return $result !== false ? $result : null;
    }

    /**
     * Sets a value in the cache.
     *
     * @param string $key The key to set
     * @param mixed $value The value to cache
     * @param int $ttl Time to live in seconds (0 for no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($this->engine === 'apcu') {
            return apcu_store($key, $value, $ttl);
        }
        return $this->memcached->set($key, $value, $ttl);
    }

    /**
     * Deletes a value from the cache.
     *
     * @param string $key The key to delete
     * @return bool True if the key was deleted, false otherwise
     */
    public function delete(string $key): bool
    {
        if ($this->engine === 'apcu') {
            return apcu_delete($key);
        }
        return $this->memcached->delete($key);
    }

    /**
     * Retrieves all keys from the cache that match a given prefix.
     *
     * @param string $prefix The prefix to match
     * @return array An array of matching keys
     */
    public function getByPrefix(string $prefix): array
    {
        $matches = [];

        if ($this->engine === 'apcu') {
            $iterator = new \APCUIterator('/^' . preg_quote($prefix, '/') . '/');
            foreach ($iterator as $item) {
                $matches[] = $item['key'];
            }
        } else {
            $keys = $this->memcached->getAllKeys();
            if ($keys === false) {
                return [];
            }
            foreach ($keys as $key) {
                if (str_starts_with($key, $prefix)) {
                    $matches[] = $key;
                }
            }
        }

        return $matches;
    }

    /**
     * Deletes all keys from the cache that match a given prefix.
     *
     * @param string $prefix The prefix to match
     */
    public function deleteByPrefix(string $prefix): void
    {
        if ($this->engine === 'apcu') {
            $iterator = new \APCUIterator('/^' . preg_quote($prefix, '/') . '/');
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        } else {
            $keys = $this->memcached->getAllKeys();
            if ($keys === false) {
                return;
            }
            foreach ($keys as $key) {
                if (str_starts_with($key, $prefix)) {
                    $this->memcached->delete($key);
                }
            }
        }
    }

    /**
     * Retrieves a value from the cache or stores it if not present.
     *
     * This method attempts to retrieve a value from the cache using the given key.
     * If the value is not found, it calls the provided callback function to generate
     * the value, stores it in the cache with the specified TTL, and then returns it.
     *
     * @param string $key The key to retrieve or store
     * @param int $ttl Time to live in seconds for the cached value
     * @param callable $callback Function to generate the value if not in cache
     * @param int $delay Delay in seconds before returning the cached value
     * @return mixed The cached or newly generated value
     */
    public function remember(string $key, int $ttl, callable $callback, int $delay = 0): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            if ($delay > 0) {
                sleep($delay);
            }
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
