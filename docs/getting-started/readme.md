[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Getting Started with Tiny PHP

Get up and running with Tiny PHP Framework in minutes. This guide will take you from zero to a working application with all the essential features configured.

## Quick Start (5 Minutes)

### 1. System Requirements Check

```bash
# Check PHP version (8.3+ required)
php --version

# Check required extensions
php -m | grep -E "(pdo|openssl|json|mbstring|curl)"

# Install Composer if not available
curl -sS https://getcomposer.org/installer | php
```

### 2. Create New Project

```bash
# Create project directory
mkdir my-tiny-app && cd my-tiny-app

# Clone framework (or download as submodule)
git clone https://github.com/ranaroussi/tiny.git

# Create project structure
php tiny/cli create

# Install dependencies
composer install
```

### 3. Environment Setup

```bash
# Create environment file
cp .env.example .env.local

# Edit configuration (database, cache, etc.)
nano .env.local
```

### 4. Database Setup

```bash
# Run migrations (creates database structure)
php tiny/cli migrate

# Seed sample data (optional)
php tiny/cli seed
```

### 5. Start Development Server

```bash
# Start built-in PHP server
php -S localhost:8000 -t html/

# Or use Tiny's development command
php tiny/cli serve --port=8000
```

Visit `http://localhost:8000` - you should see your Tiny application running!

## Detailed Installation Guide

### Prerequisites

**Required:**
- PHP 8.3 or higher with OPcache enabled
- Composer for dependency management
- Web server (Apache, Nginx, or PHP built-in server)

**Optional but Recommended:**
- Redis or Memcached for caching
- ClickHouse for analytics (if using analytics features)
- Node.js for asset compilation

### Step-by-Step Setup

#### 1. Project Initialization

```bash
# Method 1: Git Clone (for development)
git clone https://github.com/ranaroussi/tiny.git my-project
cd my-project
php tiny/cli create

# Method 2: Composer Create-Project (coming soon)
composer create-project tiny/framework my-project

# Method 3: As Git Submodule (for existing projects)
git submodule add https://github.com/ranaroussi/tiny.git tiny
git submodule update --init --recursive
```

#### 2. Dependencies Installation

```bash
# Install PHP dependencies
composer install --optimize-autoloader

# Install Node.js dependencies (if using asset compilation)
npm install

# For production, optimize autoloader
composer install --no-dev --optimize-autoloader
```

#### 3. Environment Configuration

Create environment-specific configuration files:

```bash
# Local development
cp .env.example .env.local

# Production
cp .env.example .env.prod

# Staging
cp .env.example .env.staging
```

## Project Structure

Understanding the project structure is crucial for effective development:

```
my-project/
├── app/                          # Application source code
│   ├── controllers/              # Request handlers
│   │   ├── home.php             # Home page controller
│   │   ├── auth/                # Authentication controllers
│   │   ├── api/                 # API endpoints
│   │   └── admin/               # Admin interface
│   ├── models/                   # Data models and business logic
│   │   ├── user.php             # User model
│   │   ├── billing.php          # Billing model
│   │   └── enums.php            # Application enumerations
│   ├── views/                    # Templates and UI components
│   │   ├── layouts/             # Page layouts
│   │   ├── components/          # Reusable UI components
│   │   ├── auth/                # Authentication views
│   │   └── errors/              # Error pages
│   ├── middleware/               # Request/response filters
│   │   ├── auth.php             # Authentication middleware
│   │   ├── admin.php            # Admin access middleware
│   │   └── rate_limit.php       # Rate limiting middleware
│   ├── jobs/                     # Background job definitions
│   ├── common.php                # Global application setup
│   ├── middleware.php            # Middleware configuration
│   └── scheduler.php             # Job scheduling configuration
├── html/                         # Public web directory (document root)
│   ├── index.php                # Application entry point
│   ├── static/                  # Static assets
│   │   ├── css/                 # Compiled stylesheets
│   │   ├── js/                  # JavaScript files
│   │   └── img/                 # Images and media
│   └── favicon.ico              # Site icon
├── migrations/                   # Database version control
│   ├── 20240101000000_create_users.php
│   └── migrations.sqlite        # Migration tracking
├── tiny/                         # Framework core (git submodule)
│   ├── tiny.php                 # Framework entry point
│   ├── ext/                     # Framework extensions
│   ├── helpers/                 # Utility functions
│   └── docs/                    # Framework documentation
├── vendor/                       # Composer dependencies
├── storage/                      # Application storage
│   ├── cache/                   # File cache storage
│   ├── logs/                    # Application logs
│   └── uploads/                 # User uploaded files
├── tests/                        # Test suite
│   ├── unit/                    # Unit tests
│   ├── integration/             # Integration tests
│   └── TestCase.php             # Base test class
├── .env.example                  # Environment template
├── .env.local                    # Local environment config
├── composer.json                 # PHP dependencies
├── package.json                  # Node.js dependencies (if using)
└── README.md                     # Project documentation
```

### Directory Purpose Explanation

**Application Layer (`app/`):**
- `controllers/`: Handle HTTP requests and coordinate responses
- `models/`: Contain business logic and data access
- `views/`: Template files for rendering HTML
- `middleware/`: Cross-cutting concerns (auth, logging, etc.)
- `jobs/`: Background tasks and scheduled operations

**Public Layer (`html/`):**
- Document root for web server
- Contains all publicly accessible files
- Static assets served directly by web server

**Data Layer (`migrations/`):**
- Version-controlled database schema changes
- Enables collaborative database development
- Supports rollback and deployment automation

## Configuration

Tiny uses environment-based configuration for maximum flexibility across different deployment environments.

### Basic Configuration (.env.local)

```env
# Application Settings
APP_NAME="My Tiny Application"
APP_VERSION="1.0.0"
ENV=local
DEBUG=true
TINY_TIMEZONE=UTC

# Database Configuration
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_tiny_app
DB_USER=root
DB_PASS=

# Cache Configuration
TINY_CACHE_ENGINE=apcu
TINY_COOKIE_TTL=31536000
TINY_CURL_TIMEOUT=5

# Security Settings
CRYPTO_ALGO=aes-256-cbc
CRYPTO_SECRET=your_crypto_secret_key
CRYPTO_TTL=60

# Optional cookie overrides
# TINY_COOKIE_DOMAIN=localhost
# TINY_COOKIE_PATH=/

# Email Configuration (optional)
MAILGUN_API_KEY=your_mailgun_key
MAILGUN_DOMAIN=your_domain.com
MAILGUN_FROM_ADDRESS=noreply@your_domain.com
MAILGUN_FROM_NAME="Your App Name"

# Development Tools
TINY_DEBUG_WHITELIST="*"
TINY_MINIFY_OUTPUT=false
```

### Production Configuration (.env.prod)

```env
# Application Settings
APP_NAME="My Production App"
ENV=production
DEBUG=false
TINY_TIMEZONE=UTC

# Database Configuration
DB_TYPE=mysql
DB_HOST=db.production.com
DB_PORT=3306
DB_NAME=production_db
DB_USER=app_user
DB_PASS=secure_password

# Cache Configuration
CACHE_ENGINE=redis
REDIS_HOST=cache.production.com
REDIS_PORT=6379
REDIS_PASSWORD=redis_secure_password

# Security Settings
COOKIE_DOMAIN=.yourdomain.com
COOKIE_SECURE=true
COOKIE_HTTPONLY=true
COOKIE_SAMESITE=Strict
SESSION_TTL=7200

# Performance
OPCACHE_ENABLE=1
OPCACHE_VALIDATE_TIMESTAMPS=0

# Monitoring
SENTRY_DSN=https://your-sentry-dsn
LOG_LEVEL=error
```

## Your First Application

Let's build a simple but complete application to demonstrate Tiny's capabilities.

### 1. Create a Welcome Controller

```php
<?php
// app/controllers/welcome.php

class Welcome extends TinyController
{
    public function get($request, $response)
    {
        // Pass data to the view using render parameters
        $response->render('welcome', [
            'title' => 'Welcome to Tiny PHP',
            'message' => 'Your application is running successfully!',
            'features' => [
                'High Performance' => 'Optimized for speed and memory efficiency',
                'Zero Configuration' => 'Works out of the box with sensible defaults',
                'Production Ready' => 'Battle-tested in enterprise environments',
                'Developer Friendly' => 'Intuitive APIs and comprehensive documentation'
            ]
        ]);
    }
}
```

### 2. Create the Welcome View

```php
<?php
// app/views/welcome.php
// Variables passed from controller are automatically available
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px; 
            margin: 0 auto; 
            padding: 2rem;
            line-height: 1.6;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        .features { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .feature {
            padding: 1.5rem;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .feature h3 {
            margin-top: 0;
            color: #495057;
        }
        .info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
            border-left: 4px solid #2196f3;
        }
        .next-steps {
            background: #f1f8e9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
            border-left: 4px solid #4caf50;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
    </div>
    
    <div class="info">
        <strong>System Information:</strong><br>
        PHP Version: <?= htmlspecialchars(PHP_VERSION) ?><br>
        Environment: <?= htmlspecialchars($_SERVER['ENV'] ?? 'local') ?>
    </div>
    
    <h2>Why Choose Tiny PHP?</h2>
    <div class="features">
        <?php foreach ($features as $feature => $description): ?>
            <div class="feature">
                <h3><?= htmlspecialchars($feature) ?></h3>
                <p><?= htmlspecialchars($description) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="next-steps">
        <h3>🚀 Next Steps</h3>
        <ul>
            <li><strong>Explore the Documentation:</strong> Learn about <a href="/docs/core-concepts">Core Concepts</a></li>
            <li><strong>Build Your First Feature:</strong> Create controllers, models, and views</li>
            <li><strong>Add a Database:</strong> Set up migrations and data models</li>
            <li><strong>Deploy to Production:</strong> Configure for your hosting environment</li>
        </ul>
    </div>
</body>
</html>
```

### 3. Create a Database Migration

```php
<?php
// migrations/20240101000000_create_users_table.php

class CreateUsersTable
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS users");
    }
}
```

### 4. Test Your Application

```bash
# Run migrations
php tiny/cli migrate

# Start development server
php -S localhost:8000 -t html/

# Visit your application
open http://localhost:8000/welcome
```

## Development Workflow

### Daily Development Tasks

```bash
# Start development server with hot reloading
php tiny/cli serve --reload

# Create new controller
php tiny/cli make:controller UserProfile

# Create new model
php tiny/cli make:model User

# Create new migration
php tiny/cli make:migration create_posts_table

# Run tests
php tiny/cli test

# Check code quality
php tiny/cli lint
```

### Database Management

```bash
# Create migration
php tiny/cli migration create add_email_to_users

# Run migrations
php tiny/cli migrate

# Rollback last migration
php tiny/cli migrate rollback

# Reset database (caution: drops all data)
php tiny/cli migrate reset
```

## Next Steps

Now that you have Tiny PHP running, explore these topics:

1. **[Core Concepts](../core-concepts/readme.md)**: Learn MVC, routing, and architecture
2. **[Extensions](../extensions/readme.md)**: Discover built-in functionality
3. **[Examples](../examples/readme.md)**: Study real-world application patterns
4. **[Deployment](git-deploy.md)**: Deploy to production environments

### Recommended Learning Path

1. **Week 1**: Master routing, controllers, and views
2. **Week 2**: Learn models, database operations, and migrations
3. **Week 3**: Explore extensions (cache, HTTP client, scheduler)
4. **Week 4**: Build a complete application with authentication
5. **Week 5**: Deploy to production and optimize performance

### Community and Support

- **Documentation**: Comprehensive guides and API reference
- **Examples**: Production-ready application templates
- **GitHub Issues**: Bug reports and feature requests
- **Community**: Join the discussion and share your projects

Welcome to the Tiny PHP community! 🎉
