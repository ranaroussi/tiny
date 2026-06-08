[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Database

Tiny ships with a thin, opinionated PDO wrapper called `TinyDB`. It supports **MySQL**, **PostgreSQL**, and **SQLite**, and exposes a single accessor: `tiny::db()`.

For ClickHouse, see [`tiny::clickhouse()`](../extensions/clickhouse.md).

## Configuration

Set the connection in your `.env.<env>` file:

```env
TINY_DB_TYPE=postgres        # mysql | postgres | postgresql | sqlite
TINY_DB_HOST=localhost
TINY_DB_PORT=5432
TINY_DB_NAME=myapp
TINY_DB_USER=myuser
TINY_DB_PASS=
************

# Optional
TINY_DB_PERSISTENT=true
TINY_DB_AUTOCONNECT=true

# SQLite only
# TINY_DB_SQLITE_FILE=/path/to/db.sqlite
# TINY_DB_SQLITE_SCHEMA=/path/to/schema.sql   # applied on first creation
```

Setting `TINY_DB_AUTOCONNECT=false` disables the automatic connection on boot — useful for CLI scripts that don't need a DB.

## The query API

`tiny::db()` returns a `TinyDB` instance implementing the `DB` interface. The full surface:

| Method | Purpose |
|---|---|
| `get(table, where = null, fields = '*', orderby = null, limit = null)` | Fetch rows from a table |
| `getOne(table, where = null, fields = '*')` | Fetch the first matching row |
| `getAll(table, fields = '*', orderby = null)` | Fetch every row |
| `getQuery(query, params = [])` | Run a raw SELECT and get rows |
| `execute(query, params = [])` | Run a raw write (INSERT/UPDATE/DELETE/DDL) |
| `prepare(query, values)` | Substitute placeholders and return the prepared SQL string |
| `insert(table, data)` | Insert a row, returns the new ID |
| `update(table, data, where)` | Update rows matching `where` |
| `upsert(table, data, conflict)` | Insert or update on conflict |
| `delete(table, where = null)` | Delete rows matching `where` |
| `lastInsertId($res)` | Last insert ID |
| `escapeString($text)` | Escape a value for safe interpolation |
| `getPdo()` | Get the underlying `\PDO` instance |

## Basic operations

### Reading

```php
// All rows
$users = tiny::db()->get('users');

// With WHERE conditions (associative => equality, joined with AND)
$active = tiny::db()->get('users', ['status' => 'active', 'role' => 'admin']);

// Selected columns + ORDER BY + LIMIT
$recent = tiny::db()->get(
    'users',
    ['status' => 'active'],
    'id, name, email',
    'created_at DESC',
    10
);

// Single row
$user = tiny::db()->getOne('users', ['id' => $id]);

// All rows, ordered
$ranking = tiny::db()->getAll('users', 'id, name, score', 'score DESC');
```

### Writing

```php
// Insert — returns the new id
$id = tiny::db()->insert('users', [
    'name'  => 'Ada',
    'email' => 'ada@example.com',
]);

// Update
tiny::db()->update('users',
    ['status' => 'inactive'],
    ['role' => 'guest']
);

// Upsert (insert on no-conflict, update on conflict)
tiny::db()->upsert('users',
    ['email' => 'ada@example.com', 'name' => 'Ada L.'],
    'email'   // unique column to detect conflict on
);

// Delete
tiny::db()->delete('users', ['status' => 'inactive']);
```

## Raw SQL

For anything the helpers don't cover, fall back to raw SQL with placeholder binding:

```php
$rows = tiny::db()->getQuery(
    "SELECT u.*, COUNT(o.id) AS orders
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     WHERE u.created_at > ?
     GROUP BY u.id",
    ['2024-01-01']
);

tiny::db()->execute(
    "UPDATE users SET last_login = NOW() WHERE id = ?",
    [$userId]
);
```

`prepare()` is also exposed when you need the rendered SQL string (logging, dry-run, etc.):

```php
$sql = tiny::db()->prepare(
    "SELECT * FROM users WHERE id = ? AND is_deleted = ?",
    [$userId, false]
);
$users = tiny::db()->getQuery($sql);
```

## Transactions

Use the underlying PDO for transactions:

```php
$pdo = tiny::db()->getPdo();
$pdo->beginTransaction();

try {
    tiny::db()->execute("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $fromId]);
    tiny::db()->execute("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$amount, $toId]);
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

## Migrations

Schema changes are managed by the [migrations extension](../extensions/migrations.md). In short:

```bash
php tiny/cli migrations create create_users_table
php tiny/cli migrations up
php tiny/cli migrations down
```

A migration class is a plain PHP class with `up()` and `down()` methods that call `tiny::db()->execute(...)`:

```php
<?php
class CreateUsersTable
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS users");
    }
}
```

## Best practices

1. **Always parameterise input.** Pass `[$value]` instead of concatenating into SQL.
2. **Prefer helpers (`get`, `getOne`, `insert`, `update`) for trivial queries; drop to raw SQL for joins and aggregates.**
3. **Cache hot reads** with `tiny::cache()->remember()`.
4. **Wrap multi-statement writes in a transaction** — TinyDB doesn't do this for you.
5. **Use `TINY_DB_AUTOCONNECT=false`** in CLI / scheduler contexts that don't need DB access to avoid wasted connections.
