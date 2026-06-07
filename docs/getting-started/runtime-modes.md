[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Runtime modes

Tiny runs unchanged in four modes. Pick the one that matches your hosting:

| Mode | When to use | Cold start | Concurrency | Setup |
|---|---|---|---|---|
| **PHP-FPM** (default) | Standard hosting, simple deploys | Per-request | OS process per worker | Webserver → `html/index.php` |
| **Swoole coroutines** | High concurrency, long-lived sockets | Once | Thousands of coroutines | `php swoole.php` |
| **FrankenPHP worker** | Modern app servers, persistent in-memory app | Once | Workers | FrankenPHP `--worker worker.php` |
| **OPcache preload** | Squeeze cold-start cost on FPM | Once at PHP boot | Same as FPM | `opcache.preload=preload.php` |

The application code (`app/`) never changes. Only the entry point changes.

## 1. PHP-FPM (default)

The simplest deployment. Your web server (nginx, Apache, Caddy) routes requests through `html/index.php`, which boots `tiny::init()` and dispatches to the controller.

```caddyfile
# Caddyfile
example.com {
    root * /srv/my-app/html
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

```nginx
# nginx
server {
    listen 80;
    server_name example.com;
    root /srv/my-app/html;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Nothing else to configure — every request re-bootstraps the framework. Add OPcache preloading (below) to keep cold-start under control.

## 2. Swoole coroutines

For real-time apps, WebSockets, or high-concurrency workloads, run on [Swoole](https://www.swoole.com).

### Requirements

```bash
pecl install swoole
```

Or build PHP with `--enable-swoole`.

### Run

The repo ships with a top-level `swoole.php`:

```bash
USE_SWOOLE=1 php swoole.php
```

This starts a Swoole HTTP server that boots Tiny once and serves requests inside coroutines.

### What changes in your code

Almost nothing — Tiny detects Swoole and adjusts the lifecycle automatically:

- `tiny::die()` / `tiny::exit()` throw an `ExitException` instead of terminating the worker.
- `tiny::redirect()` uses Swoole's response API.
- Database connections are per-coroutine.
- `tiny::isUsingSwoole()` returns `true`.

Avoid:
- `die()` / `exit()` directly — use `tiny::die()` / `tiny::exit()` to keep the worker alive.
- Global state mutations that leak between requests.

See [Swoole extension](../extensions/swoole.md) for details.

## 3. FrankenPHP worker mode

[FrankenPHP](https://frankenphp.dev) keeps PHP in memory and routes each request through a stateless callable. The repo ships with `worker.php` ready to go.

```bash
frankenphp run --worker worker.php
```

`worker.php` boots the app once and exposes a callable that handles each request. State is reset between requests automatically — sessions are closed, output buffers cleared, superglobals zeroed.

### What changes

Same as Swoole: avoid raw `die`/`exit`, avoid global mutations. Long-lived DB / Redis handles should be reset between requests; the bundled `ResetRegistry` in `worker.php` is the hook for that.

## 4. OPcache preloading

If you stay on PHP-FPM but want to skip cold-class loading, point OPcache at `preload.php`:

```ini
; php.ini
opcache.preload=/srv/my-app/preload.php
opcache.preload_user=www-data
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

The bundled `preload.php` compiles `tiny/tiny.php` and every file in `tiny/ext/`. Composer's classmap is also compiled when available.

Restart PHP-FPM for changes to take effect.

## Picking a mode

- **Just shipping a site?** PHP-FPM + OPcache preload. Done.
- **Long-running streams, websockets, or thousands of concurrent users?** Swoole.
- **Modern container deploys with a single static binary?** FrankenPHP.
- **Hybrid (HTTP requests via FPM, background work via Swoole)?** Run them side-by-side — Tiny is the same code in both.
