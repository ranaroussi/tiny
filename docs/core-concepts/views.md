[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Views

Views in Tiny handle the presentation layer of your application. They are PHP files that contain HTML and can include dynamic content using PHP code.

## Basic Structure

Views should be placed in the `app/views` directory and use `.php` extension:

```php
<!-- app/views/users/index.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?php echo tiny::data()->title; ?></title>
</head>
<body>
    <h1>Users</h1>
    <ul>
    <?php foreach (tiny::data()->users as $user): ?>
        <li><?php echo $user->name; ?></li>
    <?php endforeach; ?>
    </ul>
</body>
</html>
```

## Using Layouts

Layouts provide a template for common page elements:

```php
<!-- app/views/layouts/main.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?php echo tiny::data()->title; ?></title>
    <?php tiny::component()->head() ?>
</head>
<body>
    <?php tiny::component()->header() ?>

    <main>
        <?php echo tiny::data()->content; ?>
    </main>

    <?php tiny::component()->footer() ?>
</body>
</html>
```

Using a layout:
```php
<!-- app/views/users/profile.php -->
<?php tiny::layout('main') ?>

<div class="profile">
    <h1><?php echo tiny::data()->user->name; ?></h1>
    <p><?php echo tiny::data()->user->email; ?></p>
</div>
```

## Components

Components are reusable view elements:

```php
<!-- app/views/components/user-card.php -->
<div class="user-card">
    <img src="<?php echo $user->avatar; ?>" alt="<?php echo $user->name; ?>">
    <h3><?php echo $user->name; ?></h3>
    <p><?php echo $user->bio; ?></p>
</div>
```

Using components:
```php
<!-- In any view -->
<?php foreach (tiny::data()->users as $user): ?>
    <?php tiny::component()->userCard(['user' => $user]) ?>
<?php endforeach; ?>
```

## Data Access

Access data shared from controllers:

```php
<!-- Direct property access -->
<h1><?php echo tiny::data()->title; ?></h1>

<!-- Using get() method -->
<h1><?php echo tiny::get('title'); ?></h1>

<!-- Checking if data exists -->
<?php if (isset(tiny::data()->user)): ?>
    <p>Welcome, <?php echo tiny::data()->user->name; ?></p>
<?php endif; ?>
```

## Flash Messages

Display temporary messages:

```php
<!-- Display toast messages -->
<?php if ($toast = tiny::flash('toast')->get()): ?>
    <div class="toast <?php echo $toast['level']; ?>">
        <?php echo $toast['message']; ?>
    </div>
<?php endif; ?>
```

## Form Handling

Create forms with CSRF protection:

```php
<form method="POST" action="/users">
    <?php tiny::csrf()->input() ?>

    <input type="text" name="name" value="<?php echo tiny::old('name'); ?>">

    <?php if ($error = tiny::error('name')): ?>
        <span class="error"><?php echo $error; ?></span>
    <?php endif; ?>

    <button type="submit">Save</button>
</form>
```

## Asset Management

Include CSS and JavaScript files:

```php
<!-- In head component -->
<link rel="stylesheet" href="<?php echo tiny::asset('css/app.css'); ?>">
<script src="<?php echo tiny::asset('js/app.js'); ?>" defer></script>
```

## Best Practices

1. **Organization**
   - Use subdirectories for related views
   - Keep components small and focused
   - Use consistent naming conventions

2. **Security**
   - Always escape output using `<?= ?>` (shorthand for `htmlspecialchars()`)
   - Use CSRF protection in forms
   - Validate user input server-side

3. **Performance**
   - Keep logic minimal in views
   - Use components for reusable elements
   - Cache where appropriate

4. **Maintainability**
   - Use layouts for consistent structure
   - Keep views simple and focused
   - Document complex view logic

5. **Accessibility**
   - Use semantic HTML
   - Include ARIA attributes
   - Test with screen readers
</rewritten_file>
