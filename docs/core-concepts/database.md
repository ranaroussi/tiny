[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Database Operations

Tiny PHP provides a simple yet powerful database abstraction layer. Here's how to work with databases in your application.

## Configuration

Configure your database connection in `.env`:

```env
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=myapp
DB_USER=root
DB_PASS=
DB_PORT=3306
```

## Basic Operations

### Fetching Data

```php
// Get all records
$users = tiny::db()->get('users');

// Get with conditions
$activeUsers = tiny::db()->get('users', [
    'status' => 'active',
    'role' => 'admin'
]);

// Get with custom columns and ordering
$users = tiny::db()->get(
    'users',
    ['status' => 'active'],
    'id, name, email',
    'created_at DESC',
    10  // limit
);

// Get single record
$user = tiny::db()->getOne('users', ['id' => 123]);
```

### Inserting Data

```php
// Simple insert
$userId = tiny::db()->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Insert multiple records
$data = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
];
tiny::db()->insertMultiple('users', $data);
```

### Updating Data

```php
// Update records
tiny::db()->update('users',
    ['status' => 'inactive'], // data to update
    ['role' => 'guest']      // where condition
);

// Update or insert (upsert)
tiny::db()->upsert('users', [
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### Deleting Data

```php
// Delete records
tiny::db()->delete('users', ['status' => 'inactive']);

// Delete all records
tiny::db()->truncate('users');
```

## Raw Queries

For complex queries, you can use raw SQL:

```php
// Raw query with parameters
$users = tiny::db()->query(
    "SELECT * FROM users WHERE role = ? AND status = ?",
    ['admin', 'active']
);

// Raw execute
tiny::db()->execute(
    "UPDATE users SET status = ? WHERE last_login < ?",
    ['inactive', '2023-01-01']
);
```

## Transactions

Handle multiple operations atomically:

```php
try {
    tiny::db()->beginTransaction();

    tiny::db()->insert('orders', $orderData);
    tiny::db()->update('inventory', $inventoryData);

    tiny::db()->commit();
} catch (Exception $e) {
    tiny::db()->rollback();
    throw $e;
}
```

## Query Building

Build complex queries programmatically:

```php
$query = tiny::db()->prepare(
    "SELECT * FROM users WHERE status = ? AND role IN (?)",
    ['active', ['admin', 'moderator']]
);

// Results in: SELECT * FROM users WHERE status = 'active'
// AND role IN ('admin', 'moderator')
```

## Best Practices

1. **Use Prepared Statements**
   ```php
   // Good
   $user = tiny::db()->getOne('users', ['id' => $id]);

   // Avoid
   $user = tiny::db()->query("SELECT * FROM users WHERE id = $id");
   ```

2. **Handle Errors**
   ```php
   try {
       $result = tiny::db()->insert('users', $data);
   } catch (PDOException $e) {
       // Handle database errors
       tiny::log('Database error', $e->getMessage());
   }
   ```

3. **Use Transactions for Multiple Operations**
   ```php
   tiny::db()->beginTransaction();
   try {
       // Multiple database operations
       tiny::db()->commit();
   } catch (Exception $e) {
       tiny::db()->rollback();
   }
   ```

4. **Validate Data Before Operations**
   ```php
   if ($this->isValid($data, $this->schemas['user'])) {
       tiny::db()->insert('users', $data);
   }
   ```
