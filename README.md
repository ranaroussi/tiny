# Tiny — PHP Framework

[![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](#license)
[![Version](https://img.shields.io/badge/version-3.x-brightgreen)](.version)

Zero-config, batteries-included PHP framework. Filesystem-based routing, raw SQL helpers, HTMX-native, React hybrid renderer, scheduler, CMS, and 30+ integrations. Runs on PHP-FPM, Swoole, or FrankenPHP without changes.

> **Status:** v3.x — feature-complete, in active production use. Public API is stable; ScalVer applies.

---

## Get started

### Create a project (recommended)

```bash
php tiny/cli create my-app     # creates my-app/ with the boilerplate branch
php tiny/cli create            # or extract into the current directory
```

This fetches the [boilerplate branch](https://github.com/ranaroussi/tiny/tree/boilerplate) which includes Docker, Tailwind + Webpack build pipeline, app scaffold, tests, and migrations.

### Use as a submodule (existing projects)

```bash
git submodule add -b tiny https://github.com/ranaroussi/tiny.git tiny
git submodule update --init
```

Point your web server at `html/`. Create `env.php` to set `ENV`, and `.env.local` for settings.

### Drop-in (quick experiments)

```bash
git clone -b tiny https://github.com/ranaroussi/tiny.git
```

---

## Quick example

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
```

---

## CLI

```bash
php tiny/cli create [project]        # scaffold a new project from boilerplate
php tiny/cli migrations create <name>  # create a migration
php tiny/cli migrations up             # apply pending migrations
php tiny/cli migrations down           # roll back last batch
```

---

## Documentation

Full docs live on the [docs branch](https://github.com/ranaroussi/tiny/tree/docs):

- [Getting started](https://github.com/ranaroussi/tiny/blob/docs/getting-started/readme.md)
- [Core concepts](https://github.com/ranaroussi/tiny/blob/docs/core-concepts/readme.md) — MVC, routing, controllers, request/response, views, models, database, middleware, HTMX, testing
- [Extensions](https://github.com/ranaroussi/tiny/blob/docs/extensions/readme.md) — cache, components, CSRF, scheduler, CMS, SSE, ClickHouse, Swoole
- [Helpers](https://github.com/ranaroussi/tiny/blob/docs/helpers/readme.md) — Stripe, Mailgun, Twilio, OAuth, Spaces/S3, and more
- [Examples](https://github.com/ranaroussi/tiny/blob/docs/examples/readme.md) — TODO app, API, chat, file upload, user management

---

## Requirements

- **PHP 8.3+**
- **Composer**
- **Extensions:** `pdo`, `openssl`, `mbstring`, `curl`, `json`, `fileinfo`, `zip`, plus one of `apcu` or `memcached`
- **DB drivers** (use what you need): `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`
- Optional: `swoole`, `imagick`

---

## Versioning

Current version: see [`.version`](.version). Tiny follows semantic versioning post-3.0. The 2.x series is feature-frozen; new APIs are additive.

## Contributing

Issues and pull requests welcome at [github.com/ranaroussi/tiny](https://github.com/ranaroussi/tiny). Please match existing code style (PHP 8.3, `declare(strict_types=1)`, no extra Composer deps beyond `vlucas/phpdotenv` and `dragonmantank/cron-expression`).

## License

Apache License 2.0. See [`LICENSE`](https://www.apache.org/licenses/LICENSE-2.0) for the full text.

> Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
