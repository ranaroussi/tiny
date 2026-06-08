# Tiny — a small, opinionated PHP framework

[![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](#license)
[![Version](https://img.shields.io/badge/version-2.8.x-brightgreen)](.version)

Tiny is a zero-config, batteries-included PHP framework. Your filesystem **is** the router. Your SQL is just SQL. You stay in PHP. It runs unchanged on PHP-FPM, Swoole, or FrankenPHP, and ships with HTMX awareness baked into the request/response pipeline.

It was created by [Ran Aroussi](https://x.com/aroussi) to power production sites and internal tools.

> **Status:** v2.8.x — feature-complete, in active production use. Public API is stable; semver applies after 3.0.

---

## Repository branches

Tiny is organized into focused branches. Pick the entry point that matches what you need:

| Branch | What it is | Use it when… |
|---|---|---|
| [`main`](https://github.com/ranaroussi/tiny/tree/main) | Meta & overview | You're reading this — the landing page |
| [`tiny`](https://github.com/ranaroussi/tiny/tree/tiny) | Framework code only | You want the framework as a [submodule](https://git-scm.com/book/en/v2/Git-Tools-Submodules) in your project |
| [`boilerplate`](https://github.com/ranaroussi/tiny/tree/boilerplate) | Full app shell | You want Docker, Tailwind, build pipeline, and a working scaffold |
| [`docs`](https://github.com/ranaroussi/tiny/tree/docs) | Documentation | You're browsing docs or building a docs site |

---

## Why Tiny?

- **Zero ceremony.** Drop a file in `app/controllers/`. That is the route.
- **Let PHP be PHP.** No compilation step, no route cache to warm, no container to restart. Add or remove a file and the change is live instantly. This is what makes Tiny extremely fast and lightweight — it doesn't fight the language, it gets out of the way.
- **No magic ORM.** Raw SQL with a thin, safe wrapper (`get`, `getOne`, `insert`, `update`, `upsert`, `delete`) over MySQL, PostgreSQL, SQLite, and ClickHouse.
- **Pick your front-end.** Server-rendered PHP views with a built-in **component + layout system**, **HTMX-native** request/response (auto `HX-Push-Url`, HTMX-aware redirects), and a first-class **React renderer** (`tiny::renderReact()`) for hybrid SPA / server-driven apps.
- **A real scheduler, no extra binaries.** Built-in fluent cron-style scheduler with **second-level granularity**, plus a local job-testing harness so you can debug jobs without touching cron.
- **File-based CMS included.** Drop markdown into `app/cms/` and `tiny::cms()` gives you pages, posts, tags, and a sitemap-ready API — with GFM extensions for callouts, tabs, cards, columns. (Powers [aroussi.com](https://aroussi.com).)
- **Runtime agnostic.** The same code runs on classic PHP-FPM, Swoole coroutines, or FrankenPHP workers. OPcache preloading included.
- **Batteries you actually use.** Cache, CSRF, SSE, cookies, migrations, and 30+ integration helpers (Stripe, Mailgun, Twilio, Spaces/S3, OAuth, ClickHouse, Sentry-ready…).

---

## Feature highlights

**Core**
- Filesystem-based routing (`controller/section/slug` with sensible fallbacks)
- MVC primitives (`TinyController`, `TinyModel`)
- Middleware pipeline
- Environment-based configuration via `.env.<env>`
- CSRF protection, flash messages, cookies, sessions
- Server-Sent Events (SSE) helper

**Views & UI**
- **Component system** (`Component::render()`) for reusable view fragments — register PHP callables once, call them like functions from any view
- **Layout system** (`Layout::main()`, `Layout::auth()`, …) for page chrome and inheritance
- **HTMX-native** request/response — auto `HX-Push-Url`, HTMX-aware redirects via `tiny::redirect($url, 'htmx')`, `request->htmx` flag
- **React renderer** (`tiny::renderReact()` / `$response->renderReact()`) — hybrid mode that returns full HTML on first load and JSON on `X-SPA-Request` / XHR, giving you SSR + SPA from a single controller
- Static URL & home URL helpers, HTML minifier

**Scheduler & background work**
- **Built-in fluent scheduler** — no external cron runner needed beyond a single `* * * * *` entry
- Cron-style API: `->everyMinute()`, `->everyMinute(5)`, `->hourly(15)`, `->daily('22:03')`, `->monday('17:00')`, `->january(1, '00:00')`, `->at('*/5 * * * *')`
- **Second-level granularity** via the bundled `scheduler.sh` wrapper (`->everySecond()`, `->everySecond(5)`)
- Local job-testing harness (`tiny::initTestScheduler()`) for debugging jobs from a browser

**Data**
- Raw-SQL DB layer for MySQL, PostgreSQL, SQLite
- ClickHouse extension (`tiny::clickhouse()`)
- APCu or Memcached cache, with `remember()` / `getByPrefix()` helpers
- Migration system (CLI-driven, SQLite-tracked)

**Content**
- **File-based CMS** (`tiny::cms()`) — drop markdown files into `app/cms/`, get pages/posts with metadata, tag indexing, sitemap support, and caching out of the box
- GFM markdown extensions: callouts, tabs, cards, columns, toggles, steps
- OpenGraph + OG-image generation, document reader (PDF/DOCX), HTML helpers

**Runtime modes**
- Classic PHP-FPM (default)
- Swoole coroutines (`swoole.php`)
- FrankenPHP worker mode (`worker.php`)
- OPcache preloading (`preload.php`)

**Tooling**
- Built-in debugger (`tiny::dd()`, `tiny::dump()`, `tiny::log()`) gated by IP whitelist
- CLI for project scaffolding and migrations (`php tiny/cli …`)

**Integrations (helpers)**
- Payments & storage: Stripe, S3-compatible Spaces, invoice PDFs
- Email: Mailgun, Sendgrid, generic SMTP, email validator (with disposable-domain list)
- Marketing/CRM: HubSpot, Customer.io, Encharge, GetResponse, Mixpanel, Userflow, Logsnag
- Messaging: Twilio, Vonage
- Auth: OAuth (Google, GitHub, …), avatar generator
- Geo: GeoIP2 lookup, country/currency rules
- Utilities: Markdown renderer, OG-image generation, UUID, document reader, rate limiter, shell, crypto (`cypher`)

---

## Requirements

- **PHP 8.3** or newer
- **Composer**
- **PHP extensions:** `pdo`, `openssl`, `mbstring`, `curl`, `json`, `fileinfo`, `zip`, plus one of `apcu` or `memcached`
- **DB drivers** (only what you use): `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`
- Optional: `swoole` (for coroutines), `imagick` (for OG images)

---

## Quickstart

### New project (recommended)

```bash
# 1. Fetch the framework
mkdir my-app && cd my-app
git clone https://github.com/ranaroussi/tiny.git

# 2. Scaffold from the boilerplate branch
php tiny/cli create

# 3. Install dependencies
composer install
cd buildtools && npm install

# 4. Configure your environment
cp env.example .env.local

# 5. Start the dev stack
docker-compose up
```

Visit http://localhost:8080.

The boilerplate branch includes Docker, Tailwind + Webpack, app scaffold, tests, and migrations. See the [boilerplate README](https://github.com/ranaroussi/tiny/blob/boilerplate/README.md) for details.

### Existing project (submodule)

```bash
cd your-project
git submodule add -b tiny https://github.com/ranaroussi/tiny.git tiny
git submodule update --init
```

Point your web server at `html/`.

### Drop-in (quick experiments)

```bash
git clone -b tiny https://github.com/ranaroussi/tiny.git
```

---

## Configuration

Tiny is configured entirely through environment variables. The convention is the **`TINY_*` prefix** for framework- and integration-level settings (matches the `.env.example` files in `aroussi.com` and `muxi/website`):

```env
# Core
TINY_APP_DIR=app
TINY_HOMEPAGE=home
TINY_TIMEZONE=UTC
TINY_DEBUG_WHITELIST=*        # IPs allowed to see dd()/dump(); '*' = all
TINY_MINIFY_OUTPUT=false

# Database (use what you need)
TINY_DB_TYPE=postgres          # mysql | postgres | sqlite
TINY_DB_HOST=localhost
TINY_DB_PORT=5432
TINY_DB_NAME=myapp
TINY_DB_USER=postgres
TINY_DB_PASS=secret

# Cache
TINY_CACHE_DISABLED=false
TINY_CACHE_PREFIX=myapp
```

See [`docs/getting-started/configuration.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/configuration.md) for the full reference (DB, ClickHouse, Spaces/S3, Stripe, Mailgun, Twilio, Sentry, Cypher, Geo, etc.).

---

## MVC at a glance

**Controller** — `app/controllers/users.php` becomes `/users`:

```php
<?php
class Users extends TinyController
{
    public function get($request, $response)
    {
        $users = tiny::model('user')->all();
        $response->render('users/index', ['users' => $users]);
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }
        $data = $request->body(true);
        tiny::model('user')->create($data);
        $response->redirect('/users');
    }
}
```

**Model** — `app/models/user.php`:

```php
<?php
class UserModel extends TinyModel
{
    public function all(): array
    {
        return tiny::db()->get('users', null, '*', 'created_at DESC');
    }

    public function create(array $data): mixed
    {
        return tiny::db()->insert('users', $data);
    }
}
```

**View** — `app/views/users/index.php`:

```php
<?php Layout::default(['title' => 'Users']); ?>

<h1>Users</h1>
<ul>
<?php foreach (tiny::get('users') as $u): ?>
    <li><?= htmlspecialchars($u['name']) ?></li>
<?php endforeach; ?>
</ul>

<?php Component::render('footer'); ?>
```

---

## Views, components, and React

Tiny ships with three rendering strategies that you can mix and match per route.

### 1. Server-rendered PHP views

The default. Views in `app/views/` are plain PHP templates rendered by `$response->render('path/to/view', $params)`.

### 2. Components and layouts

Register **components** (reusable view fragments) once, then call them as if they were functions:

```php
// app/views/components/user-card.php
Component::register('userCard', function (array $user): void { ?>
    <article class="user-card">
        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
        <h3><?= htmlspecialchars($user['name']) ?></h3>
    </article>
<?php });
```

```php
// in any view
<?php foreach (tiny::get('users') as $user): ?>
    <?php Component::render('userCard', $user); ?>
<?php endforeach; ?>
```

**Layouts** wrap the page chrome:

```php
// app/views/layouts/main.php — invoked as Layout::main([...])
<!doctype html>
<html>
<head><title><?= htmlspecialchars(Layout::props('title', 'My App')) ?></title></head>
<body><?= Layout::props('content') ?></body>
</html>
```

See [`docs/extensions/components.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/components.md) and [`docs/extensions/layout.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/layout.md).

### 3. HTMX-native rendering

`$response->render()` automatically emits `HX-Push-Url` matching the current URL, so HTMX partial swaps keep the address bar in sync. Detect HTMX requests via `$request->htmx`, and use `tiny::redirect($url, 'htmx')` to emit `HX-Redirect` instead of a 302.

### 4. React (hybrid SSR + SPA)

For React frontends, `tiny::renderReact()` (or `$response->renderReact()`) gives you SSR-on-first-load and SPA-after-that from a single controller:

```php
class Dashboard extends TinyController
{
    public function get($request, $response)
    {
        $response->renderReact('DashboardPage', [
            'user'  => tiny::user(),
            'stats' => tiny::model('stats')->forUser(tiny::user()->id),
        ]);
    }
}
```

- On a normal request: returns the full HTML shell with props embedded — instant first paint.
- On an `X-SPA-Request: true` or `XMLHttpRequest` request: returns JSON `{component, props}` — your client-side router swaps the page without a reload.

See [`docs/core-concepts/htmx.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/htmx.md) and [`docs/core-concepts/request-response.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/request-response.md) for the full details.

---

## Routing rules

Routes are derived from the URL path against `app/controllers/`. For a URL `/users/profile/edit` the router tries, in order:

1. `users/profile/edit.php`
2. `users/profile/edit.php` (slashes in slug rewritten to hyphens)
3. `users/profile-edit.php`
4. `users/profile.php`
5. `users/index.php`
6. `users.php`
7. `404.php` (custom or built-in fallback)

Inside the controller you get parsed segments:

```php
$request->path->controller;  // "users"
$request->path->section;     // "profile"
$request->path->slug;        // "edit"
$request->path->full;        // "/users/profile/edit"
$request->htmx;              // bool, true when HX-Request header is set
```

---

## Middleware

Create middleware classes in `app/middleware/<name>.php`:

```php
<?php
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

…then register them in `app/middleware.php`:

```php
<?php
tiny::middleware('auth');
tiny::middleware('version');
```

Each registered middleware's `handle()` runs before controller dispatch.

---

## Runtime modes

Tiny is **the same code**, four ways to run it:

| Mode | Entry point | When to use |
|---|---|---|
| **PHP-FPM (default)** | `html/index.php` | Standard hosting, shared hosting, simple deploys |
| **Swoole coroutines** | `php swoole.php` | High concurrency, persistent connections, websockets |
| **FrankenPHP worker** | `worker.php` (via FrankenPHP `--worker`) | Modern app servers, app stays warm in memory |
| **OPcache preload** | `preload.php` (via `opcache.preload`) | Squeeze cold-start cost in FPM/CLI workers |

See [`docs/getting-started/runtime-modes.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/runtime-modes.md) for details.

---

## Built-in scheduler

Tiny ships with a fluent, cron-style scheduler — no extra binaries, no queue daemons, no external dependencies. Define jobs in PHP, wire up one cron entry, done. It even goes down to the **second**.

Define jobs in `app/scheduler.php`:

```php
<?php
require_once __DIR__ . '/../tiny/tiny.php';

// Class-based jobs (resolved from app/jobs/<Class>.php)
tiny::scheduler()->job('Reports/daily')->daily('02:00');
tiny::scheduler()->job('Cache/warm', ['users'])->everyMinute(5);
tiny::scheduler()->job('Heartbeat/ping')->everySecond(5);

// Free-form cron expressions
tiny::scheduler()->job('Cleanup/temp')->at('*/15 * * * *');

// Calendar shortcuts
tiny::scheduler()->job('Newsletter/weekly')->monday('09:00');
tiny::scheduler()->job('Reports/yearly')->january(1, '00:00');

tiny::scheduler()->run();
```

Add **one** cron entry — that's the whole install:

```cron
# Minute-level only
* * * * * /usr/bin/php /var/www/app/scheduler.php >/dev/null 2>&1

# Second-level (uses the bundled wrapper script)
* * * * * /var/www/tiny/scheduler.sh >/dev/null 2>&1
```

Need to debug a job without waiting for cron? Use the test harness:

```php
// app/controllers/test-scheduler.php (local environment only)
tiny::initTestScheduler();
(new Reports())->daily();
```

See [`docs/extensions/scheduler.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/scheduler.md) for the full method list (per-day, per-month, cron expressions, fixed dates).

---

## Testing

Tiny ships with a built-in, zero-ceremony testing harness. No PHPUnit XML, no bootstrap scripts, no mock libraries — just PHP files you run from the command line.

**Setup: create `.env.test`**

```env
ENV=test
DB_TYPE=sqlite
TINY_CACHE_DISABLED=true
```

When `ENV=test` + `DB_TYPE=sqlite` with no `DB_SQLITE_FILE`, Tiny auto-connects to `:memory:` — a fresh in-memory database for every test run. `TINY_CACHE_DISABLED=true` keeps tests deterministic by preventing cache pollution between runs.

**Test a controller** — `tests/users/create.php`:

```php
<?php
declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

// Seed a fresh :memory: database
tiny::db()->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

// Simulate POST request
$_POST = ['name' => 'Ran'];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Load controller and invoke
$ctrl = tiny::test('users');
$response = tiny::response(); // TinyTestResponse

try {
    $ctrl->post(tiny::request(), $response);
} catch (TinyTestExit $e) {
    // Terminating methods throw this in test mode — capture the state
}

// Assert
assert($response->redirectUrl === '/users');
assert(tiny::db()->getOne('users', "name = 'Ran'")['name'] === 'Ran');

echo "PASS\n";
```

Run it:

```bash
php tests/users/create.php
```

**Mocking with `tiny::swap()`** — for unit-style isolation, replace the real DB with a fake:

```php
class FakeDB extends DB
{
    public array $inserted = [];
    public function insert(string $table, array $data): mixed
    {
        $this->inserted[] = $data;
        return 1;
    }
}

tiny::swap('db', new FakeDB());
```

See [`docs/core-concepts/testing.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/testing.md) for the full reference.

---

## Deployment

For production, only the `html/` directory should be web-exposed. Everything else (`tiny/`, `app/`, `vendor/`, `.env.prod`) lives one directory up.

```
/srv/my-app/html      # ← exposed to the world (DocumentRoot)
/srv/my-app/tiny
/srv/my-app/app
/srv/my-app/vendor
/srv/my-app/.env.prod
```

For a complete `git push`-based deploy workflow (bare repo + `post-receive` hook), see [`docs/getting-started/git-deploy.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/git-deploy.md).

---

## Migrations

Migrations live in `migrations/` and are tracked in a local SQLite database:

```bash
php tiny/cli migrations create create_users_table   # scaffold
php tiny/cli migrations up                          # apply pending
php tiny/cli migrations down                        # roll back last batch
php tiny/cli migrations remove <name>               # delete an unapplied one
```

Migration class shape:

```php
<?php
class CreateUsersTable
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS users");
    }
}
```

---

## Documentation

Full documentation lives in [`docs/`](https://github.com/ranaroussi/tiny/blob/docs/docs/):

- **Getting started:** [overview](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/readme.md), [configuration](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/configuration.md), [runtime modes](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/runtime-modes.md), [git deploy](https://github.com/ranaroussi/tiny/blob/docs/docs/getting-started/git-deploy.md)
- **Core concepts:** [MVC](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/mvc.md), [routing](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/routing.md), [controllers](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/controllers.md), [request & response](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/request-response.md), [views](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/views.md), [models](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/models.md), [database](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/database.md), [middleware](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/middleware.md), [HTMX](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/htmx.md), [testing](https://github.com/ranaroussi/tiny/blob/docs/docs/core-concepts/testing.md)
- **Extensions:** [cache](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/cache.md), [components](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/components.md), [cookie](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/cookie.md), [CMS](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/cms.md), [CSRF](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/csrf.md), [database](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/database.md), [debugger](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/debugger.md), [flash](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/flash.md), [HTTP](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/http.md), [layout](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/layout.md), [migrations](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/migrations.md), [scheduler](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/scheduler.md), [SSE](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/sse.md), [ClickHouse](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/clickhouse.md), [Swoole](https://github.com/ranaroussi/tiny/blob/docs/docs/extensions/swoole.md)
- **Helpers:** [catalog & custom helpers](https://github.com/ranaroussi/tiny/blob/docs/docs/helpers/readme.md)
- **Examples:** [TODO app](https://github.com/ranaroussi/tiny/blob/docs/docs/examples/todo-app.md), [API](https://github.com/ranaroussi/tiny/blob/docs/docs/examples/api.md), [chat (SSE)](https://github.com/ranaroussi/tiny/blob/docs/docs/examples/chat.md), [file upload](https://github.com/ranaroussi/tiny/blob/docs/docs/examples/file-upload.md), [user management](https://github.com/ranaroussi/tiny/blob/docs/docs/examples/user-management.md)
- **Architecture & vision:** [`docs/architecture.md`](https://github.com/ranaroussi/tiny/blob/docs/docs/architecture.md)

---

## Versioning

Current version: see [`.version`](.version). Tiny follows semantic versioning post-3.0. The 2.x series is feature-frozen for backwards compatibility; new APIs are additive.

## Contributing

Issues and pull requests welcome at [github.com/ranaroussi/tiny](https://github.com/ranaroussi/tiny). Please match the existing code style (PHP 8.3 features, `declare(strict_types=1)`, no Composer-managed framework deps beyond `vlucas/phpdotenv` and `dragonmantank/cron-expression`).

## License

Tiny PHP Framework is distributed under the **Apache License 2.0**. See [`LICENSE`](https://www.apache.org/licenses/LICENSE-2.0) for the full text.

> Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
