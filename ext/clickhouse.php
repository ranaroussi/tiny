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
 * A lightweight ClickHouse client using cURL for HTTP(S) connections
 * Handles DNS caching, connection pooling, and basic query/insert operations
 * Usage:
 * $clickhouse = new TinyClickhouse([
 *    'host' => $_SERVER['CLICKHOUSE_HOST'],
 *    'port' => $_SERVER['CLICKHOUSE_PORT'],
 *    'username' => $_SERVER['CLICKHOUSE_USERNAME'],
 *    'password' => $_SERVER['CLICKHOUSE_PASSWORD'],
 *    'https' => $_SERVER['CLICKHOUSE_HTTPS'] ?? false,
 *    'timeout' => $_SERVER['CLICKHOUSE_TIMEOUT'] ?? 30
 *]);
 * $clickhouse->insert($table, $payload);
 * $clickhouse->query($query);
 * $clickhouse->update($table, $payload, $condition);
 */
class TinyClickhouse
{
    /** @var CurlHandle cURL handle */
    private $client;

    /** @var string Base URL for ClickHouse server */
    private $base_url;

    /** @var array Common cURL options used across requests */
    private $curl_options;

    /** @var bool Whether to use persistent connection */
    private $persistent = true;

    /** @var int Maximum batch size for batch inserts */
    private $batch_size = 1000;

    /**
     * Initialize ClickHouse connection with optimized settings
     *
     * @param array $options Connection options including:
     *   - host: ClickHouse server hostname/IP
     *   - port: Server port
     *   - username: Auth username
     *   - password: Auth password
     *   - https: Use HTTPS (boolean)
     *   - timeout: Request timeout (optional)
     *   - useragent: Custom User-Agent (optional)
     *   - persistent: Use persistent connection (optional, default true)
     *   - batch_size: Maximum batch size for inserts (optional, default 1000)
     */
    public function __construct(array $options = [])
    {
        // Build base URL using cached DNS or original host
        $protocol = $options['https'] ? 'https://' : 'http://';
        $this->base_url = $protocol . $options['host'];

        // Set persistent connection option
        if (isset($options['persistent'])) {
            $this->persistent = (bool)$options['persistent'];
        }

        // Set batch size if provided
        if (isset($options['batch_size']) && $options['batch_size'] > 0) {
            $this->batch_size = (int)$options['batch_size'];
        }

        // Pre-configure curl options for optimal performance
        $this->curl_options = [
            CURLOPT_PORT => $options['port'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_ENCODING => 'gzip', // Enable compression
            CURLOPT_MAXREDIRS => 3, // Reduce max redirects
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => 1,
            CURLOPT_USERPWD => $options['username'] . ':' . $options['password'],
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Encoding: gzip',
                'Content-Type: text/plain',
                'Connection: keep-alive',
                'User-Agent: ' . ($options['useragent'] ?? 'curl'),
            ],
            CURLOPT_DNS_USE_GLOBAL_CACHE => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 120,
            // Keep connection alive for better performance
            CURLOPT_TCP_KEEPALIVE => 1,
            // Use TCP_NODELAY for better latency
            CURLOPT_TCP_NODELAY => 1,
            // Allow compression for better throughput
            CURLOPT_ACCEPT_ENCODING => 'gzip,deflate',
        ];

        // Override default timeout if specified
        if (isset($options['timeout'])) {
            $this->curl_options[CURLOPT_TIMEOUT] = $options['timeout'];
        }

        // Initialize persistent cURL handle
        $this->initializeClient();
    }

    /**
     * Initialize the cURL client
     */
    private function initializeClient()
    {
        $this->client = curl_init($this->base_url);
        curl_setopt_array($this->client, $this->curl_options);
    }

    /**
     * Reset the cURL client if needed
     */
    private function resetClientIfNeeded()
    {
        if (!$this->persistent) {
            $this->client = null;
            $this->initializeClient();
        } else {
            // Reset the URL to base URL
            curl_setopt($this->client, CURLOPT_URL, $this->base_url);
        }
    }

    /**
     * Clean up resources when object is destroyed
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client = null;
            $this->client = null;
        }
    }

    /**
     * Execute a raw SQL query against ClickHouse
     *
     * @param string $query SQL query to execute
     * @return string Raw response from ClickHouse
     * @throws RuntimeException on cURL error or HTTP error (4xx/5xx)
     */
    public function query(string $query)
    {
        // Set query as POST data
        curl_setopt($this->client, CURLOPT_POSTFIELDS, $query);

        // Execute request and capture response/errors
        $response = curl_exec($this->client);
        $err = curl_error($this->client);
        $http_code = curl_getinfo($this->client, CURLINFO_HTTP_CODE);

        // Reset client for next query (if not using persistent connection)
        $this->resetClientIfNeeded();

        // Handle cURL errors
        if ($err) {
            $error_msg = "cURL Error #:" . $err;
            error_log($error_msg);
            throw new RuntimeException($error_msg);
        }

        // Handle HTTP errors
        if ($http_code >= 400) {
            $error_msg = "HTTP Error " . $http_code . " on query: " . $query . "; Response: " . $response;
            error_log($error_msg);
            throw new RuntimeException($error_msg);
        }

        return $response;
    }

    /**
     * Insert data into a ClickHouse table
     *
     * @param string $table Target table name
     * @param array $data Associative array of column => value pairs
     * @return string Query execution response
     */
    public function insert(string $table, array $data)
    {
        // Build column list and value list
        $col_names = implode(', ', array_keys($data));
        $col_values = implode("', '", array_map(fn($v) => $this->escapeValue($v), array_values($data)));

        // Construct INSERT query with quoted values
        $query = "INSERT INTO {$table} ({$col_names}) VALUES ('{$col_values}')";

        // Remove quotes from numeric and boolean values
        $query = $this->sanitizeQuery($query);

        return $this->query($query);
    }

    /**
     * Batch insert multiple rows of data into a ClickHouse table
     *
     * @param string $table Target table name
     * @param array $columns Array of column names
     * @param array $rows Array of rows, each row is an array of values matching the columns
     * @return array Array of responses, one per executed batch
     */
    public function batchInsert(string $table, array $columns, array $rows)
    {
        if (empty($rows)) {
            return [];
        }

        $responses = [];
        $batch = [];
        $col_names = implode(', ', $columns);

        foreach ($rows as $index => $row) {
            // Add row to current batch
            $batch[] = $row;

            // If batch is full or this is the last row, execute the batch
            if (count($batch) >= $this->batch_size || $index == count($rows) - 1) {
                $values = [];

                // Build values for all rows in the batch
                foreach ($batch as $batchRow) {
                    $row_values = implode("', '", array_map(fn($v) => $this->escapeValue($v), $batchRow));
                    $values[] = "('{$row_values}')";
                }

                // Join all rows into a single INSERT statement
                $query = "INSERT INTO {$table} ({$col_names}) VALUES " . implode(", ", $values);

                // Remove quotes from numeric and boolean values
                $query = $this->sanitizeQuery($query);

                // Execute the batch
                $responses[] = $this->query($query);

                // Clear batch for next iteration
                $batch = [];
            }
        }

        return $responses;
    }

    /**
     * Update data in a ClickHouse table
     *
     * @param string $table Target table name
     * @param array $data Associative array of column => value pairs
     * @param string $where WHERE clause for the
     * @return string Query execution response
     */
    public function update(string $table, array $data, string $where)
    {
        // Build column list and value list
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = '{$this->escapeValue($value)}'";
        }
        $set = implode(', ', $set);

        // Construct INSERT query with quoted values
        $query = "ALTER TABLE {$table} UPDATE {$set} WHERE {$where}";

        // Remove quotes from numeric and boolean values
        $query = $this->sanitizeQuery($query);

        return $this->query($query);
    }

    /**
     * Escape a value for use in SQL
     *
     * @param mixed $value Value to escape
     * @return string|null Escaped value or 'null' for null values
     */
    private function escapeValue($value)
    {
        if (is_null($value)) {
            return 'null';
        }

        return addslashes((string)$value);
    }

    /**
     * Remove unnecessary quotes from numeric and boolean values
     *
     * @param string $query SQL query to sanitize
     * @return string Sanitized query
     */
    private function sanitizeQuery(string $query): string
    {
        // Use a more specific regex to avoid false positives
        $re = '/\'((?:\d+(?:\.\d+)?)|true|false|null)\'/m';
        return preg_replace($re, '$1', $query);
    }

    /**
     * Execute a SELECT query and return results as an array
     *
     * @param string $query SQL SELECT query
     * @param string $format Optional result format (default: JSONEachRow)
     * @return array Query results as an associative array
     */
    public function select(string $query, string $format = 'JSONEachRow')
    {
        // Add FORMAT clause if not present
        if (!preg_match('/FORMAT\s+\w+/i', $query)) {
            $query = trim(trim($query), ';') . " FORMAT {$format}";
        }

        $response = $this->query($query);

        if ($format === 'JSONEachRow') {
            // Each line is a JSON object
            $rows = [];
            foreach (explode("\n", trim($response)) as $line) {
                if (!empty($line)) {
                    $rows[] = json_decode($line, true);
                }
            }
            return $rows;
        } elseif ($format === 'JSON') {
            // Response is a JSON object with 'data' array
            $result = json_decode($response, true);
            return $result['data'] ?? [];
        }

        // For other formats, return raw response
        return $response;
    }

    /**
     * Get column information for a table
     *
     * @param string $table Table name
     * @return array Array of column information
     */
    public function getTableColumns(string $table): array
    {
        $query = "DESCRIBE TABLE {$table} FORMAT JSON";
        $response = $this->query($query);
        $result = json_decode($response, true);
        return $result['data'] ?? [];
    }

    /**
     * Check if a table exists
     *
     * @param string $table Table name
     * @return bool True if table exists
     */
    public function tableExists(string $table): bool
    {
        try {
            $this->getTableColumns($table);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }
}
