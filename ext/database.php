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

interface DB
{
    /**
     * Returns the PDO instance.
     *
     * @return PDO The PDO instance
     */
    public function getPdo(): PDO;

    /**
     * Executes a SQL query with optional parameters.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return bool True if the query was successful, false otherwise
     */
    public function execute(string $query);

    /**
     * Returns the ID of the last inserted row.
     *
     * @param mixed $res Unused parameter (kept for compatibility)
     * @return string The last insert ID
     */
    public function last_insert_id($res): bool|int|string;

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
    public function getOne(string $table, ?string $where = null, ?string $fields = "*");

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
    public function insert(string $table, array $data);

    /**
     * Updates rows in a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to update
     * @param string|array $conditions The WHERE conditions for the update
     * @return mixed The result of the update operation or an error message
     */
    public function update(string $table, array $data, string|array $conditions = []);

    /**
     * Deletes rows from a table based on specified conditions.
     *
     * @param string $table The name of the table
     * @param string|array|null $conditions The WHERE conditions for the delete
     * @return mixed The result of the delete operation or an error message
     */
    public function delete(string $table, string|array|null $conditions = null);

    /**
     * Escapes a string for use in a SQL query.
     *
     * @param mixed $text The text to escape
     * @return string The escaped string
     */
    public function escape_string($text);
}


class TinyDB implements DB
{
    private PDO $pdo;
    private string $dbType;

    /**
     * Constructor for the TinyDB class.
     * Initializes a database connection based on the specified type and configuration.
     *
     * @param string $dbType The type of database (mysql, pgsql, postgresql, sqlite)
     * @param array $config Configuration array for the database connection
     * @throws Exception If an unsupported database type is specified
     */
    public function __construct(string $dbType, array $config)
    {
        $this->dbType = strtolower($dbType);

        switch ($this->dbType) {
            case 'mysql':
                $this->connectMySQL($config);
                break;
            case 'pgsql':
            case 'postgresql':
                $this->connectPostgreSQL($config);
                break;
            case 'sqlite':
                $this->connectSQLite($config);
                break;
            default:
                throw new Exception("Unsupported database type: $dbType");
        }
    }

    /**
     * Establishes a connection to a MySQL database.
     *
     * @param array $config Configuration array for MySQL connection
     * @throws Exception If unable to connect to any MySQL server
     */
    private function connectMySQL(array $config): void
    {
        $host     = $config['host'] ?? 'localhost';
        $port     = $config['port'] ?? 3306;
        $dbname   = $config['dbname'] ?? 'tiny';
        $user     = $config['user'] ?? 'root';
        $password = $config['password'] ?? '';
        $timeout = $config['timeout'] ?? 5;

        if (!is_array($host)) {
            $host = $host ? explode(',', tiny::trim(str_replace(' ', '', $host), ',')) : [];
        }

        if (empty($host)) {
            throw new Exception('No MySQL servers provided.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => $timeout
        ];

        $dsn = "mysql:host=" . tiny::trim($host) . ";dbname=" . tiny::trim($dbname) . ";port={$port};charset=utf8mb4";
        try {
            $this->pdo = new PDO($dsn, tiny::trim($user), tiny::trim($password), $options);
        } catch (PDOException $e) {
            throw new Exception('Unable to connect to any MySQL server.');
        }

    }

    /**
     * Establishes a connection to a PostgreSQL database.
     *
     * @param array $config Configuration array for PostgreSQL connection
     * @throws Exception If unable to open the database connection
     */
    private function connectPostgreSQL(array $config): void
    {
        $host     = $config['host'] ?? 'localhost';
        $port     = $config['port'] ?? 5432;
        $dbname   = $config['dbname'] ?? 'tiny';
        $user     = $config['user'] ?? 'root';
        $password = $config['password'] ?? '';
        $timeout  = $config['timeout'] ?? 5;

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        try {
            $this->pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $timeout
            ]);
        } catch (PDOException $e) {
            throw new Exception('Unable to open database: ' . $e->getMessage());
        }
    }

    /**
     * Establishes a connection to a SQLite database.
     *
     * @param array $config Configuration array for SQLite connection
     * @throws Exception If unable to open the database connection
     */
    private function connectSQLite(array $config): void
    {
        $db_path = $config['db_path'] ?? '';
        $db_scheme = $config['db_scheme'] ?? null;

        if (!$db_path) {
            throw new Exception('SQLite database path not provided.');
        }

        $existing_db = file_exists($db_path);
        try {
            $this->pdo = new PDO('sqlite:' . $db_path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::SQLITE_ATTR_OPEN_FLAGS => SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE | SQLITE3_OPEN_SHAREDCACHE
            ]);

            if (!$existing_db && $db_scheme != null) {
                $this->pdo->exec($db_scheme) or die('Create db failed');
            }
        } catch (PDOException $e) {
            throw new Exception('Unable to open database: ' . $e->getMessage());
        }
    }

    /**
     * Returns the PDO instance.
     *
     * @return PDO The PDO instance
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Escapes a string for use in a SQL query.
     *
     * @param mixed $text The text to escape
     * @return string The escaped string
     */
    public function escape_string($text): string
    {
        return substr($this->pdo->quote($text), 1, -1);
    }

    /**
     * Executes a SQL query with optional parameters.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return bool True if the query was successful, false otherwise
     */
    public function execute(string $query, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @param mixed $res Unused parameter (kept for compatibility)
     * @return string The last insert ID
     */
    public function last_insert_id($res = null): string
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
        $stmt = $this->pdo->query($query);
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
        $stmt = $this->pdo->query($query);
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
    public function getOne(string $table, ?string $where = null, ?string $fields = "*")
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
    public function insert(string $table, array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(array_values($data));
            return $stmt;
        } catch (PDOException $e) {
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
    public function update(string $table, array $data, string|array $conditions = [])
    {
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
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($values);
            return $stmt;
        } catch (PDOException $e) {
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
    public function delete(string $table, string|array|null $conditions = null)
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
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Alias for escape_string method.
     *
     * @param string $text The text to escape
     * @return string The escaped string
     */
    public function real_escape_string(string $text): string
    {
        return $this->escape_string($text);
    }

    /**
     * Executes a SQL query.
     *
     * @param string $query The SQL query to execute
     * @return PDOStatement|false The PDOStatement object or false on failure
     */
    public function query(string $query)
    {
        return $this->pdo->query($query);
    }

    /**
     * Prepares a SQL statement for execution.
     *
     * @param string $query The SQL query to prepare
     * @return PDOStatement The prepared PDOStatement object
     */
    public function prepare(string $query): PDOStatement
    {
        return $this->pdo->prepare($query);
    }

    /**
     * Performs an upsert operation (insert or update on conflict).
     *
     * @param string $table The name of the table
     * @param array $data An associative array of column-value pairs to insert or update
     * @param string $conflict The column(s) to check for conflicts
     * @return PDOStatement|bool The result of the upsert operation
     */
    public function upsert(string $table, array $data, string $conflict): PDOStatement|bool
    {
        if ($this->dbType === 'pgsql') {
            $keys = array_keys($data);
            $cols = implode(', ', $keys);
            $update = implode('=?, ', $keys) . '=?';
            $placeholders = trim(str_repeat('?, ', count($data)), ', ');
            $query = "INSERT INTO $table ($cols) VALUES ($placeholders) ON CONFLICT ($conflict) DO UPDATE SET $update";
            return $this->execute($query, array_merge(array_values($data), array_values($data)));
        } else {
            // For MySQL and SQLite, use REPLACE INTO as a simple upsert
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $query = "REPLACE INTO $table ($columns) VALUES ($placeholders)";
            return $this->execute($query, array_values($data));
        }
    }
}
