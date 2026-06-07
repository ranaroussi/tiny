[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Extensions

Extensions are first-party modules that live in `tiny/ext/`. They are loaded on demand via the `tiny::*` accessor pattern — you don't need to register them.

## Available extensions

| Extension | Accessor | Purpose |
|---|---|---|
| [Cache](cache.md) | `tiny::cache()` | APCu / Memcached with `remember()` and prefix operations |
| [ClickHouse](clickhouse.md) | `tiny::clickhouse()` | ClickHouse client for analytics workloads |
| [CMS](cms.md) | `tiny::cms()` | File-based markdown CMS with caching |
| [Components](components.md) | `Component::*` | Reusable view fragments |
| [Cookie](cookie.md) | `tiny::cookie()` | Signed cookie management |
| [CSRF](csrf.md) | `tiny::csrf()` | CSRF tokens for forms |
| [Database](database.md) | `tiny::db()` | MySQL / PostgreSQL / SQLite |
| [Debugger](debugger.md) | `tiny::dd/dump/log/debug()` | IP-gated debugging output |
| [Flash](flash.md) | `tiny::flash('name')` | One-shot session messages |
| [HTTP](http.md) | `tiny::http()` | HTTP client for outbound requests |
| [Layout](layout.md) | `Layout::*` | Layout system for views |
| [Migrations](migrations.md) | `php tiny/cli migrations` | Versioned schema management |
| [Scheduler](scheduler.md) | `tiny::scheduler()` | Built-in fluent cron-style scheduler |
| [SSE](sse.md) | `tiny::sse()` | Server-Sent Events streaming |
| [Swoole](swoole.md) | `tiny::swoole()` | Coroutine runtime |

## Calling pattern

Most extensions are exposed as static accessors on the `tiny` class. They're lazily instantiated and reused across the request:

```php
tiny::cache()->set('key', 'value', 3600);
$response = tiny::http()->get('https://api.example.com/data');
```

`Component` and `Layout` are global constants pointing at singleton instances, used directly in views:

```php
Component::render('userCard', $user);
Layout::main(['title' => 'Home']);
```

## Configuration

Extensions read their settings from environment variables (via `.env.<env>` or directly from the server environment). New extensions use the `TINY_*` prefix; some legacy ones don't (e.g. `COOKIE_TTL`, `DEBUG_WHITELIST`, `LOG_FILE`). The relevant variables are listed in each extension's page.

See [Configuration](../getting-started/configuration.md) for the complete reference.

## Writing a custom extension

A custom extension is a class prefixed with `Tiny`, dropped into `tiny/ext/`:

```php
<?php
// tiny/ext/myservice.php
declare(strict_types=1);

class TinyMyservice
{
    public function __construct()
    {
        // initialise from $_SERVER['TINY_MYSERVICE_…']
    }

    public function doSomething(): string
    {
        return 'ok';
    }
}
```

Use it via `tiny::myservice()`:

```php
tiny::myservice()->doSomething();
```

The framework looks up `Tiny<UcfirstName>` for any unknown static call to `tiny::<name>()`.

### Or: register at runtime

For project-specific extensions you don't want in the framework folder, register a factory closure on boot (e.g. from `app/common.php`):

```php
tiny::registerHelper('analytics', function () {
    return new \App\Services\Analytics($_SERVER['TINY_ANALYTICS_TOKEN']);
});
```

Subsequent calls to `tiny::analytics()` return the singleton built by the closure.
