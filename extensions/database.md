[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Database

The DB extension wraps PDO with a small, opinionated query builder. It supports **MySQL**, **PostgreSQL**, and **SQLite**.

For the conceptual overview see [Core Concepts → Database](../core-concepts/database.md). This page is the API reference.

## Configuration

Set in your `.env.<env>` file (the `TINY_*` prefix is the convention):

```env
TINY_DB_TYPE=postgres        # mysql | postgres | postgresql | sqlite
TINY_DB_HOST=localhost
TINY_DB_PORT=5432
TINY_DB_NAME=myapp
TINY_DB_USER=myuser
TINY_DB_PASS=secret
TINY_DB_PERSISTENT=true
TINY_DB_AUTOCONNECT=true

# SQLite-only
# TINY_DB_SQLITE_FILE=/path/to/db.sqlite
# TINY_DB_SQLITE_SCHEMA=/path/to/schema.sql
```

Set `TINY_DB_AUTOCONNECT=false` to skip the automatic connection on boot (useful for CLI scripts that don't need DB access).

## The accessor

```php
$db = tiny::db();   // returns TinyDB | null
```

`tiny::db()` is null if `TINY_DB_TYPE` is unset.

## Reads

```php
// All matching rows
$users = tiny::db()->get('users');                                // SELECT *
$users = tiny::db()->get('users', ['status' => 'active']);
$users = tiny::db()->get('users', ['status' => 'active'], 'id, name', 'created_at DESC', 10);

// Single row
$user = tiny::db()->getOne('users', ['id' => $id]);

// Everything in the table (no WHERE)
$all = tiny::db()->getAll('users', 'id, name', 'name ASC');

// Raw SELECT
$rows = tiny::db()->getQuery(
    "SELECT u.*, COUNT(o.id) AS orders FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id"
);
$rows = tiny::db()->getQuery("SELECT * FROM users WHERE id = ?", [$id]);
```

## Writes

```php
// Insert — returns the new ID
$id = tiny::db()->insert('users', [
    'name'  => 'Ada',
    'email' => 'ada@example.com',
]);

// Update
tiny::db()->update('users',
    ['status' => 'inactive'],
    ['role' => 'guest']
);

// Upsert (insert OR update on conflict)
tiny::db()->upsert('users',
    ['email' => 'ada@example.com', 'name' => 'Ada L.'],
    'email'
);

// Delete
tiny::db()->delete('users', ['status' => 'inactive']);

// Raw write
tiny::db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$id]);
```

## Preparing SQL strings

```php
$sql = tiny::db()->prepare(
    "SELECT * FROM users WHERE id = ? AND is_deleted = ?",
    [$id, false]
);
$rows = tiny::db()->getQuery($sql);
```

## Transactions

Use the underlying PDO instance:

```php
$pdo = tiny::db()->getPdo();
$pdo->beginTransaction();
try {
    tiny::db()->execute("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $from]);
    tiny::db()->execute("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$amount, $to]);
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

## ID of the last insert

```php
$id = tiny::db()->lastInsertId(null);
```

`insert()` already returns the new ID for most use cases; this is a separate accessor when you need it after a raw `execute()`.

## Escaping

```php
$safe = tiny::db()->escapeString($userInput);
```

> **Prefer parameter binding** (`['?'] + [$value]`) to manual escaping. Use this only when the placeholder API truly doesn't apply.

## Direct PDO access

```php
$pdo = tiny::db()->getPdo();
```

Useful for transactions, server-specific PDO attributes, or features the wrapper doesn't expose.

## Best practices

1. **Always bind parameters** — never concatenate user input into SQL.
2. **`get`/`getOne`/`insert`/`update` for trivial queries; drop to raw SQL for joins.**
3. **Wrap multi-statement writes in a transaction.** TinyDB doesn't do this for you.
4. **Use `TINY_DB_AUTOCONNECT=false`** in CLI scripts that don't need DB access.
5. **Cache hot reads** with `tiny::cache()->remember()`.
