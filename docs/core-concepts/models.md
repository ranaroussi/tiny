[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Models

Models in Tiny handle data and business logic. They provide an abstraction layer for working with your application's data, whether it's from a database, API, or other source.

## Basic Structure

Models should be placed in the `app/models` directory:

```php
<?php
// app/models/user.php

class UserModel
{
    public function getAll(): array
    {
        return tiny::db()->getAll('users', '*', 'name ASC');
    }

    public function getOne(int $id): ?object
    {
        return tiny::db()->getOne('users', ['id' => $id]);
    }

    public function create(array $data): bool|int
    {
        return tiny::db()->insert('users', [
            'name' => $data['name'],
            'email' => $data['email'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return tiny::db()->update('users',
            $data,
            ['id' => $id]
        );
    }

    public function delete(int $id): bool
    {
        return tiny::db()->delete('users', ['id' => $id]);
    }
}
```

## Using Models

Load and use models in your controllers:

```php
<?php
// app/controllers/users.php

class Users extends TinyController
{
    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = tiny::model('user');
    }

    public function get($request, $response)
    {
        tiny::data()->users = $this->model->getAll();
        $response->render();
    }
}
```

## Database Operations

### Basic Queries
```php
// Select all records
$users = tiny::db()->getAll('users');

// Select with conditions
$activeUsers = tiny::db()->get('users',
    'status = "active"',
    '*',
    'created_at DESC',
    10
);

// Select one record
$user = tiny::db()->getOne('users', ['id' => $id]);
```

### Raw Queries
```php
// Execute raw query
$result = tiny::db()->execute(
    "UPDATE users SET status = ? WHERE id = ?",
    ['active', $id]
);

// Get results from raw query
$users = tiny::db()->getQuery(
    "SELECT * FROM users WHERE created_at > ?"
    [date('Y-m-d', strtotime('-30 days'))]
);
```

### Transactions
```php
public function transferFunds(int $fromId, int $toId, float $amount): bool
{
    try {
        tiny::db()->getPdo()->beginTransaction();

        // Deduct from source account
        tiny::db()->execute(
            "UPDATE accounts SET balance = balance - ? WHERE id = ?",
            [$amount, $fromId]
        );

        // Add to destination account
        tiny::db()->execute(
            "UPDATE accounts SET balance = balance + ? WHERE id = ?",
            [$amount, $toId]
        );

        tiny::db()->getPdo()->commit();
        return true;
    } catch (Exception $e) {
        tiny::db()->getPdo()->rollBack();
        tiny::log('Transfer failed', ['error' => $e->getMessage()]);
        return false;
    }
}
```

## Data Validation

Implement validation in your models:

```php
public function validate(array $data): array
{
    $errors = [];

    if (empty($data['name'])) {
        $errors['name'] = 'Name is required';
    }

    if (empty($data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($data['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($data['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    return $errors;
}
```

## Caching

Implement caching in your models:

```php
public function getUser(int $id): ?object
{
    $key = "user:$id";

    return tiny::cache()->remember($key, 3600, function() use ($id) {
        return tiny::db()->getOne('users', ['id' => $id]);
    });
}
```

## Best Practices

1. **Data Integrity**
   - Validate data before saving
   - Use transactions for related operations
   - Handle errors gracefully

2. **Performance**
   - Cache frequently accessed data
   - Optimize database queries
   - Use indexes appropriately

3. **Security**
   - Sanitize input data
   - Use prepared statements
   - Implement access control

4. **Organization**
   - One model per entity
   - Keep methods focused
   - Use meaningful names

5. **Maintainability**
   - Document complex queries
   - Use consistent patterns
   - Keep business logic in models
