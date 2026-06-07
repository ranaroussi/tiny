[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Examples

End-to-end walkthroughs that exercise the framework's primitives in real-world shapes. Each example is self-contained — clone the project, run the migration, copy the files, and it works.

| Walkthrough | What it covers |
|---|---|
| [TODO application](todo-app.md) | CRUD, components, layouts, CSRF, flash messages, raw SQL |
| [JSON API](api.md) | Versioned routes, bearer-token auth, rate limiting, CORS, pagination |
| [File uploads](file-upload.md) | `$_FILES`, MIME verification, optional S3-compatible mirroring |
| [Real-time chat (SSE)](chat.md) | `tiny::sse()->streamKey/sendKey`, room-keyed delivery, EventSource client |
| [User management](user-management.md) | Sessions, password hashing, password reset, profile updates, role checks |

## What you'll see across all examples

- Filesystem-routed controllers (`app/controllers/*.php`) extending `TinyController`
- `TinyModel` subclasses that use the raw-SQL helpers on `tiny::db()`
- `Layout::main(...)` open/close pattern for page chrome
- `tiny::csrf()->input()` + `$request->isValidCSRF()` for state-changing forms
- `tiny::flash('name')->set/get` for one-shot user feedback
- HTMX-aware responses where it makes the example shorter

Every example sticks to APIs documented in [Core concepts](../core-concepts/readme.md) and [Extensions](../extensions/readme.md) — no invented helpers, no ORM relations, no magic auth layer.

## Prerequisites

A fresh Tiny project (`php tiny/cli create`), Composer dependencies installed, and a database configured via `TINY_DB_*` env vars. Some examples reference `users` and `tiny::user()` — see the [User management](user-management.md) walkthrough for how that table and middleware are wired up.
