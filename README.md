# Tiny App Boilerplate

Production-ready starter for [Tiny](https://github.com/ranaroussi/tiny) — a zero-config PHP framework.

## Quickstart

```bash
git clone -b boilerplate https://github.com/ranaroussi/tiny.git my-app
cd my-app
git submodule update --init
composer install
cd buildtools && npm install
cp env.example .env.local
docker-compose up
```

Visit http://localhost:8080.

## What's included

- **Tiny framework** — via git submodule (`tiny/`)
- **Docker stack** — nginx, php-fpm, supervisord, cron
- **Build pipeline** — Tailwind CSS + Webpack for React (in `buildtools/`)
- **App scaffold** — controllers, models, views, layouts, components, middleware, jobs, scheduler, CMS
- **Testing** — sample test in `tests/` using Tiny's built-in test harness
- **Migrations** — ready for `php tiny/cli migrations ...`

## Environment setup

Copy `env.example` to `.env.local` and edit:

```bash
cp env.example .env.local
```

For testing, also create `.env.test`:

```bash
cp env.example .env.test
```

Recommended `.env.test` settings:

```env
ENV=test
DB_TYPE=sqlite
TINY_CACHE_DISABLED=true
```

When `ENV=test` + `DB_TYPE=sqlite` with no `DB_SQLITE_FILE`, Tiny auto-connects to `:memory:` — a fresh in-memory database for every test run.

## Testing

```bash
php tests/home/get.php
```

See the [Tiny testing docs](https://github.com/ranaroussi/tiny/blob/tiny/docs/core-concepts/testing.md) for the full reference.

## Build assets

```bash
cd buildtools
npm run watch    # development
npm run build    # production
```

## Migrations

```bash
php tiny/cli migrations create create_users_table
php tiny/cli migrations up
php tiny/cli migrations down
```

## Scheduler

Edit `app/scheduler.php` to define jobs, then add to crontab:

```cron
* * * * * php /path/to/app/scheduler.php >/dev/null 2>&1
```

## Structure

```
app/
  controllers/   # one PHP file per route
  models/        # TinyModel subclasses
  views/         # templates, components, layouts
  middleware/    # middleware classes
  middleware.php # register active middleware
  jobs/          # scheduler job classes
  scheduler.php  # job definitions
  scheduler.sh   # cron wrapper
  common.php     # optional autoloaded helpers
  cms/           # markdown pages/posts
tiny/            # framework submodule (git submodule update --init)
html/            # document root
buildtools/      # Tailwind + Webpack
server-config/   # nginx, php-fpm, supervisord configs
tests/           # plain PHP test files
migrations/      # migration files
```
