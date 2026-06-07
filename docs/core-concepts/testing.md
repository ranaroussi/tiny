[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Testing

Tiny ships with a built-in, zero-ceremony testing harness. No PHPUnit XML, no bootstrap scripts, no mock libraries — just PHP files you run from the command line.

## Philosophy

Tiny's testing approach mirrors the framework itself: **let PHP be PHP**. There is no test runner to configure, no container to bootstrap, and no framework-specific assertion library to learn. You write plain PHP scripts that instantiate controllers, call methods, and inspect the response object. This means:

- **Zero startup cost** — a test file is just a PHP script
- **No dependencies** — you do not need PHPUnit, Pest, or any other test framework
- **Full framework state** — tests run inside the real framework, not a stripped-down simulation

## Three test-friendly primitives

### 1. `tiny::swap()` — inject mock singletons

Replace the framework's internal singletons with your own objects. Only works when `ENV=test`.

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

class FakeDB extends DB
{
    public array $queries = [];

    public function get(string $table, ?string $where = null, string $columns = '*', ?string $order = null, ?int $limit = null): array
    {
        $this->queries[] = "SELECT $columns FROM $table";
        return [['id' => 1, 'name' => 'Test User']];
    }
}

$fake = new FakeDB();
tiny::swap('db', $fake);
```

Supported names: `db`, `cache`, `clickhouse`.

### 2. `tiny::test()` — load a controller

Returns a controller instance without needing an HTTP request. Only works when `ENV=test`.

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

$ctrl = tiny::test('users');

// Now call any public method directly
$ctrl->get(tiny::request(), tiny::response());
```

When `tiny::test()` is called:

- `tiny::request()` returns a fresh `TinyRequest` instance
- `tiny::response()` returns a `TinyTestResponse` instance that captures instead of terminating

### 3. `TinyTestResponse` — capture instead of terminate

A drop-in replacement for `TinyResponse` used during tests. It records what the controller tried to do without actually sending headers or killing the script.

**Captured properties:**

| Property | Type | Description |
|---|---|---|
| `$response->redirectUrl` | `?string` | URL passed to `redirect()` |
| `$response->renderedView` | `?string` | View path passed to `render()` |
| `$response->renderParams` | `array` | Params passed to `render()` |
| `$response->output` | `?string` | Output captured from `send()`, `sendJSON()`, `sendFile()`, or `flush()` |
| `$response->status` | `int` | HTTP status code |
| `$response->contentType` | `?string` | Content-Type header value |

All response methods that normally terminate (`render`, `redirect`, `send`, `sendJSON`, `sendFile`, `hasCSRFError`) throw `TinyTestExit` when called on a `TinyTestResponse`. Catch it to inspect the captured state.

## Auto `:memory:` SQLite

When `ENV=test`, `DB_TYPE=sqlite`, and `DB_SQLITE_FILE` is empty, Tiny automatically connects to an in-memory SQLite database (`:memory:`). This gives you a fresh, isolated database for every test run without any configuration.

```env
# .env.test
ENV=test
DB_TYPE=sqlite
```

No `DB_SQLITE_FILE` needed — Tiny handles it.

## Complete example: testing a POST endpoint

`tests/users/create.php`:

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

// --- Setup fresh database ---
tiny::db()->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE
)");

// --- Simulate a POST request ---
$_POST = ['name' => 'Ran', 'email' => 'ran@example.com'];
$_SERVER['REQUEST_METHOD'] = 'POST';

// --- Load controller ---
$ctrl = tiny::test('users');
$response = tiny::response(); // TinyTestResponse

try {
    $ctrl->post(tiny::request(), $response);
} catch (TinyTestExit $e) {
    // Expected — redirects and renders throw in test mode
}

// --- Assert ---
assert($response->redirectUrl === '/users', 'Should redirect to /users');

$user = tiny::db()->getOne('users', "email = 'ran@example.com'");
assert($user['name'] === 'Ran', 'User should be inserted');

echo "PASS: users/create\n";
```

Run it:

```bash
php tests/users/create.php
```

If all assertions pass, you see `PASS: users/create`. If an assertion fails, PHP stops and tells you which line failed.

## Testing with a mock database

For unit-style isolation, swap the real DB with a fake:

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

class FakeUserDB extends DB
{
    public array $inserted = [];
    public array $users = [];

    public function insert(string $table, array $data): mixed
    {
        $this->inserted[] = compact('table', 'data');
        return 1;
    }

    public function get(string $table, ?string $where = null, ...): array
    {
        return $this->users;
    }
}

$fake = new FakeUserDB();
tiny::swap('db', $fake);

$ctrl = tiny::test('users');
$response = tiny::response();

$_POST = ['name' => 'Ran'];
$_SERVER['REQUEST_METHOD'] = 'POST';

try {
    $ctrl->post(tiny::request(), $response);
} catch (TinyTestExit) {}

assert($response->redirectUrl === '/users');
assert($fake->inserted[0]['table'] === 'users');
assert($fake->inserted[0]['data']['name'] === 'Ran');

echo "PASS\n";
```

## Testing GET endpoints that render views

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

$ctrl = tiny::test('users');
$response = tiny::response();

try {
    $ctrl->get(tiny::request(), $response);
} catch (TinyTestExit) {}

assert($response->renderedView === 'users/index');
assert(is_array($response->renderParams['users']));

echo "PASS\n";
```

## Testing JSON APIs

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

$ctrl = tiny::test('api/users');
$response = tiny::response();

try {
    $ctrl->get(tiny::request(), $response);
} catch (TinyTestExit) {}

assert($response->status === 200);
assert($response->contentType === 'application/json');

$json = json_decode($response->output, true);
assert(is_array($json['users']));

echo "PASS\n";
```

## TestResult helper

Tiny also provides a `TestResult` convenience class with assertion helpers. You can use it to wrap a `TinyTestResponse` for cleaner assertions:

```php
$result = new TestResult();
$result->redirect = $response->redirectUrl;
$result->status = $response->status;

assert($result->isRedirect());
assert($result->ok());
```

## Best practices

1. **One test file per controller action** — keeps tests focused and easy to run in isolation.
2. **Use `:memory:` SQLite for integration tests** — zero configuration, full database coverage.
3. **Use `tiny::swap('db', ...)` for unit tests** — faster, no database needed.
4. **Always catch `TinyTestExit`** — it is thrown by every terminating response method in test mode.
5. **Set `$_SERVER['ENV'] = 'test'` before requiring `tiny.php`** — the framework locks the environment on first load.
6. **Clean `$_POST` / `$_GET` between tests** — reset request globals at the top of each test file.
7. **Run tests individually or with a shell loop** — there is no test runner; use `find tests/ -name '*.php' -exec php {} \;` or a simple bash script.
