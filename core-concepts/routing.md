[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Routing

Tiny has no route table. The URL path is mapped directly against `app/controllers/` using a small, deterministic resolution algorithm.

## The model: controller / section / slug

Every URL is split into up to three segments:

```
/users/profile/edit
  │       │      └── slug
  │       └──────── section
  └──────────────── controller
```

Anything beyond three segments is folded into `slug`. The framework only ever resolves to **one** controller file; it does not chain.

## Resolution order

Given a URL `/users/profile/edit`, the router tries the following files in `app/controllers/`, in order, and uses the first one that exists:

1. `users/profile/edit.php`
2. `users/profile/<slashes-in-slug→hyphens>.php` *(only differs from #1 when the URL has more than three segments)*
3. `users/profile-edit.php`
4. `users/profile-<slug>.php`
5. `users/profile.php`
6. `users/index.php`
7. `users.php`
8. `404.php` *(custom; if missing, the framework returns a built-in error)*

This means you can organise controllers in whichever style fits the feature — flat, nested directories, or hyphenated leaves — without touching configuration.

### Example layouts

```
app/controllers/
├── home.php              -> /
├── about.php             -> /about
├── users.php             -> /users, /users/anything (if no override)
├── users/
│   ├── index.php         -> /users
│   ├── profile.php       -> /users/profile, /users/profile/<slug>
│   └── profile-edit.php  -> /users/profile/edit
├── blog/
│   └── post.php          -> /blog/post, /blog/post/<slug>
└── 404.php               -> custom not-found
```

## The homepage

The URL `/` is dispatched to the controller named by `TINY_HOMEPAGE` (default: `home`), i.e. `app/controllers/home.php`.

## Inside a controller

```php
<?php
class Blog extends TinyController
{
    public function get($request, $response)
    {
        $controller = $request->path->controller; // "blog"
        $section    = $request->path->section;    // segment 2 (or "")
        $slug       = $request->path->slug;       // segment 3+ (or "")
        $full       = $request->path->full;       // "/blog/2024/my-post"

        $page   = $request->query['page'] ?? 1;    // ?page=2
        $params = $request->params();              // merged GET + POST
        $body   = $request->body(true);            // raw POST/PUT JSON or form

        $isHtmx = $request->htmx;                  // bool: HX-Request header present

        $response->render('blog/list', ['posts' => [/* … */]]);
    }
}
```

> **What does *not* exist**: there is no automatic mapping of URL segments to named parameters (no `:year`, no `:id`). You read `path->section` and `path->slug` and decide what they mean.

## HTTP methods

`TinyController` dispatches to methods named after the HTTP verb in lowercase: `get`, `post`, `put`, `patch`, `delete`, `options`. Unimplemented methods return a placeholder; override only what you need.

```php
class Article extends TinyController
{
    public function get($request, $response)    { /* … */ }
    public function post($request, $response)   { /* … */ }
    public function patch($request, $response)  { /* … */ }
    public function delete($request, $response) { /* … */ }
}
```

The default `options()` responds with HTTP 204 and an `Access-Control-Allow-Methods` header listing the supported verbs.

## Custom 404

Place a controller at `app/controllers/404.php`. The framework will dispatch to it automatically when no other controller matches. It receives the same request / response objects, and `tiny::data()->error` is pre-populated with a short reason string.

```php
<?php
class NotFound extends TinyController
{
    public function get($request, $response)
    {
        http_response_code(404);
        $response->render('errors/404');
    }
}
```

## Permalinks

`tiny::router()->permalink` is the full canonical URL of the current request (scheme + host + URI). Use it for canonical tags, OG metadata, and HTMX redirects:

```php
$canonical = tiny::router()->permalink;
```

## Middleware

Middleware runs **before** controller dispatch. Register middleware in `app/middleware.php` — see [Middleware](middleware.md) for the full contract.

## Best practices

1. **Let the filesystem be the route table.** Resist building a routing DSL on top.
2. **Use sections meaningfully.** `posts/<slug>` reads cleaner than three levels of nested directories.
3. **Put a 404 controller in.** The built-in fallback is intentionally bare.
4. **Validate CSRF for state-changing verbs** (`post`, `put`, `patch`, `delete`) — see [`csrf`](../extensions/csrf.md).
5. **Reach for redirects via `tiny::redirect()` or `$response->redirect()`**, both of which auto-degrade to HTMX `HX-Redirect` when appropriate.
