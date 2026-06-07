[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Components

The Component extension provides reusable view fragments. The pattern is simple: each component file registers a PHP callable under a name, and any view can render it.

The framework exposes `Component` as a global constant pointing to a `TinyComponent` instance, initialised with the path `app/views/components/`.

## The API

```php
Component::register(string $name, callable $func): void
Component::render(string $name, ...$props): void   // echoes
Component::return(string $name, ...$props): mixed   // returns
Component::__call(string $name, array $args): void  // magic-method render: Component::myComp($x)
Component::require(string|array $files): void       // loads component file(s) without rendering
```

## Defining a component

Each component lives in its own file under `app/views/components/`. The file **registers** the callable; it does not render anything by itself.

```php
<!-- app/views/components/user-card.php -->
<?php
Component::register('userCard', function (array $user): void { ?>
    <article class="user-card">
        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
        <h3><?= htmlspecialchars($user['name']) ?></h3>
        <p><?= htmlspecialchars($user['bio'] ?? '') ?></p>
    </article>
<?php });
```

## Rendering a component

From any view (or layout):

```php
<?php foreach (tiny::get('users') as $user): ?>
    <?php Component::render('userCard', $user); ?>
<?php endforeach; ?>
```

Three equivalent forms:

```php
Component::render('userCard', $user);   // echoes
echo Component::return('userCard', $user); // returns the string
Component::userCard($user);             // magic-method shortcut for render()
```

> **Note:** components are registered on first include. The framework auto-includes every PHP file in `app/views/components/` when `Component` is first used, so you don't need to require them manually.

## Multiple props

`...$props` is spread into the callable's arguments. You can take any positional or named arguments:

```php
<!-- app/views/components/button.php -->
<?php
Component::register('button', function (string $label, string $href = '#', string $variant = 'primary'): void { ?>
    <a href="<?= htmlspecialchars($href) ?>" class="btn btn--<?= $variant ?>">
        <?= htmlspecialchars($label) ?>
    </a>
<?php });
```

```php
<?php Component::render('button', 'Save', '/users/save', 'primary'); ?>
<?php Component::button('Cancel', '/users', 'secondary'); ?>
```

## Returning a string

Use `Component::return()` when you need the rendered output as a string (for embedding in another component, for emails, etc.):

```php
<?php
Component::register('emailBody', function (array $user): string {
    return '<p>Hello ' . htmlspecialchars($user['name']) . '</p>';
});

$html = Component::return('emailBody', $user);
tiny::mailgun()->send($user['email'], 'Welcome', $html);
```

## Composition

Components can render other components:

```php
<?php
Component::register('postCard', function (array $post): void {
    Component::render('userAvatar', $post['author']);
    ?>
    <h2><?= htmlspecialchars($post['title']) ?></h2>
    <p><?= htmlspecialchars($post['excerpt']) ?></p>
    <?php
    Component::render('tagList', $post['tags']);
});
```

## Manually loading a component

If you have a component file outside the standard directory (for example a shared component library), include it with `Component::require()`:

```php
<?php Component::require('admin/data-table'); ?>
```

`require()` accepts either a string or an array of paths. The file is loaded once; subsequent calls are no-ops.

## Conditional rendering at registration time

The callable can return early or branch:

```php
<?php
Component::register('badge', function (string $label, ?string $color = null): void {
    if (!$label) return;
    ?>
    <span class="badge"<?= $color ? ' style="background:' . htmlspecialchars($color) . '"' : '' ?>>
        <?= htmlspecialchars($label) ?>
    </span>
<?php });
```

## Best practices

1. **One component per file.** It makes registration discovery obvious.
2. **Type your callable arguments.** PHP 8.3 gives you free runtime validation.
3. **Always escape user-supplied props** with `htmlspecialchars()`.
4. **Components should be presentational.** No DB calls, no HTTP requests — just rendering.
5. **Prefer `Component::render()` over the magic-method shortcut in shared code** — it's more grep-friendly.
