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

const SQLITE3_OPEN_SHAREDCACHE = 0x00020000;

interface DB
{
    /**
     * Returns the \PDO instance.
     *
     * @return \PDO The \PDO instance
     */
    public function getPdo(): \PDO;

    /**
     * Executes a SQL query with optional parameters.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return bool|int True if the query was successful, false otherwise
     */
    public function execute(string $query, array $params = []): bool|int;

    /**
     * Prepares a SQL query by replacing placeholders with values.
     *
     * @param string $query The SQL query with placeholders
     * @param array|object $values The values to replace placeholders
     * @return string The prepared SQL query
     */
    public function prepare(string $query, array|object $values = []): string;

    /**
     * Returns the ID of the last inserted row.
     *
     * @param mixed $res Unused parameter (kept for compatibility)
     * @return string The last insert ID
     */
    public function lastInsertId($res): bool|int|string;

    /**
     * Executes a SQL query and returns the result set.
     *
     * @param string $query The SQL query to execute
     * @return array The result set as an array of associative arrays
     */
    public function getQuery(string $query): bool|array;

    /**
     * Retrieves rows from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|null $where The WHERE clause (optional)
     * @param string|array $fields The fields to select (default: '*')
     * @param string|null $orderby The ORDER BY clause (optional)
     * @param int|null $limit The LIMIT clause (optional)
     * @return array The result set as an array of associative arrays
     */
    public function get(string $table, ?string $where = null, ?string $fields = '*', ?string $orderby = null, ?int $limit = null): array;

    /**
     * Retrieves a single row from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|null $where The WHERE clause (optional)
     * @param string|array $fields The fields to select (default: '*')
     * @return mixed The first row of the result set or false if no rows found
     */
    public function getOne(string $table, ?string $where = null, string|array $fields = "*"): mixed;

    /**
     * Retrieves all rows from a table.
     *
     * @param string $table The name of the table
     * @param string|array $fields The fields to select (default: '*')
     * @param string|null $orderby The ORDER BY clause (optional)
     * @return array The result set as an array of associative arrays
     */
    public function getAll(string $table, ?string $fields = '*', ?string $orderby = null): bool|array;

    /**
     * Inserts a new row into a table.
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to insert
     * @return mixed The result of the insert operation or an error message
     */
    public function insert(string $table, array $data): mixed;

    /**
     * Updates rows in a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to update
     * @param string|array $conditions The WHERE conditions for the update
     * @return mixed The result of the update operation or an error message
     */
    public function update(string $table, array $data, string|array $conditions = []): mixed;

    /**
     * Deletes rows from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|array|null $conditions The WHERE conditions for the delete
     * @return mixed The result of the delete operation or an error message
     */
    public function delete(string $table, string|array|null $conditions = null): mixed;

    /**
     * Performs an upsert operation (insert or update on conflict).
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to insert or update
     * @param string $conflict The column(s) to check for conflicts
     * @return mixed The result of the upsert operation
     */
    public function upsert(string $table, array $data, string $conflict): mixed;

    /**
     * Escapes a string for use in a SQL query.
     *
     * @param mixed $text The text to escape
     * @return string The escaped string
     */
    public function escapeString($text);
}

class TinyDB implements DB
{
    private \PDO $pdo;
    private string $dbType;
    private bool $useSwoole;

    /**
     * Constructor for the TinyDB class.
     * Initializes a database connection based on the specified type and configuration.
     *
     * @param string $dbType The type of database (mysql, pgsql, postgresql, sqlite)
     * @param array $config Configuration array for the database connection
     * @throws \Exception If an unsupported database type is specified
     */
    public function __construct(string $dbType, array $config)
    {
        $this->dbType = mb_strtolower($dbType);
        $this->useSwoole = extension_loaded('swoole') && php_sapi_name() === 'cli';

        try {
            match ($this->dbType) {
                'mysql' => $this->connectMySQL($config),
                'pgsql', 'postgresql' => $this->connectPostgreSQL($config),
                'sqlite' => $this->connectSQLite($config),
                default => throw new \Exception("Unsupported database type: $dbType")
            };
        } catch (\PDOException $e) {
            throw new \Exception('Unable to connect to database: ' . $e->getMessage());
        }
    }

    /**
     * Establishes a connection to a MySQL database.
     *
     * @param array $config Configuration array for MySQL connection
     * @throws \Exception If unable to connect to any MySQL server
     */
    private function connectMySQL(array $config): void
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $dbname = $config['dbname'] ?? 'tiny';
        $user = $config['user'] ?? 'root';
        $password = $config['password'] ?? '';
        $timeout = $config['timeout'] ?? 5;
        $persistent = $config['persistent'] ?? false;

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_TIMEOUT => $timeout,
            ...($persistent ? [\PDO::ATTR_PERSISTENT => true] : [])
        ];

        $dsn = "mysql:host=" . $host . ";dbname=" . $dbname . ";port={$port};charset=utf8mb4";
        try {
            if ($this->useSwoole) {
                $this->pdo = new \PDO($dsn, $user, $password, $options);
            } else {
                $this->createSwooleConnection($dsn, $user, $password, $options);
            }
        } catch (\PDOException $e) {
            throw new \Exception('Unable to connect to any MySQL server.');
        }
    }

    /**
     * Establishes a connection to a PostgreSQL database.
     *
     * @param array $config Configuration array for PostgreSQL connection
     * @throws \Exception If unable to open the database connection
     */
    private function connectPostgreSQL(array $config): void
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $dbname = $config['dbname'] ?? 'tiny';
        $user = $config['user'] ?? 'root';
        $password = $config['password'] ?? '';
        $timeout = $config['timeout'] ?? 5;
        $persistent = $config['persistent'] ?? false;

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_TIMEOUT => $timeout,
            ...($persistent ? [\PDO::ATTR_PERSISTENT => true] : [])
        ];

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        try {
            if ($this->useSwoole) {
                $this->pdo = new \PDO($dsn, $user, $password, $options);
            } else {
                $this->createSwooleConnection($dsn, $user, $password, $options);
            }
        } catch (\PDOException $e) {
            throw new \Exception('Unable to open database: ' . $e->getMessage());
        }
    }

    /**
     * Establishes a connection to a SQLite database.
     *
     * @param array $config Configuration array for SQLite connection
     * @throws \Exception If unable to open the database connection
     */
    private function connectSQLite(array $config): void
    {
        $db_path = $config['db_path'] ?? '';
        $db_scheme = $config['db_scheme'] ?? null;

        if (!$db_path) {
            throw new \Exception('SQLite database path not provided.');
        }

        $existing_db = file_exists($db_path);
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::SQLITE_ATTR_OPEN_FLAGS => SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE | SQLITE3_OPEN_SHAREDCACHE
        ];

        try {
            if (!$this->useSwoole) {
                $this->pdo = new \PDO('sqlite:' . $db_path, null, null, $options);
            } else {
                $this->createSwooleConnection('sqlite:' . $db_path, null, null, $options);
            }

            $this->createSwooleConnection('sqlite:' . $db_path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::SQLITE_ATTR_OPEN_FLAGS => SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE | SQLITE3_OPEN_SHAREDCACHE
            ]);

            if (!$existing_db && $db_scheme != null) {
                $this->pdo->exec($db_scheme) or die('Create db failed');
            }
        } catch (\PDOException $e) {
            throw new \Exception('Unable to open database: ' . $e->getMessage());
        }
    }

    /**
     * Returns the \PDO instance.
     *
     * @return \PDO The \PDO instance
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Escapes a string for use in a SQL query.
     *
     * @param mixed $text The text to escape
     * @return string The escaped string
     */
    public function escapeString($text): string
    {
        return substr($this->pdo->quote($text), 1, -1);
    }

    /**
     * Prepares a SQL query by replacing placeholders with values.
     *
     * @param string $query The SQL query with placeholders
     * @param array|object $values The values to replace placeholders
     * @return string The prepared SQL query
     */
    public function prepare(string $query, array|object $values = []): string
    {
        if (isset($values['csrf_token'])) {
            unset($values['csrf_token']);
        }

        $db_methods = [
            'true',
            'false',
            'null',
            'localtime',
            'transaction_timestamp()',
            'statement_timestamp()',
            'clock_timestamp()',
            'timeofday()',
            'now()',
        ];

        $db_method_prefix = [
            'extract(',
            'date_',
            'current_',
            'timezone(',
            'pg_'
        ];

        $values = is_array($values) ? $values : [$values];
        foreach ($values as $value) {
            $is_db_method = false;
            $value = is_string($value) ? str_replace("\'", "''", addcslashes(tiny::trim($value), "'")) : $value;

            if (is_bool($value)) {
                $value = $value ? 'TRUE' : 'FALSE';
            } elseif ($value === false || $value === 'false') {
                $value = 'FALSE';
            } elseif ($value === true || $value === 'true') {
                $value = 'TRUE';
            } elseif (is_numeric($value)) {
                $value = str_contains((string)$value, '.') ? floatval($value) : intval($value);
            } elseif (in_array($value, [null, 'null', 'NULL'])) {
                $value = 'NULL';
            } elseif (!in_array(strtolower($value), $db_methods)) {
                foreach ($db_method_prefix as $db_prefix) {
                    if (str_starts_with(strtolower($value), $db_prefix)) {
                        $is_db_method = true;
                    }
                }
                if (!$is_db_method) {
                    $value = "'{$value}'";
                }
            }

            $value = is_array($value) ? $value : (string)$value;
            $query = preg_replace('/(?<!\\\)\?/', $value, $query, 1);
        }
        return $query;
    }

    /**
     * Executes a SQL query with optional parameters.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return bool|int True if the query was successful, false otherwise
     */
    public function execute(string $query, array $params = []): bool|int
    {
        // used for debugging
        // if (str_contains($query, 'ON CONFLICT')) {
        //     die(str_replace('\\?', '?', $this->prepare($query, $params)));
        // }
        return $this->pdo->exec(str_replace('\\?', '?', $this->prepare($query, $params)));
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @param mixed $res Unused parameter (kept for compatibility)
     * @return string The last insert ID
     */
    public function lastInsertId($res = null): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Executes a SQL query and returns the result set.
     *
     * @param string $query The SQL query to execute
     * @return array The result set as an array of associative arrays
     */
    public function getQuery($query): array
    {
        $stmt = $this->pdo->query(str_replace('\\?', '?', $query));
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Retrieves rows from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|null $where The WHERE clause (optional)
     * @param string|array $fields The fields to select (default: '*')
     * @param string|null $orderby The ORDER BY clause (optional)
     * @param int|null $limit The LIMIT clause (optional)
     * @return array The result set as an array of associative arrays
     */
    public function get(string $table, ?string $where = null, ?string $fields = '*', ?string $orderby = null, ?int $limit = null): array
    {
        $where = ($where) ? ' WHERE ' . $where : '';
        $orderby = ($orderby) ? ' ORDER BY ' . $orderby : '';
        $limit = ($limit) ? ' LIMIT ' . $limit : '';
        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $query = "SELECT $fields FROM $table $where $orderby $limit";
        $stmt = $this->pdo->query(str_replace('\\?', '?', $query));
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Retrieves a single row from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|null $where The WHERE clause (optional)
     * @param string|array $fields The fields to select (default: '*')
     * @return mixed The first row of the result set or false if no rows found
     */
    public function getOne(string $table, ?string $where = null, string|array $fields = "*"): mixed
    {
        $res = $this->get($table, $where, $fields, '', 1);
        return $res ? $res[0] : false;
    }

    /**
     * Retrieves all rows from a table.
     *
     * @param string $table The name of the table
     * @param string|array $fields The fields to select (default: '*')
     * @param string|null $orderby The ORDER BY clause (optional)
     * @return array The result set as an array of associative arrays
     */
    public function getAll(string $table, ?string $fields = '*', ?string $orderby = null): array
    {
        return $this->get($table, null, $fields, $orderby);
    }

    /**
     * Inserts a new row into a table.
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to insert
     * @return mixed The result of the insert operation or an error message
     */
    public function insert(string $table, array $data): mixed
    {
        if (isset($data['csrf_token'])) {
            unset($data['csrf_token']);
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            return $this->execute($query, $data);
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Updates rows in a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to update
     * @param string|array $conditions The WHERE conditions for the update
     * @return mixed The result of the update operation or an error message
     */
    public function update(string $table, array $data, string|array $conditions = []): mixed
    {
        if (isset($data['csrf_token'])) {
            unset($data['csrf_token']);
        }
        $set = implode('=?, ', array_keys($data)) . '=?';
        $query = "UPDATE $table SET $set";
        $values = array_values($data);

        if (is_array($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key=?";
                $values[] = $value;
            }
            $query .= ' WHERE ' . implode(' AND ', $where);
        } elseif (is_string($conditions)) {
            $query .= ' WHERE ' . $conditions;
        }

        try {
            return $this->execute($query, $values);
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Deletes rows from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|array|null $conditions The WHERE conditions for the delete
     * @return mixed The result of the delete operation or an error message
     */
    public function delete(string $table, string|array|null $conditions = null): mixed
    {
        $query = "DELETE FROM $table";
        $values = [];

        if (is_array($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key=?";
                $values[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $where);
        } elseif (is_string($conditions)) {
            $query .= " WHERE $conditions";
        }

        try {
            return $this->execute($query, $values);
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Alias for escape_string method.
     *
     * @param string $text The text to escape
     * @return string The escaped string
     */
    public function realEscapeString(string $text): string
    {
        return $this->escapeString($text);
    }

    /**
     * Executes a SQL query.
     *
     * @param string $query The SQL query to execute
     * @return PDOStatement|false The PDOStatement object or false on failure
     */
    public function query(string $query)
    {
        return $this->pdo->query(str_replace('\\?', '?', $query));
    }


    /**
     * Performs an upsert operation (insert or update on conflict).
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to insert or update
     * @param string $conflict The column(s) to check for conflicts
     * @return mixed The result of the upsert operation
     */
    public function upsert(string $table, array $data, string $conflict): mixed
    {
        unset($data['csrf_token']);

        if (in_array($this->dbType, ['postgresql', 'pgsql'])) {

            $keys = array_keys($data);
            $cols = implode(', ', $keys);

            $update_data = $data;
            unset($update_data['id']);
            reset($update_data);
            $update_keys = array_keys($update_data);
            $update = implode('=?, ', $update_keys) . '=?';

            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $query = "INSERT INTO $table ($cols) VALUES ($placeholders) ON CONFLICT ($conflict) DO UPDATE SET $update";
            return $this->execute($query, [...array_values($data), ...array_values($update_data)]);
        } else {
            // For MySQL and SQLite, use REPLACE INTO as a simple upsert
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $query = "REPLACE INTO $table ($columns) VALUES ($placeholders)";
            return $this->execute($query, array_values($data));
        }
    }

    /**
     * Executes a SQL query asynchronously.
     *
     * @param string $sql The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return mixed The result of the query or false on failure
     */
    public function asyncQuery($sql, $params = []): mixed
    {
        if ($this->useSwoole) {
            // Use existing Swoole PDO connection
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params) ? $stmt->fetchAll() : false;
        }

        // Fallback to regular query
        return $this->query($sql);
    }

    /**
     * Handles Swoole PDO connection.
     *
     * @param string $dsn The DSN string for the connection
     * @param ?string $user The username for the connection
     * @param ?string $password The password for the connection
     * @param array $options The options for the connection
     * @throws \PDOException If unable to connect to the database
     */
    private function createSwooleConnection(string $dsn, ?string $user = null, ?string $password = null, array $options = []): void
    {
        if ($this->useSwoole) {
            $this->pdo = new \Swoole\Runtime\PDO($dsn, $user, $password, $options);
        } else {
            $this->pdo = new \PDO($dsn, $user, $password, $options);
        }
    }
}
