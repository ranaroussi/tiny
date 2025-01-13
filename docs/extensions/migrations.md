[Home](../readme.md) | [Getting Started](getting-started.md) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Database Migrations

The Migration extension helps you manage database schema changes in a version-controlled way.

## Basic Usage

### Creating Migrations

```bash
# Create a new migration
php tiny/cli migrations create create_users_table
```

This creates a new migration file in `migrations/`:

```php
<?php
// migrations/20240301_create_users_table.php

class CreateUsersTable
{
    private PDO $db;

    public function __construct()
    {
        $this->db = tiny::db()->getPdo();
    }

    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS users");
    }
}
```

### Running Migrations

```bash
# Run all pending migrations
php tiny/cli migrations up

# Rollback last migration
php tiny/cli migrations down

# Rollback specific migration
php tiny/cli migrations down create_users_table
```

## Migration Types

### Table Creation

```php
public function up(): void
{
    $this->db->exec("
        CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}
```

### Adding Columns

```php
public function up(): void
{
    $this->db->exec("
        ALTER TABLE users
        ADD COLUMN last_login TIMESTAMP NULL
    ");
}

public function down(): void
{
    $this->db->exec("
        ALTER TABLE users
        DROP COLUMN last_login
    ");
}
```

### Foreign Keys

```php
public function up(): void
{
    $this->db->exec("
        ALTER TABLE orders
        ADD CONSTRAINT fk_user_id
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
    ");
}
```

## Best Practices

1. **Naming Conventions**
   - Use descriptive names
   - Include action in name
   - Use timestamp prefix

2. **Migration Design**
   - One change per migration
   - Always provide down method
   - Test both up and down

3. **Data Handling**
   - Handle existing data
   - Use transactions
   - Consider large datasets

4. **Version Control**
   - Commit migrations
   - Never modify existing migrations
   - Document breaking changes
