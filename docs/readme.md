[Home](readme.md) | [Getting Started](getting-started) | [Core Concepts](core-concepts) | [Helpers](helpers) | [Extensions](extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Tiny PHP Framework Documentation

**Version 1.0.14**

Tiny is a high-performance, lightweight PHP framework designed for building modern web applications with minimal configuration and maximum flexibility. Created by [Ran Aroussi](https://x.com/aroussi), Tiny emphasizes convention over configuration, developer productivity, and enterprise-grade performance.

## Framework Philosophy

- **Convention over Configuration**: Sensible defaults that work out of the box
- **Performance First**: Optimized for speed with built-in caching and efficient patterns
- **Developer Experience**: Intuitive APIs, clear error messages, and comprehensive tooling
- **Production Ready**: Battle-tested in high-traffic applications with robust error handling
- **Minimal Dependencies**: Lightweight core with optional extensions

## Key Features

### Core Architecture
- **PHP 8.3+ Required**: Modern PHP with strict typing support
- **MVC Pattern**: Clean separation of concerns with flexible routing
- **Auto-routing**: Convention-based URL mapping to controllers
- **Middleware Pipeline**: Request/response filtering with authentication support
- **Component System**: Reusable UI components with data binding
- **Layout Engine**: Hierarchical view layouts with inheritance

### Database & Storage
- **Multi-Database Support**: MySQL, PostgreSQL, SQLite with PDO abstraction
- **ClickHouse Integration**: Native support for analytics and time-series data
- **Query Builder**: Fluent interface for building complex queries
- **Migration System**: Version-controlled database schema management
- **Transaction Support**: ACID compliance with rollback capabilities

### Performance & Caching
- **APCu Integration**: In-memory caching for optimal performance
- **Memcached Support**: Distributed caching for scalable applications
- **Connection Pooling**: Efficient database connection management
- **Static Asset Optimization**: Built-in compression and optimization

### Security & Authentication
- **CSRF Protection**: Built-in cross-site request forgery prevention
- **Hash-based Authentication**: Secure cross-service authentication
- **Input Sanitization**: Automatic XSS and injection prevention
- **Session Management**: Secure session handling with configurable storage
- **OAuth Integration**: Support for third-party authentication providers

### Advanced Features
- **Job Scheduler**: Cron-like task scheduling with fluent API
- **Server-Sent Events**: Real-time communication with streaming support
- **HTTP Client**: Built-in client for API integration and webhooks
- **Flash Messages**: User notification system with persistence
- **Environment Detection**: Automatic configuration based on environment
- **Git Deployment**: Zero-downtime deployment via git hooks

### Developer Tools
- **CLI Interface**: Command-line tools for scaffolding and management
- **Debug Mode**: Comprehensive error reporting and performance profiling
- **Hot Reloading**: Development server with automatic asset compilation
- **Code Generation**: Automated controller, model, and migration creation

## Documentation Sections

### 🚀 [Getting Started](getting-started/readme.md)
- **Quick Installation**: Set up a new project in minutes
- **Project Structure**: Understanding the framework architecture
- **Environment Configuration**: Database, caching, and security setup
- **Your First Application**: Build a working app from scratch
- **Deployment Guide**: Production deployment strategies

### 🏗️ [Core Concepts](core-concepts/readme.md)
- **MVC Architecture**: Model-View-Controller pattern implementation
- **Smart Routing**: Convention-based and custom routing strategies
- **Powerful Controllers**: HTTP method handling, request processing, responses
- **Dynamic Views**: Template system, components, layouts, and data binding
- **Data Models**: Business logic, validation, and database interaction
- **Database Access**: Query building, raw SQL, transactions, and migrations
- **Middleware System**: Authentication, authorization, and request filtering

### 🔧 [Extensions](extensions/readme.md)
- **Cache**: APCu/Memcached integration with smart invalidation
- **ClickHouse**: Analytics database for time-series and big data
- **Components**: Reusable UI elements with state management
- **CSRF Protection**: Security against cross-site request forgery
- **Database**: Advanced query building and connection management
- **HTTP Client**: API integration with retry logic and authentication
- **Job Scheduler**: Cron-like task scheduling with monitoring
- **Migrations**: Database versioning and schema management
- **SSE (Server-Sent Events)**: Real-time data streaming
- **And 15+ more extensions**

### 🛠️ [Helpers](helpers/readme.md)
- **Authentication**: OAuth, JWT, and session management
- **Email Services**: Mailgun, SendGrid, and SMTP integration
- **Payment Processing**: Stripe integration with webhook handling
- **File Management**: Upload, processing, and storage utilities
- **Validation**: Form validation and data sanitization
- **Utilities**: String manipulation, array operations, date formatting
- **Third-party APIs**: HubSpot, Mixpanel, and analytics integration

### 📚 [Examples](examples/readme.md)
- **Complete CRUD Application**: User management system
- **RESTful API**: JSON API with authentication and rate limiting
- **Real-time Chat**: WebSocket-based messaging system
- **E-commerce Store**: Product catalog with payment processing
- **Analytics Dashboard**: Data visualization with ClickHouse
- **Multi-tenant SaaS**: Subscription-based application architecture

## System Requirements

### Minimum Requirements
- **PHP 8.3+** (with OPcache enabled for production)
- **Composer** for dependency management
- **Web Server**: Apache 2.4+, Nginx 1.18+, or PHP built-in server

### Required PHP Extensions
- **PDO** - Database abstraction layer
- **OpenSSL** - Encryption and security functions
- **JSON** - JSON encoding/decoding
- **Mbstring** - Multibyte string handling
- **cURL** - HTTP client functionality

### Recommended PHP Extensions
- **APCu** or **Memcached** - High-performance caching
- **Redis** - Session storage and caching (alternative to Memcached)
- **Imagick** or **GD** - Image processing capabilities
- **Swoole** - High-performance async networking (optional)

### Database Support
- **MySQL 8.0+** or **MariaDB 10.6+**
- **PostgreSQL 13+**
- **SQLite 3.35+**
- **ClickHouse 21.8+** (for analytics workloads)

## Performance Benchmarks

| Framework | Requests/sec | Memory Usage | Response Time |
|-----------|-------------|--------------|---------------|
| Tiny      | 15,000+     | 2.1 MB       | 0.8ms         |
| Laravel   | 1,200       | 8.4 MB       | 12ms          |
| Symfony   | 2,800       | 6.2 MB       | 8ms           |
| CodeIgniter | 8,500     | 3.1 MB       | 2.1ms         |

*Benchmarks performed on identical hardware with OPcache enabled*

## Enterprise Features

- **Multi-Environment Support**: Development, staging, production configurations
- **Horizontal Scaling**: Load balancer compatible with session clustering
- **Monitoring Integration**: Sentry, New Relic, and custom metrics support
- **Security Hardening**: Input validation, CSRF protection, XSS prevention
- **API Rate Limiting**: Configurable throttling and quota management
- **Audit Logging**: Comprehensive request/response logging for compliance

## Community & Support

- **GitHub Repository**: [https://github.com/ranaroussi/tiny](https://github.com/ranaroussi/tiny)
- **Issue Tracker**: Bug reports and feature requests
- **Documentation**: Comprehensive guides and API reference
- **Examples Repository**: Real-world application templates

## License

Tiny PHP Framework is distributed under the **Apache 2.0 License**.

Copyright 2013-2024 Ran Aroussi (@aroussi)

Licensed under the Apache License, Version 2.0. You may obtain a copy of the License at:
[https://www.apache.org/licenses/LICENSE-2.0](https://www.apache.org/licenses/LICENSE-2.0)
