# Tiny Framework — Agent Context

Tiny is a zero-config, batteries-included PHP 8.3+ framework. **It lets PHP be PHP** — no compilation step, no container to restart, no route cache to warm. The filesystem is the router; add or remove a file and the route appears or disappears instantly. Raw SQL over a thin wrapper. HTMX-native. Runs unchanged on PHP-FPM, Swoole, or FrankenPHP.

## Project Layout

```
tiny/
  tiny.php          # Main framework class (singleton facade)
  bootstrap.php     # Composer autoload, env, dotenv, session, output minifier
  ext/              # Core classes: db, request, response, controller, model, cache, csrf, scheduler, etc.
  helpers/          # 30+ integrations: Stripe, Mailgun, Twilio, Spaces/S3, OAuth, etc.
  cli               # CLI entry point for scaffolding and migrations
  docs/             # Full documentation
  sample-project.zip# Unpacked by `php tiny/cli create`
```

App layout (after `cli create`):

```
app/
  controllers/      # one PHP file per route
  models/           # TinyModel subclasses
  views/
    components/     # reusable UI snippets
    layouts/        # page chrome
  middleware/
  middleware.php    # registers active middleware
  common.php        # optional autoloaded helpers
html/               # Document root (index.php)
migrations/         # SQLite-tracked migration files
```

## Core Conventions

- **`declare(strict_types=1);`** on every PHP file.
- **No Composer-managed framework deps** beyond `vlucas/phpdotenv` and `dragonmantank/cron-expression`.
- **Environment variables** use the `TINY_*` prefix for framework-level settings.
- **Routing:** `app/controllers/users.php` → `/users`. Subdirectories map to URL segments. Hyphenated files act as section/slug fallbacks.
- **Database:** Raw SQL via `tiny::db()->get(...)`, `getOne(...)`, `insert(...)`, `update(...)`, `upsert(...)`, `delete(...)`. No ORM.
- **Views:** Plain PHP templates rendered by `$response->render('path/to/view', $params)`.
- **Components:** Register PHP callables once, invoke like functions via `Component::render('name', $args)`.
- **Layouts:** Wrap page chrome via `Layout::main(['title' => '...'])`.
- **HTMX:** `$request->htmx` flag, `$response->render()` auto-emits `HX-Push-Url`, `tiny::redirect($url, 'htmx')` for `HX-Redirect`.
- **React (hybrid SSR+SPA):** `$response->renderReact('ComponentName', $props)` — returns full HTML on first load, JSON on `X-SPA-Request`.
- **Scheduler:** Fluent cron-style API in `app/scheduler.php`, second-level granularity via `scheduler.sh`.
- **Middleware:** Classes in `app/middleware/<name>.php` with a `handle()` method; registered in `app/middleware.php` via `tiny::middleware('name')`.
- **Migrations:** CLI-driven, tracked in a local SQLite database.
- **Runtime modes:** PHP-FPM (default), Swoole (`swoole.php`), FrankenPHP (`worker.php`), OPcache preload (`preload.php`).

## Key Facade Methods

- `tiny::init()` — bootstrap
- `tiny::db()` — DB instance
- `tiny::cache()` — cache instance
- `tiny::request()` / `tiny::response()` — HTTP objects
- `tiny::router()` — route info (controller, section, slug, htmx)
- `tiny::model('name')` — load model
- `tiny::user($data)` — get/set current user
- `tiny::set()` / `tiny::get()` / `tiny::data()` — global data bag
- `tiny::helpers()` / `tiny::registerHelper()` — load or register helpers

## Testing

Tiny has a built-in, zero-ceremony testing harness — no PHPUnit, no bootstrap scripts, no mock libraries. Test files are plain PHP scripts run from the command line.

- **`tiny::swap('db', $fake)`** — inject a mock/stub singleton in test env (`db`, `cache`, `clickhouse`).
- **`tiny::test('users')`** — load a controller in test env and return its instance.
- **`TinyTestResponse`** — capture-only response returned by `tiny::response()` in test mode; records `redirectUrl`, `renderedView`, `renderParams`, `output`, `status`, `contentType`.
- **`TinyTestExit`** — exception thrown by terminating response methods (`redirect`, `render`, `send`, etc.) in test mode. Always catch it in tests.
- **Auto `:memory:` SQLite** — when `ENV=test` + `DB_TYPE=sqlite` with no file specified, Tiny auto-connects to `:memory:`.

**Test pattern:**

```php
$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

// Optionally swap the DB
tiny::swap('db', new FakeDB());

// Setup request globals
$_POST = ['name' => 'Ran'];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Load controller and call method
$ctrl = tiny::test('users');
$response = tiny::response();

try {
    $ctrl->post(tiny::request(), $response);
} catch (TinyTestExit) {}

assert($response->redirectUrl === '/users');
echo "PASS\n";
```

## When Modifying Code

1. Match existing code style (PHP 8.3 features, `declare(strict_types=1)`).
2. Do not introduce new Composer dependencies without a strong reason.
3. Prefer raw SQL over an ORM.
4. Use the built-in component/layout system for reusable UI.
5. Keep controllers thin; move logic to models or helpers.
6. Validate CSRF on every mutating HTTP verb.
7. Use middleware for cross-cutting concerns (auth, rate limiting), not controller boilerplate.
