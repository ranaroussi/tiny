[Home](../readme.md) | [Getting Started](getting-started.md) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Debugger Extension

The Debugger extension provides powerful debugging tools to help you inspect variables, handle errors, and manage logs during development.

## Basic Usage

### Variable Inspection

```php
// Debug single variable
tiny::debug($variable);

// Debug multiple variables
tiny::debug($var1, $var2, $var3);

// Debug with label
tiny::debug('User Data:', $userData);

// Debug and die
tiny::dd($variable);
```

### Stack Traces

```php
// Get current stack trace
tiny::debug()->trace();

// Get stack trace for an exception
try {
    // Some code
} catch (Exception $e) {
    tiny::debug()->trace($e);
}
```

### Error Handling

```php
// Log error with context
tiny::debug()->error('Database connection failed', [
    'host' => $host,
    'error' => $e->getMessage()
]);

// Custom error handling
tiny::debug()->handleError(function($error) {
    // Custom error handling logic
    mail('admin@example.com', 'Error Alert', $error);
});
```

## Advanced Features

### Log Management

```php
// Write to log file
tiny::debug()->log('Important event occurred');

// Log with level
tiny::debug()->log('Payment processed', 'info');

// Log with context
tiny::debug()->log('User action', 'info', [
    'user_id' => $userId,
    'action' => $action
]);
```

### Output Formatting

```php
// Pretty print arrays/objects
tiny::debug()->pretty($data);

// Format as table
tiny::debug()->table($arrayData);

// Syntax highlighted code
tiny::debug()->code($phpCode);
```

### Performance Monitoring

```php
// Start timer
tiny::debug()->timer('query');

// Some code to measure
$result = $db->query();

// End timer and get duration
$duration = tiny::debug()->timer('query')->end();
```

## Configuration

Configure debugging in your `.env` file:

```env
DEBUG=true
DEBUG_WHITELIST=127.0.0.1,::1
LOG_FILE=/path/to/debug.log
LOG_LEVEL=debug
```

## Best Practices

1. **Security**
   - Disable debugging in production
   - Use IP whitelisting
   - Sanitize sensitive data
   - Manage log access

2. **Performance**
   - Use selective debugging
   - Clean up debug code
   - Monitor log sizes
   - Use appropriate log levels

3. **Organization**
   - Group related debug calls
   - Use meaningful labels
   - Structure log messages
   - Clean up old logs
