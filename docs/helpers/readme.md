[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Helpers

Tiny PHP Framework comes preloaded with several helper functions and utilities to simplify common programming tasks. These helpers are designed to make your code more readable and maintainable.

## Commonly Used Helpers

1. [Stripe](stripe.md)
   - Payment processing
   - Subscription management
   - Webhook handling
   - Customer management

2. [Spaces (S3)](spaces.md)
   - File storage
   - CDN integration
   - Access control
   - File operations

3. [Rate Limiter](rate-limiter.md)
   - Request throttling
   - API rate limiting
   - Abuse prevention
   - Custom rules

4. [OAuth](oauth.md)
   - Social authentication
   - Provider management
   - Token handling
   - Profile mapping

5. [Geos](geos.md)
   - Location detection
   - Country/region handling
   - IP geolocation
   - Currency localization

6. [Utils](utils.md)
   - String manipulation
   - Array operations
   - Date formatting
   - Common utilities

## Adding Custom Helpers

Place your custom helpers in the `app/helpers/` directory:

```php
<?php
// app/helpers/my_helper.php

function format_currency($amount, $currency = 'USD'): string
{
    return money_format('%i', $amount) . ' ' . $currency;
}

// Register with tiny class (optional)
tiny::register('currency', function() {
    return new class {
        public function format($amount, $currency = 'USD') {
            return format_currency($amount, $currency);
        }
    };
});
```

## Helper Examples

### Stripe Helper

```php
// Process payment
$payment = tiny::stripe()->charge([
    'amount' => 2000, // $20.00
    'currency' => 'usd',
    'customer' => $customerId
]);

// Create subscription
$subscription = tiny::stripe()->subscribe($customerId, [
    'price' => 'price_H2ZlLQs9w0cp',
    'trial_period_days' => 14
]);
```

### Spaces Helper

```php
// Upload file
$url = tiny::spaces()->upload(
    'bucket-name',
    'path/to/file.pdf',
    $fileContent
);

// Generate signed URL
$signedUrl = tiny::spaces()->signedUrl(
    'bucket-name',
    'private/file.pdf',
    '+2 hours'
);
```

### Rate Limiter Helper

```php
// Check rate limit
if (!tiny::rateLimit()->allow('api', 60, 100)) {
    // Too many requests
    throw new TooManyRequestsException();
}

// Custom rate limit
tiny::rateLimit()->create('uploads', [
    'max_requests' => 10,
    'period' => 3600,
    'by' => 'user_id'
]);
```

### OAuth Helper

```php
// Configure provider
tiny::oauth()->configure('google', [
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret'
]);

// Get user profile
$profile = tiny::oauth()->getUserProfile('google');

// Handle callback
$token = tiny::oauth()->handleCallback('google');
```

### Geo Helper

```php
// Get user location
$location = tiny::geo()->getLocation();

// Check country
if (tiny::geo()->isCountry('US')) {
    // Show US-specific content
}

// Format currency by location
$price = tiny::geo()->formatCurrency(29.99);
```

### Utils Helper

```php
// String manipulation
$slug = tiny::utils()->slugify('Hello World!');
$excerpt = tiny::utils()->excerpt($longText, 150);

// Array operations
$flattened = tiny::utils()->arrayFlatten($nestedArray);
$filtered = tiny::utils()->arrayFilter($array, $callback);

// Date formatting
$formatted = tiny::utils()->formatDate('2024-03-15', 'human');
$timeAgo = tiny::utils()->timeAgo('2024-03-15 14:30:00');
```

## Best Practices

1. **Organization**
   - Group related helpers
   - Use descriptive names
   - Document functionality
   - Follow naming conventions

2. **Performance**
   - Cache expensive operations
   - Optimize frequently used helpers
   - Use lazy loading
   - Monitor resource usage

3. **Security**
   - Validate input
   - Sanitize output
   - Handle errors
   - Follow security best practices

4. **Maintenance**
   - Keep helpers focused
   - Update documentation
   - Test thoroughly
   - Version control changes
