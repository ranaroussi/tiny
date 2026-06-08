[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# MVC Architecture

Tiny is a classical MVC framework with one quirk: there is no route table. The URL path maps directly to the filesystem (`app/controllers/...`), and from there it's the familiar Model → Controller → View flow.

```
Request → Router → Middleware → Controller → Model → View → Response
```

## Controllers (`app/controllers/`)

Controllers extend `TinyController`. Each HTTP verb is a method (`get`, `post`, `put`, `patch`, `delete`). See [Controllers](controllers.md) for the full reference.

```php
<?php
class UserProfile extends TinyController
{
    public function get($request, $response)
    {
        $user = tiny::model('user')->byId($request->path->slug);
        $response->render('user/profile', ['user' => $user]);
    }

    public function patch($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }
        $data = $request->body(true);
        if (tiny::model('user')->update($data)) {
            tiny::flash('toast')->set(['level' => 'success', 'message' => 'Updated']);
            return $response->redirect('/profile');
        }
        $response->render('user/profile', ['errors' => tiny::model('user')->validationErrors]);
    }
}
```

## Models (`app/models/`)

Models extend `TinyModel` and own data access + validation. Load them with `tiny::model('user')`.

```php
<?php
class UserModel extends TinyModel
{
    public array $schemas = [
        'account' => [
            'name'   => 'string(100)',
            'email'  => 'string(255)',
            'active' => 'bool',
        ],
    ];

    public function byId(int $id): ?array
    {
        return tiny::cache()->remember("user:$id", 60, function () use ($id) {
            return tiny::db()->getOne('users', ['id' => $id]);
        }) ?: null;
    }

    public function update(array $data): bool
    {
        if (!$this->isValid($data, $this->schemas['account'])) {
            return false;
        }
        return (bool) tiny::db()->update('users', $data, ['id' => $data['id']]);
    }
}
```

`TinyModel::isValid()` runs schema validation; the result is stored in `$this->validationErrors`. There's also `validationErrorsToAlpineJs()` for one-shot Alpine.js error binding.

See [Models](models.md) for the validation grammar and more patterns.

## Views (`app/views/`)

Views are plain PHP templates. They can use the global `tiny::data()` bag, the `Component` and `Layout` singletons, and any helper:

```php
<?php Layout::default(['title' => 'Profile']); ?>

<h1><?= htmlspecialchars(tiny::get('user')['name']) ?></h1>

<?php if ($errors = tiny::get('errors')): ?>
    <ul class="errors">
    <?php foreach ($errors as $field => $msg): ?>
        <li><?= htmlspecialchars($msg) ?></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php Component::render('user-card', ['user' => tiny::get('user')]); ?>
```

See [Views](views.md), [Components](../extensions/components.md), and [Layouts](../extensions/layout.md).

## Request flow in detail

1. **Bootstrap** — `html/index.php` loads `tiny/tiny.php`, which initialises config, DB (if `TINY_DB_AUTOCONNECT` ≠ false), router, helpers, and middleware.
2. **Routing** — the URL is resolved to a controller file. See [Routing](routing.md).
3. **Middleware** — each registered middleware's `handle()` runs (web requests only). See [Middleware](middleware.md).
4. **Controller dispatch** — the matching HTTP-verb method is invoked with `(TinyRequest $request, TinyResponse $response)`.
5. **Response** — the controller calls `$response->render(...)`, `$response->sendJSON(...)`, or similar. Output is buffered (and optionally minified) before being sent to the client.

## Best practices

- **Controllers are thin.** They orchestrate; they don't compute.
- **Models own data + business rules.** Validation lives next to the schema.
- **Views are dumb.** No SQL, no HTTP calls — just rendering.
- **Use `tiny::cache()->remember()` aggressively** for hot reads.
- **Share request-scoped state via `tiny::data()` / `tiny::set()` / `tiny::get()`** — that's exactly what it's for.

## See also

- [Routing](routing.md)
- [Controllers](controllers.md)
- [Request & Response](request-response.md)
- [Views](views.md)
- [Models](models.md)
- [Database](database.md)
- [Middleware](middleware.md)
- [HTMX integration](htmx.md)
