[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Flash Messages Extension

The Flash extension provides a way to store temporary messages between requests, commonly used for user notifications and feedback.

## Basic Usage

### Setting Flash Messages

```php
// Simple message
tiny::flash()->set('Profile updated successfully');

// With type/level
tiny::flash('success')->set('Changes saved');
tiny::flash('error')->set('Invalid input');

// Structured message
tiny::flash('toast')->set([
    'title' => 'Success!',
    'message' => 'Your profile has been updated.',
    'level' => 'success'
]);
```

### Retrieving Messages

```php
// Get message
$message = tiny::flash()->get();

// Get and keep message
$message = tiny::flash()->get(true);

// Check specific message
if (tiny::flash('error')->exists()) {
    // Handle error case
}
```

## Advanced Features

### Multiple Message Types

```php
// Different message types
tiny::flash('info')->set('Please review your settings');
tiny::flash('warning')->set('Your session will expire soon');
tiny::flash('error')->set('Invalid credentials');
tiny::flash('success')->set('Welcome back!');
```

### Message Persistence

```php
// Keep message for multiple requests
tiny::flash('notice')->set('Complete your profile')->keep();

// Keep until explicitly cleared
tiny::flash('important')->set('Account needs verification')->persist();

// Clear persisted message
tiny::flash('important')->clear();
```

### Structured Messages

```php
// Toast notifications
tiny::flash('toast')->set([
    'title' => 'New Message',
    'message' => 'You have received a new message',
    'level' => 'info',
    'duration' => 5000 // milliseconds
]);

// Alert messages
tiny::flash('alert')->set([
    'type' => 'warning',
    'message' => 'Please backup your data',
    'dismissible' => true
]);
```

### Checking Messages

```php
// Check if message exists
if (tiny::flash('success')->exists()) {
    // Handle success case
}

// Check message content
if (tiny::flash('status')->is('pending')) {
    // Handle pending status
}

// Get all flash messages
$messages = tiny::flash()->all();
```

## View Integration

```php
<!-- In your layout -->
<?php if (tiny::flash('toast')->exists()): ?>
    <?php $toast = tiny::flash('toast')->get(); ?>
    <div class="toast toast-<?= $toast['level'] ?>">
        <h4><?= $toast['title'] ?></h4>
        <p><?= $toast['message'] ?></p>
    </div>
<?php endif ?>

<!-- For multiple messages -->
<?php foreach (tiny::flash()->all() as $type => $message): ?>
    <div class="alert alert-<?= $type ?>">
        <?= $message ?>
    </div>
<?php endforeach ?>
```

## Best Practices

1. **Message Types**
   - Use consistent message types
   - Choose appropriate levels
   - Keep messages clear and concise
   - Use structured data when needed

2. **Timing**
   - Clear messages after display
   - Use persistence sparingly
   - Consider message lifetime
   - Handle expired messages

3. **User Experience**
   - Position messages appropriately
   - Use appropriate styling
   - Allow message dismissal
   - Maintain consistency

4. **Security**
   - Sanitize message content
   - Avoid sensitive information
   - Validate message types
   - Clean old messages
