[Home](readme.md) | [Getting Started](getting-started) | [Core Concepts](core-concepts) | [Helpers](helpers) | [Extensions](extensions) | [Examples](examples) | [Repo](https://github.com/ranaroussi/tiny)

# Tiny PHP Framework — Documentation

Tiny is a zero-config, batteries-included PHP framework. Your filesystem is the router, your SQL is just SQL, and the same code runs on PHP-FPM, Swoole, or FrankenPHP.

This documentation covers everything from a 60-second quickstart through every extension and helper that ships with the framework.

> **Repository branches:**
> - [`main`](https://github.com/ranaroussi/tiny/tree/main) — landing page and project overview
> - [`tiny`](https://github.com/ranaroussi/tiny/tree/tiny) — framework code only (use as a [submodule](https://git-scm.com/book/en/v2/Git-Tools-Submodules))
> - [`boilerplate`](https://github.com/ranaroussi/tiny/tree/boilerplate) — full app shell with Docker, Tailwind + Webpack build pipeline, tests, and migrations
> - [`docs`](https://github.com/ranaroussi/tiny/tree/docs) — this documentation

## What's in the box

**Core**
- Filesystem-based routing with controller / section / slug resolution
- `TinyController` / `TinyModel`
- Middleware pipeline
- CSRF, flash messages, cookies, sessions
- Server-Sent Events helper

**Views & UI**
- **Component system** (`Component::register()` / `Component::render()`)
- **Layout system** (`Layout::main()`, `Layout::auth()`, …)
- **HTMX-native** request/response with auto `HX-Push-Url`
- **React renderer** (`tiny::renderReact()`) — SSR + SPA from one controller
- Static asset URL helpers, HTML minification

**Scheduler & background work**
- **Built-in fluent scheduler** — one cron entry, no external runners
- Cron-style API plus calendar shortcuts (`->monday('09:00')`, `->january(1)`)
- **Second-level granularity** via `scheduler.sh`
- Local job-testing harness for browser-driven debugging

**Data**
- Raw-SQL DB layer (MySQL, PostgreSQL, SQLite) with a thin safe wrapper
- ClickHouse extension
- APCu / Memcached cache with `remember()` and prefix operations
- Migration system (CLI-driven, tracked locally)

**Content**
- **File-based CMS** (`tiny::cms()`) with markdown + GFM extensions (callouts, tabs, cards, columns) — powers production sites like aroussi.com
- Tag indexing, sitemap-ready API, automatic caching

**Runtime modes**
- Classic PHP-FPM (default)
- Swoole coroutines
- FrankenPHP worker mode
- OPcache preloading

**Tooling**
- IP-gated debugger (`dd`, `dump`, `log`, `ddump`)
- CLI for scaffolding and migrations
- Built-in zero-ceremony testing harness (`tiny::swap()`, `tiny::test()`, `TinyTestResponse`, auto `:memory:` SQLite)

**Integrations (30+ helpers)**
- Payments: Stripe, S3-compatible Spaces, invoice PDFs
- Email: Mailgun, Sendgrid, generic email, disposable-email validator
- Marketing / CRM: HubSpot, Customer.io, Encharge, GetResponse, Mixpanel, Userflow, Logsnag
- Messaging: Twilio, Vonage
- Auth & identity: OAuth, avatar generator, UUID, crypto (cypher)
- Geo: GeoIP2 + currency / country rules
- Media: Markdown renderer, OpenGraph + OG-image generation, document reader
- Misc: rate limiter, shell, HTML helpers, Caddy config

## How to read this site

1. **[Getting Started](getting-started/readme.md)** — install, configure, runtime modes, deploy.
2. **[Core Concepts](core-concepts/readme.md)** — MVC, routing, controllers, request/response, views, models, database, middleware, HTMX, testing.
3. **[Extensions](extensions/readme.md)** — every first-party extension that lives in `tiny/ext/`.
4. **[Helpers](helpers/readme.md)** — the integration catalog plus how to register custom helpers.
5. **[Examples](examples/readme.md)** — end-to-end walkthroughs (TODO app, API, chat, file upload, user management).
6. **[Architecture & vision](architecture.md)** — the design philosophy behind Tiny.

## Requirements

- PHP **8.3** or newer
- Composer
- PHP extensions: `pdo`, `openssl`, `mbstring`, `curl`, `json`, `fileinfo`, `zip`, plus one of `apcu` / `memcached`
- DB drivers as needed: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`
- Optional: `swoole` (coroutine mode), `imagick` (OG-image helper)

## License

Tiny PHP Framework is distributed under the **Apache 2.0** License.
