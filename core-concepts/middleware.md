[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Middleware

Middleware runs before controller dispatch. Typical uses are authentication, CORS, rate limiting, logging, and feature/version pinning.

## The contract

A middleware is a class with a single `handle(): void` method:

```php
<?php
// app/middleware/auth.php

class AuthMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            tiny::redirect('/login');
        }
    }
}
```

Two conventions are required:

1. **Filename:** `app/middleware/<name>.php`
2. **Class name:** `<Name>Middleware` (kebab-case in the filename becomes PascalCase for the class — `rate-limit.php` → `RateLimitMiddleware`).

The framework instantiates the class and calls `handle()`. There is no return value; if you need to halt execution, redirect or `tiny::exit()`.

## Registering middleware

Edit `app/middleware.php` and list the middleware you want active, in order:

```php
<?php
// app/middleware.php

tiny::middleware('auth');
tiny::middleware('rate-limit');
tiny::middleware('cors');
tiny::middleware('logger');
```

Each `tiny::middleware('foo')` call:
- Looks for `app/middleware/foo.php`.
- Skips silently if the file is missing.
- Queues it for execution.

After the framework finishes loading middleware files, it calls `handle()` on each one in registration order. **Earlier middleware can short-circuit later middleware** by redirecting or exiting.

> **Important:** middleware is not executed for CLI scripts (e.g. the scheduler). It only runs for web requests.

## Conditional registration

`tiny::middleware()` is just a function call — you can register middleware conditionally based on the request:

```php
<?php
// app/middleware.php

if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin')) {
    tiny::middleware('admin-only');
}

if (($_SERVER['ENV'] ?? 'prod') !== 'local') {
    tiny::middleware('https-only');
}

tiny::middleware('auth');
tiny::middleware('csrf');
```

## Common middleware patterns

### Authentication

```php
<?php
// app/middleware/auth.php

class AuthMiddleware
{
    public function handle(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            if (tiny::router()->htmx) {
                tiny::header('HX-Redirect: /login');
                tiny::exit();
            }
            tiny::redirect('/login');
        }

        $user = tiny::db()->getOne('users', ['id' => $userId]);
        if ($user) {
            tiny::user($user);
        }
    }
}
```

### CORS

```php
<?php
// app/middleware/cors.php

class CorsMiddleware
{
    public function handle(): void
    {
        tiny::header('Access-Control-Allow-Origin: *');
        tiny::header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        tiny::header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            tiny::exit();
        }
    }
}
```

### Rate limiting

```php
<?php
// app/middleware/rate-limit.php

class RateLimitMiddleware
{
    public function handle(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl = tiny::rateLimiter('api', 100, 60); // 100 requests / 60 seconds

        if (!$rl->check($ip)) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            tiny::exit();
        }
    }
}
```

### Version pinning

```php
<?php
// app/middleware/version.php

class VersionMiddleware
{
    public function handle(): void
    {
        tiny::data()->app_version = $_SERVER['APP_VERSION'] ?? 'dev';
    }
}
```

## Best practices

1. **One responsibility per middleware.** Don't bundle auth + logging + CORS into a single file.
2. **Order matters.** Auth → rate-limit → CORS → logging is a sensible default.
3. **Be fast.** Middleware runs on every request — avoid heavy I/O without caching.
4. **Be HTMX-aware.** Auth redirects should emit `HX-Redirect` for HTMX requests instead of plain 302 redirects.
5. **Skip CLI.** Middleware doesn't run under CLI dispatch, so don't put scheduler-critical logic there.
