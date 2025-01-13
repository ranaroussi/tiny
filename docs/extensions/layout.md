[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Layout Extension

The Layout extension provides a powerful way to create reusable page layouts and manage the structure of your views.

## Basic Usage

### Creating Layouts

Create a base layout in `app/views/layouts/default.php`:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= tiny::data()->title ?? 'My App' ?></title>
    <?php tiny::layout()->head() ?>
</head>
<body>
    <header>
        <?php tiny::layout()->section('header') ?>
    </header>

    <main>
        <?php tiny::layout()->content() ?>
    </main>

    <footer>
        <?php tiny::layout()->section('footer') ?>
    </footer>

    <?php tiny::layout()->scripts() ?>
</body>
</html>
```

### Using Layouts in Views

```php
<!-- app/views/home.php -->
<?php tiny::layout()->extend('default') ?>

<?php tiny::layout()->section('header') ?>
    <h1>Welcome to My App</h1>
<?php tiny::layout()->endSection() ?>

<?php tiny::layout()->section('content') ?>
    <div class="content">
        <p>This is the main content.</p>
    </div>
<?php tiny::layout()->endSection() ?>

<?php tiny::layout()->section('footer') ?>
    <p>&copy; <?= date('Y') ?> My App</p>
<?php tiny::layout()->endSection() ?>
```

## Advanced Features

### Adding Assets

```php
// Add CSS files
tiny::layout()->css([
    '/css/main.css',
    '/css/components.css'
]);

// Add JavaScript files
tiny::layout()->js([
    '/js/app.js',
    '/js/utils.js'
]);

// Add inline styles
tiny::layout()->style('
    .custom-class {
        background: #f0f0f0;
    }
');

// Add inline scripts
tiny::layout()->script('
    document.addEventListener("DOMContentLoaded", function() {
        // Your code here
    });
');
```

### Nested Layouts

```php
<!-- app/views/layouts/admin.php -->
<?php tiny::layout()->extend('default') ?>

<?php tiny::layout()->section('header') ?>
    <nav class="admin-nav">
        <!-- Admin navigation -->
    </nav>
<?php tiny::layout()->endSection() ?>

<!-- app/views/admin/dashboard.php -->
<?php tiny::layout()->extend('admin') ?>

<?php tiny::layout()->section('content') ?>
    <div class="dashboard">
        <!-- Dashboard content -->
    </div>
<?php tiny::layout()->endSection() ?>
```

### Dynamic Sections

```php
// Check if section exists
if (tiny::layout()->hasSection('sidebar')) {
    tiny::layout()->section('sidebar');
}

// Default content for sections
tiny::layout()->section('sidebar', function() {
    echo '<div class="default-sidebar">Default content</div>';
});
```

### Component Integration

```php
<!-- Include components in layouts -->
<?php tiny::layout()->section('header') ?>
    <?php tiny::component()->navigation() ?>
    <?php tiny::component()->searchBar() ?>
<?php tiny::layout()->endSection() ?>
```

## Best Practices

1. **Structure Organization**
   - Keep layouts in `app/views/layouts/`
   - Use meaningful layout names
   - Maintain consistent structure
   - Separate concerns (content/presentation)

2. **Asset Management**
   - Group related assets
   - Use asset versioning
   - Consider load order
   - Optimize for performance

3. **Section Naming**
   - Use descriptive section names
   - Keep names consistent
   - Document required sections
   - Provide defaults when needed

4. **Reusability**
   - Create modular layouts
   - Share common sections
   - Use components for repeated elements
   - Keep layouts DRY

5. **SEO and Meta Data**
   ```php
   tiny::layout()->meta([
       'description' => 'Page description',
       'keywords' => 'key, words',
       'robots' => 'index,follow'
   ]);

   tiny::layout()->title('Page Title | My App');
   ```

## Example: Complete Layout System

```php
<!-- app/views/layouts/default.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= tiny::layout()->getTitle() ?></title>
    <?php tiny::layout()->meta() ?>
    <?php tiny::layout()->head() ?>
</head>
<body class="<?= tiny::layout()->bodyClass() ?>">
    <?php tiny::component()->header() ?>

    <div class="container">
        <?php if (tiny::layout()->hasSection('sidebar')): ?>
            <aside class="sidebar">
                <?php tiny::layout()->section('sidebar') ?>
            </aside>
        <?php endif ?>

        <main class="content">
            <?php tiny::layout()->content() ?>
        </main>
    </div>

    <?php tiny::component()->footer() ?>
    <?php tiny::layout()->scripts() ?>
</body>
</html>
```

This layout system provides a flexible and maintainable way to structure your application's views while keeping your code DRY and organized.

For more information about components:
-- See [Components](../extensions/readme.md#components)
