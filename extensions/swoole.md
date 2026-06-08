[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Swoole

[Swoole](https://www.swoole.com) is a C-extension HTTP server with coroutine support. Running Tiny under Swoole means the framework boots **once** and handles thousands of concurrent requests inside lightweight coroutines.

## Requirements

- Swoole PHP extension (`pecl install swoole`)
- PHP CLI (Swoole doesn't run under FPM)

## Starting the server

The repo ships with a top-level `swoole.php`:

```bash
USE_SWOOLE=1 php swoole.php
```

By default Swoole listens on port 9501. Set `SWOOLE_PORT` and `SWOOLE_HOST` to override.

## How it works

1. `swoole.php` calls `tiny::swoole()->start()`.
2. Each incoming HTTP request becomes a coroutine.
3. Tiny's router and dispatcher run inside the coroutine.
4. Database / cache calls cooperatively yield while waiting for I/O, so a single worker can handle many requests in parallel.

## Code differences

The application code is identical to PHP-FPM, but a few things behave differently:

### Don't call `die()` or `exit()`

Use `tiny::die()` and `tiny::exit()` instead. They throw an `ExitException` that the Swoole worker catches, ending the current coroutine cleanly without killing the whole worker:

```php
// ❌
exit;

// ✅
tiny::exit();
tiny::die('fatal error');
```

### Use `tiny::redirect()` (not raw headers)

Under Swoole, you can't `header('Location: ...')`. `tiny::redirect()` and `$response->redirect()` route through Swoole's response API automatically.

### `isAsync()` returns `true`

`$request->isAsync()` reports `true` under Swoole — useful when your views need to behave differently for streamed contexts.

### Detect at runtime

```php
if (tiny::isUsingSwoole()) {
    // Swoole-specific tweaks
}
```

### Coroutine-aware DB connections

`TinyDB` detects Swoole and uses coroutine-safe PDO connections. No code change required.

### Avoid global mutable state

Globals leak between coroutines. Don't store request-specific state on static class properties or `$_SERVER`. Use `tiny::data()`, which is per-request.

## API

`tiny::swoole()` returns a `TinySwoole` singleton:

```php
tiny::swoole()->start();                       // boot the server
tiny::swoole()->co(fn () => doSomething());    // wrap a callable in a coroutine
tiny::swoole()->header($name, $value);         // set a response header
tiny::swoole()->redirect($url, $code);         // redirect within Swoole
```

You'll rarely need these directly — the framework calls them for you.

## Running under a process supervisor

In production, run `swoole.php` under systemd, supervisord, or a container restart policy:

```ini
# /etc/systemd/system/myapp.service
[Unit]
Description=My App (Swoole)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/srv/my-app
Environment=USE_SWOOLE=1
ExecStart=/usr/bin/php /srv/my-app/swoole.php
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
```

Then put a reverse proxy (nginx, Caddy) in front to terminate TLS.

## When to choose Swoole

Swoole is worth it when you have:

- Long-lived requests (SSE, websockets, server push)
- High concurrency with I/O-heavy workloads (lots of DB / HTTP calls per request)
- Tight latency budgets (Swoole skips PHP-FPM's per-request bootstrap)

It's overkill for:

- Mostly-static sites
- Low-traffic apps where simpler hosting wins
- Teams that don't want to manage a long-running process

For those, stay on PHP-FPM (optionally with [OPcache preloading](../getting-started/runtime-modes.md#4-opcache-preloading)).

## See also

- [Runtime modes overview](../getting-started/runtime-modes.md)
- [SSE](sse.md) — Server-Sent Events pair well with Swoole's coroutine model
