[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Getting Started with Tiny PHP

## Installation

1. Create a new directory for your project:

```bash
mkdir my-project && cd my-project
```

2. Clone the Tiny framework repository:

```bash
git clone https://github.com/ranaroussi/tiny.git .
```

3. Create the project structure:

```bash
php tiny/cli create
```

4. Install dependencies:

```bash
composer install
```

## Project Structure

After installation, your project structure will look like this:

```
/your-project
├── app/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   │   ├── components/
│   │   └── layouts/
│   └── middleware/
│   └── middleware.php
├── html/
│   └── index.php
├── migrations/
├── tiny/
├── vendor/
├── .env.example
├── composer.json
└── env.php
```

### Directory Structure Explained

- `app/`: Contains your application code
  - `controllers/`: Controller classes that handle requests
  - `models/`: Model classes for data and business logic
  - `views/`: View templates and components
  - `middleware/`: Request/response middleware
- `html/`: Public directory (document root)
- `migrations/`: Database migration files
- `tiny/`: The Tiny framework core
- `vendor/`: Composer dependencies
- `.env.example`: Environment configuration template
- `composer.json`: Composer configuration
- `env.php`: Environment detection

## Configuration

1. Copy `.env.example` to `.env.local` for local development:

```bash
cp .env.example .env.local
```

2. Edit `.env.local` with your configuration:

```env
# Application
SITE_NAME="My App"
DEBUG=true
DEBUG_WHITELIST=*
TIMEZONE=UTC

# Database
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=myapp
DB_USER=root
DB_PASS=
DB_PORT=3306

# Cache
CACHE_ENGINE=apcu
MEMCACHED_HOST=localhost
MEMCACHED_PORT=11211

# Security
COOKIE_DOMAIN=localhost
COOKIE_PATH=/
COOKIE_TTL=86400
```

3. Configure your web server to point to the `html` directory as the document root.

## Creating Your First Page

1. Create a controller (`app/controllers/hello.php`):

```php
<?php

class Hello extends TinyController
{
    public function get($request, $response)
    {
        tiny::data()->message = "Hello from Tiny PHP!";
        $response->render();
    }
}
```

2. Create a view (`app/views/hello.php`):

```php
<h1><?= tiny::data()->message ?></h1>
```

3. Access your page at `http://localhost/hello`

## Next Steps

Learn about [Core Concepts](../core-concepts)
