# Extensions

Tiny PHP Framework comes with several built-in extensions that provide core functionality. These extensions are located in the `tiny/ext/` directory and are automatically loaded when needed.

## Main Extensions

1. [Cache](cache.md)
   - Memory caching with APCu or Memcached
   - Key-value storage
   - Cache invalidation
   - Remember/recall pattern

2. [CSRF Protection](csrf.md)
   - Token generation and validation
   - Form protection
   - AJAX request security
   - Token management

3. [Database](database.md)
   - PDO abstraction
   - Query building
   - Raw SQL support
   - Transaction handling
   - Migration system

4. [HTTP Client](http.md)
   - HTTP requests
   - Response handling
   - Header management
   - File uploads
   - SSL/TLS support

5. [Layouts](layout.md) and [Components](components.md)
   - Template management
   - Layout inheritance
   - Section rendering
   - Component integration

6. [Migration](migration.md)
   - Database schema versioning
   - Up/down migrations
   - Migration tracking
   - Batch processing

7. [Scheduler](scheduler.md)
   - Task scheduling
   - Cron-like syntax
   - Job management
   - Error handling

8. [SSE (Server-Sent Events)](sse.md)
   - Real-time updates
   - Event streaming
   - Client connection management
   - Message broadcasting

9. [Debugger](debugger.md)
   - Variable inspection
   - Stack traces
   - Error handling
   - Log management

10. [Flash Messages](flash.md)
    - Session-based messages
    - Multiple message types
    - Message persistence
    - Toast notifications

11. [Cookie](cookie.md)
    - Cookie management
    - Secure cookie handling
    - Encryption support
    - Domain/path control

## Using Extensions

Extensions are accessed through the `tiny` class:

```php
// Cache example
tiny::cache()->set('key', 'value', 3600);
$value = tiny::cache()->get('key');

// Database example
$users = tiny::db()->get('users', ['active' => true]);

// CSRF example
tiny::csrf()->generate();
$token = tiny::csrf()->getToken();

// HTTP client example
$response = tiny::http()->get('https://api.example.com/data');
```

## Creating Custom Extensions

You can create your own extensions by adding them to the `tiny/ext/` directory:

1. Create a new file: `tiny/ext/myextension.php`
2. Define your extension class:

```php
<?php

class TinyMyExtension
{
    public function someMethod()
    {
        // Your extension code
    }
}
```

3. Use your extension:

```php
tiny::myextension()->someMethod();
```
