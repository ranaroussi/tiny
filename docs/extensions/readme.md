[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Tiny Framework Extensions

Tiny's extension system provides powerful, optional functionality that extends the core framework without bloating it. Each extension is designed for high performance, ease of use, and production readiness.

## Extension Philosophy

- **Modular Design**: Extensions are loaded only when needed
- **Zero Configuration**: Sensible defaults that work out of the box
- **Performance Focused**: Optimized for high-traffic applications
- **Production Ready**: Battle-tested in enterprise environments
- **Developer Friendly**: Intuitive APIs with comprehensive documentation

## Core Extensions

### 📋 [Cache](cache.md) - High-Performance Caching
**APCu/Memcached integration with intelligent invalidation**
- In-memory caching with microsecond access times
- Distributed caching for scalable applications
- Smart cache invalidation and dependency tracking
- Built-in performance monitoring and statistics

### 🧩 [Components](components.md) - Reusable UI Elements
**Modern component-based view system with props and state**
- Reusable UI components with parameter passing
- Slot-based content composition
- Component lifecycle hooks and state management
- Server-side rendering with client-side hydration

### 🍪 [Cookie](cookie.md) - Secure Cookie Management
**Enterprise-grade cookie handling with security features**
- Secure, HTTP-only cookies with SameSite protection
- Automatic encryption for sensitive data
- GDPR-compliant cookie consent management
- Session clustering and distributed storage

### 🚫 [CSRF](csrf.md) - Security Protection
**Cross-Site Request Forgery prevention with token management**
- Automatic CSRF token generation and validation
- SPA and AJAX request protection
- Token rotation and lifecycle management
- Integration with forms and API endpoints

### 🚀 [ClickHouse](clickhouse.md) - Analytics Database
**Native ClickHouse integration for big data and analytics**
- High-performance analytics queries
- Time-series data processing
- Batch insert optimization
- Real-time data streaming

### 🔍 [Debugger](debugger.md) - Development Tools
**Comprehensive debugging and profiling tools**
- SQL query logging and performance analysis
- Request/response debugging with timing
- Memory usage profiling and leak detection
- Error tracking with stack traces and context

### ⚡ [Flash](flash.md) - User Notifications
**Persistent messaging system for user feedback**
- Multi-level notification system (success, error, warning, info)
- Session-based message persistence
- HTMX and AJAX compatibility
- Custom message formatting and styling

### 🌐 [HTTP](http.md) - External API Integration
**Full-featured HTTP client with retry logic and authentication**
- RESTful API integration with authentication
- Automatic retry logic with exponential backoff
- Request/response middleware
- Webhook processing and validation

### 🎨 [Layout](layout.md) - Template System
**Hierarchical layout system with inheritance**
- Nested layout inheritance
- Section-based content organization
- Dynamic layout selection
- Asset management and optimization

### 📄 [Migrations](migrations.md) - Database Versioning
**Version-controlled database schema management**
- Forward and backward migration support
- Team collaboration with conflict resolution
- Production-safe deployment strategies
- Cross-database compatibility

### ⏰ [Scheduler](scheduler.md) - Task Automation
**Cron-like job scheduling with monitoring**
- Fluent scheduling API with natural language
- Job queuing and parallel execution
- Failure handling and retry mechanisms
- Performance monitoring and alerting

### 🔄 [SSE](sse.md) - Real-time Communication
**Server-Sent Events for live data streaming**
- Real-time data streaming to browsers
- Event-driven architecture
- Connection management and reconnection
- Multi-channel broadcasting

## Using Extensions

Extensions are automatically loaded when accessed, providing zero-configuration functionality:

### Basic Extension Usage

```php
// Cache extension - set and get data
tiny::cache()->set('user_profile_123', $userProfile, 3600);
$cachedProfile = tiny::cache()->get('user_profile_123');

// HTTP client - make API requests (static methods)
$response = TinyHTTP::get('https://api.stripe.com/v1/customers', [
    'headers' => ['Authorization: Bearer ' . $apiKey]
]);

// Database operations
$users = tiny::db()->get('users', ['status' => 'active']);
$user = tiny::db()->getOne('users', ['id' => 123]);

// Flash messages for user feedback
tiny::flash('toast')->set('Payment processed successfully');

// CSRF protection for forms
if (!tiny::csrf()->isValid()) {
    tiny::response()->sendJSON(['error' => 'Invalid security token'], 403);
}
```

### Advanced Extension Patterns

```php
// Cache operations with TTL and prefixes
tiny::cache()->set('billing_account_123', $billingData, 1800);
$cachedData = tiny::cache()->remember('expensive_calc_456', 3600, function() {
    return performExpensiveCalculation();
});

// HTTP requests with options
$response = TinyHTTP::post('https://api.example.com/webhooks', [
    'json' => $payload,
    'headers' => ['Authorization: Bearer ' . $token],
    'timeout' => 30
]);

// Cookie handling with security
tiny::cookie('user_prefs', ['theme' => 'dark', 'lang' => 'en'])->save();
$preferences = tiny::cookie('user_prefs')->read();

// Request/Response patterns
$email = tiny::request()->params('email');
tiny::response()->render('profile', ['user' => $userData]);
```

## Extension Configuration

Extensions are configured through environment variables, providing flexible deployment options:

### Cache Configuration

```env
# Cache engine (apcu or memcached)
TINY_CACHE_ENGINE=apcu

# For production with Memcached:
# TINY_CACHE_ENGINE=memcached
# MEMCACHED_HOST=localhost
# MEMCACHED_PORT=11211
```

### Security Configuration

```env
# Cookie configuration (optional overrides)
# TINY_COOKIE_DOMAIN=.example.com
# TINY_COOKIE_PATH=/
TINY_COOKIE_TTL=31536000

# Crypto security
CRYPTO_ALGO=aes-256-cbc
CRYPTO_SECRET=your_secure_secret_key
CRYPTO_TTL=60
```

### Database Configuration

```env
# ClickHouse analytics database
CLICKHOUSE_HOST=analytics.example.com
CLICKHOUSE_PORT=8123
CLICKHOUSE_DATABASE=analytics
CLICKHOUSE_USER=readonly
CLICKHOUSE_PASSWORD=secure_password
CLICKHOUSE_COMPRESSION=gzip

# Migration settings
MIGRATION_TABLE=schema_migrations
MIGRATION_LOCK=true
MIGRATION_TIMEOUT=300
```

### HTTP Client Configuration

```env
# Default HTTP client settings
HTTP_TIMEOUT=30
HTTP_CONNECT_TIMEOUT=10
HTTP_USER_AGENT="TinyFramework/1.0"
HTTP_FOLLOW_REDIRECTS=true
HTTP_MAX_REDIRECTS=5

# SSL/TLS configuration
HTTP_VERIFY_SSL=true
HTTP_SSL_CERT_PATH=/path/to/cert.pem
HTTP_SSL_KEY_PATH=/path/to/key.pem
```

### Scheduler Configuration

```env
# Job scheduler settings
SCHEDULER_TIMEZONE=UTC
SCHEDULER_MAX_EXECUTION_TIME=300
SCHEDULER_LOCK_TTL=3600
SCHEDULER_LOG_LEVEL=info

# Job retry configuration
SCHEDULER_MAX_RETRIES=3
SCHEDULER_RETRY_DELAY=60
SCHEDULER_EXPONENTIAL_BACKOFF=true
```

## Creating Custom Extensions

Build powerful custom extensions that integrate seamlessly with the Tiny ecosystem:

### Extension Development Guidelines

1. **Naming Convention**: Extensions must be named `Tiny{Name}` (e.g., `TinyPayments`)
2. **File Location**: Place in `tiny/ext/{name}.php` (e.g., `tiny/ext/payments.php`)
3. **Lazy Loading**: Extensions are instantiated only when accessed
4. **Configuration**: Use environment variables for configuration
5. **Error Handling**: Implement comprehensive error handling
6. **Performance**: Optimize for high-traffic scenarios

### Complete Extension Example

```php
<?php
// tiny/ext/payments.php

declare(strict_types=1);

class TinyPayments
{
    private array $providers = [];
    private array $config = [];

    public function __construct()
    {
        // Load configuration from environment
        $this->config = [
            'default_provider' => $_SERVER['PAYMENTS_DEFAULT_PROVIDER'] ?? 'stripe',
            'currency' => $_SERVER['PAYMENTS_CURRENCY'] ?? 'USD',
            'webhook_secret' => $_SERVER['PAYMENTS_WEBHOOK_SECRET'] ?? '',
            'test_mode' => ($_SERVER['PAYMENTS_TEST_MODE'] ?? 'false') === 'true'
        ];

        // Initialize providers
        $this->initializeProviders();
    }

    /**
     * Process a payment with automatic provider selection
     */
    public function charge(array $paymentData): array
    {
        $provider = $paymentData['provider'] ?? $this->config['default_provider'];

        if (!isset($this->providers[$provider])) {
            throw new InvalidArgumentException("Unsupported payment provider: {$provider}");
        }

        try {
            // Validate payment data
            $this->validatePaymentData($paymentData);

            // Process payment through provider
            $result = $this->providers[$provider]->processPayment($paymentData);

            // Log transaction
            $this->logTransaction($result, $paymentData);

            return [
                'success' => true,
                'transaction_id' => $result['id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'status' => $result['status']
            ];

        } catch (PaymentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    private function validatePaymentData(array $data): void
    {
        $required = ['amount', 'currency', 'customer_id'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Missing required field: {$field}");
            }
        }
    }
}
```

### Using Your Custom Extension

```php
// Process a payment
$result = tiny::payments()->charge([
    'amount' => 2999, // $29.99 in cents
    'currency' => 'USD',
    'customer_id' => 'cus_123456789',
    'payment_method_id' => 'pm_987654321',
    'description' => 'Monthly subscription'
]);

if ($result['success']) {
    echo "Payment successful: {$result['transaction_id']}";
} else {
    echo "Payment failed: {$result['error']}";
}
```

## Extension Performance Optimization

### Lazy Loading Strategy

Extensions are loaded only when accessed, reducing memory usage and startup time:

```php
// Extension is loaded only when first accessed
$cached = tiny::cache()->get('key'); // TinyCache loaded here

// Subsequent calls use the same instance
$another = tiny::cache()->set('key2', 'value'); // Uses existing instance
```

### Memory Management

```php
// Extensions implement memory-efficient patterns
class TinyCache
{
    private static array $instances = [];
    private array $stats = ['hits' => 0, 'misses' => 0];

    public function __destruct()
    {
        // Clean up resources
        $this->disconnect();

        // Log performance statistics
        if ($this->stats['hits'] + $this->stats['misses'] > 0) {
            $hitRate = $this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses']);
            error_log("Cache hit rate: " . round($hitRate * 100, 2) . "%");
        }
    }
}
```

## Production Deployment

### Extension Health Checks

```php
// Extension health check endpoint
class HealthCheck
{
    public function checkExtensions(): array
    {
        $results = [];

        // Check cache connectivity
        try {
            tiny::cache()->set('health_check', time(), 60);
            $value = tiny::cache()->get('health_check');
            $results['cache'] = ['status' => 'healthy', 'latency' => 0];
        } catch (Exception $e) {
            $results['cache'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        // Check database connectivity
        try {
            tiny::db()->getQuery('SELECT 1');
            $results['database'] = ['status' => 'healthy'];
        } catch (Exception $e) {
            $results['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        return $results;
    }
}
```

### Extension Best Practices

1. **Configuration Management**: Use environment variables for all configuration
2. **Error Handling**: Implement comprehensive error handling with proper logging
3. **Performance**: Cache expensive operations and optimize database queries
4. **Security**: Validate all inputs and implement proper authentication
5. **Testing**: Write unit tests for all extension functionality
6. **Documentation**: Provide clear documentation and examples
7. **Backward Compatibility**: Maintain API stability across versions

---

## Next Steps

1. **Explore Individual Extensions**: Dive deep into specific extension documentation
2. **Build Custom Extensions**: Create extensions tailored to your application needs
3. **Performance Tuning**: Optimize extension configuration for your infrastructure
4. **Monitoring Setup**: Implement monitoring and alerting for extension health
5. **Team Training**: Educate your team on extension best practices

Each extension provides production-ready functionality with comprehensive documentation, examples, and best practices for enterprise deployment.
