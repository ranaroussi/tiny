[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Cookie Extension

The Cookie extension provides a secure and convenient way to manage browser cookies in your Tiny PHP application.

## Basic Usage

### Setting Cookies

```php
// Simple cookie
tiny::cookie('user_preference')->set('dark_mode');

// With expiration (in seconds)
tiny::cookie('remember_me')->set('true', 86400); // 24 hours

// With all options
tiny::cookie('session')->set('abc123', [
    'expires' => time() + 3600,
    'path' => '/',
    'domain' => '.example.com',
    'secure' => true,
    'httponly' => true
]);
```

### Reading Cookies

```php
// Get cookie value
$value = tiny::cookie('user_preference')->get();

// With default value
$theme = tiny::cookie('theme')->get('light');

// Check if cookie exists
if (tiny::cookie('remember_me')->exists()) {
    // Cookie exists
}
```

### Removing Cookies

```php
// Delete a cookie
tiny::cookie('user_preference')->destroy();

// Delete with specific path/domain
tiny::cookie('session')
    ->setPath('/admin')
    ->setDomain('.example.com')
    ->destroy();
```

## Advanced Features

### Encrypted Cookies

```php
// Set encrypted cookie
tiny::cookie('sensitive_data')
    ->encrypt()
    ->set($userData);

// Get decrypted value
$userData = tiny::cookie('sensitive_data')
    ->encrypt()
    ->get();
```

### Array Data

```php
// Store array data
tiny::cookie('cart')->set([
    'items' => ['product1', 'product2'],
    'total' => 29.99
]);

// Retrieve array data
$cart = tiny::cookie('cart')->get();
echo $cart['total'];
```

### Cookie Options

```php
// Set cookie options
$cookie = tiny::cookie('session')
    ->setPath('/admin')
    ->setDomain('.example.com')
    ->setSecure(true)
    ->setHttpOnly(true)
    ->setSameSite('Strict');

$cookie->set('value');
```

### Cookie Prefixes

```php
// Use cookie prefixes for additional security
tiny::cookie('__Secure-token')->set($token);
tiny::cookie('__Host-session')->set($sessionId);
```

## Configuration

Configure default cookie settings in your `.env` file:

```env
COOKIE_DOMAIN=example.com
COOKIE_PATH=/
COOKIE_TTL=86400
COOKIE_SECURE=true
COOKIE_HTTPONLY=true
COOKIE_SAMESITE=Lax
```

## Best Practices

1. **Security**
   - Use HTTPS with secure flag
   - Enable HttpOnly when possible
   - Set appropriate SameSite
   - Encrypt sensitive data
   - Use cookie prefixes

2. **Domain & Path**
   - Set specific paths
   - Use appropriate domains
   - Consider subdomains
   - Restrict cookie scope

3. **Data Management**
   - Store minimal data
   - Use appropriate TTLs
   - Clean up expired cookies
   - Handle missing cookies

4. **Compliance**
   - Follow privacy laws
   - Get user consent
   - Document cookie usage
   - Provide cookie policy

## Examples

### Authentication Cookie

```php
// Set authentication cookie
tiny::cookie('auth')
    ->encrypt()
    ->setHttpOnly(true)
    ->setSecure(true)
    ->setSameSite('Strict')
    ->set([
        'user_id' => $userId,
        'token' => $token,
        'expires' => time() + 86400
    ]);

// Check authentication
$auth = tiny::cookie('auth')->encrypt()->get();
if ($auth && time() < $auth['expires']) {
    // User is authenticated
}
```

### Remember Me Functionality

```php
// Set remember me cookie
if ($request->remember_me) {
    tiny::cookie('remember_me')
        ->encrypt()
        ->setHttpOnly(true)
        ->setSecure(true)
        ->set([
            'selector' => $selector,
            'token' => $hashedToken
        ], 2592000); // 30 days
}
```

### User Preferences

```php
// Save user preferences
tiny::cookie('preferences')->set([
    'theme' => 'dark',
    'language' => 'en',
    'notifications' => true
]);

// Get user preference with default
$theme = tiny::cookie('preferences')->get()['theme'] ?? 'light';
```
