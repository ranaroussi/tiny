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
    private \Memcached|null $memcached;
    private bool $disabled = false;

    /**
     * Initializes the TinyCache with the specified caching engine.
     *
     * @param string $engine The caching engine to use ('apcu' or 'memcached')
     * @param string|null $memcached_host The Memcached host (default: 'localhost')
     * @param int|null $memcached_port The Memcached port (default: 11211)
     * @throws \RuntimeException If the specified engine is invalid or unavailable
     */
    public function __construct(
        private string $engine = 'apcu',
        ?string $memcached_host = 'localhost',
        ?int $memcached_port = 11211,
        ?bool $disable = false
    ) {
        $this->disabled = $disable ?? false;
        if ($this->disabled) {
            $this->disabled = true;
            return;
        }
        match ($this->engine) {
            'apcu' => $this->initApcu(),
            'memcached' => $this->initMemcached($memcached_host, $memcached_port),
            default => throw new \RuntimeException('Invalid cache engine specified')
        };
    }

    /**
     * Initializes the APCu caching engine.
     *
     * @throws \RuntimeException If APCu extension is not available or not enabled
     */
    private function initApcu(): void
    {
        if (!extension_loaded('apcu') || !(apcu_enabled() || ini_get('apc.enabled'))) {
            throw new \RuntimeException('APCu extension is not available or not enabled');
        }
    }

    /**
     * Initializes the Memcached caching engine.
     *
     * @param string|null $host The Memcached host
     * @param int|null $port The Memcached port
     * @throws \RuntimeException If Memcached extension is not available
     */
    private function initMemcached(?string $host, ?int $port): void
    {
        if (!class_exists('Memcached')) {
            throw new \RuntimeException('Memcached extension is not available');
        }
        $this->memcached = new \Memcached();
        $this->memcached->addServer($host, $port);
    }

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key The key to retrieve
     * @param callable|null $memcached_cb Callback for Memcached (ignored for APCu)
     * @param int $get_flags Flags for Memcached get operation (ignored for APCu)
     * @return mixed The cached value or null if not found
     */
    public function get(string $key, ?callable $memcached_cb = null, int $get_flags = 0): mixed
    {
        if ($this->disabled) {
            return null;
        }

        if ($this->engine === 'apcu') {
            return apcu_fetch($key, $success) ?: null;
        }

        return $this->memcached->get($key, $memcached_cb, $get_flags) ?: null;
    }

    /**
     * Stores a value in the cache.
     *
     * @param string $key The key to store the value under
     * @param mixed $value The value to store
     * @param int $ttl Time-to-live in seconds (0 for no expiry)
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($this->disabled) {
            return true;
        }

        if ($this->engine === 'apcu') {
            return apcu_store($key, $value, $ttl);
        }
        return $this->memcached->set($key, $value, $ttl);
    }

    /**
     * Deletes a value from the cache.
     *
     * @param string $key The key to delete
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        if ($this->disabled) {
            return true;
        }
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
        if ($this->disabled) {
            return [];
        }

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
     * Deletes all values from the cache that match a given prefix.
     *
     * @param string $prefix The prefix to match
     */
    public function deleteByPrefix(string $prefix): void
    {
        if ($this->disabled) {
            return;
        }
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
     * Retrieves a value from the cache or stores it if not found.
     *
     * @param string $key The key to retrieve or store
     * @param int $ttl Time-to-live in seconds for the stored value
     * @param callable $callback Function to generate the value if not found in cache
     * @param int $delay Delay in seconds before returning the value (for testing purposes)
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
        if ($this->disabled) {
            return $value;
        }
        $this->set($key, $value, $ttl);
        return $value;
    }
}
