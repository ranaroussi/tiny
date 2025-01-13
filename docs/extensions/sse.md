[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Server-Sent Events (SSE) Extension

The SSE extension provides real-time server-to-client communication using Server-Sent Events. It supports both cache-based and PostgreSQL-based event streaming.

## Basic Usage

### Server-Side

```php
// Initialize SSE connection
$app->sse->start();

// Send a simple message
$app->sse->send('Hello World');

// Send JSON data
$app->sse->send(json_encode([
    'event' => 'update',
    'data' => ['status' => 'success']
]));
```

### Client-Side

```javascript
// Initialize SSE connection
const sse = new EventSource('/events');

// Listen for messages
sse.onmessage = (event) => {
    console.log('Received:', event.data);
};

// Handle connection status
sse.onerror = (error) => {
    console.error('SSE error:', error);
};
```

## Cache-Based Streaming

### Server-Side Publisher
```php
// Send data through cache
$app->sse->sendKey('user_updates', [
    'user_id' => 123,
    'status' => 'online'
]);

// Send termination signal
$app->sse->sendKey('user_updates', '[DONE]');
```

### Server-Side Consumer
```php
// Stream data from cache
$app->sse->streamKey('user_updates', 1); // Check every 1 second
```

## PostgreSQL Notifications

### Database Setup
```sql
-- Create notification function
CREATE OR REPLACE FUNCTION notify_user_updates()
RETURNS trigger AS $$
BEGIN
    PERFORM pg_notify('user_updates', row_to_json(NEW)::text);
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Create trigger
CREATE TRIGGER user_updates_trigger
    AFTER INSERT ON users
    FOR EACH ROW
    EXECUTE PROCEDURE notify_user_updates();
```

### Server-Side Consumer
```php
// Stream PostgreSQL notifications
$app->sse->streamPostgres('user_updates', 1); // Check every 1 second
```

## Advanced Features

### Custom Event Names
```javascript
// Client-side
const sse = new EventSource('/events');
sse.addEventListener('user_update', (event) => {
    const data = JSON.parse(event.data);
    console.log('User update:', data);
});
```

### Connection Management
```php
// Server-side
$app->sse->start();

// Set headers
header('X-Accel-Buffering: no'); // For nginx
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Prevent session blocking
session_write_close();
```

## Best Practices

1. **Resource Management**
   - Close SSE connections when no longer needed
   - Use appropriate sleep intervals
   - Handle client disconnections gracefully

2. **Data Format**
   - Use JSON for structured data
   - Keep payloads small and efficient
   - Include timestamps when relevant

3. **Error Handling**
   - Implement client-side reconnection logic
   - Log server-side errors appropriately
   - Handle network interruptions gracefully

4. **Security**
   - Validate client permissions
   - Sanitize data before sending
   - Use HTTPS in production
