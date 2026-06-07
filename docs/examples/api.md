[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Building a JSON API

This example builds a small versioned JSON API with token authentication, rate limiting, structured errors, and CORS. It uses only first-party Tiny primitives — no extra packages.

## Project structure

```
my-app/
├── app/
│   ├── controllers/
│   │   └── api/
│   │       └── v1/
│   │           ├── auth.php          # POST /api/v1/auth
│   │           └── posts.php         # /api/v1/posts[/<id>]
│   ├── middleware/
│   │   ├── api-cors.php
│   │   └── api-auth.php
│   ├── middleware.php
│   └── models/
│       ├── user.php
│       └── post.php
└── migrations/
    └── 20240101_api_tokens.php
```

## Tokens table

```php
<?php
// migrations/20240101_api_tokens.php
class ApiTokens extends TinyMigration
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE api_tokens (
                token       VARCHAR(64) PRIMARY KEY,
                user_id     INT NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_seen   TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }
    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS api_tokens");
    }
}
```

## Auth endpoint

`app/controllers/api/v1/auth.php`:

```php
<?php

class ApiV1Auth extends TinyController
{
    public function post($request, $response)
    {
        $body  = $request->body(true);
        $email = $body['email']    ?? '';
        $pass  = $body['password'] ?? '';

        if (!$email || !$pass) {
            return $response->sendJSON(['error' => 'email and password required'], 400);
        }

        $user = tiny::db()->getOne('users', ['email' => $email]);
        if (!$user || !password_verify($pass, $user->password_hash)) {
            return $response->sendJSON(['error' => 'invalid credentials'], 401);
        }

        $token = bin2hex(random_bytes(32));
        tiny::db()->insert('api_tokens', [
            'token'   => $token,
            'user_id' => $user->id,
        ]);

        $response->sendJSON([
            'token' => $token,
            'user'  => ['id' => $user->id, 'email' => $user->email, 'name' => $user->name],
        ], 201);
    }
}
```

Note: passwords are verified with PHP's stdlib `password_verify()` — no framework helper required.

## Middleware

`app/middleware/api-cors.php`:

```php
<?php

class ApiCorsMiddleware
{
    public function handle(): void
    {
        $request = tiny::request();
        if (!str_starts_with($request->path->full, '/api/')) {
            return;
        }

        tiny::header('Access-Control-Allow-Origin: *');
        tiny::header('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token');
        tiny::header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

        if ($request->method === 'OPTIONS') {
            tiny::response()->send('', 204);
        }
    }
}
```

`app/middleware/api-auth.php`:

```php
<?php

class ApiAuthMiddleware
{
    public function handle(): void
    {
        $request  = tiny::request();
        $response = tiny::response();

        // Skip CORS pre-flight and the auth endpoint itself.
        if (!str_starts_with($request->path->full, '/api/')) return;
        if ($request->path->full === '/api/v1/auth')          return;

        // Rate limit by client IP (100 req / 60s).
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!tiny::rateLimiter('api', 100, 60)->check($ip)) {
            return $response->sendJSON(['error' => 'rate limit exceeded'], 429);
        }

        // Bearer token.
        $auth  = $request->headers['Authorization'] ?? '';
        $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : null;

        if (!$token) {
            return $response->sendJSON(['error' => 'missing bearer token'], 401);
        }

        $row = tiny::db()->getOne('api_tokens', ['token' => $token]);
        if (!$row) {
            return $response->sendJSON(['error' => 'invalid token'], 401);
        }

        $user = tiny::db()->getOne('users', ['id' => $row->user_id]);
        if (!$user) {
            return $response->sendJSON(['error' => 'user not found'], 401);
        }

        // Attach the user so controllers can read `tiny::user()`.
        tiny::user($user);

        // Touch last_seen.
        tiny::db()->update('api_tokens', ['last_seen' => date('Y-m-d H:i:s')], ['token' => $token]);
    }
}
```

Register both in `app/middleware.php`:

```php
<?php
// order matters: CORS first (handles OPTIONS pre-flight), then auth
tiny::middleware('api-cors');
tiny::middleware('api-auth');
```

## Resource controller

`app/controllers/api/v1/posts.php`:

```php
<?php

class ApiV1Posts extends TinyController
{
    public function get($request, $response)
    {
        // /api/v1/posts/<id> → single
        if ($request->path->section) {
            $post = tiny::db()->getOne('posts', ['id' => (int)$request->path->section]);
            if (!$post) {
                return $response->sendJSON(['error' => 'not found'], 404);
            }
            return $response->sendJSON(['data' => $post]);
        }

        // /api/v1/posts?page=&limit=
        $page  = max(1, (int)$request->params('page', 1));
        $limit = min(100, max(1, (int)$request->params('limit', 20)));
        $offset = ($page - 1) * $limit;

        $pdo  = tiny::db()->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT :l OFFSET :o");
        $stmt->bindValue('l', $limit,  \PDO::PARAM_INT);
        $stmt->bindValue('o', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows  = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $total = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();

        $response->sendJSON([
            'data' => $rows,
            'meta' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ]);
    }

    public function post($request, $response)
    {
        $data    = $request->body(true);
        $errors  = $this->validate($data);
        if ($errors) {
            return $response->sendJSON(['error' => 'validation failed', 'errors' => $errors], 422);
        }

        $data['user_id']    = tiny::user()->id;
        $data['created_at'] = date('Y-m-d H:i:s');

        $id = tiny::db()->insert('posts', $data);
        $post = tiny::db()->getOne('posts', ['id' => $id]);

        $response->sendJSON(['data' => $post], 201);
    }

    public function patch($request, $response)
    {
        $id = (int)$request->path->section;
        if (!$id) {
            return $response->sendJSON(['error' => 'id required'], 400);
        }

        $existing = tiny::db()->getOne('posts', ['id' => $id]);
        if (!$existing) {
            return $response->sendJSON(['error' => 'not found'], 404);
        }
        if ($existing->user_id !== tiny::user()->id) {
            return $response->sendJSON(['error' => 'forbidden'], 403);
        }

        $data = $request->body(true);
        tiny::db()->update('posts', $data, ['id' => $id]);

        $response->sendJSON(['data' => tiny::db()->getOne('posts', ['id' => $id])]);
    }

    public function delete($request, $response)
    {
        $id = (int)$request->path->section;
        $existing = tiny::db()->getOne('posts', ['id' => $id]);
        if (!$existing) {
            return $response->sendJSON(['error' => 'not found'], 404);
        }
        if ($existing->user_id !== tiny::user()->id) {
            return $response->sendJSON(['error' => 'forbidden'], 403);
        }

        tiny::db()->delete('posts', ['id' => $id]);
        $response->send('', 204);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['title'])) {
            $errors['title'] = 'title is required';
        } elseif (mb_strlen($data['title']) > 255) {
            $errors['title'] = 'title is too long';
        }
        if (empty($data['body'])) {
            $errors['body'] = 'body is required';
        }
        return $errors;
    }
}
```

URLs:

| Method | Path | Action |
|---|---|---|
| `POST` | `/api/v1/auth` | Issue a bearer token |
| `GET`  | `/api/v1/posts` | List posts (paginated) |
| `GET`  | `/api/v1/posts/<id>` | Fetch one |
| `POST` | `/api/v1/posts` | Create |
| `PATCH`| `/api/v1/posts/<id>` | Update (owner only) |
| `DELETE`| `/api/v1/posts/<id>` | Delete (owner only) |

## Calling the API

```bash
# Get a token
curl -X POST http://localhost/api/v1/auth \
  -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.com","password":"hunter2"}'

# Use it
curl http://localhost/api/v1/posts \
  -H 'Authorization: Bearer <token>'

# Create
curl -X POST http://localhost/api/v1/posts \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{"title":"Hello","body":"World"}'
```

## A consistent error envelope

Every error response above has the shape:

```json
{ "error": "<reason>" }
```

…with validation errors adding an `errors` map keyed by field. Stick to a single shape and clients will be much easier to write.

## Best practices

1. **Version under `/api/v<n>/`** — the filesystem router makes this free.
2. **Bearer tokens, not session cookies** — APIs should be stateless. Store tokens in a table you can revoke from.
3. **Rate limit early** — at the start of the auth middleware, before any DB work.
4. **Never return `password_hash` or similar columns** — select explicitly, or strip in the controller.
5. **`Content-Type: application/json` everywhere** — set by `sendJSON()`, but verify it isn't overridden by other middleware.
6. **Validate input, don't sanitize.** Reject bad shapes with `422`; don't silently coerce.
7. **CORS pre-flight** — return `204` immediately on `OPTIONS`, before auth checks.
