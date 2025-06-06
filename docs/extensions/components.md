[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Working with Components

Components in Tiny PHP allow you to create reusable UI elements that can be shared across your views.

## Creating Components

Components are stored in `app/views/components/`:

```php
<!-- app/views/components/alert.php -->
<div class="alert alert-<?php echo $props->type ?? 'info'; ?>">
    <?php echo $props->message; ?>
    <?php if ($props->dismissible): ?>
        <button class="close">&times;</button>
    <?php endif ?>
</div>
```

## Using Components

### Basic Usage

```php
<!-- In your view -->
<?php tiny::component()->alert([
    'type' => 'success',
    'message' => 'Operation successful!',
    'dismissible' => true
]) ?>
```

### With Slots

```php
<!-- app/views/components/card.php -->
<div class="card">
    <div class="card-header">
        <?php echo $props->title; ?>
    </div>
    <div class="card-body">
        <?php echo $slot; ?>
    </div>
</div>

<!-- In your view -->
<?php tiny::component()->card(['title' => 'My Card'], function() { ?>
    <p>This is the card content</p>
    <button>Click me</button>
<?php }) ?>
```

## Component Properties

Access component properties using the `$props` object:

```php
<!-- app/views/components/user-card.php -->
<div class="user-card">
    <img src="<?php echo $props->avatar; ?>" alt="<?php echo $props->name; ?>">
    <h3><?php echo $props->name; ?></h3>
    <?php if ($props->isOnline): ?>
        <span class="status-badge">Online</span>
    <?php endif ?>
</div>
```

## Nested Components

Components can use other components:

```php
<!-- app/views/components/post-card.php -->
<article class="post">
    <?php tiny::component()->userAvatar(['user' => $props->author]) ?>

    <div class="content">
        <h2><?php echo $props->title; ?></h2>
        <?php echo $props->content; ?>
    </div>

    <?php tiny::component()->commentList(['comments' => $props->comments]) ?>
</article>
```

## Dynamic Components

Load components dynamically:

```php
<?php
// Determine component type at runtime
$type = $user->hasPermission('admin') ? 'adminMenu' : 'userMenu';
tiny::component()->$type(['user' => $user]);
```

## Component Logic

Add logic to your components using PHP:

```php
<!-- app/views/components/pagination.php -->
<?php
$currentPage = $props->currentPage;
$totalPages = $props->totalPages;
$range = range(
    max(1, $currentPage - 2),
    min($totalPages, $currentPage + 2)
);
?>

<nav class="pagination">
    <?php foreach ($range as $page): ?>
        <a href="?page=<?php echo $page; ?>"
           class="<?php echo $page === $currentPage ? 'active' : ''; ?>">
            <?php echo $page; ?>
        </a>
    <?php endforeach ?>
</nav>
```

## Component Collections

Group related components:

```php
<!-- app/views/components/form/input.php -->
<div class="form-group">
    <label><?php echo $props->label; ?></label>
    <input type="<?php echo $props->type ?? 'text'; ?>"
           name="<?php echo $props->name; ?>"
           value="<?php echo $props->value ?? ''; ?>"
           class="form-control">
</div>

<!-- Usage -->
<?php
tiny::component()->form->input([
    'label' => 'Email',
    'type' => 'email',
    'name' => 'email'
]);
?>
```

## Best Practices

1. **Keep Components Focused**
   - Single responsibility
   - Reusable across views
   - Clear and descriptive names

2. **Use Props Validation**
   ```php
   <?php
   if (!isset($props->required_prop)) {
       throw new Exception('Required prop missing');
   }
   ?>
   ```

3. **Default Values**
   ```php
   $type = $props->type ?? 'default';
   $classes = $props->classes ?? [];
   ```

4. **Document Components**
   ```php
   <!-- app/views/components/data-table.php -->
   <?php
   /**
    * Data Table Component
    *
    * @param array  $columns Column definitions
    * @param array  $data    Table data
    * @param string $class   Additional CSS classes
    */
   ?>
   ```

5. **Consistent Naming**
   - Use kebab-case for files
   - Use descriptive, action-based names
   - Group related components
```
