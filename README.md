# Tiny — PHP Framework

[![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](#license)
[![Version](https://img.shields.io/badge/version-2.8.x-brightgreen)](https://github.com/ranaroussi/tiny/blob/tiny/.version)

Zero-config, batteries-included PHP framework. Your filesystem **is** the router. Your SQL is just SQL. You stay in PHP. Runs unchanged on PHP-FPM, Swoole, or FrankenPHP.

> **Status:** v2.8.x — feature-complete, in active production use. Public API is stable; semver applies after 3.0.

---

## What makes Tiny different

- **Zero ceremony.** Drop a file in `app/controllers/`. That is the route.
- **Let PHP be PHP.** No compilation step, no route cache to warm, no container to restart. Add or remove a file and the change is live instantly. This is what makes Tiny extremely fast and lightweight — it doesn't fight the language, it gets out of the way.
- **No magic ORM.** Raw SQL with a thin, safe wrapper over MySQL, PostgreSQL, SQLite, and ClickHouse.
- **HTMX-native** request/response with auto `HX-Push-Url` and HTMX-aware redirects.
- **React hybrid renderer** — SSR on first load, JSON on subsequent requests. One controller, both modes.
- **Built-in scheduler** — fluent cron-style API with second-level granularity. No external binaries.
- **File-based CMS** — drop markdown into `app/cms/`, get pages, posts, tags, and a sitemap-ready API.
- **30+ integration helpers** — Stripe, Mailgun, Twilio, Spaces/S3, OAuth, ClickHouse, and more.

---

## Repository structure

Tiny is organized into focused branches. Pick the entry point that matches what you need:

| Branch | What it is | Get started |
|---|---|---|
| **[`boilerplate`](https://github.com/ranaroussi/tiny/tree/boilerplate)** | Full app shell with Docker, Tailwind, build pipeline, tests, and migrations | `php tiny/cli create my-app` |
| **[`tiny`](https://github.com/ranaroussi/tiny/tree/tiny)** | Framework code only — for submodules and drop-in use | `git submodule add -b tiny https://github.com/ranaroussi/tiny.git tiny` |
| **[`docs`](https://github.com/ranaroussi/tiny/tree/docs)** | Full documentation, examples, and architecture writeups | Browse on GitHub |
| **`main`** | This page — the landing page | You're here |

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

See the [docs branch](https://github.com/ranaroussi/tiny/tree/docs) for the full reference: routing, middleware, HTMX, React rendering, scheduler, testing, deployment, and more.

---

## Contributing

Issues and pull requests welcome at [github.com/ranaroussi/tiny](https://github.com/ranaroussi/tiny). Please match existing code style (PHP 8.3, `declare(strict_types=1)`).

## License

Apache License 2.0. See [LICENSE](https://www.apache.org/licenses/LICENSE-2.0) for the full text.

> Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
