[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Request & Response

Controllers receive a `TinyRequest` and a `TinyResponse` on every invocation. Both are also accessible globally via `tiny::request()` and `tiny::response()` — useful from middleware and components.

## `TinyRequest`

### Properties

```php
$request->method;        // "GET" | "POST" | …
$request->headers;       // associative array (output of getallheaders())
$request->htmx;          // bool: true when HX-Request header is present
$request->user;          // object set by middleware via tiny::user($user)
$request->query;         // $_GET
$request->path;          // object: { controller, section, slug, full }
$request->csrf_token;    // populated after body() is called
```

### `path`

```php
$request->path->controller;  // "users"   (first URL segment)
$request->path->section;     // "profile" (second URL segment, or "")
$request->path->slug;        // "edit"    (everything after, or "")
$request->path->full;        // "/users/profile/edit"
```

### Method: `params(?string $key = null, mixed $fallback = null)`

Case-insensitive lookups over the merged `$_REQUEST` (GET + POST + cookies):

```php
$all   = $request->params();
$email = $request->params('email');
$page  = $request->params('page', 1);
```

### Method: `body(bool $associative = false)`

Returns the parsed request body (handles JSON, form-encoded, and `$_POST`). Pass `true` for an associative array; default is `stdClass`.

```php
$body = $request->body();       // stdClass
$body = $request->body(true);   // array
```

The CSRF token is extracted from `body()` automatically and stripped from the returned value.

### Method: `json()`

Returns the raw `php://input` string. Useful for webhook signature verification:

```php
$raw       = $request->json();
$signature = $request->headers['Stripe-Signature'];
tiny::stripe()->verifyWebhook($raw, $signature);
```

### Method: `isValidCSRF(bool $remove = true): bool`

Validates the CSRF token in the body. Returns `true` if valid, `false` otherwise. By default the token is consumed after validation.

```php
if (!$request->isValidCSRF()) {
    return $response->hasCSRFError();
}
```

### Method: `isAsync(): bool`

Returns `true` if any of these is true:

- Running under Swoole + CLI
- `X-Requested-With: AsyncRequest` header
- `?async=true` in query string

Use it to short-circuit rendering of large templates.

## `TinyResponse`

All `$response` methods that "send" a response terminate the request by default. Pass `$die = false` to keep the script running.

### `render(string $file = '', array $params = [], bool $die = true): void`

Renders a view file from `app/views/`. `$params` are merged into `tiny::data()`. Automatically emits `HX-Push-Url` matching the current permalink (HTMX-friendly).

```php
$response->render('users/index');
$response->render('users/show', ['user' => $user]);
$response->render('users/show', ['user' => $user], false); // don't exit
```

### `renderReact(string $component, array $props, array $meta = [], ?string $template = null): void`

Renders a React component. Two modes:

- **First load / direct browser hit:** returns full HTML by rendering the optional `$template` view with the component name and props embedded.
- **SPA navigation:** when the request has `X-SPA-Request: true` or is XHR, returns JSON `{component, props}` for client-side routing.

```php
$response->renderReact('DashboardPage', [
    'user'  => tiny::user(),
    'stats' => $stats,
], meta: ['title' => 'Dashboard'], template: 'react-shell');
```

### `redirect(?string $goto = null, ?string $header = null): void`

HTMX-aware redirect. If the current request is HTMX, emits `HX-Redirect`; otherwise sends a 302.

```php
$response->redirect('/login');
$response->redirect('/login', 'htmx');   // force HX-Redirect
$response->redirect('/users', 301);      // permanent redirect
```

The `$header` parameter accepts `301`, `302`, `'javascript'`, `'htmx'`, or `'csrf'`.

### `send(mixed $payload, int $code = 200, bool $die = true): void`

JSON-encodes the payload and sends it with the given status code (no content-type header — that's `sendJSON`'s job).

```php
$response->send(['status' => 'ok']);
$response->send($obj, 201);
```

### `sendJSON(mixed $data, int $code = 200, bool $die = true): void`

Sets `Content-Type: application/json` and emits a JSON response.

```php
$response->sendJSON(['users' => $users]);
$response->sendJSON(['error' => 'Bad input'], 422);
```

### `sendFile(string $path, int $code = 200, bool $die = true): void`

Streams a file's contents as the response body.

```php
$response->sendFile('/srv/reports/q4.pdf');
```

### `flush(string $string = '', bool $finish_request = true): void`

Flush partial content. Useful for long-running tasks where you want to send some output before the script finishes (e.g. ahead-of-time HTML hints).

```php
$response->flush('<!doctype html>…');
expensiveBackgroundWork();
```

### `hasCSRFError(string $id = 'CSRF-VALIDATION-FAILED', bool $nextPage = false): void`

Display a CSRF error. Pass `$nextPage = true` to defer the error to the next page load (uses flash messaging).

```php
if (!$request->isValidCSRF()) {
    return $response->hasCSRFError();
}
```

## Global accessors

When you don't have a controller method's `$request` / `$response` in scope (e.g. from middleware, helpers, components), use the singletons:

```php
tiny::request()->headers['User-Agent'];
tiny::response()->sendJSON(['ok' => true]);
```

These return the same instances passed to the controller.

## See also

- [Routing](routing.md) — how `$request->path` is populated
- [Controllers](controllers.md) — verb dispatch
- [HTMX & React](htmx.md) — front-end rendering options
- [CSRF extension](../extensions/csrf.md) — token lifecycle
