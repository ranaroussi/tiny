[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Core Concepts

The conceptual tour of the framework. Read these in order if you're new to Tiny.

## Architecture

1. **[MVC architecture](mvc.md)** — how Tiny implements Model-View-Controller, and the request lifecycle.

## Request handling

2. **[Routing](routing.md)** — the filesystem-based router, 3-segment URL resolution, and the fallback algorithm.
3. **[Controllers](controllers.md)** — `TinyController`, HTTP-verb dispatch, request/response helpers.
4. **[Request & Response](request-response.md)** — the full `TinyRequest` / `TinyResponse` API reference.
5. **[Middleware](middleware.md)** — pre-controller hooks (auth, CORS, logging, rate limiting).

## Views

6. **[Views](views.md)** — templates, components, layouts, asset helpers.
7. **[HTMX & React](htmx.md)** — built-in HTMX awareness and React SSR/SPA rendering via `tiny::renderReact()`.

## Data

8. **[Models](models.md)** — `TinyModel`, schema validation, caching patterns.
9. **[Database](database.md)** — raw-SQL helpers for MySQL, PostgreSQL, SQLite.

## See also

- [Extensions](../extensions/readme.md) — first-party modules (cache, CSRF, SSE, scheduler, CMS, ClickHouse, …)
- [Helpers](../helpers/readme.md) — integration catalog (Stripe, Mailgun, OAuth, S3, …)
- [Architecture & vision](../architecture.md) — the design philosophy behind Tiny
