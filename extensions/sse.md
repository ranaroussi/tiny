[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Server-Sent Events (SSE)

`tiny::sse()` provides a small toolkit for streaming events from the server to a browser using [Server-Sent Events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events). It handles header negotiation, session release, output buffering, and exposes three streaming strategies: arbitrary callbacks, cache-keyed push/pull, and PostgreSQL `LISTEN/NOTIFY`.

Under PHP-FPM, SSE works but ties up a worker for the lifetime of the stream. Under [Swoole](swoole.md) it's much cheaper — coroutines cost almost nothing.

## API

```php
$sse = tiny::sse();

TinySSE::start();              // emit SSE headers, close session, flush buffers
$sse->send(string $data);      // emit one "data: ..." frame
$sse->flush();                 // force-flush output

$sse->stream(callable $fn, int $sleep = 10);
$sse->streamKey(string $key, int $sleep = 1);
$sse->sendKey(string $key, mixed $data);
$sse->streamPostgres(string $channel, int $sleep = 1);
```

## Generic streaming with a callback

Most flexible mode. Provide a function that returns a payload (or `null` if nothing changed); the SSE loop sends each non-null return value as one frame.

```php
class Heartbeat extends TinyController
{
    public function get($request, $response)
    {
        tiny::sse()->stream(function () {
            return json_encode([
                'ts'   => time(),
                'load' => sys_getloadavg()[0],
            ]);
        }, sleep: 5);
    }
}
```

```js
// client
const es = new EventSource('/heartbeat');
es.onmessage = ({ data }) => console.log(JSON.parse(data));
```

The loop terminates automatically if the client disconnects (`connection_aborted()`).

## Cache-keyed streaming

When the *producer* and *consumer* are different processes (e.g. a background worker fills a queue, the controller streams to the browser), pair `sendKey()` with `streamKey()`. Both back onto `tiny::cache()`.

```php
// producer (in a job, scheduler, or another controller)
tiny::sse()->sendKey('user:42:updates', json_encode([
    'status' => 'processed',
    'order'  => 1234,
]));

// consumer (browser-facing controller)
class UserUpdates extends TinyController
{
    public function get($request, $response)
    {
        tiny::sse()->streamKey("user:{$request->path->section}:updates", sleep: 1);
    }
}
```

The consumer reads, sends, then deletes the cache entry — so each message is delivered exactly once.

Send `"[DONE]"` as the cache value to terminate the stream gracefully.

## PostgreSQL `LISTEN/NOTIFY`

For PostgreSQL-backed apps, you can stream straight from `pg_notify`:

```sql
CREATE OR REPLACE FUNCTION notify_user_updates() RETURNS trigger AS $$
BEGIN
    PERFORM pg_notify('user_updates', row_to_json(NEW)::text);
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER user_updates_trigger
    AFTER INSERT ON users
    FOR EACH ROW
    EXECUTE PROCEDURE notify_user_updates();
```

```php
class UserUpdates extends TinyController
{
    public function get($request, $response)
    {
        tiny::sse()->streamPostgres('user_updates', sleep: 1);
    }
}
```

Each `pg_notify` payload is sent to the connected browser as a JSON-encoded message.

## Client-side handling

```html
<script>
const es = new EventSource('/sse/dashboard');

es.onmessage = ({ data }) => {
    if (data === '[DONE]') {
        es.close();
        return;
    }
    const payload = JSON.parse(data);
    updateUI(payload);
};

es.onerror = (err) => {
    console.warn('SSE disconnected, browser will retry');
};
</script>
```

EventSource auto-reconnects on network errors with an exponential backoff — you usually don't need a `setTimeout` loop.

### Custom event names

`send()` emits an unnamed (default) event. To use named events, write directly:

```php
echo "event: progress\n";
echo "data: " . json_encode($payload) . "\n\n";
tiny::sse()->flush();
```

```js
es.addEventListener('progress', e => updateProgress(JSON.parse(e.data)));
```

## Behind the scenes

`TinySSE::start()` does the heavy lifting:

- Closes the session (`session_write_close`) so other requests aren't blocked
- Sets `Content-Type: text/event-stream`, `Cache-Control: no-store`, `X-Accel-Buffering: no`
- Disables gzip and time limits
- Forces output to flush on every write

You usually don't call it directly — `stream()`, `streamKey()`, and `streamPostgres()` invoke it for you.

## Best practices

1. **Use Swoole or FrankenPHP for SSE in production.** PHP-FPM workers are precious; long-lived SSE connections starve the pool.
2. **Send `[DONE]` to terminate** gracefully — clients can close the connection without waiting for a network error.
3. **Keep payloads small and ASCII-safe.** SSE is line-oriented; embedded newlines in `data:` need careful escaping.
4. **Set `X-Accel-Buffering: no`** at the proxy if you put nginx in front. The extension already emits this header, but nginx config sometimes overrides it.
5. **Validate authorization once at start.** The SSE handler runs in a loop — re-checking auth every iteration is rarely worth the cost.
