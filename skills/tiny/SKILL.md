# Tiny PHP Framework — Coding Agent Skill

You are an expert in the Tiny PHP framework. Before writing or modifying any code in a Tiny project, internalize the rules below.

---

## Philosophy

- **Let PHP be PHP.** No compilation step, no route cache to warm, no container to restart. Drop a PHP file in `app/controllers/` and the route is live instantly; remove it and it's gone. This is why Tiny is extremely fast and lightweight — it doesn't fight the language, it gets out of the way.
- **Zero ceremony.** The filesystem is the router.
- **No ORM.** Raw SQL with a thin, safe wrapper.
- **Stay in PHP.** Server-rendered views, HTMX-native, optional React hybrid renderer.
- **One codebase, many runtimes.** Identical code runs on PHP-FPM, Swoole, and FrankenPHP.

---

## File & Code Conventions

1. **Every PHP file** must start with `<?php` and `declare(strict_types=1);`.
2. **No Composer-managed framework deps** beyond `vlucas/phpdotenv` and `dragonmantank/cron-expression`.
3. Match the existing code style: snake_case for variables, PascalCase for classes, camelCase for methods, 4-space indentation.
4. Use PHP 8.3 features (match expressions, named arguments, readonly properties, enums where appropriate).
5. Apache 2.0 license header is standard but optional for app code.

---

## Project Structure

After `php tiny/cli create`:

```
app/
  controllers/       # one PHP file per route
  models/            # TinyModel subclasses
  views/
    components/      # reusable UI snippets (Component::register / Component::render)
    layouts/         # page chrome (Layout::main, Layout::auth, ...)
  middleware/        # middleware classes
  middleware.php     # registers active middleware
  common.php         # optional autoloaded helpers
  scheduler.php      # cron-style job definitions
  jobs/              # job classes invoked by the scheduler
  cms/               # markdown pages/posts for tiny::cms()
html/                # Document root (index.php)
migrations/          # migration files (SQLite-tracked)
tiny/                # framework files (this repo)
```

---

## Routing

Routes are derived from `app/controllers/` against the URL path. For `/users/profile/edit`:

1. `users/profile/edit.php`
2. `users/profile/edit.php` (slashes → hyphens in slug)
3. `users/profile-edit.php`
4. `users/profile.php`
5. `users/index.php`
6. `users.php`
7. `404.php`

Inside a controller:

```php
$request->path->controller;  // "users"
$request->path->section;     // "profile"
$request->path->slug;        // "edit"
$request->path->full;        // "/users/profile/edit"
$request->htmx;              // bool
```

---

## Controllers

Controllers extend `TinyController` and live in `app/controllers/`. Each public method named after an HTTP verb handles matching requests.

```php
<?php

declare(strict_types=1);

class Users extends TinyController
{
    private UserModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = tiny::model('user');
    }

    public function get($request, $response): void
    {
        $users = $this->model->all();
        $response->render('users/index', ['users' => $users]);
    }

    public function post($request, $response): void
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }
        $data = $request->body(true);
        $this->model->create($data);
        $response->redirect('/users');
    }
}
```

Always call `parent::__construct()` if you define your own constructor.

### Response Object Methods

```php
$response->render('view/path', ['key' => $value]);   // render view, die by default
$response->render('view/path', $params, false);        // render, don't exit
$response->send($text, 200, true);                     // plain text response
$response->sendJSON(['ok' => true], 200, true);        // JSON response
$response->sendFile('/path/to/file.pdf', 200, true);  // file contents
$response->redirect('/login');                         // 302 redirect
$response->redirect('/login', 'htmx');                // HX-Redirect header
$response->flush('partial content', true);            // flush output buffer
$response->hasCSRFError('id', false);                  // show CSRF error
```

`$response->render()` automatically emits `HX-Push-Url: {permalink}` for HTMX.

---

## Request Object

```php
$request->method;             // "GET" | "POST" | …
$request->headers;            // associative array
$request->user;               // object set by middleware / tiny::user()
$request->htmx;               // bool
$request->query;              // $_GET
$request->path->controller;
$request->path->section;
$request->path->slug;
$request->path->full;
$request->csrf_token;         // populated after body() runs

$request->params();           // merged $_REQUEST (case-insensitive)
$request->params('email');    // single key, optional fallback as 2nd arg
$request->body();             // request body as object (JSON or form)
$request->body(true);         // same, as associative array
$request->json();             // raw php://input string
$request->isValidCSRF();      // bool
$request->isAsync();          // bool (Swoole | X-Requested-With | ?async=true)
```

---

## Models

Models extend `TinyModel`. There is no ORM — use raw SQL through the DB wrapper.

```php
<?php

declare(strict_types=1);

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

    public function update(int $id, array $data): bool
    {
        return tiny::db()->update('users', $data, "id = $id");
    }

    public function delete(int $id): bool
    {
        return tiny::db()->delete('users', "id = $id");
    }
}
```

### Validation

`TinyModel` provides a schema validator:

```php
public function create(array $data): mixed
{
    $schema = [
        'name'  => 'string(255)',
        'email' => 'string(255)',
        'age'   => '[int]',          // optional int
        'role'  => 'string|int',     // union type
    ];

    if (!$this->isValid($data, $schema)) {
        return false;
    }

    return tiny::db()->insert('users', $data);
}
```

Validation errors are stored in `$this->validationErrors`.

### DB Wrapper Methods

```php
tiny::db()->get($table, $where, $columns, $order, $limit);
tiny::db()->getOne($table, $where, $columns);
tiny::db()->insert($table, $data);
tiny::db()->update($table, $data, $where);
tiny::db()->upsert($table, $data, $uniqueColumns);
tiny::db()->delete($table, $where);
tiny::db()->execute($sql);
tiny::db()->query($sql);
tiny::db()->lastInsertId();
```

Supports MySQL, PostgreSQL, SQLite, and ClickHouse (`tiny::clickhouse()`).

---

## Views

Views are plain PHP templates in `app/views/`.

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

### Sharing Data with Views

Three equivalent ways:

```php
// 1. Inline via render() — preferred for view-specific data
$response->render('users/index', ['users' => $users, 'total' => 42]);

// 2. Global data bag
tiny::data()->users = $users;
tiny::set('total', 42);

// 3. Read inside view
tiny::data()->users;
tiny::get('total');
```

### Components

Register reusable view fragments once, call them like functions:

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

### Layouts

```php
// app/views/layouts/main.php
<!doctype html>
<html>
<head><title><?= htmlspecialchars(Layout::props('title', 'My App')) ?></title></head>
<body><?= Layout::props('content') ?></body>
</html>
```

Invoke from a view:

```php
<?php Layout::main(['title' => 'Dashboard']); ?>
<!-- page content -->
```

---

## HTMX Integration

Tiny is HTMX-native:

- `$request->htmx` — `true` when `HX-Request` header is present.
- `$response->render()` — automatically sends `HX-Push-Url: {permalink}`.
- `tiny::redirect($url, 'htmx')` — sends `HX-Redirect` instead of a 302.

Detect HTMX in controllers for partial vs full rendering:

```php
public function get($request, $response): void
{
    if ($request->htmx) {
        $response->render('users/_partial_list', ['users' => $this->model->all()]);
    } else {
        $response->render('users/index', ['users' => $this->model->all()]);
    }
}
```

---

## React Hybrid Renderer (SSR + SPA)

`$response->renderReact()` returns full HTML on first load and JSON on `X-SPA-Request` / XHR:

```php
class Dashboard extends TinyController
{
    public function get($request, $response): void
    {
        $response->renderReact('DashboardPage', [
            'user'  => tiny::user(),
            'stats' => tiny::model('stats')->forUser(tiny::user()->id),
        ]);
    }
}
```

---

## Middleware

Create middleware classes in `app/middleware/<name>.php`:

```php
<?php

declare(strict_types=1);

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

Register in `app/middleware.php`:

```php
<?php
tiny::middleware('auth');
tiny::middleware('version');
```

Each registered middleware's `handle()` runs before controller dispatch.

---

## Scheduler

Define jobs in `app/scheduler.php`:

```php
<?php
require_once __DIR__ . '/../tiny/tiny.php';

tiny::scheduler()->job('Reports/daily')->daily('02:00');
tiny::scheduler()->job('Cache/warm', ['users'])->everyMinute(5);
tiny::scheduler()->job('Heartbeat/ping')->everySecond(5);
tiny::scheduler()->job('Cleanup/temp')->at('*/15 * * * *');
tiny::scheduler()->job('Newsletter/weekly')->monday('09:00');
tiny::scheduler()->job('Reports/yearly')->january(1, '00:00');

tiny::scheduler()->run();
```

Job classes live in `app/jobs/` and match the path:
`Reports/daily` → `app/jobs/reports.php` with a `daily()` method.

Cron entry:

```cron
# Minute-level
* * * * * /usr/bin/php /var/www/app/scheduler.php >/dev/null 2>&1

# Second-level (bundled wrapper)
* * * * * /var/www/tiny/scheduler.sh >/dev/null 2>&1
```

Test harness (local only):

```php
tiny::initTestScheduler();
(new Reports())->daily();
```

---

## Environment & Configuration

Tiny uses `.env.<env>` files loaded by `env.php` which defines the `ENV` constant (`local|dev|stage|prod`).

Framework settings use the `TINY_*` prefix:

```env
TINY_APP_DIR=app
TINY_HOMEPAGE=home
TINY_TIMEZONE=UTC
TINY_DEBUG_WHITELIST=*
TINY_MINIFY_OUTPUT=false

TINY_DB_TYPE=postgres
TINY_DB_HOST=localhost
TINY_DB_PORT=5432
TINY_DB_NAME=myapp
TINY_DB_USER=postgres
TINY_DB_PASS=******

TINY_CACHE_DISABLED=false
TINY_CACHE_PREFIX=myapp
```

Read values from `$_SERVER` directly, or via `tiny::config($key)`.

---

## Migrations

```bash
php tiny/cli migrations create create_users_table
php tiny/cli migrations up
php tiny/cli migrations down
php tiny/cli migrations remove <name>
```

Migration shape:

```php
<?php

declare(strict_types=1);

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

## Flash Messages

```php
public function post($request, $response): void
{
    if ($this->model->create($request->body(true))) {
        tiny::flash('toast')->set(['level' => 'success', 'message' => 'User created']);
        return $response->redirect('/users');
    }
    tiny::flash('toast')->set(['level' => 'error', 'message' => 'Failed']);
    $response->redirect('/users/new');
}
```

Read in the next request:

```php
$toast = tiny::flash('toast')->get(); // consumed; pass true to peek
```

---

## CSRF

Always validate on state-changing verbs:

```php
public function post($request, $response): void
{
    if (!$request->isValidCSRF()) {
        return $response->hasCSRFError();
    }
    // safe to mutate state
}
```

Render token in forms:

```php
<form method="post">
    <?php tiny::csrf()->input(); ?>
    <!-- … -->
</form>
```

---

## Cache

```php
tiny::cache()->set('key', $value, 3600);
$value = tiny::cache()->get('key');
$value = tiny::cache()->remember('key', 3600, fn() => expensiveOperation());
$values = tiny::cache()->getByPrefix('users_');
```

Supports APCu (default) and Memcached.

---

## Helpers & Custom Helpers

Built-in helpers live in `tiny/helpers/` (Stripe, Mailgun, Twilio, Spaces/S3, OAuth, etc.). Load them:

```php
tiny::helpers('stripe');           // load one
tiny::helpers(['stripe', 'mailgun']); // load many
tiny::helpers('*');                // load all
```

Register custom helpers:

```php
tiny::registerHelper('analytics', function () {
    return new AnalyticsClient();
});

// usage
$client = tiny::analytics();
```

---

## CMS (File-Based)

Drop markdown into `app/cms/`:

```php
$cms = tiny::cms();
$page = $cms->page('about');
$posts = $cms->posts();
$tags = $cms->tags();
```

Supports GFM extensions: callouts, tabs, cards, columns, toggles, steps. Auto-caches.

---

## Anti-Patterns to Avoid

1. **Don't add an ORM.** Use the raw SQL wrapper.
2. **Don't put business logic in controllers.** Move it to models or helpers.
3. **Don't scatter `tiny::data()` assignments.** Prefer `$response->render($view, $params)`.
4. **Don't duplicate auth checks across controllers.** Use middleware.
5. **Don't forget CSRF on POST/PUT/PATCH/DELETE.**
6. **Don't add heavy Composer deps** without strong justification.
7. **Don't create routes manually.** The filesystem *is* the router.

---

## When Adding New Features

1. Create the controller in `app/controllers/` (respect routing fallbacks).
2. Create the model in `app/models/` if data access is needed.
3. Create the view in `app/views/`.
4. Register components in `app/views/components/` if reusable UI is needed.
5. Add middleware in `app/middleware/` and register in `app/middleware.php` for cross-cutting concerns.
6. Write migrations in `migrations/` for schema changes.
7. Use `tiny::flash()` for user-facing status messages.
8. Use `tiny::cache()->remember()` for expensive computations.
