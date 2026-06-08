[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Examples](../examples) | [Repo](https://github.com/ranaroussi/tiny)

# Getting Started

This section walks you from `git clone` to a running page in under five minutes.

- [Quickstart](#quickstart)
- [Project layout](#project-layout)
- [Configuration](configuration.md) — full `TINY_*` env var reference
- [Runtime modes](runtime-modes.md) — PHP-FPM, Swoole, FrankenPHP, OPcache preload
- [Git-push deployment](git-deploy.md)

## Quickstart

The fastest way to get a full working project is via the [`boilerplate`](https://github.com/ranaroussi/tiny/tree/boilerplate) branch:

```bash
# 1. Create the project directory
mkdir my-app && cd my-app

# 2. Clone the framework
git clone https://github.com/ranaroussi/tiny.git

# 3. Scaffold from the boilerplate branch
php tiny/cli create

# 4. Pull the framework submodule
git submodule update --init

# 5. Install dependencies
composer install
cd buildtools && npm install

# 6. Configure environment
cp env.example .env.local
# edit .env.local with your DB and integration credentials

# 7. Start the dev stack
docker-compose up
```

Visit http://localhost:8080.

The boilerplate includes Docker, a Tailwind + Webpack build pipeline, sample tests, migrations, and a working app scaffold. See the [boilerplate branch README](https://github.com/ranaroussi/tiny/blob/boilerplate/README.md) for details.

### Alternative: submodule (existing projects)

If you already have a project and just want the framework as a submodule:

```bash
git submodule add -b tiny https://github.com/ranaroussi/tiny.git tiny
git submodule update --init
```

Point your web server at `html/`.

### Alternative: drop-in (quick experiments)

```bash
git clone -b tiny https://github.com/ranaroussi/tiny.git
```

## Project layout

After `php tiny/cli create` (from the boilerplate branch) your tree looks like this:

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
├── tests/                 # plain PHP test files (tiny::test() + TinyTestResponse)
├── tiny/                  # the framework (git submodule → tiny branch)
├── vendor/                # composer dependencies
├── buildtools/            # Tailwind CSS + Webpack build pipeline
├── server-config/         # nginx, php-fpm, supervisord configs for Docker
├── Dockerfile
├── docker-compose.yml
├── .env.local             # per-env config (also .env.dev, .env.prod, ...)
├── .env.test              # test env: sqlite, :memory:, cache disabled
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
