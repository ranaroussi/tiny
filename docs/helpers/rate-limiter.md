# Rate Limiter Helper

The Rate Limiter helper provides a way to control request rates and prevent abuse in your application.

## Configuration

Configure in your `.env` file:

```env
RATE_LIMIT_DRIVER=redis    # or memcached
RATE_LIMIT_PREFIX=ratelimit
```

## Basic Usage

### Simple Rate Limiting

```php
// Check if request is allowed
if (!tiny::rateLimit()->allow('api', 60, 100)) {
    // Too many requests - 100 requests per 60 seconds exceeded
    throw new TooManyRequestsException();
}

// With custom identifier
$userId = tiny::user()->id;
if (!tiny::rateLimit()->allow('api:' . $userId, 60, 100)) {
    // Rate limit exceeded for this user
}
```

### Custom Rate Limits

```php
// Create a rate limiter
tiny::rateLimit()->create('uploads', [
    'max_requests' => 10,
    'period' => 3600,  // 1 hour
    'by' => 'user_id'
]);

// Check the limit
if (!tiny::rateLimit()->check('uploads')) {
    // Upload limit exceeded
}
```

## Advanced Features

### Multiple Windows

```php
// Check multiple time windows
$limits = [
    ['period' => 60, 'limit' => 30],    // 30 per minute
    ['period' => 3600, 'limit' => 500], // 500 per hour
    ['period' => 86400, 'limit' => 2000] // 2000 per day
];

if (!tiny::rateLimit()->allowMany('api', $limits)) {
    // One of the limits exceeded
}
```

### Rate Information

```php
// Get remaining requests
$remaining = tiny::rateLimit()->remaining('api');

// Get reset time
$reset = tiny::rateLimit()->reset('api');

// Get full rate info
$info = tiny::rateLimit()->info('api');
echo "Remaining: {$info->remaining}, Reset: {$info->reset}";
```

## Best Practices

1. **Rate Design**
   - Set appropriate limits
   - Consider user types
   - Use multiple windows
   - Plan for bursts

2. **Error Handling**
   - Return 429 status
   - Include reset headers
   - Provide clear messages
   - Log excessive attempts

3. **Performance**
   - Use efficient storage
   - Clean expired records
   - Monitor memory usage
   - Optimize key design
