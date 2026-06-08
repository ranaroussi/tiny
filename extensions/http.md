[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# HTTP client

`TinyHTTP` is a thin cURL wrapper for outbound HTTP. It returns a normalized response object with `status_code`, `headers`, `body`, parsed `json`, and `success`/`error` fields. Method shortcuts cover GET/POST/PUT/PATCH/DELETE, plus convenience `*JSON` variants that auto-encode the body.

Access via `tiny::http()` (singleton) or call `TinyHTTP::*` statically — both work.

## API

```php
tiny::http()->get($url, array $options = []);
tiny::http()->post($url, array $options = []);
tiny::http()->put($url, array $options = []);
tiny::http()->patch($url, array $options = []);
tiny::http()->delete($url, array $options = []);
tiny::http()->request($method, $url, array $options = []);

tiny::http()->postJSON($url, $data, array $options = []);
tiny::http()->putJSON($url, $data, array $options = []);
tiny::http()->patchJSON($url, $data, array $options = []);
tiny::http()->deleteJSON($url, $data, array $options = []);

TinyHTTP::setDefaultHeaders(array $headers);   // applies to every subsequent request
TinyHTTP::clearDefaultHeaders();
TinyHTTP::getRedirectTarget($url);             // resolve final URL after redirects
```

## Options array

The `$options` map (passed to every method) supports:

| Key | Type | Purpose |
|---|---|---|
| `headers` | `array` | Associative `Header-Name => value` |
| `query` | `array` | Appended as `?k=v&…` |
| `body` | `string` / `array` | Raw or form-encoded body |
| `json` | `array` / `object` / `string` | JSON-encoded body + `Content-Type: application/json` |
| `timeout` | `int` | Seconds (cURL `CURLOPT_TIMEOUT`) |
| `ssl` | `array` | `verify`, `cert`, `key`, `keypass` |
| `finalUrl` | `bool` | Include resolved redirect URL on the response (default `true`) |

## Response object

Every method returns a `stdClass`:

```php
$res = tiny::http()->get('https://example.com/api/me');

$res->success;       // bool
$res->status_code;   // 200
$res->headers;       // ['Content-Type' => 'application/json', …]
$res->body;          // raw response body string
$res->json;          // json_decode($res->body) — null if not JSON
$res->url;           // effective URL after redirects
$res->error;         // cURL error string, set only if $res->success is false
```

## Examples

### Simple GET with query params

```php
$res = tiny::http()->get('https://api.example.com/search', [
    'query' => ['q' => 'tiny php', 'page' => 1],
]);

if ($res->success) {
    foreach ($res->json->results as $hit) {
        echo $hit->title;
    }
}
```

### POST JSON

```php
$res = tiny::http()->postJSON('https://api.example.com/users', [
    'name'  => 'Ada',
    'email' => 'ada@example.com',
], [
    'headers' => ['Authorization' => 'Bearer ' . $token],
]);

if (!$res->success || $res->status_code !== 201) {
    tiny::log("create user failed: $res->status_code $res->body");
}
```

### Form-encoded POST

```php
$res = tiny::http()->post('https://api.example.com/webhook', [
    'body' => http_build_query(['event' => 'ping']),
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
]);
```

### File upload via cURL file

```php
$res = tiny::http()->post('https://api.example.com/upload', [
    'body' => [
        'file' => new CURLFile('/tmp/report.pdf'),
    ],
]);
```

(When `body` is an array, cURL sends it as `multipart/form-data` automatically.)

### Custom timeout & SSL

```php
$res = tiny::http()->get('https://internal.example.com/data', [
    'timeout' => 5,
    'ssl' => [
        'verify' => true,
        'cert'   => '/etc/ssl/clients/me.crt',
        'key'    => '/etc/ssl/clients/me.key',
    ],
]);
```

### Default headers

If most of your outbound calls share a token, set it once:

```php
TinyHTTP::setDefaultHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'User-Agent'    => 'my-app/1.0',
]);
```

Default headers are merged into every subsequent request; per-call `headers` override.

### Resolving redirect targets

To check where a short URL eventually lands without downloading the body:

```php
$final = TinyHTTP::getRedirectTarget('https://t.co/abc123');
```

## Best practices

1. **Always check `$res->success`.** A non-2xx response still has `success === true` — it just means the request completed. Branch on `status_code` separately.
2. **Set a `timeout`.** The default is generous; in app code, cap it to a few seconds.
3. **Don't log full response bodies.** They may contain secrets returned by upstream APIs.
4. **Use the `*JSON` helpers** when posting JSON — they set the body, encode the data, and add the `Content-Type` header in one call.
5. **Verify SSL in production.** Don't disable cert verification to "make it work" without understanding why.
