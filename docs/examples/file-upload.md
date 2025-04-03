[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# File Upload Example

This example demonstrates how to handle file uploads in Tiny PHP Framework, including validation, storage, and image processing.

## Project Structure

```
/your-project
├── app/
│   ├── controllers/
│   │   └── uploads.php
│   ├── models/
│   │   └── upload.php
│   └── views/
│       └── uploads/
│           ├── form.php
│           └── success.php
├── public/
│   └── uploads/
│       ├── images/
│       └── documents/
└── config/
    └── uploads.php
```

## Configuration

```php
<?php
// config/uploads.php

return [
    'max_size' => 10 * 1024 * 1024, // 10MB
    'allowed_types' => [
        'image' => ['image/jpeg', 'image/png', 'image/gif'],
        'document' => ['application/pdf', 'application/msword']
    ],
    'storage' => [
        'local' => [
            'path' => 'public/uploads'
        ],
        'spaces' => [
            'bucket' => 'your-bucket',
            'folder' => 'uploads'
        ]
    ]
];
```

## Upload Controller

```php
<?php
// app/controllers/uploads.php

class Uploads extends TinyController
{
    private $model;

    public function __construct()
    {
        $this->model = tiny::model('upload');
    }

    // Show upload form
    public function get($request, $response)
    {
        return $response->render('uploads/form');
    }

    // Handle file upload
    public function post($request, $response)
    {
        // Validate file
        $file = $request->files->upload;

        if (!$file || !$file->isValid()) {
            return $response->back()->withError('Invalid file upload');
        }

        // Check file type
        if (!$this->model->isAllowedType($file->getMimeType())) {
            return $response->back()->withError('File type not allowed');
        }

        // Process upload
        try {
            $result = $this->model->store($file);

            return $response->redirect('/uploads/success')
                ->with('file', $result);
        } catch (Exception $e) {
            return $response->back()
                ->withError('Upload failed: ' . $e->getMessage());
        }
    }
}
```

## Upload Model

```php
<?php
// app/models/upload.php

class UploadModel extends TinyModel
{
    private $config;

    public function __construct()
    {
        $this->config = tiny::config('uploads');
    }

    public function store($file)
    {
        // Generate safe filename
        $filename = tiny::utils()->safeFilename($file->getName());
        $type = $this->getFileType($file->getMimeType());

        // Store file locally or in cloud
        if ($this->config['storage']['driver'] === 'spaces') {
            return $this->storeInSpaces($file, $type, $filename);
        }

        return $this->storeLocally($file, $type, $filename);
    }

    private function storeLocally($file, $type, $filename)
    {
        $path = $this->config['storage']['local']['path'] . '/' . $type;
        $fullPath = $path . '/' . $filename;

        // Create directory if it doesn't exist
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Move uploaded file
        if (!$file->moveTo($fullPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        // If it's an image, create thumbnails
        if ($type === 'images') {
            $this->createThumbnails($fullPath);
        }

        return [
            'filename' => $filename,
            'path' => $fullPath,
            'url' => '/uploads/' . $type . '/' . $filename,
            'type' => $type
        ];
    }

    private function storeInSpaces($file, $type, $filename)
    {
        $key = $this->config['storage']['spaces']['folder'] . '/' . $type . '/' . $filename;

        // Upload to DigitalOcean Spaces
        $url = tiny::spaces()->upload($key, $file->getTempName(), [
            'ACL' => 'public-read',
            'ContentType' => $file->getMimeType()
        ]);

        return [
            'filename' => $filename,
            'path' => $key,
            'url' => $url,
            'type' => $type
        ];
    }

    private function createThumbnails($path)
    {
        $sizes = [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600]
        ];

        foreach ($sizes as $size => [$width, $height]) {
            $thumbPath = $this->getThumbPath($path, $size);

            tiny::image($path)
                ->resize($width, $height)
                ->save($thumbPath);
        }
    }
}
```

## Upload Form View

```php
<!-- app/views/uploads/form.php -->
<?php tiny::layout()->extend('default') ?>

<?php tiny::layout()->section('content') ?>
    <div class="upload-form">
        <h1>File Upload</h1>

        <?php if ($error = tiny::flash('error')->get()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif ?>

        <form action="/upload" method="POST" enctype="multipart/form-data">
            <?php echo tiny::csrf()->field(); ?>

            <div class="form-group">
                <label for="upload">Choose File</label>
                <input type="file" name="upload" id="upload" required>
                <small>Max size: 10MB. Allowed types: JPG, PNG, GIF, PDF, DOC</small>
            </div>

            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    </div>

    <script>
        // Optional: Add client-side validation and preview
        document.getElementById('upload').onchange = function(e) {
            const file = e.target.files[0];
            if (file.size > <?php echo $config['max_size']; ?>) {
                alert('File is too large');
                e.target.value = '';
            }
        };
    </script>
<?php tiny::layout()->endSection() ?>
```

## Features Demonstrated

- File validation
- Secure file storage
- Cloud storage integration
- Image processing
- Thumbnail generation
- Progress tracking
- Error handling
- CSRF protection

## Best Practices

1. **Security**
   - Validate file types
   - Use secure filenames
   - Set upload limits
   - Check file contents
   - Implement CSRF protection

2. **Storage**
   - Use appropriate paths
   - Handle duplicates
   - Clean temporary files
   - Organize by type/date

3. **User Experience**
   - Show progress
   - Preview uploads
   - Handle errors gracefully
   - Provide feedback

4. **Performance**
   - Process async when possible
   - Optimize images
   - Use appropriate storage
   - Clean up failed uploads
