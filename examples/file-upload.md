[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# File uploads

This example accepts a single uploaded file, validates it, stores it on disk (with an optional S3-compatible mirror), and renders a confirmation. It uses PHP's built-in `$_FILES` superglobal — Tiny doesn't wrap it.

## Project structure

```
my-app/
├── app/
│   ├── controllers/
│   │   └── uploads.php
│   ├── models/
│   │   └── upload.php
│   └── views/
│       └── uploads/
│           ├── form.php
│           └── success.php
├── html/
│   └── static/uploads/          # local destination; served as /static/uploads/...
└── migrations/
    └── 20240101_uploads.php
```

## Table

```php
<?php
// migrations/20240101_uploads.php
class Uploads extends TinyMigration
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE uploads (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                filename    VARCHAR(255) NOT NULL,
                mime_type   VARCHAR(100) NOT NULL,
                size_bytes  INT NOT NULL,
                url         VARCHAR(500) NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS uploads");
    }
}
```

## Configuration

Put limits in your `.env`:

```env
TINY_UPLOAD_MAX_BYTES=10485760   # 10 MB
TINY_UPLOAD_DIR=/srv/my-app/html/static/uploads
TINY_UPLOAD_PUBLIC_PREFIX=/static/uploads
```

## Model

`app/models/upload.php`:

```php
<?php

class UploadModel extends TinyModel
{
    public const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function store(array $file, int $userId): array
    {
        $this->validateFile($file);

        $ext  = self::ALLOWED_MIME[$file['type']];
        $name = bin2hex(random_bytes(8)) . '.' . $ext;

        $dir       = rtrim($_SERVER['TINY_UPLOAD_DIR'] ?? '', '/');
        $publicDir = rtrim($_SERVER['TINY_UPLOAD_PUBLIC_PREFIX'] ?? '/static/uploads', '/');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fullPath = "$dir/$name";
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \RuntimeException('move_uploaded_file failed');
        }

        $url = "$publicDir/$name";

        // Optional: mirror to S3-compatible storage if configured.
        if (!empty($_SERVER['TINY_S3_BUCKET'])) {
            $url = tiny::spaces()->uploadFromDisk($fullPath, "uploads/$name");
        }

        $id = tiny::db()->insert('uploads', [
            'user_id'    => $userId,
            'filename'   => $name,
            'mime_type'  => $file['type'],
            'size_bytes' => $file['size'],
            'url'        => $url,
        ]);

        return tiny::db()->getOne('uploads', ['id' => $id]);
    }

    private function validateFile(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('upload error: ' . $file['error']);
        }

        $max = (int)($_SERVER['TINY_UPLOAD_MAX_BYTES'] ?? 10 * 1024 * 1024);
        if ($file['size'] > $max) {
            throw new \RuntimeException("file too large (max $max bytes)");
        }

        // Re-check MIME from contents, not the client-supplied header.
        $detected = mime_content_type($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$detected])) {
            throw new \RuntimeException("file type not allowed: $detected");
        }

        // Override the client value with the trusted one.
        $file['type'] = $detected;
    }
}
```

The MIME check uses `mime_content_type()` against the actual bytes — never trust the value the browser sends in `$_FILES['…']['type']`.

## Controller

`app/controllers/uploads.php`:

```php
<?php

class Uploads extends TinyController
{
    public function get($request, $response)
    {
        $response->render('uploads/form');
    }

    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->hasCSRFError();
        }

        $file = $_FILES['upload'] ?? null;
        if (!$file) {
            tiny::flash('toast')->set(['level' => 'error', 'message' => 'No file uploaded']);
            return $response->redirect('/uploads');
        }

        try {
            $upload = tiny::model('upload')->store($file, tiny::user()->id);
        } catch (\Throwable $e) {
            tiny::flash('toast')->set([
                'level'   => 'error',
                'message' => $e->getMessage(),
            ]);
            return $response->redirect('/uploads');
        }

        $response->render('uploads/success', ['upload' => $upload]);
    }
}
```

## Form view

`app/views/uploads/form.php`:

```php
<?php Layout::main(['title' => 'Upload a file']); ?>

    <h1>Upload a file</h1>

    <?php $toast = tiny::flash('toast')->get(); ?>
    <?php if ($toast): ?>
        <div class="alert alert-<?= htmlspecialchars($toast['level']) ?>">
            <?= htmlspecialchars($toast['message']) ?>
        </div>
    <?php endif ?>

    <form method="POST" action="/uploads" enctype="multipart/form-data">
        <?php tiny::csrf()->input(); ?>

        <label>Choose a file
            <input type="file" name="upload"
                   accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"
                   required>
        </label>
        <small>JPG, PNG, GIF, WebP, or PDF. Max 10 MB.</small>

        <button type="submit">Upload</button>
    </form>

<?php Layout::main(); ?>
```

## Success view

`app/views/uploads/success.php`:

```php
<?php Layout::main(['title' => 'Upload complete']); ?>

    <h1>Upload complete</h1>

    <p>Stored as <code><?= htmlspecialchars($upload->filename) ?></code>
       (<?= number_format($upload->size_bytes) ?> bytes).</p>

    <p><a href="<?= htmlspecialchars($upload->url) ?>" target="_blank">View file</a></p>
    <p><a href="/uploads">Upload another</a></p>

<?php Layout::main(); ?>
```

## Notes on size limits

PHP enforces upload limits in `php.ini` before your code ever runs:

```ini
upload_max_filesize = 10M
post_max_size       = 12M     # must be ≥ upload_max_filesize, with headroom for fields
max_file_uploads    = 20
```

If you let an oversize file through these, `$_FILES['upload']['error']` will be `UPLOAD_ERR_INI_SIZE`, which is what the `validateFile()` check catches.

## Streaming directly to S3 (skipping local disk)

When `TINY_S3_BUCKET` is set, the model above uploads the moved file to S3-compatible storage via `tiny::spaces()->uploadFromDisk()`. If you want to skip the local disk entirely, stream straight from `tmp_name`:

```php
$url = tiny::spaces()->uploadFromDisk($file['tmp_name'], "uploads/$name");
unlink($file['tmp_name']);   // optional; PHP cleans tmp on shutdown anyway
```

## Best practices

1. **Verify MIME from bytes**, not the browser-supplied header.
2. **Never store user filenames as-is.** Always generate a server-side name (random or hashed).
3. **Store outside the web root by default.** Only place files under `html/` when you genuinely want them served directly.
4. **Bound the size with both `php.ini` and your validator.** Defense in depth.
5. **Strip EXIF for images** if you accept user-uploaded photos (privacy). PHP's `imagecreatefromjpeg` + `imagejpeg` round-trip drops it.
6. **CSRF-protect the form.** A multipart form still needs the token in a hidden field.
