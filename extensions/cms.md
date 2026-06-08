[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# CMS (file-based markdown)

The CMS extension turns a directory of markdown files into a navigable, cacheable content tree. It's exposed via `tiny::cms()` and powers production sites such as [aroussi.com](https://aroussi.com).

There is no database. Pages and posts are markdown files on disk. Metadata is parsed from front-matter, the rendered HTML is cached, and tags / paths are indexed in memory.

## Layout convention

```
app/
└── cms/                          # default; override via TINY_CMS_PATH
    ├── pages/
    │   ├── about.md
    │   └── pricing.md
    ├── posts/
    │   ├── 2024-03-15-hello-world.md
    │   └── 2024-04-01-launch.md
    ├── terms.md                  # top-level pages also work
    └── privacy.md
```

The directory structure is conventional, not enforced — you can use any names. Common roots are `posts/` for dated content and `pages/` for evergreen ones.

## A page file

```markdown
---
title: Hello world
description: My first post
tags: [intro, launch]
draft: false
---

# Hello

This is **markdown** with [GFM](https://github.github.com/gfm/) extensions.

> [!NOTE]
> Callouts work out of the box.
```

Front-matter is parsed into a `metadata` array on the returned page object. Anything below `---` is markdown.

## Configuration

```env
TINY_CMS_PATH=/srv/my-app/app/cms     # absolute path; default is app/cms
TINY_CMS_REBUILD_TOKEN=…              # optional bearer token for rebuild endpoint
```

## API

```php
$cms = tiny::cms($ttl = 86400 * 30);    // singleton; $ttl is cache lifetime in seconds
```

### `scanCMS(?string $path = null, ?int $ttl = null): int`

Walks the CMS directory and populates the in-memory + APCu cache. Returns the number of files scanned. Call it once on first cold load (e.g. from your home controller) or via a cron/rebuild endpoint.

```php
$count = tiny::cms()->scanCMS();
```

### `getPathPages(string $path = '', null|string|int $since = null): array`

Returns all pages under a given path. Used for index pages.

```php
$posts = tiny::cms()->getPathPages('posts');
$recent = tiny::cms()->getPathPages('posts', '2024-01-01');
```

### `getPage(string $file, ?int $ttl = null): ?object`

Loads a single file by its relative path.

```php
$privacy = tiny::cms()->getPage('privacy.md');
echo $privacy->html;
echo $privacy->metadata['title'];
```

### `getLikelyPage(string $file, ?int $ttl = null): ?object`

Tries common variants (with/without `.md`, with/without trailing slash, in subdirs). Useful for handling user-supplied slugs that may or may not include the extension.

```php
$page = tiny::cms()->getLikelyPage('pages/' . $request->path->controller);
```

### `getPath(string $path = ''): array`

Lists files in a CMS directory (without parsing). Faster than `getPathPages()` when you only need filenames.

### `refreshFile(string $file, ?string $path = null): bool`

Invalidate a single file's cache (e.g. after a git pull).

### `refreshPath(string $path = ''): void`

Invalidate everything under a path.

## Typical patterns

### Blog index controller

```php
class Blog extends TinyController
{
    public function get($request, $response)
    {
        $posts = tiny::cms()->getPathPages('posts');
        if (count($posts) === 0) {
            tiny::cms()->scanCMS();
            $posts = tiny::cms()->getPathPages('posts');
        }
        // sort by metadata['date'] desc
        usort($posts, fn($a, $b) => strcmp($b->metadata['date'] ?? '', $a->metadata['date'] ?? ''));
        $response->render('blog/index', ['posts' => $posts]);
    }
}
```

### Post controller

```php
class Post extends TinyController
{
    public function get($request, $response)
    {
        $slug = $request->path->section;
        $page = tiny::cms()->getLikelyPage("posts/$slug");
        if (!$page) {
            tiny::controller('404', true);
        }
        $response->render('blog/post', ['post' => $page]);
    }
}
```

### Legal pages

```php
class Legal extends TinyController
{
    public function get($request, $response)
    {
        $response->render('legal/index', [
            'terms'      => tiny::cms()->getPage('terms.md'),
            'privacy'    => tiny::cms()->getPage('privacy.md'),
            'cookie'     => tiny::cms()->getPage('cookie.md'),
            'disclaimer' => tiny::cms()->getPage('disclaimer.md'),
        ]);
    }
}
```

### Rebuild endpoint (CI / webhook)

```php
class RpcSitemap extends TinyController
{
    public function post($request, $response)
    {
        // require bearer token
        $expected = $_SERVER['TINY_CMS_REBUILD_TOKEN'] ?? '';
        $given    = str_replace('Bearer ', '', $request->headers['Authorization'] ?? '');
        if (!$expected || $given !== $expected) {
            return $response->sendJSON(['error' => 'unauthorized'], 401);
        }

        tiny::cms()->scanCMS();
        $response->sendJSON(['files' => tiny::cms()->scannedFiles]);
    }
}
```

Trigger from your deploy script or git webhook to re-warm the CMS cache after content updates.

## Markdown extensions

In addition to GFM, the CMS understands:

- `> [!NOTE]`, `[!INFO]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, `[!CAUTION]`, `[!DANGER]` callouts
- `[[tabs]] … [[/tabs]]` with `[[tab Label]] … [[/tab]]` for multi-language code samples
- `(href)[[card]] … [[/card]]` for clickable cards
- `:::: cols=3 …. ::::` for responsive grids
- `[[toggle]]`, `[[sidebar]]`, `[[bookmark]]`, `[[steps]]` for richer documentation

These are rendered by the `markdown` and `opengraph` helpers, which the CMS loads automatically.

## Best practices

1. **Store metadata in front-matter.** Title, description, date, tags, draft flag — anything you want to display alongside the post.
2. **Use date-prefixed filenames** for posts (`2024-03-15-my-post.md`) — they sort naturally.
3. **Re-scan on deploy**, not per-request. `getPathPages()` is fast on cache hits; full scans aren't.
4. **Keep markdown small.** Each file is loaded into memory on scan.
5. **Don't commit secrets in front-matter.** It's served as-is to the controller.
