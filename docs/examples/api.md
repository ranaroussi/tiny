[Home](../readme.md) | [Getting Started](getting-started.md) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Building an API

This example demonstrates how to build a RESTful API using Tiny PHP Framework.

## Project Structure

```
/your-project
├── app/
│   ├── controllers/
│   │   └── api/
│   │       ├── v1/
│   │       │   ├── auth.php
│   │       │   ├── users.php
│   │       │   └── posts.php
│   │       └── index.php
│   ├── models/
│   │   ├── user.php
│   │   └── post.php
│   └── middleware/
│       └── api.php
```

## API Authentication

```php
<?php
// app/controllers/api/v1/auth.php

class ApiV1Auth extends TinyController
{
    public function post($request, $response)
    {
        $credentials = $request->body(true);

        if (!tiny::auth()->validate($credentials)) {
            return $response->sendJSON([
                'error' => 'Invalid credentials'
            ], 401);
        }

        $token = tiny::auth()->createToken($user);

        return $response->sendJSON([
            'token' => $token,
            'user' => $user
        ]);
    }
}
```

## API Middleware

```php
<?php
// app/middleware/api.php

return [
    'api/*' => function($request, $response) {
        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');

        // Rate limiting
        if (!tiny::rateLimit()->allow('api', 60, 100)) {
            return $response->sendJSON([
                'error' => 'Too many requests'
            ], 429);
        }

        // Token validation
        if (!$request->bearerToken()) {
            return $response->sendJSON([
                'error' => 'Unauthorized'
            ], 401);
        }

        // Validate token
        if (!tiny::auth()->validateToken($request->bearerToken())) {
            return $response->sendJSON([
                'error' => 'Invalid token'
            ], 401);
        }
    }
];
```

## Resource Controller

```php
<?php
// app/controllers/api/v1/posts.php

class ApiV1Posts extends TinyController
{
    private $model;

    public function __construct()
    {
        $this->model = tiny::model('post');
    }

    // GET /api/v1/posts
    public function get($request, $response)
    {
        $page = $request->query->page ?? 1;
        $limit = $request->query->limit ?? 10;

        $posts = $this->model->paginate($page, $limit);

        return $response->sendJSON([
            'data' => $posts->items,
            'meta' => [
                'current_page' => $posts->currentPage,
                'total_pages' => $posts->totalPages,
                'total_items' => $posts->totalItems
            ]
        ]);
    }

    // POST /api/v1/posts
    public function post($request, $response)
    {
        $data = $request->body(true);

        if (!$this->model->validate($data)) {
            return $response->sendJSON([
                'error' => 'Validation failed',
                'errors' => $this->model->errors
            ], 422);
        }

        $post = $this->model->create($data);

        return $response->sendJSON([
            'data' => $post
        ], 201);
    }

    // PATCH /api/v1/posts/{id}
    public function patch($request, $response)
    {
        $id = $request->path->slug;
        $data = $request->body(true);

        if (!$this->model->update($id, $data)) {
            return $response->sendJSON([
                'error' => 'Update failed'
            ], 400);
        }

        return $response->sendJSON([
            'data' => $this->model->find($id)
        ]);
    }

    // DELETE /api/v1/posts/{id}
    public function delete($request, $response)
    {
        $id = $request->path->slug;

        if (!$this->model->delete($id)) {
            return $response->sendJSON([
                'error' => 'Delete failed'
            ], 400);
        }

        return $response->sendJSON(null, 204);
    }
}
```

## Error Handling

```php
<?php
// app/controllers/api/error.php

class ApiError extends TinyController
{
    public function get($request, $response)
    {
        $error = [
            'error' => $response->getError(),
            'code' => $response->getCode()
        ];

        if (tiny::debug()->isEnabled()) {
            $error['trace'] = $response->getTrace();
        }

        return $response->sendJSON($error, $response->getCode());
    }
}
```

## API Documentation

Create an OpenAPI specification:

```yaml
openapi: 3.0.0
info:
  title: Tiny PHP API
  version: 1.0.0
paths:
  /api/v1/posts:
    get:
      summary: List posts
      parameters:
        - name: page
          in: query
          schema:
            type: integer
        - name: limit
          in: query
          schema:
            type: integer
      responses:
        200:
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Post'
```

## Testing

```php
<?php
// tests/api/PostsTest.php

class PostsTest extends TestCase
{
    public function testListPosts()
    {
        $response = $this->get('/api/v1/posts', [
            'Authorization' => 'Bearer ' . $this->getTestToken()
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertIsArray($response->json()->data);
    }

    public function testCreatePost()
    {
        $response = $this->post('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test content'
        ], [
            'Authorization' => 'Bearer ' . $this->getTestToken()
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Test Post', $response->json()->data->title);
    }
}
```

## Features Demonstrated

- RESTful routing
- API authentication
- Rate limiting
- Request validation
- Response formatting
- Error handling
- API documentation
- Testing
- Middleware usage
- Database operations

## Best Practices

1. **Versioning**
   - Version your API endpoints
   - Maintain backward compatibility
   - Document breaking changes
   - Use semantic versioning

2. **Security**
   - Implement rate limiting
   - Validate all input
   - Use proper authentication
   - Set CORS headers

3. **Response Format**
   - Use consistent structure
   - Include metadata
   - Handle errors uniformly
   - Follow HTTP standards

4. **Documentation**
   - Keep docs up-to-date
   - Provide examples
   - Document all endpoints
   - Include error responses
