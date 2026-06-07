[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Controllers

Controllers handle a single URL. They extend `TinyController` and live in `app/controllers/`. Each public method named after an HTTP verb (`get`, `post`, `put`, `patch`, `delete`, `options`) handles the matching request.

## Basic structure

```php
<?php
class Users extends TinyController
{
    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = tiny::model('user');
    }

    public function get($request, $response)
    {
        $users = $this->model->all();
        $response->render('users/index', ['users' => $users]);
    }
}
```

The constructor of `TinyController` initialises `$this->method` (the request verb) and `$this->allowedMethods` (the verbs the framework will accept). Always call `parent::__construct()` if you define your own.

## HTTP methods

```php
class Users extends TinyController
{
    public function get($request, $response)    { /* GET    /users */ }
    public function post($request, $response)   { /* POST   /users */ }
    public function patch($request, $response)  { /* PATCH  /users */ }
    public function put($request, $response)    { /* PUT    /users */ }
    public function delete($request, $response) { /* DELETE /users */ }
    // options() is handled by TinyController by default (HTTP 204 + CORS header)
}
```

## The request object

`$request` is a `TinyRequest`. The public surface is:

```php
$request->method;             // "GET" | "POST" | …
$request->headers;            // associative array from getallheaders()
$request->user;               // object set by middleware / tiny::user()
$request->htmx;               // bool, true when HX-Request header present
$request->query;              // $_GET
$request->path->controller;   // first URL segment
$request->path->section;      // second URL segment
$request->path->slug;         // remaining URL segment(s)
$request->path->full;         // "/users/profile/edit"
$request->csrf_token;         // populated after body() runs

$request->params();           // merged $_REQUEST (case-insensitive lookups)
$request->params('email');    // single key, optional fallback as 2nd arg
$request->body();             // request body as object (JSON or form-encoded)
$request->body(true);         // same, as associative array
$request->json();             // raw php://input string
$request->isValidCSRF();      // bool, validates token in body
$request->isAsync();          // bool: Swoole | X-Requested-With | ?async=true
```

> See [Request & Response](request-response.md) for the exhaustive reference.

## The response object

`$response` is a `TinyResponse`:

```php
// Render a view (terminates by default)
$response->render('users/index');
$response->render('users/index', ['users' => $users]);
$response->render('users/index', ['users' => $users], false); // don't exit

// Send plain text / JSON-encoded payload
$response->send($payload);                  // 200 OK
$response->send($payload, 201);
$response->sendJSON(['ok' => true]);        // sets content-type
$response->sendJSON($data, 422);

// Send a file's contents as the response body
$response->sendFile('/path/to/report.pdf');

// Redirect (HTMX-aware; uses HX-Redirect when applicable)
$response->redirect('/login');
$response->redirect('/login', 'htmx');      // force HX-Redirect

// Flush partial output for streamed responses
$response->flush('partial content');

// CSRF error display
$response->hasCSRFError();                  // immediately
$response->hasCSRFError('MY-CODE', true);   // on next page load (via flash)
```

`$response->render()` automatically emits `HX-Push-Url` matching the current `permalink`, so HTMX-driven partial renders correctly update the browser URL.

## Sharing data with views

There are three equivalent ways to pass data into a view:

```php
// 1) Inline via render() — preferred for view-specific data
$response->render('users/index', ['users' => $users, 'total' => 42]);

// 2) Set on the global data bag
tiny::data()->users = $users;
tiny::set('total', 42);

// 3) Read inside the view
tiny::data()->users;
tiny::get('total');
```

`tiny::data()` is a plain `stdClass` shared across the request. Use it for cross-cutting things (current user, site config, feature flags) and prefer the `render($file, $params)` form for per-view data.

## Flash messages

```php
public function post($request, $response)
{
    if ($this->model->create($request->body(true))) {
        tiny::flash('toast')->set(['level' => 'success', 'message' => 'User created']);
        return $response->redirect('/users');
    }
    tiny::flash('toast')->set(['level' => 'error', 'message' => 'Failed to create user']);
    $response->redirect('/users/new');
}
```

In the next request:

```php
$toast = tiny::flash('toast')->get(); // consumed; pass true to peek
```

See [`flash`](../extensions/flash.md) for details.

## CSRF

Always validate CSRF on state-changing verbs:

```php
public function post($request, $response)
{
    if (!$request->isValidCSRF()) {
        return $response->hasCSRFError();
    }
    // … safe to mutate state
}
```

Render a token field in your form view with:

```php
<form method="post">
    <?php tiny::csrf()->input(); ?>
    <!-- … -->
</form>
```

## Organising controllers

- **Subdirectories** group related routes: `app/controllers/account/billing.php` → `/account/billing`.
- **`index.php`** acts as the default for a directory: `account/index.php` → `/account`.
- **Hyphenated leaves** are equivalent to a `section/slug` URL: `users/profile-edit.php` → `/users/profile/edit`.

## Best practices

1. **Keep controllers thin.** Move business logic into models or helpers.
2. **Validate CSRF on every mutating verb.**
3. **Prefer `$response->render($view, $params)`** over scattering `tiny::data()` assignments.
4. **Use a custom `404.php` controller** for branded error pages.
5. **Use middleware** for auth, rate-limiting, version pinning — not boilerplate in every controller.
