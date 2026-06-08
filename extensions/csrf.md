[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# CSRF protection

The CSRF extension generates and validates session-bound tokens for non-idempotent requests. Tokens are 32 bytes of cryptographic randomness, stored in `$_SESSION`, and consumed on first valid use (single-use by default).

It pairs naturally with `$request->isValidCSRF()` and `$response->hasCSRFError()` on the request/response objects — most of the time you'll never call `tiny::csrf()` directly.

## API

```php
$csrf = tiny::csrf();

$csrf->generate();              // create + store + return a new token
$csrf->isValid($token = null, $remove = true);  // validate; consumes by default
$csrf->input($echo = true);     // render a hidden <input> for forms
$csrf->showError($id = 'CSRF-VALIDATION-FAILED', $nextPage = false);
$csrf->getTokenName();          // 'csrf_token'
```

`isValid()` reads the submitted token in this order: explicit argument → `$_POST['csrf_token']` → `$_GET['csrf_token']`.

## In a form

```php
<form method="POST" action="/users">
    <?php tiny::csrf()->input(); ?>
    <input name="name" required>
    <button type="submit">Create</button>
</form>
```

`input()` calls `generate()` lazily — if no token exists for this request, one is created. It emits:

```html
<input type="hidden" name="csrf_token" value="…">
```

## In the controller

The shortest valid flow:

```php
class Users extends TinyController
{
    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            $response->hasCSRFError(nextPage: true);
            return $response->redirect('/users/new');
        }

        // ... create user
    }
}
```

`$request->isValidCSRF()` is just a thin wrapper around `tiny::csrf()->isValid()` that also strips the token from the body.

`$response->hasCSRFError()` is the matching helper on the response object. It either sets `tiny::data()->CSRFError` (current page) or fires a flash toast for the next page load (`nextPage: true`).

## For HTMX / AJAX

HTMX form submissions naturally include the hidden input. For pure-JS clients, send the token in a header and read it from the rendered page:

```html
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
```

```js
const token = document.querySelector('meta[name=csrf-token]').content;
fetch('/api/widgets', {
    method: 'POST',
    headers: { 'X-CSRF-Token': token, 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Foo' }),
});
```

Then in the controller:

```php
$token = $request->headers['X-CSRF-Token'] ?? null;
if (!tiny::csrf()->isValid($token)) {
    return $response->sendJSON(['error' => 'Invalid CSRF token'], 403);
}
```

## Tokens are single-use

By default `isValid()` consumes the token. This is safer (replay-proof) but means SPAs need to fetch a fresh one between submissions. Pass `remove: false` to keep the token alive:

```php
tiny::csrf()->isValid($token, remove: false);
```

A typical pattern is to expose a `/api/csrf` endpoint that returns a freshly generated token after each form submit.

## Best practices

1. **Always use `$request->isValidCSRF()` for POST/PUT/PATCH/DELETE** that mutate state.
2. **Don't put the token in the URL.** Use POST body or `X-CSRF-Token` header.
3. **Combine with `SameSite=Lax` (or `Strict`) cookies** for defense in depth.
4. **Serve over HTTPS.** Tokens in cleartext on hostile networks defeat the point.
5. **Don't use CSRF tokens for authentication.** They prove "this request came from a form your server rendered," not "this user is who they say."
