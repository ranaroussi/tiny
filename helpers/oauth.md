[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# OAuth Helper

The OAuth helper simplifies social authentication integration in your application.

## Configuration

Configure OAuth providers in your `.env` file:

```env
OAUTH_GOOGLE_ID=your_client_id
OAUTH_GOOGLE_SECRET=your_client_secret
OAUTH_GOOGLE_REDIRECT=https://your-app.com/auth/google/callback

OAUTH_GITHUB_ID=your_client_id
OAUTH_GITHUB_SECRET=your_client_secret
OAUTH_GITHUB_REDIRECT=https://your-app.com/auth/github/callback
```

## The User Object

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

## Basic Usage

### Provider Setup

```php
// Configure provider
tiny::oauth()->configure('google', [
    'client_id' => $_ENV['OAUTH_GOOGLE_ID'],
    'client_secret' => $_ENV['OAUTH_GOOGLE_SECRET'],
    'redirect_uri' => $_ENV['OAUTH_GOOGLE_REDIRECT']
]);

// Get authorization URL
$url = tiny::oauth()->getAuthUrl('google', [
    'scope' => ['email', 'profile']
]);
```

### Authentication Flow

```php
// Handle OAuth callback
try {
    $token = tiny::oauth()->handleCallback('google');
    $profile = tiny::oauth()->getUserProfile('google');

    // Create or update user
    $user = tiny::user()->createFromOAuth($profile);

    // Log user in
    tiny::auth()->login($user);
} catch (OAuthException $e) {
    // Handle authentication error
}
```

## Advanced Features

### Custom Scopes

```php
// Request specific permissions
$url = tiny::oauth()->getAuthUrl('github', [
    'scope' => ['user', 'repo'],
    'state' => csrf_token()
]);
```

### Token Management

```php
// Get access token
$token = tiny::oauth()->getAccessToken('google');

// Refresh token
$newToken = tiny::oauth()->refreshToken('google', $refreshToken);

// Revoke token
tiny::oauth()->revokeToken('google', $token);
```

### Provider API Calls

```php
// Make authenticated API request
$response = tiny::oauth()->request('google', 'GET', 'userinfo');

// With custom parameters
$repos = tiny::oauth()->request('github', 'GET', 'user/repos', [
    'sort' => 'updated',
    'per_page' => 10
]);
```

## Supported Providers

- Google
- GitHub
- Facebook
- Twitter
- LinkedIn
- Microsoft
- Apple

## Best Practices

1. **Security**
   - Validate state parameter
   - Use HTTPS endpoints
   - Store tokens securely
   - Handle token expiration

2. **User Experience**
   - Handle errors gracefully
   - Provide clear messages
   - Support account linking
   - Remember user choice

3. **Data Handling**
   - Normalize profile data
   - Handle missing fields
   - Update user data
   - Respect privacy settings
