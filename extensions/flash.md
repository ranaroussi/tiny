[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Flash messages

The flash extension stores small, one-shot messages in the session — useful for "saved!" toasts, "invalid input" errors, and other notifications that should survive a redirect but not appear twice.

Each named slot (`tiny::flash('toast')`, `tiny::flash('error')`, …) is independent. Reading a slot **consumes** it unless you pass `keep: true`.

## API

```php
$flash = tiny::flash('toast');     // named slot; default name is 'flash_msg'

$flash->set($value);               // store
$flash->get(bool $keep = false);   // read (and clear unless $keep)
$flash->is($value, bool $keep = false);  // compare
```

`$value` can be a string or an array — useful for structured toasts.

## Basic use

```php
class Users extends TinyController
{
    public function post($request, $response)
    {
        // ... save user ...

        tiny::flash('toast')->set([
            'level'   => 'success',
            'title'   => 'Saved',
            'message' => 'Your profile has been updated.',
        ]);

        $response->redirect('/account');
    }
}
```

```php
<!-- app/views/account.php -->
<?php $toast = tiny::flash('toast')->get(); ?>
<?php if ($toast): ?>
    <div class="toast toast-<?= htmlspecialchars($toast['level']) ?>">
        <strong><?= htmlspecialchars($toast['title']) ?></strong>
        <p><?= htmlspecialchars($toast['message']) ?></p>
    </div>
<?php endif ?>
```

The toast is gone on the next page load — exactly the property you want.

## `is()` for status checks

When you just want a yes/no answer (e.g. "did we just log them out?"):

```php
if (tiny::flash('action')->is('logged-out')) {
    echo '<p>You have been signed out.</p>';
}
```

`is()` consumes by default; pass `keep: true` to peek.

## Keeping a value for one more request

```php
$value = tiny::flash('notice')->get(keep: true);
```

This reads the value without removing it — it'll still be available on the *next* request. (There's no "persist forever" API on purpose; use the session directly if you need long-lived state.)

## Patterns

### Form-validation errors

```php
// in the controller
tiny::flash('form-errors')->set([
    'email' => 'That address is already in use.',
]);
$response->redirect('/signup');
```

```php
<!-- in the form view -->
<?php $errors = tiny::flash('form-errors')->get() ?? []; ?>
<input name="email">
<?php if (isset($errors['email'])): ?>
    <small class="error"><?= htmlspecialchars($errors['email']) ?></small>
<?php endif ?>
```

### Multi-slot toasts

```php
tiny::flash('success')->set('Profile saved');
tiny::flash('warning')->set('Re-verify your email');
```

```php
<?php foreach (['success', 'warning', 'error', 'info'] as $level): ?>
    <?php $msg = tiny::flash($level)->get(); ?>
    <?php if ($msg): ?>
        <div class="alert alert-<?= $level ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif ?>
<?php endforeach ?>
```

## Notes

- Flash data lives in `$_SESSION`, so it requires sessions to be active (`tiny::init()` handles this).
- Don't store large blobs — sessions usually live in files or Redis, and oversized payloads slow everything down.
- Don't store secrets. Session storage is server-side, but flash values are usually rendered into HTML on the next request.
- The CSRF extension's `showError(nextPage: true)` uses `tiny::flash('toast')` under the hood — keep the slot name conflict in mind if you reuse `'toast'` for your own messages.
