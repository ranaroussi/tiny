# Utils Helper

The Utils helper provides a collection of commonly used utility functions for string manipulation, array handling, date formatting, and more.

## String Utilities

### Text Manipulation

```php
// Slugify text
$slug = tiny::utils()->slugify('Hello World!'); // hello-world

// Generate excerpt
$excerpt = tiny::utils()->excerpt($longText, 150);

// Clean HTML
$clean = tiny::utils()->cleanHtml($dirtyHtml);

// Generate random string
$random = tiny::utils()->random(16);

// Mask string
$masked = tiny::utils()->mask('1234567890', '****-**-####');
```

### Text Formatting

```php
// Title case
$title = tiny::utils()->titleCase('hello world');

// Camel case
$camel = tiny::utils()->camelCase('hello_world');

// Snake case
$snake = tiny::utils()->snakeCase('helloWorld');

// Kebab case
$kebab = tiny::utils()->kebabCase('hello world');
```

## Array Utilities

### Array Manipulation

```php
// Flatten array
$flat = tiny::utils()->arrayFlatten($nestedArray);

// Dot notation
$dotted = tiny::utils()->arrayDot(['user' => ['name' => 'John']]);
// Result: ['user.name' => 'John']

// Get value using dot notation
$value = tiny::utils()->arrayGet($array, 'user.settings.theme', 'default');

// Set value using dot notation
tiny::utils()->arraySet($array, 'user.settings.theme', 'dark');
```

### Array Operations

```php
// Filter array
$filtered = tiny::utils()->arrayFilter($array, fn($item) => $item->active);

// Map array
$mapped = tiny::utils()->arrayMap($array, fn($item) => $item->name);

// Group by key
$grouped = tiny::utils()->arrayGroupBy($array, 'category');

// Sort by key
$sorted = tiny::utils()->arraySortBy($array, 'name');
```

## Date Utilities

### Date Formatting

```php
// Format date
$formatted = tiny::utils()->formatDate('2024-03-15', 'human');
// Result: "March 15, 2024"

// Time ago
$timeAgo = tiny::utils()->timeAgo('2024-03-15 14:30:00');
// Result: "2 hours ago"

// Date difference
$diff = tiny::utils()->dateDiff('2024-03-15', '2024-04-15');
// Result: 31 (days)
```

### Date Operations

```php
// Add time
$future = tiny::utils()->dateAdd('2024-03-15', '1 month');

// Subtract time
$past = tiny::utils()->dateSub('2024-03-15', '1 week');

// Compare dates
$isAfter = tiny::utils()->dateIsAfter('2024-03-15', '2024-03-14');
```

## File Utilities

```php
// Get file extension
$ext = tiny::utils()->fileExtension('document.pdf');

// Get mime type
$mime = tiny::utils()->fileMimeType('/path/to/file.jpg');

// Format file size
$size = tiny::utils()->formatFileSize(1024576); // "1 MB"

// Get safe filename
$safe = tiny::utils()->safeFilename('My Document (1).pdf');
```

## Number Utilities

```php
// Format number
$formatted = tiny::utils()->formatNumber(1234.56, 2);

// Format currency
$price = tiny::utils()->formatCurrency(29.99, 'USD');

// Format percentage
$percent = tiny::utils()->formatPercent(0.1234);

// Format bytes
$bytes = tiny::utils()->formatBytes(1024576);
```

## Validation Utilities

```php
// Validate email
if (tiny::utils()->isEmail('user@example.com')) {
    // Valid email
}

// Validate URL
if (tiny::utils()->isUrl('https://example.com')) {
    // Valid URL
}

// Validate IP address
if (tiny::utils()->isIp('192.168.1.1')) {
    // Valid IP
}

// Validate JSON
if (tiny::utils()->isJson($string)) {
    // Valid JSON
}
```

## Security Utilities

```php
// Generate token
$token = tiny::utils()->generateToken(32);

// Hash password
$hash = tiny::utils()->hashPassword($password);

// Verify password
if (tiny::utils()->verifyPassword($password, $hash)) {
    // Password matches
}

// Encrypt data
$encrypted = tiny::utils()->encrypt($data, $key);

// Decrypt data
$decrypted = tiny::utils()->decrypt($encrypted, $key);
```

## Best Practices

1. **Performance**
   - Cache expensive operations
   - Use built-in PHP functions when possible
   - Optimize string operations
   - Handle large arrays efficiently

2. **Security**
   - Sanitize input data
   - Use secure random generators
   - Follow encryption best practices
   - Validate user input

3. **Consistency**
   - Use consistent naming
   - Follow coding standards
   - Document edge cases
   - Handle errors gracefully

4. **Maintenance**
   - Keep utilities focused
   - Write unit tests
   - Document usage examples
   - Version control changes
