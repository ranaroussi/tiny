[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Migrations Extension

The Migrations extension provides a way to manage database schema changes over time. It supports creating, running, and rolling back migrations for MySQL, PostgreSQL, and SQLite databases.

## Basic Usage

### Creating a Migration

```php
// Create a new migration
$app->migration->create('create_users_table');
```

This will create a new migration file in the `migrations` directory with a timestamp prefix:
```php
<?php

class CreateUsersTable
{
    public function up()
    {
        $sql = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        tiny::db()->execute($sql);
    }

    public function down()
    {
        tiny::db()->execute("DROP TABLE IF EXISTS users");
    }
}
```

### Running Migrations

```php
// Run all pending migrations
$app->migration->up();

// Rollback the last batch of migrations
$app->migration->down();

// Remove a pending migration
$app->migration->remove('create_users_table');
```

## Migration Files

### File Structure
- Migrations are stored in the `migrations` directory
- Files are prefixed with a timestamp (e.g., `20240101123456_create_users_table.php`)
- Each migration class must implement `up()` and `down()` methods

### Example Migrations

#### Creating a Table
```php
class CreateProductsTable
{
    public function up()
    {
        $sql = "CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        tiny::db()->execute($sql);
    }

    public function down()
    {
        tiny::db()->execute("DROP TABLE IF EXISTS products");
    }
}
```

#### Modifying a Table
```php
class AddCategoryToProducts
{
    public function up()
    {
        $sql = "ALTER TABLE products
                ADD COLUMN category_id INT,
                ADD FOREIGN KEY (category_id)
                REFERENCES categories(id)";

        tiny::db()->execute($sql);
    }

    public function down()
    {
        $sql = "ALTER TABLE products
                DROP FOREIGN KEY products_category_id_foreign,
                DROP COLUMN category_id";

        tiny::db()->execute($sql);
    }
}
```

## Migration Tracking

- Migrations are tracked in an SQLite database (`migrations/migrations.sqlite`)
- Each migration is assigned a batch number
- Rollbacks are performed by batch
- Migration status is stored to prevent duplicate runs

## Best Practices

1. **Naming Conventions**
   - Use descriptive names for migrations
   - Prefix table operations (create_, add_, modify_, remove_)
   - Keep names concise but clear

2. **Migration Content**
   - One migration per logical change
   - Include both up and down methods
   - Test rollback functionality
   - Use appropriate SQL for your database type

3. **Database Operations**
   - Use transactions where appropriate
   - Consider data preservation in rollbacks
   - Add appropriate indexes
   - Set proper column types and constraints

4. **Version Control**
   - Commit migration files to version control
   - Never modify existing migrations
   - Create new migrations for changes
   - Document breaking changes

## Common Operations

### Indexes and Keys
```php
// Adding indexes
$sql = "CREATE INDEX idx_user_email ON users(email)";

// Adding foreign keys
$sql = "ALTER TABLE posts
        ADD CONSTRAINT fk_user_id
        FOREIGN KEY (user_id)
        REFERENCES users(id)";
```

### Column Modifications
```php
// Adding columns
$sql = "ALTER TABLE users
        ADD COLUMN last_login TIMESTAMP NULL";

// Modifying columns
$sql = "ALTER TABLE users
        MODIFY email VARCHAR(320) NOT NULL";

// Dropping columns
$sql = "ALTER TABLE users
        DROP COLUMN temporary_field";
```

### Data Migration
```php
// Moving data between tables
$sql = "INSERT INTO new_table
        SELECT * FROM old_table
        WHERE condition = true";
```
