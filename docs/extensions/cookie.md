[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Cookies

The cookie extension wraps `setcookie()` with structured values, sensible defaults, and a small chainable API. Each named cookie is its own object; reading and writing are explicit, and nothing is sent to the browser until you call `save()`.

## Configuration (read from `$_SERVER`)

| Variable | Default | Purpose |
|---|---|---|
| `COOKIE_TTL` | `0` (session-only) | Default lifetime in seconds |
| `COOKIE_DOMAIN` | `HTTP_HOST` | Default `Domain=` attribute |
| `COOKIE_PATH` | `tiny::config()->url_path` | Default `Path=` attribute |

Set these in your `.env` to apply consistent defaults across cookies.

## API

```php
$cookie = tiny::cookie('preferences');     // load existing or create new

$cookie->exists;                            // bool: was it sent in the request?
$cookie->read();                            // entire data array
$cookie->read('theme');                     // single key
$cookie->write('theme', 'dark');            // set one key
$cookie->write(['theme' => 'dark', …]);     // replace all data
$cookie->save();                            // persist to the browser
$cookie->save($expiry);                     // override TTL on save
$cookie->destroy();                         // delete from the browser
```

Cookies store arrays (PHP-serialized under the hood), so a single named cookie can hold a small structured record.

## Reading a cookie

`tiny::cookie('name')` parses the incoming cookie into `->data` immediately. If it doesn't exist, `->data` is `[]` and `->exists` is `false`.

```php
$prefs = tiny::cookie('preferences');
$theme = $prefs->read('theme') ?? 'light';
```

## Writing & saving

```php
$prefs = tiny::cookie('preferences');
$prefs->write('theme', 'dark');
$prefs->write('locale', 'en-US');
$prefs->save();   // sends Set-Cookie header
```

Or replace the whole record at once:

```php
tiny::cookie('preferences')
    ->write(['theme' => 'dark', 'locale' => 'en-US'])
    ->save();
```

### Custom expiry

By default cookies expire after `COOKIE_TTL` seconds (or at the end of the session if unset). Override per-cookie:

```php
tiny::cookie('remember-me')
    ->write('token', $token)
    ->save(time() + 30 * 86400);   // 30 days
```

## Deleting a cookie

```php
tiny::cookie('remember-me')->destroy();
```

This sets the cookie with an expired timestamp and also clears `$_COOKIE` for the current request.

## Patterns

### "Remember me" token

```php
tiny::cookie('auth')
    ->write([
        'user_id' => $user->id,
        'token'   => $hashedRememberToken,
    ])
    ->save(time() + 30 * 86400);
```

### User preferences

```php
$prefs = tiny::cookie('preferences');

if ($_POST['theme'] ?? null) {
    $prefs->write('theme', $_POST['theme'])->save(time() + 365 * 86400);
}

$theme = $prefs->read('theme') ?? 'light';
```

### Reading nested data

Because the cookie body is an array, you can store small structured records:

```php
$cart = tiny::cookie('cart')->read();
$itemCount = count($cart['items'] ?? []);
```

## Security notes

The extension intentionally exposes the standard PHP cookie surface (`setcookie()`), so anything you can do there you can do here. A few practical recommendations:

- **Don't put secrets in cookies.** Cookies are visible to the user; for sensitive payloads use sessions (server-side) and only send an opaque session ID.
- **Set `Secure` cookies under HTTPS.** Add it to the server config or use the `session.cookie_secure` setting; the extension respects standard PHP cookie globals.
- **Sign tokens you do store.** A "remember me" cookie should contain `[user_id, hmac(user_id || rotation_secret)]` so a tampered value is rejected on the next request.
- **Keep cookies small.** They're sent on every request to your domain. The bigger they get, the slower every page load.
- **Encrypt with `tiny::cypher()`** if you must store anything user-private but want server-side verifiability:

```php
$payload = tiny::cypher()->encrypt(json_encode($data));
tiny::cookie('session')->write('p', $payload)->save();
```
