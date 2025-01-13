[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# User Management Example

This example demonstrates how to implement a complete user management system including authentication, roles, and profile management.

## Project Structure

```
/your-project
├── app/
│   ├── controllers/
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   ├── register.php
│   │   │   └── password.php
│   │   └── users/
│   │       ├── profile.php
│   │       └── settings.php
│   ├── models/
│   │   ├── user.php
│   │   └── role.php
│   ├── middleware/
│   │   └── auth.php
│   └── views/
│       ├── auth/
│       │   ├── login.php
│       │   ├── register.php
│       │   └── reset-password.php
│       └── users/
│           ├── profile.php
│           └── settings.php
└── database/
    └── migrations/
        └── 001_create_users_tables.php
```

## Database Migration

```php
<?php
// database/migrations/001_create_users_tables.php

return [
    'up' => "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
            email_verified_at TIMESTAMP NULL,
            remember_token VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE user_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        );

        CREATE TABLE password_resets (
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX password_resets_email_index (email)
        );
    ",
    'down' => "
        DROP TABLE user_roles;
        DROP TABLE roles;
        DROP TABLE password_resets;
        DROP TABLE users;
    "
];
```

## User Model

```php
<?php
// app/models/user.php

class UserModel extends TinyModel
{
    protected $table = 'users';
    protected $hidden = ['password', 'remember_token'];

    public function roles()
    {
        return $this->belongsToMany('role', 'user_roles');
    }

    public function hasRole($role)
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function verifyPassword($password)
    {
        return tiny::utils()->verifyPassword($password, $this->password);
    }

    public function setPassword($password)
    {
        $this->password = tiny::utils()->hashPassword($password);
        return $this;
    }

    public function generateVerificationToken()
    {
        return tiny::utils()->generateToken(32);
    }
}
```

## Authentication Controllers

```php
<?php
// app/controllers/auth/login.php

class Login extends TinyController
{
    public function get($request, $response)
    {
        return $response->render('auth/login');
    }

    public function post($request, $response)
    {
        $credentials = $request->body(true);

        // Validate input
        if (!tiny::utils()->isEmail($credentials['email'])) {
            return $response->back()->withError('Invalid email format');
        }

        // Attempt login
        if (!tiny::auth()->attempt($credentials)) {
            return $response->back()->withError('Invalid credentials');
        }

        // Handle remember me
        if ($request->body->remember_me) {
            tiny::auth()->rememberMe();
        }

        return $response->redirect('/dashboard');
    }
}

// app/controllers/auth/register.php
class Register extends TinyController
{
    public function post($request, $response)
    {
        $data = $request->body(true);

        // Validate user data
        if (!$this->validate($data)) {
            return $response->back()
                ->withErrors($this->errors);
        }

        // Create user
        $user = tiny::model('user')->create([
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => tiny::utils()->hashPassword($data['password'])
        ]);

        // Send verification email
        $this->sendVerificationEmail($user);

        // Auto login
        tiny::auth()->login($user);

        return $response->redirect('/dashboard')
            ->with('success', 'Account created successfully');
    }
}
```

## Profile Management

```php
<?php
// app/controllers/users/profile.php

class Profile extends TinyController
{
    public function __construct()
    {
        // Require authentication
        if (!tiny::isAuthenticated()) {
            return tiny::response()->redirect('/login');
        }
    }

    public function get($request, $response)
    {
        return $response->render('users/profile', [
            'user' => tiny::user()
        ]);
    }

    public function post($request, $response)
    {
        $data = $request->body(true);

        // Handle avatar upload
        if ($request->files->avatar) {
            $avatar = tiny::spaces()->upload(
                'avatars',
                $request->files->avatar
            );
            $data['avatar_url'] = $avatar;
        }

        // Update profile
        tiny::user()->update($data);

        return $response->back()
            ->with('success', 'Profile updated successfully');
    }
}
```

## Authentication Middleware

```php
<?php
// app/middleware/auth.php

return [
    'auth' => function($request, $response) {
        if (!tiny::isAuthenticated()) {
            if ($request->isAjax()) {
                return $response->sendJSON([
                    'error' => 'Unauthorized'
                ], 401);
            }
            return $response->redirect('/login');
        }
    },

    'role' => function($role) {
        return function($request, $response) use ($role) {
            if (!tiny::user()->hasRole($role)) {
                return $response->redirect('/dashboard')
                    ->with('error', 'Unauthorized access');
            }
        };
    }
];
```

## Profile View

```php
<!-- app/views/users/profile.php -->
<?php tiny::layout()->extend('default') ?>

<?php tiny::layout()->section('content') ?>
    <div class="profile-container">
        <h1>Profile Settings</h1>

        <?php if ($message = tiny::flash()->get()): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif ?>

        <form action="/profile" method="POST" enctype="multipart/form-data">
            <?= tiny::csrf()->field() ?>

            <div class="form-group">
                <label>Avatar</label>
                <input type="file" name="avatar" accept="image/*">
            </div>

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= $user->name ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= $user->email ?>" required>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password">
                <small>Leave blank to keep current password</small>
            </div>

            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>
<?php tiny::layout()->endSection() ?>
```

## Features Demonstrated

- User authentication
- Role-based authorization
- Profile management
- Password reset
- Email verification
- Remember me functionality
- File uploads
- Form validation
- Flash messages

## Best Practices

1. **Security**
   - Hash passwords
   - Validate input
   - Use CSRF protection
   - Implement rate limiting
   - Secure session handling

2. **User Experience**
   - Clear error messages
   - Email notifications
   - Profile completeness
   - Account recovery
   - Session management

3. **Data Management**
   - Validate email uniqueness
   - Handle soft deletes
   - Manage user roles
   - Track user activity
   - Clean expired tokens

4. **Performance**
   - Cache user data
   - Optimize queries
   - Handle file uploads
   - Manage sessions
   - Background tasks

</rewritten_file>
