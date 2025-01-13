[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Extensions

Tiny Framework comes with several built-in extensions that provide additional functionality to your application. These extensions (located in `tiny/ext`) are designed to be lightweight and easy to use.

## Available Extensions

- [Cache](cache.md) - APCu/Memcached integration for caching
- [Components](components.md) - Component-based view system
- [Cookie](cookie.md) - Cookie management
- [CSRF](csrf.md) - Cross-Site Request Forgery protection
- [Debugger](debugger.md) - Debugging tools and error handling
- [Flash](flash.md) - Flash messages for user notifications
- [HTTP](http.md) - HTTP client for making API requests
- [Layout](layout.md) - Layout system for views
- [Migrations](migrations.md) - Database migration system

## Using Extensions

Extensions are automatically loaded when needed. You can access them through the global `tiny::**extention()**` method:

```php
// Using cache extension
tiny::cache()->set('key', 'value', 3600);

// Using HTTP client
$response = tiny::http()->get('https://api.example.com/data');
```

## Extension Configuration

Most extensions can be configured through the `.env` file or environment variables. For example:

```env
# Cache configuration
CACHE_ENGINE=apcu,memcached
MEMCACHED_HOST=localhost
MEMCACHED_PORT=11211

# Cookie configuration
COOKIE_DOMAIN=localhost
COOKIE_PATH=/
COOKIE_TTL=86400
COOKIE_SECURE=true
COOKIE_HTTPONLY=true
COOKIE_SAMESITE=Lax
```

## Creating Custom Extensions

You can create your own extensions by adding them to the `tiny/ext` directory. Each extension should be a class that follows the Tiny naming convention:

```php
<?php

declare(strict_types=1);

class TinyCustom
{
    public function __construct()
    {
        // Extension initialization
    }

    public function doSomething()
    {
        // Extension functionality
    }
}
```

Then use it in your application:

```php
tiny::custom()->doSomething();
```
