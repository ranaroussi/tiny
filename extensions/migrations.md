[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Migrations

The Migrations extension manages versioned database schema changes. It works with MySQL, PostgreSQL, and SQLite, and tracks applied migrations in a local SQLite ledger so applying twice is a no-op.

## The CLI

All migration operations go through `php tiny/cli migrations <command>`:

```bash
# Create a new migration file in migrations/
php tiny/cli migrations create create_users_table

# Apply all pending migrations
php tiny/cli migrations up

# Roll back the most recently applied batch
php tiny/cli migrations down

# Delete a migration file that hasn't been applied yet
php tiny/cli migrations remove create_users_table
```

A migration file is named with a timestamp prefix, e.g. `migrations/20240315120000_create_users_table.php`. Migrations are applied in filename order.

## The class shape

Every migration is a class with `up(): void` and `down(): void` methods. Inside, use `tiny::db()->execute()` for DDL and DML:

```php
<?php
class CreateUsersTable
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                name TEXT NOT NULL,
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

The class name should be the PascalCase form of the migration's slug (without the timestamp prefix). `create_users_table` → `CreateUsersTable`.

## Tracking

Applied migrations are tracked in `migrations/migrations.sqlite`. The ledger records:

- Which migrations have run
- Which **batch** they belong to (each `migrations up` invocation that applies new migrations is one batch)

`migrations down` rolls back the entire most-recent batch — so if a single `up` applied three migrations, the matching `down` reverts all three.

## Common operations

### Add a column

```php
class AddPhoneToUsers
{
    public function up(): void
    {
        tiny::db()->execute("ALTER TABLE users ADD COLUMN phone TEXT");
    }

    public function down(): void
    {
        tiny::db()->execute("ALTER TABLE users DROP COLUMN phone");
    }
}
```

### Add an index

```php
class IndexUsersEmail
{
    public function up(): void
    {
        tiny::db()->execute("CREATE INDEX idx_users_email ON users(email)");
    }

    public function down(): void
    {
        tiny::db()->execute("DROP INDEX idx_users_email");
    }
}
```

### Data migration

```php
class BackfillUserSlugs
{
    public function up(): void
    {
        $users = tiny::db()->get('users', null, 'id, name');
        foreach ($users as $u) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $u['name']));
            tiny::db()->update('users', ['slug' => $slug], ['id' => $u['id']]);
        }
    }

    public function down(): void
    {
        tiny::db()->execute("UPDATE users SET slug = NULL");
    }
}
```

## Best practices

1. **One logical change per migration.** Easier to roll back, easier to review.
2. **Always implement `down()`.** Even if it's just `DROP`. Future-you will thank you.
3. **Test the round-trip locally** (`up`, then `down`, then `up` again) before deploying.
4. **Never edit a migration after it's been applied to a shared environment.** Create a new one.
5. **Keep DDL portable.** If you target both PostgreSQL and MySQL, watch for `SERIAL` vs `AUTO_INCREMENT`, `NOW()` vs `CURRENT_TIMESTAMP`, etc.
6. **Commit migrations to version control.** They're part of the application, not artefacts.
