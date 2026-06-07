[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Building a TODO Application

A small CRUD app that exercises routing, controllers, models, CSRF, components, and flash messages. Assumes an existing `users` table and a logged-in user available via `tiny::user()`.

## Project structure

```
my-app/
├── app/
│   ├── controllers/
│   │   └── todo.php             # /todo
│   ├── models/
│   │   └── todo.php             # TodoModel
│   └── views/
│       ├── components/
│       │   └── todo-item.php    # reusable list row
│       └── todo/
│           ├── index.php
│           └── edit.php
└── migrations/
    └── 20240101_create_todos.php
```

## Migration

Create `migrations/20240101_create_todos.php`:

```php
<?php

class CreateTodos extends TinyMigration
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE todos (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                title       VARCHAR(255) NOT NULL,
                description TEXT,
                status      ENUM('pending', 'completed') DEFAULT 'pending',
                due_date    DATE NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS todos");
    }
}
```

Run it:

```bash
php tiny/cli migrations up
```

## Model

`app/models/todo.php`:

```php
<?php

class TodoModel extends TinyModel
{
    public array $schema = [
        'title'       => 'string:255',
        'description' => 'string',
        'status'      => 'string:9',          // 'pending' | 'completed'
        'due_date'    => 'date',
    ];

    public function listForUser(int $userId): array
    {
        return tiny::db()->get('todos', ['user_id' => $userId], '*', 'created_at DESC');
    }

    public function ownedBy(int $id, int $userId): ?object
    {
        return tiny::db()->getOne('todos', ['id' => $id, 'user_id' => $userId]);
    }

    public function create(array $data, int $userId): int|false
    {
        if (!$this->isValid($data, $this->schema)) {
            return false;
        }
        $data['user_id'] = $userId;
        return tiny::db()->insert('todos', $data);
    }

    public function update(int $id, int $userId, array $data): bool
    {
        if (!$this->isValid($data, $this->schema)) {
            return false;
        }
        return tiny::db()->update('todos', $data, ['id' => $id, 'user_id' => $userId]);
    }

    public function destroy(int $id, int $userId): bool
    {
        return tiny::db()->delete('todos', ['id' => $id, 'user_id' => $userId]);
    }
}
```

`isValid()` populates `$this->validationErrors` on failure.

## Controller

`app/controllers/todo.php`:

```php
<?php

class Todo extends TinyController
{
    private TodoModel $todos;

    public function __construct()
    {
        $this->todos = tiny::model('todo');
    }

    public function get($request, $response)
    {
        $userId = tiny::user()->id;

        // /todo/<id>/edit  → edit form
        if ($request->path->section && $request->path->slug === 'edit') {
            $todo = $this->todos->ownedBy((int)$request->path->section, $userId);
            if (!$todo) {
                return tiny::controller('404', true);
            }
            return $response->render('todo/edit', ['todo' => $todo]);
        }

        // /todo  → list
        $response->render('todo/index', [
            'todos' => $this->todos->listForUser($userId),
        ]);
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }

        $data = $request->body(true);
        $id = $this->todos->create($data, tiny::user()->id);

        if ($id === false) {
            tiny::flash('form-errors')->set($this->todos->validationErrors);
            return $response->redirect('/todo');
        }

        tiny::flash('toast')->set([
            'level'   => 'success',
            'message' => 'Todo added',
        ]);
        $response->redirect('/todo');
    }

    public function patch($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }

        $id     = (int)$request->path->section;
        $userId = tiny::user()->id;

        if (!$this->todos->update($id, $userId, $request->body(true))) {
            tiny::flash('form-errors')->set($this->todos->validationErrors);
            return $response->redirect("/todo/$id/edit");
        }

        tiny::flash('toast')->set(['level' => 'success', 'message' => 'Todo saved']);
        $response->redirect('/todo');
    }

    public function delete($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->sendJSON(['error' => 'Invalid CSRF token'], 403);
        }

        $ok = $this->todos->destroy(
            (int)$request->path->section,
            tiny::user()->id
        );

        $response->sendJSON(['success' => $ok], $ok ? 200 : 404);
    }
}
```

URLs handled:

| Method | Path | Action |
|---|---|---|
| `GET` | `/todo` | List todos |
| `GET` | `/todo/42/edit` | Edit form |
| `POST` | `/todo` | Create |
| `PATCH` | `/todo/42` | Update |
| `DELETE` | `/todo/42` | Delete |

## Views

`app/views/todo/index.php`:

```php
<?php Layout::main(['title' => 'My todos']); ?>

    <h1>My todos</h1>

    <?php $errs = tiny::flash('form-errors')->get() ?? []; ?>
    <?php $toast = tiny::flash('toast')->get(); ?>
    <?php if ($toast): ?>
        <div class="alert alert-<?= htmlspecialchars($toast['level']) ?>">
            <?= htmlspecialchars($toast['message']) ?>
        </div>
    <?php endif ?>

    <form method="POST" action="/todo">
        <?php tiny::csrf()->input(); ?>
        <input name="title" placeholder="What needs to be done?" required>
        <?php if (isset($errs['title'])): ?><small class="error"><?= htmlspecialchars($errs['title']) ?></small><?php endif ?>
        <button type="submit">Add</button>
    </form>

    <ul id="todo-list">
        <?php foreach ($todos as $todo): ?>
            <?php Component::render('todoItem', $todo); ?>
        <?php endforeach ?>
    </ul>

    <script src="/static/js/todo.js" defer></script>

<?php Layout::main(); ?>
```

`app/views/components/todo-item.php`:

```php
<?php

Component::register('todoItem', function (object $todo): void { ?>
    <li data-id="<?= (int)$todo->id ?>">
        <strong><?= htmlspecialchars($todo->title) ?></strong>
        <?php if ($todo->description): ?><p><?= htmlspecialchars($todo->description) ?></p><?php endif ?>

        <a href="/todo/<?= (int)$todo->id ?>/edit">Edit</a>
        <button class="js-delete" data-id="<?= (int)$todo->id ?>">Delete</button>
    </li>
<?php });
```

`app/views/todo/edit.php`:

```php
<?php Layout::main(['title' => 'Edit todo']); ?>

    <h1>Edit todo</h1>

    <?php $errs = tiny::flash('form-errors')->get() ?? []; ?>

    <form method="POST" action="/todo/<?= (int)$todo->id ?>">
        <?php tiny::csrf()->input(); ?>
        <input type="hidden" name="_method" value="PATCH">

        <label>Title
            <input name="title" value="<?= htmlspecialchars($todo->title) ?>" required>
        </label>
        <?php if (isset($errs['title'])): ?><small class="error"><?= htmlspecialchars($errs['title']) ?></small><?php endif ?>

        <label>Description
            <textarea name="description"><?= htmlspecialchars($todo->description ?? '') ?></textarea>
        </label>

        <label>Status
            <select name="status">
                <option value="pending"   <?= $todo->status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $todo->status === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </label>

        <button type="submit">Save</button>
    </form>

<?php Layout::main(); ?>
```

(The `_method` hidden field is the standard PHP way to send a PATCH/DELETE through an HTML form; your router middleware or controller can read it and dispatch accordingly. Alternatively, use HTMX's `hx-patch`.)

## Client-side delete

`html/static/js/todo.js`:

```js
const token = document.querySelector('input[name=csrf_token]')?.value;

document.body.addEventListener('click', async (e) => {
    if (!e.target.matches('.js-delete')) return;
    if (!confirm('Delete this todo?')) return;

    const id = e.target.dataset.id;
    const res = await fetch(`/todo/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': token, 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: token }),
    });

    if (res.ok) {
        document.querySelector(`li[data-id="${id}"]`)?.remove();
    }
});
```

## Component registration

The `Component::register(...)` call at the top of `todo-item.php` runs once when the file is included. If you have many components, include them all from `app/common.php` (which Tiny autoloads) with a single glob:

```php
<?php
// app/common.php
foreach (glob(__DIR__ . '/views/components/*.php') as $file) {
    require_once $file;
}
```

## Things to try next

- **HTMX** — replace the form's `<form method="POST" action="/todo">` with `<form hx-post="/todo" hx-target="#todo-list" hx-swap="beforeend">` and return just the `<li>` from the controller when `$request->htmx` is true.
- **Filtering** — add `?status=pending` and read it via `$request->params('status')`.
- **Due dates** — add a `<input type="date" name="due_date">`; the `date` validator in the model schema already covers it.
- **Pagination** — extend `listForUser()` with `LIMIT`/`OFFSET` and surface `?page=` from `$request->params('page', 1)`.
