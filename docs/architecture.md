[Home](readme.md) | [Getting Started](getting-started) | [Core Concepts](core-concepts) | [Helpers](helpers) | [Extensions](extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Architecture & vision

Tiny is built on a small set of opinions. This page explains what they are and why.

## Opinions, briefly

1. **The filesystem is the route table.** No DSL, no annotations, no compiled routes. The file path is the URL.
2. **SQL is just SQL.** No ORM, no migration auto-generation, no entity classes. A thin safe wrapper around PDO that knows MySQL, PostgreSQL, and SQLite. Drop to raw SQL whenever you want.
3. **Stay in PHP.** No new templating language, no build step for views. PHP is the templating language. Layouts and components are PHP callables.
4. **Pick your front-end per route.** Server-rendered PHP, HTMX partial swaps, or React SSR + SPA — all from the same controller pattern. No global commitment.
5. **One cron entry, one scheduler.** Built-in fluent scheduler, down to the second, with a local test harness. No external runners.
6. **Boot once if you can.** The same application code runs on PHP-FPM (boot per request), Swoole (boot once), and FrankenPHP (boot once). You opt into the right one for your traffic shape without rewriting controllers.
7. **Batteries included, but optional.** CMS, OAuth, Stripe, Mailgun, Twilio, ClickHouse, S3 — drop them into `TINY_AUTOLOAD_HELPERS` and call `tiny::stripe()`. Or don't, and they cost you nothing.
8. **The framework should disappear.** When you read a Tiny controller, you read PHP. The amount of framework-specific knowledge required to navigate a Tiny codebase is intentionally minimal.

## What Tiny is good at

- **Small-to-mid-sized web apps and SaaS products.** Marketing site + auth + payments + dashboard — Tiny gets you there with very little ceremony.
- **Content-driven sites.** The file-based CMS turns a directory of markdown into a fully cached, taggable, searchable site.
- **HTMX-first apps.** Auto `HX-Push-Url`, HTMX-aware redirects, server-rendered partials — Tiny was designed around HTMX patterns.
- **Hybrid React apps.** `tiny::renderReact()` gives you SSR + SPA from one controller. No Next.js, no separate Node process.
- **Internal tools and CRUD admin apps.** The filesystem router and raw-SQL DB layer make new pages trivial.
- **Background jobs in the same project.** The built-in scheduler runs from one cron entry; you don't need Sidekiq, Resque, or RabbitMQ to get started.

## What Tiny is not for

- **Heavyweight enterprise apps with deep domain models.** If you want an ORM, repositories, dependency injection containers, and event sourcing out of the box, reach for Laravel or Symfony.
- **Pure microservices or pure CLI tools.** Tiny is a web framework first; the request/response model is central.
- **Teams that need a strict typed schema layer.** Schema validation exists (`TinyModel::isValid()`) but it's not the kind of static type safety you'd get from Doctrine entities or Eloquent casts.

## Comparison

| | **Tiny** | **Laravel** | **Slim / Lumen** |
|---|---|---|---|
| Routes | Filesystem | Declarative + closures | Closures / controllers |
| ORM | None (raw SQL helpers) | Eloquent | None / your choice |
| Templates | PHP + components/layouts | Blade | PHP / Twig |
| HTMX | First-class (`HX-Push-Url`, redirects) | Via packages | Manual |
| React | Built-in SSR + SPA (`renderReact`) | Inertia | Manual |
| Scheduler | Built-in, second-level | Built-in, minute-level | External |
| Runtime modes | FPM / Swoole / FrankenPHP | FPM (+ Octane) | FPM |
| Helpers | 30+ first-party | Via Composer | Via Composer |
| Composer deps | Minimal (dotenv, cron-expr) | Many | Minimal |
| Learning curve | Hours | Days | Hours |

## Design tradeoffs

**No DI container.** `tiny::cache()`, `tiny::db()`, etc. are static accessors that return singletons. This is deliberate: it's grep-friendly, has zero boot cost, and makes the dependency graph visible at a glance. The cost is that you can't trivially swap implementations in tests — but the framework's surface is small enough that we lean on integration tests rather than mocking.

**No ORM.** A thin PDO wrapper covers 90% of CRUD; the rest is raw SQL. Tiny doesn't try to abstract over database differences (`SERIAL` vs `AUTO_INCREMENT`, `RETURNING` vs `lastInsertId()`). If you target one database, that's a feature; if you target three, you write the dialect-specific code yourself.

**No route table.** The filesystem mapping is fast and obvious — you can always grep for a URL to find the controller. The cost is that there's no good way to attach metadata to a route (rate limit tiers, custom middleware, route names). Middleware is global, ordering is in `app/middleware.php`, and per-route gating is done inside the controller.

**HTMX-aware response object.** `$response->render()` automatically emits `HX-Push-Url`. This is the right default for the workflows Tiny targets, but it means responses are not purely "send these bytes" — they make some decisions for you. Override with explicit `tiny::header()` calls when needed.

**Helpers are opt-in.** `TINY_AUTOLOAD_HELPERS` controls which helper files are loaded. The framework boots without any of them, so unused integrations cost nothing. The flip side is that helpers don't appear in static analysis until they're loaded.

## Stability and versioning

- **2.x** — current. Public API is stable; new APIs are additive.
- **3.0** — will introduce semver guarantees for the public surface (`tiny::*`, `TinyController`, `TinyModel`, `TinyRequest`, `TinyResponse`, extension accessors).
- Internal APIs (anything not on the documented surface) may change between minors.

## Roadmap signals

These are likely directions, not commitments:

- Built-in queue helper for async jobs (background workers, not just scheduled ones)
- Optional connection pooling for the Swoole runtime
- Tighter `TINY_*` prefix handling (today some legacy variables are read without the prefix)
- More first-party React adapters (Tanstack Router / Remix-style client bootstrapping)

If any of these matter to you, open an issue.

## Why does this exist?

Tiny started as the framework powering [aroussi.com](https://aroussi.com) and a handful of internal projects. The brief was: "build a website without spending three days configuring a framework first." Everything in Tiny is there because a real project needed it, and nothing is there because some other framework had it.

The design ethos is closest in spirit to early Sinatra (Ruby) or modern Hono (Go/TS): a small, sharp tool that gets out of your way.
