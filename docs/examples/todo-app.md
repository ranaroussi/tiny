[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Building a TODO Application

This example demonstrates how to build a simple TODO application using the Tiny PHP Framework. We'll cover database operations, authentication, validation, and more.

## Project Structure

```
/your-project
├── app/
│   ├── controllers/
│   │   ├── todo.php
│   │   └── auth.php
│   ├── models/
│   │   └── todo.php
│   └── views/
│       ├── components/
│       │   ├── todo-item.php
│       │   └── todo-form.php
│       └── todo/
│           ├── index.php
│           └── edit.php
├── migrations/
│   └── 20240101_create_todos_table.php
└── html/
    └── index.php
```

## Database Migration

Create a migration for the todos table:

```php
<?php
// migrations/20240101_create_todos_table.php

class CreateTodosTable
{
    private PDO $db;

    public function __construct()
    {
        $this->db = tiny::db()->getPdo();
    }

    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE todos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                status ENUM('pending', 'completed') DEFAULT 'pending',
                due_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS todos");
    }
}
```

## Model

Create the TODO model with validation and business logic:

```php
<?php
// app/models/todo.php

class TodoModel extends TinyModel
{
    public array $schemas = [
        'todo' => [
            'title' => 'string:255',
            'description' => 'string',
            'status' => 'string',
            'due_date' => 'datetime'
        ]
    ];

    public function getAll(): array
    {
        return tiny::db()->get(
            'todos',
            ['user_id' => tiny::user()->id],
            '*',
            'created_at DESC'
        );
    }

    public function getOne(int $id): ?object
    {
        return tiny::db()->getOne(
            'todos',
            [
                'id' => $id,
                'user_id' => tiny::user()->id
            ]
        );
    }

    public function create(array $data): bool|int
    {
        if (!$this->isValid($data, $this->schemas['todo'])) {
            return false;
        }

        $data['user_id'] = tiny::user()->id;
        return tiny::db()->insert('todos', $data);
    }

    public function update(int $id, array $data): bool
    {
        if (!$this->isValid($data, $this->schemas['todo'])) {
            return false;
        }

        return tiny::db()->update(
            'todos',
            $data,
            [
                'id' => $id,
                'user_id' => tiny::user()->id
            ]
        );
    }

    public function delete(int $id): bool
    {
        return tiny::db()->delete(
            'todos',
            [
                'id' => $id,
                'user_id' => tiny::user()->id
            ]
        );
    }
}
```

## Controller

Create the TODO controller to handle requests:

```php
<?php
// app/controllers/todo.php

class Todo extends TinyController
{
    private $model;

    public function __construct()
    {
        $this->model = tiny::model('todo');
    }

    public function get($request, $response)
    {
        if ($request->path->slug) {
            tiny::data()->todo = $this->model->getOne((int)$request->path->slug);
            if (!tiny::data()->todo) {
                return $response->render(404);
            }
            return $response->render('todo/edit');
        }

        tiny::data()->todos = $this->model->getAll();
        $response->render('todo/index');
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            $response->hasCSRFError();
            return $response->render();
        }

        $data = $request->body(true);

        if ($this->model->create($data)) {
            tiny::flash('toast')->set([
                'level' => 'success',
                'message' => 'Todo created successfully'
            ]);
            return $response->redirect('/todo');
        }

        tiny::data()->errors = $this->model->validationErrors;
        tiny::data()->todo = $data;
        $response->render();
    }

    public function patch($request, $response)
    {
        if (!$request->isValidCSRF()) {
            $response->hasCSRFError();
            return $response->render();
        }

        $data = $request->body(true);
        $id = (int)$request->path->slug;

        if ($this->model->update($id, $data)) {
            tiny::flash('toast')->set([
                'level' => 'success',
                'message' => 'Todo updated successfully'
            ]);
            return $response->redirect('/todo');
        }

        tiny::data()->errors = $this->model->validationErrors;
        tiny::data()->todo = $data;
        $response->render('todo/edit');
    }

    public function delete($request, $response)
    {
        if (!$request->isValidCSRF()) {
            $response->hasCSRFError();
            return $response->sendJSON(['error' => 'Invalid CSRF token'], 403);
        }

        $id = (int)$request->path->slug;

        if ($this->model->delete($id)) {
            return $response->sendJSON(['success' => true]);
        }

        return $response->sendJSON(['error' => 'Failed to delete todo'], 500);
    }
}
```

## Views

Create the views for listing and editing todos:

```php
<!-- app/views/todo/index.php -->
<div class="container mx-auto p-4">
    <h1 class="text-2xl mb-4">My Todos</h1>

    <form method="POST" class="mb-8">
        <?php tiny::csrf()->input() ?>

        <div class="mb-4">
            <input type="text"
                   name="title"
                   placeholder="What needs to be done?"
                   class="w-full p-2 border rounded">
        </div>

        <button type="submit"
                class="px-4 py-2 bg-blue-500 text-white rounded">
            Add Todo
        </button>
    </form>

    <div class="space-y-4">
        <?php foreach (tiny::data()->todos as $todo): ?>
            <?php tiny::component()->todoItem($todo) ?>
        <?php endforeach ?>
    </div>
</div>

<!-- app/views/components/todo-item.php -->
<div class="flex items-center justify-between p-4 bg-white shadow rounded">
    <div>
        <h3 class="font-bold"><?php echo $props->title; ?></h3>
        <p class="text-gray-600"><?php echo $props->description; ?></p>
    </div>

    <div class="flex space-x-2">
        <a href="/todo/<?php echo $props->id; ?>"
           class="px-3 py-1 bg-blue-100 text-blue-600 rounded">
            Edit
        </a>

        <button onclick="deleteTodo(<?php echo $props->id; ?>)"
                class="px-3 py-1 bg-red-100 text-red-600 rounded">
            Delete
        </button>
    </div>
</div>
```

## JavaScript

Add client-side functionality:

```javascript
// html/js/todo.js
async function deleteTodo(id) {
    if (!confirm('Are you sure?')) return;

    const response = await fetch(`/todo/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-Token': document.querySelector('[name="csrf_token"]').value
        }
    });

    const result = await response.json();

    if (result.success) {
        location.reload();
    } else {
        alert('Failed to delete todo');
    }
}
```

## Usage

1. Run the migration:
```bash
php tiny/cli migrations up
```

2. Access your TODO application at `http://localhost/todo`

## Features Demonstrated

- CRUD operations
- Form handling
- CSRF protection
- Validation
- Flash messages
- Component reuse
- Database operations
- Client-side interaction
- Error handling

## Next Steps

You could enhance this application by adding:

1. Due date handling
2. Priority levels
3. Categories/tags
4. Search functionality
5. Sorting options
6. Pagination
7. Task sharing
8. File attachments
