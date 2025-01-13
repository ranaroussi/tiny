[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Spaces (S3) Helper

The Spaces helper provides an interface for managing file storage using DigitalOcean Spaces or Amazon S3.

## Configuration

Configure in your `.env` file:

```env
SPACES_KEY=your_access_key
SPACES_SECRET=your_secret_key
SPACES_REGION=nyc3
SPACES_BUCKET=your-bucket
SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
```

## Basic Usage

### Uploading Files

```php
// Upload file from path
$url = tiny::spaces()->upload(
    'uploads/images',
    '/path/to/local/file.jpg'
);

// Upload from content
$url = tiny::spaces()->uploadContent(
    'uploads/files/doc.pdf',
    $fileContent,
    [
        'ContentType' => 'application/pdf',
        'ACL' => 'public-read'
    ]
);

// Upload with custom options
$url = tiny::spaces()->upload('path/to/file.jpg', $file, [
    'ACL' => 'private',
    'CacheControl' => 'max-age=31536000',
    'Metadata' => ['user_id' => '123']
]);
```

### Downloading Files

```php
// Get file
$content = tiny::spaces()->get('path/to/file.jpg');

// Get with expiring URL
$url = tiny::spaces()->signedUrl('private/file.pdf', '+2 hours');

// Download to local path
tiny::spaces()->download('remote/file.zip', '/local/path/file.zip');
```

### File Management

```php
// Check if file exists
if (tiny::spaces()->exists('path/to/file.jpg')) {
    // File exists
}

// Delete file
tiny::spaces()->delete('path/to/file.jpg');

// Copy file
tiny::spaces()->copy(
    'source/file.jpg',
    'destination/file.jpg'
);

// Move/rename file
tiny::spaces()->move(
    'old/path/file.jpg',
    'new/path/file.jpg'
);
```

## Advanced Features

### Directory Operations

```php
// List files
$files = tiny::spaces()->list('uploads/images/');

// List with prefix
$files = tiny::spaces()->list('uploads/', [
    'prefix' => 'images/',
    'maxKeys' => 100
]);

// Delete directory
tiny::spaces()->deleteDir('old/uploads/');
```

### Access Control

```php
// Make file public
tiny::spaces()->setAcl(
    'path/to/file.jpg',
    'public-read'
);

// Make file private
tiny::spaces()->setAcl(
    'path/to/file.jpg',
    'private'
);
```

### Metadata Management

```php
// Get file info
$info = tiny::spaces()->info('path/to/file.jpg');

// Update metadata
tiny::spaces()->updateMetadata('path/to/file.jpg', [
    'ContentType' => 'image/jpeg',
    'CacheControl' => 'max-age=86400'
]);
```

## Best Practices

1. **Organization**
   - Use consistent path structure
   - Group related files
   - Use meaningful prefixes
   - Clean up temporary files

2. **Performance**
   - Use appropriate cache headers
   - Optimize file sizes
   - Batch operations
   - Use CDN when possible

3. **Security**
   - Validate file types
   - Use private ACLs
   - Set appropriate permissions
   - Clean file metadata

4. **Error Handling**
   - Handle upload failures
   - Verify file integrity
   - Log operations
   - Implement retries
