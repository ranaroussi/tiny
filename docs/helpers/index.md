# Helpers

Tiny PHP Framework includes a collection of helper functions and utilities to simplify common programming tasks. These helpers are designed to make your code more readable and maintainable.

## Available Helpers

1. [Authentication](auth.md)
   - User authentication
   - Session management
   - OAuth integration
   - Permission handling

2. [Validation](validation.md)
   - Data validation
   - Schema validation
   - Type checking
   - Custom validation rules

3. [File Management](file.md)
   - File operations
   - Directory handling
   - File uploads
   - Path manipulation

4. [String Manipulation](string.md)
   - String formatting
   - Text processing
   - Encoding/decoding
   - Pattern matching

5. [Array Utilities](array.md)
   - Array manipulation
   - Collection handling
   - Data transformation
   - Array filtering

## Using Helpers

Helpers are accessed through the `tiny` class or directly via global functions:

```php
// Using the tiny class
$user = tiny::user();
$isValid = tiny::validate($data, $rules);
$path = tiny::path('uploads/images');

// Using global functions
$str = str_slugify('Hello World');
$arr = array_dot(['user' => ['name' => 'John']]);
```

## Common Helpers Reference

### Authentication Helpers

```php
// Get current user
$user = tiny::user();

// Check authentication
if (tiny::isAuthenticated()) {
    // User is logged in
}

// Check permissions
if (tiny::can('edit_posts')) {
    // User has permission
}
```

### Validation Helpers

```php
// Validate data against rules
$isValid = tiny::validate($data, [
    'name' => 'required|string|max:255',
    'email' => 'required|email',
    'age' => 'numeric|min:18'
]);

// Type checking
$isInt = tiny::isInt($value);
$isBool = tiny::isBool($value);
```

### File Helpers

```php
// File operations
$exists = tiny::fileExists($path);
$content = tiny::fileGet($path);
tiny::filePut($path, $content);

// Directory operations
tiny::makeDir($path);
$files = tiny::listFiles($directory);
```

### String Helpers

```php
// String manipulation
$slug = tiny::slugify('Hello World'); // hello-world
$truncated = tiny::truncate($text, 100);
$cleaned = tiny::clean($userInput);

// Text processing
$excerpt = tiny::excerpt($content, 150);
$formatted = tiny::format($template, $vars);
```

### Array Helpers

```php
// Array manipulation
$flattened = tiny::arrayFlatten($nested);
$dotted = tiny::arrayDot($array);
$filtered = tiny::arrayFilter($array, $callback);

// Collection handling
$collection = tiny::collect($array);
$mapped = $collection->map(fn($item) => $item * 2);
```

## Creating Custom Helpers

You can create your own helpers by adding them to the `app/helpers/` directory:

1. Create a new helper file:

```php
<?php
// app/helpers/my_helper.php

function my_custom_function($param) {
    // Helper implementation
}

// Register with tiny class (optional)
tiny::register('myHelper', function() {
    return new class {
        public function customFunction($param) {
            return my_custom_function($param);
        }
    };
});
```

2. Use your helper:

```php
// Direct usage
$result = my_custom_function('test');

// Via tiny class
$result = tiny::myHelper()->customFunction('test');
```

## Best Practices

1. **Naming Conventions**
   - Use descriptive names
   - Follow consistent naming patterns
   - Prefix to avoid conflicts

2. **Function Design**
   - Keep functions focused
   - Use type hints
   - Return consistent types
   - Document parameters and return types

3. **Error Handling**
   - Use appropriate error handling
   - Return meaningful error messages
   - Validate input parameters

4. **Performance**
   - Cache expensive operations
   - Optimize for common use cases
   - Consider memory usage
