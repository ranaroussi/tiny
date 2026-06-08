[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Cache

The cache extension wraps two engines behind a single interface: **APCu** (in-process, default) and **Memcached** (distributed). Both back the framework's own internal caching (config, router, schema) — so even if you never call `tiny::cache()` directly, you get it for free.

The instance is created lazily on first access and reused for the whole request.

## Choosing an engine

By default, Tiny uses APCu. Pass `'memcached'` to `tiny::cache()` to switch:

```php
$cache = tiny::cache('memcached');
```

Memcached host/port are read from `tiny::config()->memcached`, falling back to `localhost:11211`. Set them during bootstrap if you use Memcached.

## API

```php
$cache = tiny::cache();

// Read
$value = $cache->get('user_42');               // null if missing

// Write
$cache->set('user_42', $user, ttl: 3600);      // ttl seconds; 0 = no expiry

// Delete
$cache->delete('user_42');

// Remember (read-through)
$user = $cache->remember('user_42', 3600, fn () => tiny::db()->get('users', ['id' => 42]));

// Prefix operations
$keys = $cache->getByPrefix('user_');
$cache->deleteByPrefix('user_');
```

`getByPrefix()` and `deleteByPrefix()` rely on:
- **APCu** — `APCUIterator` (fast, accurate)
- **Memcached** — `getAllKeys()` (slow, optional in newer versions of the extension)

## APCu vs Memcached

| | **APCu** | **Memcached** |
|---|---|---|
| Topology | Single PHP process | Multiple servers |
| Speed | Fastest (in-memory) | Network round-trip |
| Shared across workers? | Yes (same PHP-FPM pool) | Yes (cluster) |
| Survives PHP restart? | No | Yes |
| Prefix iteration? | Always | Best-effort |

For a single-server app or a serverless deploy, APCu is almost always the right choice. Switch to Memcached only when you need cache shared across machines.

## Patterns

### Read-through with `remember()`

```php
$products = tiny::cache()->remember('products:featured', 600, function () {
    return tiny::db()->getAll('products', ['featured' => true]);
});
```

### Group invalidation

Namespace cache keys by entity, then nuke the whole namespace when it changes:

```php
function userKey(int $id, string $suffix): string {
    return "user:$id:$suffix";
}

tiny::cache()->set(userKey($id, 'profile'), $profile, 3600);
tiny::cache()->set(userKey($id, 'orders'),  $orders,  600);

// On user update:
tiny::cache()->deleteByPrefix("user:$id:");
```

### Don't cache request-scoped data

For per-request memoization use `tiny::data()` instead — it's automatically cleared between requests under Swoole/FrankenPHP, and it doesn't compete for cache slots.

## Notes

- Cache is **not** a queue. Don't use it for inter-request messaging beyond what SSE's `streamKey()` does.
- APCu is per-process under CLI; if you call `tiny::cache()` from a CLI script, you get a private cache that doesn't share state with FPM workers.
- TTL is best-effort. Memcached evicts under memory pressure regardless of TTL.
