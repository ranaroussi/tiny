# Authentication Helpers

The authentication helpers provide a simple interface for managing user authentication, sessions, and permissions in your Tiny PHP application.

## Basic Usage

### User Management

```php
// Get current user
$user = tiny::user();

// Check if user is authenticated
if (tiny::isAuthenticated()) {
    // User is logged in
}

// Get user property
$email = tiny::user()->email;
$name = tiny::user()->name;

// Check user role
if (tiny::user()->is_admin) {
    // User is an admin
}
```

### Session Management

```php
// Start a user session
tiny::startSession($userId);

// End current session
tiny::endSession();

// Refresh session
tiny::refreshSession();
```

### OAuth Integration

```php
// Configure OAuth providers
$config = buildOAuthConfig([
    'google' => [
        'client_id' => 'your-client-id',
        'client_secret' => 'your-client-secret'
    ]
]);

// Handle OAuth callback
$profile = tiny::oauth()->getUserProfile();

// Create or update user
tiny::user()->createFromOAuth($profile);
```

### Permission Handling

```php
// Check single permission
if (tiny::can('edit_posts')) {
    // User can edit posts
}

// Check multiple permissions
if (tiny::canAll(['edit_posts', 'delete_posts'])) {
    // User can both edit and delete posts
}

// Check any permission
if (tiny::canAny(['edit_posts', 'view_posts'])) {
    // User can either edit or view posts
}
```

## Advanced Features

### Custom Authentication

```php
// Custom authentication logic
class MyAuth extends TinyAuth
{
    public function validate($credentials)
    {
        // Custom validation logic
        return $this->checkCredentials($credentials);
    }
}

// Register custom auth
tiny::register('auth', new MyAuth());
```

### Role-Based Access Control

```php
// Define roles and permissions
$roles = [
    'admin' => ['*'],
    'editor' => ['edit_posts', 'publish_posts'],
    'user' => ['view_posts']
];

// Check role
if (tiny::hasRole('admin')) {
    // User is an admin
}

// Check role with permissions
if (tiny::hasRole('editor', 'edit_posts')) {
    // Editor can edit posts
}
```

### Session Security

```php
// Set session security options
tiny::session()->secure([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '.example.com',
    'secure' => true,
    'httponly' => true
]);

// Regenerate session ID
tiny::session()->regenerate();
```

## Best Practices

1. **Security**
   - Always validate user input
   - Use HTTPS for authentication
   - Implement CSRF protection
   - Set appropriate cookie settings

2. **Session Management**
   - Use secure session settings
   - Regularly regenerate session IDs
   - Clean up expired sessions
   - Handle session timeouts

3. **Permission Design**
   - Use granular permissions
   - Group related permissions
   - Document permission requirements
   - Implement role hierarchy

4. **OAuth Integration**
   - Secure OAuth credentials
   - Handle OAuth errors gracefully
   - Validate OAuth data
   - Implement proper state handling
