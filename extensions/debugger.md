[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Debugger

The debugger gives you five tools for inspecting and logging state. They're gated by an IP whitelist so it's safe to leave instrumented code in production — non-whitelisted visitors see nothing.

All five live on the `tiny` facade itself (mixed in via the `TinyDebugger` trait):

```php
tiny::debug(...$vars);     // pretty-print to the response (HTML), with file:line trace
tiny::dump(...$vars);      // same as debug() but quieter (no trace block)
tiny::dd(...$vars);        // debug() then exit
tiny::ddump(...$vars);     // dump() then exit
tiny::log(...$vars);       // append to LOG_FILE; safe in production
```

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `DEBUG_WHITELIST` | `*` | Comma-separated IPs that may see `dd/dump/debug` output. `*` = everyone (set in dev only) |
| `LOG_FILE` | `sys_get_temp_dir()/tiny.log` | Destination for `tiny::log()` |

In production, set `DEBUG_WHITELIST` to your office IP (or unset it entirely):

```env
DEBUG_WHITELIST=203.0.113.4,198.51.100.10
LOG_FILE=/var/log/myapp/debug.log
```

When a request comes in from outside the whitelist, all four display methods become no-ops — `tiny::dd()` won't even exit. `tiny::log()` always writes, regardless of IP.

## Examples

### Inspect during a request

```php
class Users extends TinyController
{
    public function get($request, $response)
    {
        $users = tiny::model('user')->all();
        tiny::debug($users, $request->query);
        $response->render('users/index', ['users' => $users]);
    }
}
```

Visiting `/users` from your whitelisted IP shows the dump above the rendered page.

### Hard halt for "what is this thing?"

```php
$row = tiny::db()->getOne('orders', ['id' => $id]);
tiny::dd($row);   // dump and stop here
```

### Production logging

```php
try {
    chargeCard($order);
} catch (StripeException $e) {
    tiny::log('charge failed', $e->getMessage(), ['order_id' => $order->id]);
    $response->redirect('/checkout/failed');
}
```

`tiny::log()` accepts any mix of strings, arrays, and objects — it serializes the lot to JSON, prefixes a timestamp, and appends to `LOG_FILE`.

### Quiet inline dumps

```php
tiny::dump($computed);   // no trace block, just the value
```

Use `dump()` when you're checking a known variable in a tight loop; use `debug()` when you want the call-site info.

## What renders

`debug()` / `dump()` output is wrapped in an inline-styled block at the top of the response:

```
== tiny::debug ==                                        [/app/controllers/users.php:14]
─────────────────────────────────────────────────────────────────────────────────────────
array(3) {
  [0] => object(stdClass)#42 { ... }
  ...
}
```

So if a non-whitelisted user *does* somehow trip a call, the worst case is empty markup — never raw data.

## Patterns

### Conditional verbose logging

```php
if ($_SERVER['ENV'] !== 'prod') {
    tiny::log('debug context', compact('user', 'order'));
}
```

### Inspect a chained call

```php
$user = tiny::db()
    ->dd('select * from users where id = ?', [$id])   // dump the rendered SQL
    ->getOne('users', ['id' => $id]);
```

(Many extension classes have their own `->dd()` helpers — see [database.md](database.md) for `TinyDB::dd()`.)

### Stack traces in logs

`tiny::log()` doesn't include a stack trace by default. When you need one:

```php
tiny::log('something went wrong', (new \Exception)->getTraceAsString());
```

## Best practices

1. **Never enable `DEBUG_WHITELIST=*` in production.** It's a one-line credential exposure if you do.
2. **Prefer `tiny::log()` for ongoing diagnostics**; it's IP-agnostic and won't pollute the response.
3. **Don't `dd()` in middleware** unless you're actively debugging — it kills the request unconditionally for whitelisted IPs and is easy to forget about.
4. **Rotate `LOG_FILE`** with logrotate or your platform's equivalent. The framework doesn't manage rotation.
5. **Scrub secrets before logging.** Tokens, passwords, and PII don't belong in `tiny::log()` calls.
