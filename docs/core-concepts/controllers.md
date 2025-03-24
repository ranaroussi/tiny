[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Controllers

Controllers in Tiny handle HTTP requests and manage the application's flow. They are responsible for processing input, interacting with models, and preparing data for views.

## Basic Structure

Controllers should be placed in the `app/controllers` directory and extend the `TinyController` class:

```php
<?php

class Users extends TinyController
{
    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = tiny::model('user');
    }

    public function get($request, $response)
    {
        $users = $this->model->getAll();
        tiny::data()->users = $users;
        $response->render();
    }
}
```

## HTTP Methods

Controllers automatically map HTTP methods to corresponding methods:

```php
class Users extends TinyController
{
    // GET /users
    public function get($request, $response)
    {
        $response->render();
    }

    // POST /users
    public function post($request, $response)
    {
        $data = $request->body(true);
        // Handle creation
    }

    // PATCH /users
    public function patch($request, $response)
    {
        $data = $request->body(true);
        // Handle update
    }

    // DELETE /users
    public function delete($request, $response)
    {
        // Handle deletion
    }
}
```

## Request Handling

The `$request` object provides access to request data:

```php
public function get($request, $response)
{
    // URL parameters
    $section = $request->path->section;
    $slug = $request->path->slug;

    // Query parameters
    $page = $request->query['page'] ?? 1;

    // POST/PUT data
    $data = $request->body(true); // true for associative array

    // Files
    $file = $request->files->upload;

    // Headers
    $token = $request->headers['Authorization'];

    // CSRF validation
    if (!$request->isValidCSRF()) {
        $response->hasCSRFError();
        return $response->render();
    }
}
```

## Response Handling

The `$response` object provides methods for sending responses:

```php
public function get($request, $response)
{
    // Render default view
    $response->render();

    // Render specific view
    $response->render('users/profile');

    // Send JSON
    $response->sendJSON([
        'status' => 'success',
        'data' => $data
    ]);

    // Redirect
    $response->redirect('/');

    // Redirect back with error
    $response->back()->withError('Invalid input');

    // Set status code
    $response->sendJSON($data, 201);
}
```

## Data Sharing

Share data with views using `tiny::data()`:

```php
public function get($request, $response)
{
    // Share single value
    tiny::data()->user = $this->model->getUser();

    // Share multiple values
    tiny::data()->merge([
        'user' => $user,
        'posts' => $posts,
        'comments' => $comments
    ]);

    $response->render();
}
```

## Flash Messages

Send temporary messages to the user:

```php
public function post($request, $response)
{
    if ($this->model->create($data)) {
        tiny::flash('toast')->set([
            'level' => 'success',
            'message' => 'User created successfully'
        ]);
        return $response->redirect('/users');
    }
}
```

## Best Practices

1. **Keep Controllers Thin**
   - Move business logic to models
   - Keep methods focused and small
   - Use dependency injection

2. **Security**
   - Always validate CSRF tokens for state-changing operations
   - Sanitize input data
   - Use proper HTTP methods

3. **Response Types**
   - Use appropriate status codes
   - Format JSON responses consistently
   - Handle errors gracefully

4. **Organization**
   - Group related controllers in subdirectories
   - Use meaningful names
   - Follow RESTful conventions

5. **Error Handling**
   - Use try-catch blocks for risky operations
   - Provide meaningful error messages
   - Log errors appropriately
