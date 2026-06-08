[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# HTMX & React

Tiny has first-class awareness of two modern front-end patterns: **HTMX** (server-driven partial updates) and **React** (hybrid SSR + SPA). Both work out of the box; pick whichever fits the page.

## HTMX integration

[HTMX](https://htmx.org) lets you swap fragments of HTML over the wire without a JS framework. Tiny detects HTMX requests, exposes a flag, and emits the right response headers automatically.

### Detecting HTMX requests

When a browser sends an `HX-Request: true` header (HTMX does this on every AJAX call), the router records it:

```php
if ($request->htmx) {
    // partial render
} else {
    // full page
}
```

The same flag is also on the router (`tiny::router()->htmx`), useful from middleware:

```php
if (tiny::router()->htmx) {
    tiny::header('HX-Trigger: user-logged-in');
}
```

### Auto `HX-Push-Url`

`$response->render()` automatically emits an `HX-Push-Url` header matching the current permalink. This means HTMX partial swaps **keep the address bar in sync** with no extra work:

```php
class Users extends TinyController
{
    public function get($request, $response)
    {
        $response->render('users/index');   // emits HX-Push-Url: /users
    }
}
```

### HTMX redirects

`tiny::redirect()` and `$response->redirect()` detect HTMX requests and emit `HX-Redirect` instead of a 302 — important because HTMX's default behaviour is to swap the response body, not follow redirects.

```php
$response->redirect('/login');             // auto-degrades to HX-Redirect if HTMX
$response->redirect('/login', 'htmx');     // force HX-Redirect regardless
```

### Partial rendering pattern

A common shape: return just the changed fragment on HTMX requests, full HTML on direct navigation.

```php
class TodoList extends TinyController
{
    public function post($request, $response)
    {
        $todo = tiny::model('todo')->create($request->body(true));

        if ($request->htmx) {
            // just the new <li>
            return $response->render('todo/_item', ['todo' => $todo]);
        }

        // full page reload after non-HTMX submit
        $response->redirect('/todos');
    }
}
```

```php
<!-- app/views/todo/index.php -->
<ul id="todo-list" hx-get="/todos" hx-trigger="load">
    <!-- HTMX populates this -->
</ul>

<form hx-post="/todos" hx-target="#todo-list" hx-swap="beforeend">
    <?php tiny::csrf()->input(); ?>
    <input name="title" required>
    <button>Add</button>
</form>
```

### Triggering client-side events

Use the `HX-Trigger` response header to fire client-side events:

```php
tiny::header('HX-Trigger: refresh-sidebar');
$response->render('user/profile');
```

```html
<aside hx-get="/sidebar" hx-trigger="refresh-sidebar from:body">…</aside>
```

## React integration

For pages where React is a better fit (heavy interactivity, shared state, complex forms), `tiny::renderReact()` gives you SSR-on-first-load and SPA-after-that from a single controller.

### Basic use

```php
class Dashboard extends TinyController
{
    public function get($request, $response)
    {
        $response->renderReact('DashboardPage', [
            'user'  => tiny::user(),
            'stats' => tiny::model('stats')->forUser(tiny::user()->id),
        ], meta: [
            'title' => 'Dashboard',
        ], template: 'react-shell');
    }
}
```

What happens:

| Request | Response |
|---|---|
| Direct browser navigation (no `X-SPA-Request`, no XHR) | Renders the `react-shell` view, which receives `$component`, `$props`, `$meta` and is responsible for emitting the HTML shell + hydration script |
| HTMX / XHR / SPA request (`X-SPA-Request: true`) | JSON response: `{"component": "DashboardPage", "props": {...}}` |

### A typical shell template

```php
<!-- app/views/react-shell.php -->
<!doctype html>
<html>
<head>
    <title><?= htmlspecialchars($meta['title'] ?? 'App') ?></title>
    <script>
        window.__INITIAL_DATA__ = <?= json_encode([
            'component' => $component,
            'props'     => $props,
        ]) ?>;
    </script>
    <script src="<?= tiny::getStaticURL('js/app.js') ?>" defer></script>
</head>
<body>
    <div id="root"></div>
</body>
</html>
```

The client-side bootstrap reads `window.__INITIAL_DATA__`, mounts the named component, and for subsequent navigation hits the same controller with `X-SPA-Request: true` (or `XMLHttpRequest`) — getting JSON back and updating the page client-side.

### Setting the SPA header from the client

```js
fetch('/dashboard', {
    headers: { 'X-SPA-Request': 'true' },
})
    .then(r => r.json())
    .then(({ component, props }) => mount(component, props));
```

### When to use React vs HTMX

| Use HTMX when | Use React when |
|---|---|
| Most logic lives on the server | UI has heavy client state |
| You want minimal JS | You need offline / optimistic UI |
| Pages are mostly server-rendered | Pages are app-like |
| Forms, lists, filters, search | Dashboards, editors, complex flows |

Tiny lets you mix both — HTMX for one route, React for another, server-rendered PHP for everything else.

## See also

- [Request & Response](request-response.md) — `renderReact()` signature
- [Components](../extensions/components.md) — server-side reusable fragments
- [Routing](routing.md) — `request->htmx` lives on the router
