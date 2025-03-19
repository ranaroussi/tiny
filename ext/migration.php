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

require_once __DIR__ . '/../bootstrap.php';

class TinyMigration
{
    private $db;
    private $sqliteDb;
    private $path;

    /**
     * Constructor for the TinyMigration class.
     * Initializes the migration path, database connections, and SQLite database for tracking migrations.
     */
    public function __construct()
    {
        $this->path = explode('/tiny/', __DIR__)[0] . '/migrations/';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
        $this->initSqliteDb();
        $this->db = tiny::db()->getPdo();
    }

    /**
     * Initializes the SQLite database used for tracking migrations.
     */
    private function initSqliteDb(): void
    {
        $this->sqliteDb = new PDO('sqlite:' . $this->path . 'migrations.sqlite');
        $this->sqliteDb->exec('CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY, name TEXT, batch INTEGER)');
    }

    /**
     * Creates a new migration file.
     *
     * @param string $name The name of the migration
     * @throws RuntimeException If a migration with the same name already exists
     */
    public function create(string $name): void
    {
        $timestamp = date('YmdHis');
        $createdAt = date('Y-m-d H:i:s');
        $filename = $timestamp . '_' . $name . '.php';

        // Check if a migration with the same name already exists
        $existingFiles = glob($this->path . '*_' . $name . '.php');
        if (!empty($existingFiles)) {
            die("[!] A migration with the name '$name' already exists.");
        }

        $content = $this->getMigrationTemplate($name, $createdAt);

        file_put_contents($this->path . $filename, $content);
        echo "[✓] TinyMigration created: migrations/$filename\n";
    }

    /**
     * Runs all pending migrations.
     */
    public function up(): void
    {
        $migrations = $this->getPendingMigrations();
        $batch = $this->getNextBatchNumber();

        foreach ($migrations as $migration) {
            require_once $migration;
            $className = $this->getMigrationClassName($migration);
            $instance = new $className();

            try {
                $this->db->beginTransaction();
                $instance->up($this->db);
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                }
                $this->markMigrationAsRun($migration, $batch);
                echo "Migrated: " . basename($migration) . "\n";
            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                echo "Error migrating " . basename($migration) . ": " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Rolls back the last batch of migrations.
     */
    public function down(): void
    {
        $lastBatch = $this->getLastBatchNumber();
        $migrations = $this->getLastBatchMigrations($lastBatch);

        foreach (array_reverse($migrations) as $migration) {
            require_once $migration;
            $className = $this->getMigrationClassName($migration);
            $instance = new $className();

            try {
                $this->db->beginTransaction();
                $instance->down($this->db);
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                }
                $this->removeMigrationRecord($migration);
                echo "Rolled back: " . basename($migration) . "\n";
            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                echo "Error rolling back " . basename($migration) . ": " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Removes a migration file if it hasn't been run yet.
     *
     * @param string $name The name of the migration to remove
     */
    public function remove(string $name): void
    {
        $files = glob($this->path . '*_' . $name . '.php');
        if (empty($files)) {
            echo "[!] No migration found with the name '$name'.\n";
            return;
        }

        if (count($files) > 1) {
            echo "[!] Multiple migrations found with the name '$name'. Please specify the full filename:\n";
            foreach ($files as $file) {
                echo "   " . basename($file) . "\n";
            }
            return;
        }

        $file = $files[0];
        $filename = basename($file);

        // Check if the migration has been run
        $stmt = $this->sqliteDb->prepare('SELECT * FROM migrations WHERE name = ?');
        $stmt->execute([$filename]);
        $migration = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($migration) {
            echo "[!] Cannot remove migration '$filename' as it has already been run (batch {$migration['batch']}).\n";
            echo "    If you want to revert this migration, use the 'down' command instead.\n";
            return;
        }

        // Remove the file
        if (unlink($file)) {
            echo "[✓] TinyMigration removed: $filename\n";
        } else {
            echo "[!] Failed to remove migration file: $filename\n";
        }
    }

    /**
     * Retrieves all pending migrations.
     *
     * @return array An array of pending migration file paths
     */
    private function getPendingMigrations(): array
    {
        $files = glob($this->path . '*.php');
        $ranMigrations = $this->getRanMigrations();
        return array_filter($files, fn($file) => !in_array(basename($file), $ranMigrations));
    }

    /**
     * Retrieves all migrations that have been run.
     *
     * @return array An array of migration names that have been run
     */
    private function getRanMigrations(): array
    {
        $stmt = $this->sqliteDb->query('SELECT name FROM migrations');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Gets the next batch number for migrations.
     *
     * @return int The next batch number
     */
    private function getNextBatchNumber(): int
    {
        $stmt = $this->sqliteDb->query('SELECT MAX(batch) FROM migrations');
        return (int)$stmt->fetchColumn() + 1;
    }

    /**
     * Gets the last batch number of migrations.
     *
     * @return int The last batch number
     */
    private function getLastBatchNumber(): int
    {
        $stmt = $this->sqliteDb->query('SELECT MAX(batch) FROM migrations');
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieves migrations from the last batch.
     *
     * @param int $batch The batch number
     * @return array An array of migration file paths from the specified batch
     */
    private function getLastBatchMigrations(int $batch): array
    {
        $stmt = $this->sqliteDb->prepare('SELECT name FROM migrations WHERE batch = ?');
        $stmt->execute([$batch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map(fn($name) => $this->path . $name, $migrations);
    }

    /**
     * Marks a migration as run in the database.
     *
     * @param string $migration The migration file path
     * @param int $batch The batch number
     */
    private function markMigrationAsRun(string $migration, int $batch): void
    {
        $stmt = $this->sqliteDb->prepare('INSERT INTO migrations (name, batch) VALUES (?, ?)');
        $stmt->execute([basename($migration), $batch]);
    }

    /**
     * Removes a migration record from the database.
     *
     * @param string $migration The migration file path
     */
    private function removeMigrationRecord(string $migration): void
    {
        $stmt = $this->sqliteDb->prepare('DELETE FROM migrations WHERE name = ?');
        $stmt->execute([basename($migration)]);
    }

    /**
     * Gets the class name from a migration file path.
     *
     * @param string $migration The migration file path
     * @return string The migration class name
     */
    private function getMigrationClassName(string $migration): string
    {
        $file_name = pathinfo(basename($migration), PATHINFO_FILENAME);
        $file_name = substr($file_name, 15);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $file_name)));
    }

    /**
     * Generates the content for a new migration file.
     *
     * @param string $name The name of the migration
     * @param string $createdAt The creation timestamp
     * @return string The content of the migration file
     */
    private function getMigrationTemplate(string $name, string $createdAt): string
    {
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        return <<<PHP
<?php
declare(strict_types=1);

/**
 * @migration: $className
 * @generated: $createdAt
 * @description: ...
 */

class {$className}
{
    private PDO \$db;
    public function __construct()
    {
        \$this->db = tiny::db()->getPdo();
    }

    public function up(): void
    {
        \$this->db->exec("--sql
            -- Your SQL for the 'up' migration
        ");
    }

    public function down(): void
    {
        \$this->db->exec("--sql
            -- Your SQL for the 'down' migration
        ");
    }
}
PHP;
    }
}


// // --------------

// if (tiny::isCLI()) {
//     $migration = new TinyMigration();

//     if ($argc < 2) {
//         echo "Usage: php migration.php [create|up|down|remove] [name]\n";
//         exit(1);
//     }

//     $command = $argv[1];

//     switch ($command) {
//         case 'create':
//             if ($argc < 3) {
//                 echo "Please provide a name for the migration.\n";
//                 exit(1);
//             }
//             $migration->create($argv[2]);
//             break;
//         case 'up':
//             $migration->up();
//             break;
//         case 'down':
//             $migration->down();
//             break;
//         case 'remove':
//             if ($argc < 3) {
//                 echo "Please provide a name for the migration to remove.\n";
//                 exit(1);
//             }
//             $migration->remove($argv[2]);
//             break;
//         default:
//             echo "Invalid command. Use 'create', 'up', 'down', or 'remove'.\n";
//             exit(1);
//     }
// }
