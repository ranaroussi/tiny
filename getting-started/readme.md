[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Examples](../examples) | [Repo](https://github.com/ranaroussi/tiny)

# Getting Started

This section walks you from `git clone` to a running page in under five minutes.

- [Quickstart](#quickstart)
- [Project layout](#project-layout)
- [Configuration](configuration.md) — full `TINY_*` env var reference
- [Runtime modes](runtime-modes.md) — PHP-FPM, Swoole, FrankenPHP, OPcache preload
- [Git-push deployment](git-deploy.md)

## Quickstart

```bash
# 1. Create the project directory
mkdir my-app && cd my-app

# 2. Clone the framework
git clone https://github.com/ranaroussi/tiny.git

# 3. Scaffold the application (unpacks the sample project)
php tiny/cli create

# 4. Install dependencies
composer install

# 5. Configure environment
cp .env.example .env.local
# edit .env.local with your DB and integration credentials

# 6. Serve ./html with your web server (Caddy, nginx, Apache, FrankenPHP, PHP-FPM)
```

Open `http://localhost` and you should see the sample landing page rendered by `app/controllers/home.php`.

## Project layout

After `php tiny/cli create` your tree looks like this:

```
my-app/
├── app/
│   ├── controllers/       # one PHP file per route
│   ├── models/            # TinyModel subclasses
│   ├── views/
│   │   ├── components/    # reusable view snippets (Component::render)
│   │   └── layouts/       # page chrome (Layout::*)
│   ├── middleware/        # one class per file: <Name>Middleware
│   ├── middleware.php     # registers active middleware with tiny::middleware()
│   ├── common.php         # optional, autoloaded if present
│   ├── jobs/              # scheduler job classes (optional)
│   └── cms/               # markdown content for tiny::cms() (optional)
├── html/                  # ← document root
│   └── index.php          # bootstraps tiny and dispatches to controllers
├── migrations/            # migration classes (managed by tiny/cli migrations)
├── tiny/                  # the framework
├── vendor/                # composer dependencies
├── .env.local             # per-env config (also .env.dev, .env.prod, ...)
├── composer.json
└── env.php                # defines the ENV constant (local|dev|stage|prod)
```

### What each piece does

| Path | Role |
|---|---|
| `html/` | The only directory exposed to the world. Contains `index.php` (bootstrap) plus static assets in `static/`. |
| `app/controllers/` | One PHP file per route. The file path **is** the URL. |
| `app/views/` | Templates rendered via `$response->render('path/to/view')` or `tiny::render(...)`. |
| `app/models/` | Domain logic. Load with `tiny::model('user')`; class must be `UserModel extends TinyModel`. |
| `app/middleware/` + `middleware.php` | Pre-controller hooks. `auth.php` exports `AuthMiddleware::handle()`. |
| `app/common.php` | Autoloaded on every request — put global utility functions here. |
| `migrations/` | Versioned schema changes. Tracked in a local SQLite ledger. |
| `tiny/` | The framework itself. Treat as a submodule / vendored copy. |
| `env.php` | One line: `const ENV = 'local';` — picks which `.env.<env>` to load. |

## Minimal first page

Create the controller `app/controllers/hello.php`:

```php
<?php
class Hello extends TinyController
{
    public function get($request, $response)
    {
        $response->render('hello', ['name' => $request->query['name'] ?? 'world']);
    }
}
```

Create the view `app/views/hello.php`:

```php
<h1>Hello, <?= htmlspecialchars(tiny::get('name')) ?>!</h1>
```

Visit `http://localhost/hello?name=ada`. That's it.

## Next steps

- **[Configuration](configuration.md)** — every supported `TINY_*` environment variable.
- **[Runtime modes](runtime-modes.md)** — how to run Tiny on Swoole, FrankenPHP, or with OPcache preloading.
- **[Core Concepts](../core-concepts/readme.md)** — the conceptual tour: routing, controllers, views, models, middleware, HTMX.
- **[Git-push deployment](git-deploy.md)** — bare-repo + `post-receive` workflow.
