[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# User management

This example wires up the pieces of a typical auth flow — registration, login, logout, profile update, password reset — using only Tiny primitives and PHP's standard library. There is no `tiny::auth()` magic: identity is just a row in `users` plus a session cookie.

## Project structure

```
my-app/
├── app/
│   ├── controllers/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   ├── password.php           # /password and /password/reset/<token>
│   │   └── profile.php
│   ├── middleware/
│   │   └── auth.php
│   ├── middleware.php
│   ├── models/
│   │   └── user.php
│   └── views/
│       ├── auth/
│       │   ├── login.php
│       │   ├── register.php
│       │   ├── password.php
│       │   └── password-reset.php
│       └── profile.php
└── migrations/
    └── 20240101_users.php
```

## Tables

```php
<?php
// migrations/20240101_users.php
class Users extends TinyMigration
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE users (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                email           VARCHAR(255) UNIQUE NOT NULL,
                password_hash   VARCHAR(255) NOT NULL,
                name            VARCHAR(255) NOT NULL,
                role            ENUM('user', 'admin') DEFAULT 'user',
                status          ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        tiny::db()->execute("
            CREATE TABLE password_resets (
                token       VARCHAR(64) PRIMARY KEY,
                user_id     INT NOT NULL,
                expires_at  DATETIME NOT NULL,
                INDEX idx_user (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }
    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS password_resets");
        tiny::db()->execute("DROP TABLE IF EXISTS users");
    }
}
```

## Model

`app/models/user.php`:

```php
<?php

class UserModel extends TinyModel
{
    public function findByEmail(string $email): ?object
    {
        return tiny::db()->getOne('users', ['email' => $email]);
    }

    public function findById(int $id): ?object
    {
        return tiny::db()->getOne('users', ['id' => $id]);
    }

    public function create(array $data): int|false
    {
        $errors = $this->validateRegistration($data);
        if ($errors) {
            tiny::flash('form-errors')->set($errors);
            return false;
        }

        return tiny::db()->insert('users', [
            'email'         => mb_strtolower($data['email']),
            'name'          => trim($data['name']),
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $patch = array_intersect_key($data, ['name' => true, 'email' => true]);

        if (!empty($data['password'])) {
            if (mb_strlen($data['password']) < 8) {
                tiny::flash('form-errors')->set(['password' => 'must be at least 8 characters']);
                return false;
            }
            $patch['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return tiny::db()->update('users', $patch, ['id' => $id]);
    }

    public function verify(string $email, string $password): ?object
    {
        $user = $this->findByEmail(mb_strtolower($email));
        if (!$user || $user->status !== 'active') return null;
        if (!password_verify($password, $user->password_hash)) return null;
        return $user;
    }

    public function startPasswordReset(int $userId, int $ttlSeconds = 3600): string
    {
        $token = bin2hex(random_bytes(32));
        tiny::db()->insert('password_resets', [
            'token'      => $token,
            'user_id'    => $userId,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
        return $token;
    }

    public function completePasswordReset(string $token, string $newPassword): bool
    {
        $row = tiny::db()->getOne('password_resets', ['token' => $token]);
        if (!$row) return false;
        if (strtotime($row->expires_at) < time()) return false;
        if (mb_strlen($newPassword) < 8) return false;

        tiny::db()->update('users',
            ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)],
            ['id' => $row->user_id]
        );
        tiny::db()->delete('password_resets', ['token' => $token]);
        return true;
    }

    private function validateRegistration(array $data): array
    {
        $errors = [];
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'invalid email';
        } elseif ($this->findByEmail(mb_strtolower($data['email']))) {
            $errors['email'] = 'already registered';
        }
        if (empty($data['name'])) {
            $errors['name'] = 'required';
        }
        if (mb_strlen($data['password'] ?? '') < 8) {
            $errors['password'] = 'must be at least 8 characters';
        }
        return $errors;
    }
}
```

## Middleware

`app/middleware/auth.php` — runs for every request and exposes `tiny::user()` when a session is active:

```php
<?php

class AuthMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) return;

        $user = tiny::model('user')->findById((int)$_SESSION['user_id']);
        if ($user && $user->status === 'active') {
            tiny::user($user);
        } else {
            unset($_SESSION['user_id']);
        }
    }
}
```

Register in `app/middleware.php`:

```php
<?php
tiny::middleware('auth');
```

Controllers that require login enforce it themselves:

```php
private function requireLogin($response): void
{
    if (!tiny::user()) {
        $response->redirect('/login');
    }
}
```

Or, even simpler, check at the top of `get`/`post`:

```php
if (!tiny::user()) return $response->redirect('/login');
```

## Auth controllers

`app/controllers/register.php`:

```php
<?php

class Register extends TinyController
{
    public function get($request, $response)
    {
        $response->render('auth/register');
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }

        $id = tiny::model('user')->create($request->body(true));
        if ($id === false) {
            return $response->redirect('/register');
        }

        $_SESSION['user_id'] = $id;
        tiny::flash('toast')->set(['level' => 'success', 'message' => 'Welcome!']);
        $response->redirect('/profile');
    }
}
```

`app/controllers/login.php`:

```php
<?php

class Login extends TinyController
{
    public function get($request, $response)
    {
        if (tiny::user()) return $response->redirect('/profile');
        $response->render('auth/login');
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }

        $body = $request->body(true);

        // Throttle by IP to slow down credential-stuffing.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!tiny::rateLimiter('login', 10, 60)->check($ip)) {
            tiny::flash('toast')->set(['level' => 'error', 'message' => 'Too many attempts. Try again in a minute.']);
            return $response->redirect('/login');
        }

        $user = tiny::model('user')->verify($body['email'] ?? '', $body['password'] ?? '');
        if (!$user) {
            tiny::flash('toast')->set(['level' => 'error', 'message' => 'Invalid email or password.']);
            return $response->redirect('/login');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;

        $response->redirect('/profile');
    }
}
```

`app/controllers/logout.php`:

```php
<?php

class Logout extends TinyController
{
    public function get($request, $response)   { $this->post($request, $response); }

    public function post($request, $response)
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        $response->redirect('/');
    }
}
```

## Password reset

`app/controllers/password.php`:

```php
<?php

class Password extends TinyController
{
    public function get($request, $response)
    {
        // /password               → request form
        // /password/reset/<token> → reset form
        if ($request->path->section === 'reset' && $request->path->slug) {
            return $response->render('auth/password-reset', ['token' => $request->path->slug]);
        }
        $response->render('auth/password');
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }
        $body = $request->body(true);

        // /password           → start flow (send the email)
        if (!$request->path->section) {
            $user = tiny::model('user')->findByEmail(mb_strtolower($body['email'] ?? ''));
            if ($user) {
                $token = tiny::model('user')->startPasswordReset($user->id);
                $link  = tiny::getHomeURL('password/reset/' . $token, true);

                // If TINY_MAILGUN_API_KEY is configured:
                if (!empty($_SERVER['TINY_MAILGUN_API_KEY'])) {
                    tiny::mailgun()->send($user->email,
                        'Reset your password',
                        "Use this link within 1 hour: $link");
                } else {
                    tiny::log("Password reset link for {$user->email}: $link");
                }
            }
            // Don't reveal whether the email exists.
            tiny::flash('toast')->set(['level' => 'info', 'message' => 'If that email is registered, a reset link has been sent.']);
            return $response->redirect('/login');
        }

        // /password/reset/<token> → complete the reset
        if ($request->path->section === 'reset' && $request->path->slug) {
            $ok = tiny::model('user')->completePasswordReset(
                $request->path->slug,
                $body['password'] ?? ''
            );
            if (!$ok) {
                tiny::flash('toast')->set(['level' => 'error', 'message' => 'Invalid or expired reset link.']);
                return $response->redirect('/password');
            }
            tiny::flash('toast')->set(['level' => 'success', 'message' => 'Password updated. Sign in below.']);
            return $response->redirect('/login');
        }
    }
}
```

## Profile

`app/controllers/profile.php`:

```php
<?php

class Profile extends TinyController
{
    public function get($request, $response)
    {
        if (!tiny::user()) return $response->redirect('/login');
        $response->render('profile', ['user' => tiny::user()]);
    }

    public function post($request, $response)
    {
        if (!tiny::user()) return $response->redirect('/login');
        if (!$request->isValidCSRF()) return $response->hasCSRFError();

        if (!tiny::model('user')->updateProfile(tiny::user()->id, $request->body(true))) {
            return $response->redirect('/profile');
        }

        tiny::flash('toast')->set(['level' => 'success', 'message' => 'Profile updated']);
        $response->redirect('/profile');
    }
}
```

## Role check (admin pages)

Roles are a single column. Gate handlers explicitly:

```php
public function get($request, $response)
{
    if (!tiny::user() || tiny::user()->role !== 'admin') {
        return tiny::controller('404', true);
    }
    // ...
}
```

For multi-role setups, swap the `role` column for a `user_roles` join table and adapt the check.

## Views

`app/views/auth/register.php`:

```php
<?php Layout::main(['title' => 'Create an account']); ?>

    <h1>Create an account</h1>
    <?php $err = tiny::flash('form-errors')->get() ?? []; ?>

    <form method="POST" action="/register">
        <?php tiny::csrf()->input(); ?>

        <label>Name <input name="name" required></label>
        <?php if (isset($err['name'])): ?><small class="error"><?= htmlspecialchars($err['name']) ?></small><?php endif ?>

        <label>Email <input type="email" name="email" required></label>
        <?php if (isset($err['email'])): ?><small class="error"><?= htmlspecialchars($err['email']) ?></small><?php endif ?>

        <label>Password <input type="password" name="password" minlength="8" required></label>
        <?php if (isset($err['password'])): ?><small class="error"><?= htmlspecialchars($err['password']) ?></small><?php endif ?>

        <button>Sign up</button>
    </form>

<?php Layout::main(); ?>
```

`app/views/profile.php`:

```php
<?php Layout::main(['title' => 'Your profile']); ?>

    <h1>Your profile</h1>

    <?php $toast = tiny::flash('toast')->get(); ?>
    <?php if ($toast): ?>
        <div class="alert alert-<?= htmlspecialchars($toast['level']) ?>"><?= htmlspecialchars($toast['message']) ?></div>
    <?php endif ?>

    <form method="POST" action="/profile">
        <?php tiny::csrf()->input(); ?>
        <label>Name  <input name="name"  value="<?= htmlspecialchars($user->name) ?>"></label>
        <label>Email <input name="email" value="<?= htmlspecialchars($user->email) ?>"></label>
        <label>New password (leave blank to keep) <input type="password" name="password" minlength="8"></label>
        <button>Save</button>
    </form>

    <form method="POST" action="/logout">
        <?php tiny::csrf()->input(); ?>
        <button>Sign out</button>
    </form>

<?php Layout::main(); ?>
```

(Login and password-reset views follow the same shape — a CSRF input plus the relevant fields.)

## Best practices

1. **Use `password_hash` / `password_verify`** with `PASSWORD_DEFAULT`. Re-hash on login if `password_needs_rehash()` returns true.
2. **`session_regenerate_id(true)` on login.** Stops session-fixation attacks.
3. **Throttle login attempts by IP.** A 10-per-minute limit (as above) costs almost nothing.
4. **Don't reveal account existence** in the password-reset flow. Always show the same confirmation.
5. **Expire reset tokens.** One-hour TTL is standard; delete the row on use.
6. **CSRF every POST.** The `$request->isValidCSRF()` check should be the first thing in every state-changing handler.
7. **Lowercase emails for comparison** (`mb_strtolower`). Users won't notice; you'll avoid duplicate accounts.
8. **Keep the user table flat.** Hang preferences, addresses, OAuth links etc. off it in joined tables.
