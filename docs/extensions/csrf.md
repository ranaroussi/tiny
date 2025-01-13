[Home](../readme.md) | [Getting Started](getting-started.md) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# CSRF Protection

The CSRF (Cross-Site Request Forgery) extension provides protection against CSRF attacks by generating and validating tokens for forms and AJAX requests.

## Basic Usage

### Form Protection

```php
<!-- In your form -->
<form method="POST">
    <?php tiny::csrf()->input() ?>
    <!-- form fields -->
</form>
```

### Manual Token Handling

```php
// Generate a new token
$token = tiny::csrf()->generate();

// Get current token
$token = tiny::csrf()->getToken();

// Validate token
if (tiny::csrf()->isValid($token)) {
    // Token is valid
}
```

### AJAX Requests

```javascript
fetch('/api/data', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
    },
    body: JSON.stringify(data)
});
```

## Configuration

Configure CSRF settings in your `.env` file:

```env
CSRF_TOKEN_LENGTH=32
CSRF_TOKEN_NAME=csrf_token
CSRF_HEADER_NAME=X-CSRF-Token
```

## Best Practices

1. **Always Use in Forms**
   - Include CSRF token in all forms
   - Use the built-in input helper
   - Validate tokens on submission

2. **AJAX Protection**
   - Include token in headers
   - Handle validation errors
   - Refresh tokens as needed

3. **Security Considerations**
   - Use HTTPS
   - Set proper cookie settings
   - Implement token rotation
