[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Database Extension

The Database extension provides a simple and efficient interface for database operations, supporting MySQL, PostgreSQL, and SQLite.

## Configuration

Configure your database connection in your `.env` file:

```env
DB_TYPE=mysql     # mysql, pgsql, postgresql, or sqlite
DB_HOST=localhost # not needed for sqlite
DB_NAME=myapp
DB_USER=root      # not needed for sqlite
DB_PASS=          # not needed for sqlite
DB_PORT=3306      # 3306 for MySQL, 5432 for PostgreSQL
```

## Basic Usage

```php
// Basic CRUD Operations
// Create
$app->db->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Read
$user = $app->db->getOne('users', 'email = "john@example.com"');
$users = $app->db->get('users', 'active = 1', '*', 'name ASC', 10);
$allUsers = $app->db->getAll('users');

// Update
$app->db->update('users',
    ['active' => 1],
    'email = "john@example.com"'
);

// Delete
$app->db->delete('users', 'email = "john@example.com"');

// Upsert (Insert or Update)
$app->db->upsert('users', [
    'email' => 'john@example.com',
    'name' => 'John Doe'
], 'email');
```

## Raw Queries

```php
// Execute a raw query
$app->db->execute('UPDATE users SET active = 1 WHERE id = ?', [123]);

// Get results from a raw query
$results = $app->db->getQuery('SELECT * FROM users WHERE active = 1');

// Prepare a query with named parameters
$query = $app->db->prepare(
    'SELECT * FROM users WHERE name = :name',
    ['name' => 'John']
);
```

## Advanced Features

### PDO Access
```php
// Get direct PDO instance for advanced operations
$pdo = $app->db->getPdo();
```

### String Escaping
```php
// Safely escape strings for queries
$safe = $app->db->escapeString($unsafeString);
```

## Database Types

### MySQL
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=myapp
DB_USER=root
DB_PASS=
```

### PostgreSQL
```env
DB_TYPE=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=myapp
DB_USER=postgres
DB_PASS=
```

### SQLite
```env
DB_TYPE=sqlite
DB_NAME=/path/to/database.sqlite
```

## Best Practices

1. **Use Prepared Statements**
   - Always use prepared statements or parameter binding
   - Never concatenate values directly into queries
   - Use the built-in methods that handle this automatically

2. **Error Handling**
   - Database operations can throw exceptions
   - Wrap database operations in try-catch blocks
   - Log database errors appropriately

3. **Connection Management**
   - The framework handles connection pooling
   - Connections are automatically closed when needed
   - Use transactions for multiple related operations

4. **Query Optimization**
   - Select only needed columns
   - Use appropriate indexes
   - Keep queries simple and efficient
