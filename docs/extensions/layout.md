[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Layouts

Layouts in Tiny are plain PHP. There is no templating DSL, no `@yield`, no `extends`. A "layout" is a pair of files (`open.php` and `close.php`) that wrap your view's content. You invoke them as if they were methods on the global `Layout` constant.

This page covers `TinyLayout`. For views and components see [views.md](../core-concepts/views.md) and [components.md](components.md).

## File convention

Layouts live in `app/views/layouts/<name>/`:

```
app/views/layouts/
├── main/
│   ├── open.php       # rendered first time Layout::main(...) is called
│   └── close.php      # rendered second time Layout::main(...) is called
└── modal/
    ├── open.php
    └── close.php
```

## The `Layout` constant

Tiny's bootstrap defines `Layout` as a `TinyLayout` instance pointing at `app/views/layouts/`. Calls to it dispatch through `__call`:

```php
<?php Layout::main(['title' => 'Dashboard', 'user' => $user]); ?>

    <h1>Welcome, <?= htmlspecialchars($user->name) ?></h1>
    <p>Here are your widgets…</p>

<?php Layout::main(); ?>
```

First call: opens the layout, props are stored, `open.php` is included.
Second call: pops the stored props, `close.php` is included.

Between the two calls, the view renders its body — sandwiched inside the layout shell.

You can equally use `tiny::layout()` if `Layout` isn't defined as a constant in your app's bootstrap:

```php
<?php tiny::layout()->main(['title' => 'Dashboard']); ?>
…
<?php tiny::layout()->main(); ?>
```

## Reading props inside the layout

`open.php` and `close.php` access the props via `Layout::props($name, $fallback)`:

```php
<!-- app/views/layouts/main/open.php -->
<!doctype html>
<html lang="<?= Layout::props('lang', 'en') ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars(Layout::props('title', 'My App')) ?></title>
    <?php foreach (Layout::props('css', []) as $href): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
    <?php endforeach ?>
</head>
<body>
<main>
```

```php
<!-- app/views/layouts/main/close.php -->
</main>
<footer>&copy; <?= date('Y') ?></footer>
<?php foreach (Layout::props('js', []) as $src): ?>
    <script src="<?= htmlspecialchars($src) ?>"></script>
<?php endforeach ?>
</body>
</html>
```

## Nesting

Layouts compose by nesting:

```php
<?php Layout::main(['title' => 'Admin']); ?>
    <?php Layout::admin(['sidebar' => $items]); ?>
        <h2>Users</h2>
        <?php tiny::component()->userTable(['users' => $users]); ?>
    <?php Layout::admin(); ?>
<?php Layout::main(); ?>
```

The first/second-call mechanism means each layout closes itself; nesting is just lexical scope in the view file.

## With components

Layouts are great for page chrome (head, nav, footer). Reusable widgets belong in [components](components.md). A common shape:

```php
<?php Layout::main(['title' => 'Dashboard']); ?>

    <?php tiny::component()->navBar(['active' => 'dashboard']); ?>

    <h1>Dashboard</h1>
    <?php tiny::component()->statsGrid(['stats' => $stats]); ?>
    <?php tiny::component()->recentOrders(['orders' => $orders]); ?>

<?php Layout::main(); ?>
```

## Best practices

1. **Keep layouts dumb.** Move computation into the controller or a component. The layout should only emit HTML and read props.
2. **Always close.** Forgetting the second `Layout::main()` produces a half-rendered page; the framework can't auto-close because PHP doesn't have block scope.
3. **Pass everything via props.** Don't reach into `tiny::data()` from a layout file — props are easier to grep and refactor.
4. **One layout per page.** Nesting is fine, but if you find yourself with three deep, consider splitting into components instead.
5. **Match HTML escaping discipline.** Layouts emit raw HTML and rely on you to `htmlspecialchars()` user data.
