# Cache Extension

The Cache extension provides a simple interface for storing and retrieving data from memory caches like APCu or Memcached.

## Configuration

Configure caching in your `.env` file:

```env
CACHE_ENGINE=apcu     # or memcached
MEMCACHED_HOST=localhost
MEMCACHED_PORT=11211
```

## Basic Usage

```php
// Store a value
tiny::cache()->set('user_123', $userData, 3600); // Cache for 1 hour

// Retrieve a value
$userData = tiny::cache()->get('user_123');

// Delete a value
tiny::cache()->delete('user_123');

// Check if key exists
if (tiny::cache()->has('user_123')) {
    // Key exists
}
```

## Advanced Features

### Remember Pattern

Cache a value if it doesn't exist:

```php
$value = tiny::cache()->remember('expensive_query', 3600, function() {
    // This will only run if the cache key doesn't exist
    return tiny::db()->get('large_table', ['status' => 'active']);
});
```

### Prefix Operations

Work with groups of cached items:

```php
// Get all keys matching a prefix
$keys = tiny::cache()->getByPrefix('user_');

// Delete all items matching a prefix
tiny::cache()->deleteByPrefix('user_');
```

### Multiple Items

Store or retrieve multiple items at once:

```php
// Store multiple
tiny::cache()->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2'
], 3600);

// Get multiple
$values = tiny::cache()->getMultiple(['key1', 'key2']);
```

## APCu vs Memcached

### APCu
- Single server only
- Faster (in-memory)
- No network overhead
- Process-bound

### Memcached
- Distributed caching
- Multiple server support
- Network-based
- Shared across processes

## Best Practices

1. **Key Naming**
   - Use consistent naming conventions
   - Include version/context in keys
   - Keep keys reasonably short

2. **TTL (Time To Live)**
   - Set appropriate expiration times
   - Consider data volatility
   - Use infinite TTL sparingly

3. **Error Handling**
   - Always have a fallback
   - Handle cache misses gracefully
   - Monitor cache usage

4. **Cache Invalidation**
   - Clear related caches when data changes
   - Use prefixes for group invalidation
   - Consider cache warming strategies
