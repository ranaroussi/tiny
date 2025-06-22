[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Core Concepts

Tiny PHP Framework implements a modern, performance-oriented architecture built on proven patterns. Understanding these core concepts will help you build robust, maintainable applications that scale from prototypes to enterprise systems.

## Framework Philosophy

Tiny is designed around these principles:
- **Convention over Configuration**: Sensible defaults that reduce boilerplate
- **Performance by Design**: Every component optimized for speed and memory efficiency
- **Developer Happiness**: Intuitive APIs that make common tasks simple
- **Production Ready**: Enterprise-grade features built into the core

## Architecture Overview

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   HTTP Request  │───▶│    Middleware    │───▶│   Controller    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                         │
                                                         ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  HTTP Response  │◄───│      View        │◄───│      Model      │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │                        │
                                ▼                        ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │   Components     │    │    Database     │
                       └──────────────────┘    └─────────────────┘
```

## Core Components Deep Dive

### 1. [MVC Architecture](mvc.md)
**The Foundation of Clean Code**
- Modern MVC implementation with clear separation of concerns
- Dependency injection and inversion of control patterns
- Service layer architecture for complex business logic
- Best practices for scalable application design
- Performance optimizations and caching strategies

### 2. [Smart Routing](routing.md)
**Convention-Based URL Mapping**
- Zero-configuration auto-routing based on file structure
- Custom route definitions with parameter validation
- RESTful resource routing with automatic CRUD operations
- Route caching for high-performance applications
- Middleware attachment and route groups
- Subdomain and API versioning support

### 3. [Powerful Controllers](controllers.md)
**Request Processing and Business Logic**
- HTTP method mapping (GET, POST, PUT, DELETE, PATCH)
- Request validation and sanitization
- Response formatting (JSON, XML, HTML, streams)
- Error handling and exception management
- Dependency injection and service location
- Testing strategies and mocking

### 4. [Dynamic Views & Components](views.md)
**Modern Template System**
- PHP-based templating with layout inheritance
- Reusable component architecture with props and slots
- Data binding and automatic escaping for security
- Conditional rendering and loops
- Asset management and optimization
- Server-side rendering with client-side hydration

### 5. [Data Models](models.md)
**Business Logic and Data Access**
- Active Record pattern with query builder integration
- Model relationships (one-to-one, one-to-many, many-to-many)
- Data validation and sanitization rules
- Event hooks (creating, created, updating, updated, deleting)
- Caching strategies and performance optimization
- Testing and mocking data layers

### 6. [Database Access](database.md)
**Multi-Database Support and Query Building**
- MySQL, PostgreSQL, SQLite, and ClickHouse support
- Fluent query builder with method chaining
- Raw SQL with parameter binding and prepared statements
- Database transactions with rollback support
- Connection pooling and read/write splitting
- Migration system with version control
- Performance monitoring and query optimization

### 7. [Middleware System](middleware.md)
**Request/Response Pipeline**
- Pre and post-request processing
- Authentication and authorization layers
- Rate limiting and throttling
- CORS handling and security headers
- Request logging and monitoring
- Custom middleware development
- Performance impact and optimization

## Performance Considerations

### Memory Management
- Object pooling for frequently used instances
- Lazy loading of extensions and helpers
- Efficient data structures and algorithms
- Memory profiling and optimization techniques

### Caching Strategies
- **OPcache**: Bytecode caching for improved performance
- **APCu**: In-memory data caching with TTL support
- **Memcached**: Distributed caching for scalable applications
- **Query Caching**: Database query result caching
- **View Caching**: Compiled template caching

### Database Optimization
- Connection pooling and persistent connections
- Query optimization and index usage
- Bulk operations and batch processing
- Read replica support for read-heavy workloads

## Security Features

### Input Validation
- Automatic XSS prevention with output escaping
- SQL injection protection with parameterized queries
- CSRF token validation for state-changing operations
- File upload security with type validation

### Authentication & Authorization
- Session-based authentication with secure cookies
- JWT token support for API authentication
- Role-based access control (RBAC)
- OAuth integration for third-party authentication

## Development Workflow

### Hot Reloading
- Automatic asset compilation and browser refresh
- Development server with debugging enabled
- Error reporting with stack traces and context

### Testing Integration
- Unit testing with PHPUnit integration
- HTTP testing for controllers and routes
- Database testing with transactions and rollbacks
- Mocking and stubbing for external dependencies

### Debugging Tools
- SQL query logging and performance analysis
- Request/response debugging with detailed timing
- Memory usage profiling and leak detection
- Error reporting with context and stack traces

---

## Next Steps

1. **Start with [MVC Architecture](mvc.md)** to understand the framework's foundation
2. **Learn [Routing](routing.md)** to map URLs to your application logic
3. **Build [Controllers](controllers.md)** to handle HTTP requests effectively
4. **Create [Views](views.md)** for dynamic, responsive user interfaces
5. **Design [Models](models.md)** for robust data management
6. **Master [Database Access](database.md)** for efficient data operations
7. **Implement [Middleware](middleware.md)** for cross-cutting concerns

Each section includes practical examples, best practices, and real-world use cases to help you build production-ready applications.
