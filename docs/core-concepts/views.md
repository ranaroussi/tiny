[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Views

Views in Tiny are plain PHP templates living in `app/views/`. They are invoked from controllers via `$response->render('path/to/view', $params)` and have access to the global `tiny::*` API plus two helpers: `Component` and `Layout`.

## Basic structure

```php
<!-- app/views/users/index.php -->
<!doctype html>
<html>
<head>
    <title><?= htmlspecialchars(tiny::get('title')) ?></title>
</head>
<body>
    <h1>Users</h1>
    <ul>
    <?php foreach (tiny::get('users') as $user): ?>
        <li><?= htmlspecialchars($user['name']) ?></li>
    <?php endforeach; ?>
    </ul>
</body>
</html>
```

## Layouts

Layouts wrap page chrome. Register them as PHP files in `app/views/layouts/` — the file `main.php` becomes `Layout::main(...)`:

```php
<!-- app/views/layouts/main.php -->
<!doctype html>
<html>
<head>
    <title><?= htmlspecialchars(Layout::props('title', 'My App')) ?></title>
    <link rel="stylesheet" href="<?= tiny::getStaticURL('css/app.css') ?>">
</head>
<body>
    <?php Component::render('header'); ?>
    <main><?= Layout::props('content') ?></main>
    <?php Component::render('footer'); ?>
</body>
</html>
```

Invoke a layout from a view:

```php
<!-- app/views/users/index.php -->
<?php Layout::main([
    'title'   => 'Users',
    'content' => '<h1>Users</h1>',
]); ?>
```

`Layout::props($name, $fallback)` reads a value passed in; the fallback is used when the key isn't present.

See [Layout extension](../extensions/layout.md) for details.

## Components

Components are reusable view fragments. Define them in `app/views/components/` by **registering** a callable:

```php
<!-- app/views/components/user-card.php -->
<?php
Component::register('userCard', function (array $user): void { ?>
    <article class="user-card">
        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
        <h3><?= htmlspecialchars($user['name']) ?></h3>
        <p><?= htmlspecialchars($user['bio']) ?></p>
    </article>
<?php });
```

Use it from any view in three equivalent ways:

```php
<?php Component::render('userCard', $user); ?>
<?php echo Component::return('userCard', $user); ?>
<?php Component::userCard($user); ?>   <!-- magic-method shortcut -->
```

See [Components extension](../extensions/components.md) for the full API.

## Passing data to views

Three equivalent options:

```php
// Controller — preferred
$response->render('users/index', ['users' => $users, 'title' => 'Users']);

// Controller — global bag
tiny::data()->users = $users;
tiny::set('title', 'Users');

// View — reading
tiny::data()->users;
tiny::get('title', 'Default Title');   // optional fallback
```

## Flash messages in views

```php
<?php if ($toast = tiny::flash('toast')->get()): ?>
    <div class="toast toast--<?= htmlspecialchars($toast['level']) ?>">
        <?= htmlspecialchars($toast['message']) ?>
    </div>
<?php endif; ?>
```

## CSRF tokens

For every form that does state-changing work, embed a CSRF input:

```php
<form method="post" action="/users">
    <?php tiny::csrf()->input(); ?>
    <input type="text" name="name">
    <button type="submit">Save</button>
</form>
```

The `input()` helper echoes a hidden field with the current token.

## Static asset URLs

```php
<link rel="stylesheet" href="<?= tiny::getStaticURL('css/app.css') ?>">
<script src="<?= tiny::getStaticURL('js/app.js') ?>" defer></script>
<img src="<?= tiny::getStaticURL('images/logo.svg') ?>">
```

`tiny::staticURL($file)` echoes; `tiny::getStaticURL($file)` returns the string. Both honour `TINY_STATIC_DIR` (which can be a CDN URL).

## Home URLs

```php
<a href="<?= tiny::getHomeURL('/about') ?>">About</a>
<a href="<?= tiny::getHomeURL('/contact', full: true) ?>">Contact (absolute URL)</a>
```

`tiny::homeURL()` echoes the same value.

## HTMX integration

When the current request was made by HTMX (`HX-Request` header present), `$request->htmx` is `true`. Render partials or full pages accordingly:

```php
<?php if (tiny::request()->htmx): ?>
    <!-- just the list, the rest of the page stays -->
    <?php foreach (tiny::get('users') as $u): Component::render('userCard', $u); endforeach; ?>
<?php else: ?>
    <?php Layout::main(['title' => 'Users', 'content' => /* full HTML */]); ?>
<?php endif; ?>
```

See [HTMX integration](htmx.md) for the full story.

## Best practices

1. **Escape every output** — `htmlspecialchars()` (or `<?= htmlspecialchars(...) ?>`). No exceptions.
2. **Components for reuse, layouts for chrome.** Don't mix them up.
3. **Keep logic out of views.** No queries, no HTTP calls — only rendering.
4. **Use `tiny::get($key)` over `tiny::data()->$key`** when you want default fallbacks.
5. **CSRF every form.** Always.
