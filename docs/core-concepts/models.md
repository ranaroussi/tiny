[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Models

Models in Tiny encapsulate data access and business rules. They extend `TinyModel`, live in `app/models/`, and are loaded with `tiny::model('name')`.

## Basic structure

`app/models/user.php`:

```php
<?php
class UserModel extends TinyModel
{
    public function all(): array
    {
        return tiny::db()->getAll('users', '*', 'name ASC');
    }

    public function byId(int $id): ?array
    {
        return tiny::db()->getOne('users', ['id' => $id]) ?: null;
    }

    public function create(array $data): int|bool
    {
        return tiny::db()->insert('users', [
            'name'       => $data['name'],
            'email'      => $data['email'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return (bool) tiny::db()->update('users', $data, ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        return (bool) tiny::db()->delete('users', ['id' => $id]);
    }
}
```

> **Naming convention:** the file is `app/models/user.php`, the class is `UserModel`. `tiny::model('user')` looks for `app/models/user.php` and instantiates the class found there.

## Using models from controllers

```php
class Users extends TinyController
{
    private UserModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = tiny::model('user');
    }

    public function get($request, $response)
    {
        $response->render('users/index', ['users' => $this->model->all()]);
    }
}
```

Models are cached per request, so calling `tiny::model('user')` twice returns the same instance.

## Validation with schemas

`TinyModel` ships with a schema validator. Define schemas as associative arrays and call `isValid($data, $schema)`:

```php
<?php
class UserModel extends TinyModel
{
    public array $schemas = [
        'account' => [
            'name'   => 'string(100)',
            'email'  => 'string(255)',
            'active' => 'bool',
            'role'   => 'string',
            'tags'   => '[array(50)]',     // optional, max 50 items
            'bio'    => '[string]|null',   // either string or null (optional)
        ],
    ];

    public function updateAccount(array $data): bool
    {
        if (!$this->isValid($data, $this->schemas['account'])) {
            return false;  // $this->validationErrors is now populated
        }
        return (bool) tiny::db()->update('users', $data, ['id' => $data['id']]);
    }
}
```

### Schema grammar

| Token | Meaning |
|---|---|
| `string` | Must be a string |
| `int` | Must be an integer |
| `bool` | Must be a boolean |
| `float` / `double` | Must be a float |
| `array` | Must be an array |
| `object` | Must be an object |
| `callable` / `resource` | PHP standard |
| `datetime` | Parseable by `strtotime` |
| `string(255)` | String, max 255 chars |
| `array(10)` | Array, max 10 items |
| `[string]` | Optional (allows `null` / missing) |
| `string\|int` | Union — must satisfy any side |
| `MyEnum` | PHP enum case value |
| `MyClass` | Instance of class |

After validation, errors are available as `$model->validationErrors` (field → reason map). Use `validationErrorsToAlpineJs()` to project them into an Alpine.js binding string:

```php
<div x-data="{ invalid: {} }" x-init="<?= $model->validationErrorsToAlpineJs() ?>">
    <input :class="{ 'border-red-500': invalid.email }">
</div>
```

## Database operations from models

All DB helpers (see [Database](database.md)) are available via `tiny::db()`:

```php
public function activeUsers(int $limit = 50): array
{
    return tiny::db()->get(
        'users',
        ['status' => 'active'],
        'id, name, email, last_login',
        'last_login DESC',
        $limit
    );
}

public function recentSignups(string $since): array
{
    return tiny::db()->getQuery(
        "SELECT * FROM users WHERE created_at > ? ORDER BY created_at DESC",
        [$since]
    );
}
```

## Caching reads

`tiny::cache()->remember()` is the standard pattern for hot lookups:

```php
public function byId(int $id): ?array
{
    return tiny::cache()->remember("user:$id", 60, function () use ($id) {
        return tiny::db()->getOne('users', ['id' => $id]);
    }) ?: null;
}

public function invalidate(int $id): void
{
    tiny::cache()->delete("user:$id");
}
```

Use `tiny::cache()->deleteByPrefix("user:")` to nuke an entire family of keys at once.

## Transactions inside models

```php
public function transfer(int $fromId, int $toId, float $amount): bool
{
    $pdo = tiny::db()->getPdo();
    $pdo->beginTransaction();
    try {
        tiny::db()->execute("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $fromId]);
        tiny::db()->execute("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$amount, $toId]);
        $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        tiny::log($e->getMessage());
        return false;
    }
}
```

## Best practices

1. **One model per entity.** `UserModel`, `OrderModel`, `InvoiceModel` — not a god-object.
2. **Validation lives with the schema.** Store schemas on the model and call `isValid()` before any write.
3. **Cache reads, invalidate writes.** The cache layer is cheap; use it.
4. **Keep transactions narrow.** Begin → commit → return; don't span HTTP boundaries.
5. **Never return raw PDO statements from a model.** Always return arrays / objects / scalars.
