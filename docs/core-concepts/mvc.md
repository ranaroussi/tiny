[Home](../readme.md) | [Getting Started](getting-started.md) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# MVC Architecture

The Model-View-Controller (MVC) pattern is at the heart of Tiny PHP Framework. It provides a clean separation of concerns and helps organize your code effectively.

## Overview

```
Request → Router → Controller → Model → Controller → View → Response
```

## Components

### Models (`app/models/`)

Models handle data and business logic:

```php
<?php

class UserModel extends TinyModel
{
    // Define validation schemas
    public array $schemas = [
        'account' => [
            'name' => 'string:100',
            'email' => 'string:255',
            'active' => 'bool'
        ]
    ];

    public function getAccount(): object
    {
        return tiny::db()->getOne('users', ['id' => tiny::user()->id]);
    }

    public function updateAccount(array $data): bool
    {
        if (!$this->isValid($data, $this->schemas['account'])) {
            return false;
        }
        return tiny::db()->update('users', $data, ['id' => tiny::user()->id]);
    }
}
```

### Views (`app/views/`)

Views handle the presentation layer:

```php
<!-- app/views/user/profile.php -->
<div class="profile">
    <h1><?= tiny::data()->user->name ?></h1>
    <p><?= tiny::data()->user->email ?></p>

    <?php tiny::component()->userStats() ?>
</div>
```

### Controllers (`app/controllers/`)

Controllers handle request/response flow:

```php
<?php

class UserProfile extends TinyController
{
    private $model;

    public function __construct()
    {
        $this->model = tiny::model('user');
    }

    public function get($request, $response)
    {
        tiny::data()->user = $this->model->getAccount();
        $response->render();
    }

    public function patch($request, $response)
    {
        $data = $request->body(true);

        if (!$request->isValidCSRF()) {
            $response->hasCSRFError();
            return $response->render();
        }

        if ($this->model->updateAccount($data)) {
            tiny::flash('toast')->set([
                'level' => 'success',
                'message' => 'Profile updated successfully'
            ]);
            return $response->redirect('/profile');
        }

        tiny::data()->errors = $this->model->validationErrors;
        $response->render();
    }
}
```

## Data Flow

1. **Request Handling**
   - User makes a request to `/profile`
   - Router maps URL to `UserProfile` controller
   - Middleware processes request

2. **Controller Processing**
   - Controller instantiates required model
   - Handles HTTP method (GET, POST, etc.)
   - Processes input data
   - Interacts with model for data operations

3. **Model Operations**
   - Validates data using schemas
   - Performs database operations
   - Implements business logic
   - Returns data/results to controller

4. **View Rendering**
   - Controller passes data to view via `tiny::data()`
   - View renders HTML using data
   - Components are included as needed
   - Layout wraps the final output

5. **Response**
   - Final HTML is sent to browser
   - Redirects are handled if needed
   - Error responses are formatted appropriately

## Best Practices

1. **Models**
   - Keep business logic in models
   - Use validation schemas
   - Handle database operations
   - Return clean data objects

2. **Views**
   - Use components for reusable UI elements
   - Keep logic minimal
   - Use layouts for consistent structure
   - Escape output properly

3. **Controllers**
   - Keep thin and focused
   - Handle request/response flow
   - Validate CSRF tokens
   - Use flash messages for user feedback

4. **General**
   - Follow single responsibility principle
   - Use dependency injection
   - Keep code DRY
   - Document complex logic

For more information:
- See [Database Operations](../core-concepts/database.md)
- Check [Extensions](../extensions/readme.md)
