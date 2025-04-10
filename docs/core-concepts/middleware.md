[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Middleware

Middleware provides a mechanism for filtering HTTP requests entering your application. Common use cases include authentication, logging, CORS, and rate limiting.

## Basic Structure

Middleware files should be placed in the `app/middleware` directory:

```php
<?php
// app/middleware/auth.php

class AuthMiddleware
{
    public function handle()
    {
        if (!tiny::auth()->check()) {
            return tiny::response()->redirect('/login');
        }
    }
}
```

## Registering Middleware

Middleware is loaded in the order specified in `app/middleware.php`. This order is important as each middleware is executed sequentially:

```php
<?php
// app/middleware.php

// Middleware will be executed in this order
tiny::middleware('auth');       // First - Check authentication
tiny::middleware('rate-limit'); // Second - Check rate limits
tiny::middleware('cors');       // Third - Handle CORS
tiny::middleware('logger');     // Fourth - Log request
```

The order matters because:
- Earlier middleware can prevent later middleware from executing
- Some middleware may depend on others being run first
- Security middleware (auth, CSRF) should typically run early
- Logging middleware often runs last to capture the full request lifecycle

For example, you might want this order:
1. Authentication (verify user)
2. Rate limiting (prevent abuse)
3. CORS (handle cross-origin requests)
4. Request logging (log authenticated user info)


## Common Middleware Examples

### Authentication
```php
<?php
// app/middleware/auth.php

class AuthMiddleware
{
    public function handle()
    {
        if (!tiny::auth()->check()) {
            if (tiny::request()->isHtmx()) {
                return tiny::response()->sendJSON([
                    'error' => 'Unauthorized'
                ], 401);
            }

            tiny::flash('toast')->set([
                'level' => 'error',
                'message' => 'Please login to continue'
            ]);

            return tiny::response()->redirect('/login');
        }
    }
}
```

### CORS
```php
<?php
// app/middleware/cors.php

class CorsMiddleware
{
    public function handle()
    {
        tiny::header('Access-Control-Allow-Origin: *');
        tiny::header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        tiny::header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if (tiny::request()->method === 'OPTIONS') {
            tiny::exit();
        }
    }
}
```

### Rate Limiting
```php
<?php
// app/middleware/rate-limit.php

class RateLimitMiddleware
{
    public function handle()
    {
        $ip = tiny::request()->ip();
        $key = "rate_limit:$ip";

        if (!tiny::rateLimit()->allow($key, 60, 100)) {
            return tiny::response()->sendJSON([
                'error' => 'Too many requests'
            ], 429);
        }
    }
}
```

### Request Logging
```php
<?php
// app/middleware/logger.php

class LoggerMiddleware
{
    public function handle()
    {
        $request = tiny::request();
        $log = [
            'ip' => $request->ip(),
            'method' => $request->method,
            'path' => $request->path,
            'user_agent' => $request->headers['User-Agent'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        tiny::log('Request', $log);
    }
}
```

## Best Practices

1. **Performance**
   - Keep middleware lightweight
   - Use caching where appropriate
   - Process only what's necessary

2. **Organization**
   - One responsibility per middleware
   - Use descriptive names
   - Group related middleware

3. **Error Handling**
   - Return appropriate responses
   - Log errors when needed
   - Provide clear error messages

4. **Security**
   - Validate input data
   - Protect against common attacks
   - Use proper status codes

5. **Configuration**
   - Use environment variables
   - Make middleware configurable
   - Document configuration options
