# HTTP Client

The HTTP client extension provides a simple interface for making HTTP requests to external services.

## Basic Usage

### GET Requests

```php
// Simple GET request
$response = tiny::http()->get('https://api.example.com/data');

// With query parameters
$response = tiny::http()->get('https://api.example.com/search', [
    'q' => 'search term',
    'page' => 1
]);

// With headers
$response = tiny::http()->get('https://api.example.com/data', [], [
    'Authorization' => 'Bearer ' . $token
]);
```

### POST Requests

```php
// JSON POST
$response = tiny::http()->post('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Form POST
$response = tiny::http()->post('https://api.example.com/upload', [
    'file' => new CURLFile('/path/to/file.pdf')
], [
    'Content-Type' => 'multipart/form-data'
]);
```

### Other Methods

```php
// PUT request
$response = tiny::http()->put($url, $data);

// PATCH request
$response = tiny::http()->patch($url, $data);

// DELETE request
$response = tiny::http()->delete($url);
```

## Advanced Features

### Request Options

```php
$response = tiny::http()->get('https://api.example.com', [], [
    'timeout' => 30,
    'verify_ssl' => true,
    'follow_redirects' => true,
    'max_redirects' => 5
]);
```

### Response Handling

```php
$response = tiny::http()->get($url);

// Get status code
$status = $response->getStatusCode();

// Get headers
$headers = $response->getHeaders();

// Get body
$body = $response->getBody();

// Get JSON
$data = $response->json();
```

### Error Handling

```php
try {
    $response = tiny::http()->get($url);
} catch (TinyHttpException $e) {
    // Handle request errors
    $error = $e->getMessage();
    $statusCode = $e->getCode();
}
```

## Best Practices

1. **Timeout Management**
   - Set appropriate timeouts
   - Handle timeout errors
   - Use retry logic for critical requests

2. **SSL/TLS Security**
   - Verify SSL certificates
   - Use proper CA certificates
   - Handle SSL errors appropriately

3. **Error Handling**
   - Catch exceptions
   - Log errors
   - Provide fallback behavior
