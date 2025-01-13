[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Understanding Routing

Tiny PHP uses a simple and intuitive routing system based on the file structure in your `app/controllers` directory.

## Basic Routing

Routes are automatically created based on your controller file structure:

```
app/controllers/
├── home.php          -> /
├── about.php         -> /about
├── blog.php          -> /blog
└── user/
    ├── profile.php   -> /user/profile
    └── settings.php  -> /user/settings
```

## Controller Structure

```php
<?php
// app/controllers/blog.php

class Blog extends TinyController
{
    // Handle GET requests
    public function get($request, $response)
    {
        $response->render();
    }

    // Handle POST requests
    public function post($request, $response)
    {
        // Handle form submission
    }

    // Handle DELETE requests
    public function delete($request, $response)
    {
        // Handle deletion
    }
}
```

## URL Parameters

Access URL segments through the request object:

```php
public function get($request, $response)
{
    // URL: /blog/2024/03
    $year = $request->path->year;   // 2024
    $month = $request->path->month; // 03

    // URL: /blog/my-post-slug
    $slug = $request->path->slug;
}
```

## Query Parameters

Access query string parameters:

```php
public function get($request, $response)
{
    // URL: /search?q=php&category=tutorials
    $query = $request->query->q;        // "php"
    $category = $request->query->category; // "tutorials"
}
```

## Request Methods

Tiny supports all standard HTTP methods:

```php
class Article extends TinyController
{
    // GET /article
    public function get($request, $response)
    {
        // List articles or show single article
    }

    // POST /article
    public function post($request, $response)
    {
        // Create new article
    }

    // PATCH /article/123
    public function patch($request, $response)
    {
        // Update article
    }

    // DELETE /article/123
    public function delete($request, $response)
    {
        // Delete article
    }
}
```

## Response Handling

```php
public function get($request, $response)
{
    // Render view
    $response->render();

    // Render specific view
    $response->render('blog/post');

    // Redirect
    $response->redirect('/dashboard');

    // JSON response
    $response->sendJSON([
        'status' => 'success',
        'data' => $data
    ]);

    // Custom status code
    $response->sendJSON($data, 201);
}
```

## Middleware

Apply middleware to your routes:

```php
<?php
// app/middleware.php

return [
    'auth' => [
        '/dashboard',
        '/settings',
        '/profile'
    ],
    'admin' => [
        '/admin/*'
    ]
];
```

## Error Handling

Create custom error pages:

```php
// app/controllers/404.php
class NotFound extends TinyController
{
    public function get($request, $response)
    {
        $response->render('errors/404');
    }
}
```

## Best Practices

1. **Use RESTful Routes**
   - GET for reading
   - POST for creating
   - PATCH for updating
   - DELETE for removing

2. **Keep Controllers Focused**
   - One responsibility per controller
   - Move business logic to models
   - Keep methods small and clear

3. **Handle Errors Gracefully**
   - Provide meaningful error messages
   - Use appropriate status codes
   - Log important errors

4. **Secure Routes**
   - Use middleware for authentication
   - Validate CSRF tokens
   - Sanitize input data
