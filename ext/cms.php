<?php

declare(strict_types=1);
tiny::helpers(['markdown', 'opengraph']);

/**
 * CMS Model for managing markdown content with caching.
 *
 * Handles retrieval, parsing, and caching of markdown files from the
 * app/cms directory. Supports section-based organization and cache
 * invalidation.
 *
 * ------------------------------------------------------------
 *
 * # Human-readable markdown
 *
 * Write and maintain pages using **human-readable markdown**
 * that stays easy to read at source while supporting rich layouts and
 * interactivity.
 *
 * We use **GitHub-flavored markdown (GFM)** with a few lightweight,
 * intuitive extensions for rich layouts and interactivity:
 * - ✅ Callouts
 * - ✅ Tabs
 * - ✅ Cards
 * - ✅ Columns
 * - ✅ Toggles
 * - ✅ Boxed sidebars
 * - ✅ Bookmarks
 * - ✅ Steps
 *
 * Everything renders beautifully without breaking readability for
 * humans or editors.
 *
 *
 * ## 1. Callouts
 *
 * GitHub-flavored callouts work natively using the `> [!TYPE]` pattern.
 *
 * ```md
 * > [!NOTE]
 * > This endpoint requires authentication via headers.
 * >
 * > Example: `TGX-API-KEY`
 * ```
 *
 * ### Supported types
 *
 * - `[!NOTE]` – default callout style
 * - `[!INFO]` – informational callout
 * - `[!TIP]` – helpful tips and suggestions
 * - `[!IMPORTANT]` – important information to note
 * - `[!WARNING]` – warnings about potential issues
 * - `[!CAUTION]` – cautionary notices
 * - `[!DANGER]` – critical warnings about dangerous actions
 *
 * Each will render with its own visual style.
 *
 * ---
 *
 * ## 2. Tabs
 *
 * Tabs group related content (for example, code snippets in multiple languages).
 *
 * ```md
 * [[tabs]]
 *
 * [[tab python]]
 * ```python
 * print("Hello, World!")
 * ```
 * [[/tab]]
 *
 * [[tab javascript]]
 * ```js
 * console.log("Hello, World!")
 * ```
 * [[/tab]]
 *
 * [[/tabs]]
 * ```
 *
 * **Rules:**
 *
 * - text after `"tab"` defines the tab’s title.
 * - Spaces are converted to `-` for the tab’s internal ID.
 * - If no label is provided, tabs default to `tab-1`, `tab-2`, etc.
 * - If **all tabs** contain only fenced code blocks, the container renders with
 *   `<div class="tab-group">`.
 *
 * ---
 *
 * ## 3. Cards
 *
 * Cards highlight small pieces of information, previews, or summaries.
 *
 * ```md
 * (link/to/)[[card]]
 * #### Chat window
 * The main area where users talk to your bot.
 * [[/card]]
 * ```
 *
 * Each `[[card]]` block can include **any valid markdown** (headings, images, lists, etc.).
 *
 * ---
 *
 * ## 4. Columns
 *
 * Columns group cards or blocks into a responsive grid layout.
 *
 * ```md
 * :::: cols=3
 *
 * [[card]] card 1 [[/card]]
 * [[card]] card 2 [[/card]]
 * [[card]] card 3 [[/card]]
 *
 * ::::
 * ```
 *
 * Set `cols=2`, `cols=3`, etc. to control layout.
 *
 * ---
 *
 * ## 5. Toggles
 *
 * Toggles render as collapsible `<details>` elements for optional or advanced content.
 *
 * ```md
 * [[toggle Advanced options]]
 * You can include **any content** here — text, lists, code, etc.
 * [[/toggle]]
 * ```
 *
 * Renders as:
 *
 * ```html
 * <details>
 *   <summary>Advanced options</summary>
 *   <p>You can include any content here...</p>
 * </details>
 * ```
 *
 * ---
 *
 * ## 6. Boxed sidebars
 *
 * Boxed sections float beside the main content for tips, reminders, or notes.
 *
 * ```md
 * [[boxed float-right]]
 * You can add diagrams, notes, or additional context here.
 * [[/boxed]]
 * ```
 *
 * ### Float options
 * - `float-right` (default)
 * - `float-left`
 *
 * Renders as:
 *
 * ```html
 * <div class="boxed float-right">
 *   <p>You can add diagrams, notes, or additional context here.</p>
 * </div>
 * ```
 *
 * ---
 *
 * ## 7. Bookmarks
 *
 * Bookmarks create a clean, icon-enhanced list of linked articles or related reading.
 *
 * ```md
 * [+] [test article 1]({base_url}/docs/some-article)
 * [+] [test article 2]({base_url}/docs/some-article)
 * [+] [test article 3]({base_url}/docs/some-article)
 * ```
 *
 * Each line renders with a small bookmark icon and the link text, for example:
 *
 * 📑 **test article 1**
 * 📑 **test article 2**
 * 📑 **test article 3**
 *
 * ---
 *
 * ## 8. Steps
 *
 * Steps create numbered, sequential instructions for tutorials and guides.
 *
 * ```md
 * [[steps]]
 *
 * [[step Create your account]]
 *
 * Visit our website and click the **Sign Up** button. Fill in your:
 *
 * - Email address
 * - Password
 * - Display name
 *
 * [[/step]]
 *
 * [[step Verify your email]]
 *
 * Check your inbox for a verification email. Click the link to confirm your email address.
 *
 * > [!TIP]
 * > Check your spam folder if you don't see the email.
 *
 * [[/step]]
 *
 * [[step Complete your profile]]
 *
 * Add more information to your profile:
 *
 * 1. Upload a profile picture
 * 2. Add your bio
 * 3. Set your preferences
 *
 * [[/step]]
 *
 * [[/steps]]
 * ```
 *
 * **Rules:**
 *
 * - Steps are automatically numbered (Step 1, Step 2, etc.)
 * - Text after `[[step ...]]` becomes the step title (optional)
 * - Each step supports **full markdown** (lists, code blocks, callouts, etc.)
 * - Steps render as `<ul class="steps">` with `<li class="step">` items
 *
 * Renders as:
 *
 * ```html
 * <ul class="steps">
 *   <li class="step">
 *     <h3 class="step-title">Step 1</h3>
 *     <p>Visit our website and click the <strong>Sign Up</strong> button...</p>
 *   </li>
 *   <li class="step">
 *     <h3 class="step-title">Step 2</h3>
 *     <p>Check your inbox...</p>
 *   </li>
 * </ul>
 * ```
 *
 * ---
 *
 * ## Writing conventions
 *
 * - Keep all custom tags (`[[...]]`, `::::`, etc.) **flush-left** — avoid indenting them inside lists or code.
 * - Inside custom blocks, you can write normal markdown (headings, lists, tables, etc.).
 * - Use blank lines between structural blocks for readability.
 * - Keep labels lowercase for consistency, but they’re case-insensitive.
 *
 * ---
 *
 * ## Example: combining multiple elements
 *
 * ```md
 * > [!TIP]
 * > You can combine **callouts** with cards, tabs, or toggles for richer docs.
 *
 * [[tabs]]
 *
 * [[tab label="python"]]
 * ```python
 * print("Hello")
 * ```
 * [[/tab]]
 *
 * [[tab label="javascript"]]
 * ```js
 * console.log("Hello")
 * ```
 * [[/tab]]
 *
 * [[/tabs]]
 *
 * [[toggle More examples]]
 * :::: cols=2
 * [[card]]**Feature 1**: fast setup[[/card]]
 * [[card]]**Feature 2**: human-readable markdown[[/card]]
 * ::::
 * [[/toggle]]
 * ```
 *
 * ---
 *
 * ## Final notes
 *
 * - These patterns are designed for **clarity and compatibility** — markdown remains human-readable in raw form.
 * - No build-time magic is required — all features can be parsed via a simple line-based preprocessor.
 * - Always preview rendered docs before committing to ensure structure looks right.
 *
 * ------------------------------------------------------------
 */
class TinyCMS
{
    /**
     * Default time-to-live for cached content in seconds.
     * Set to 30 days (84600 seconds * 30).
     */
    public int $ttl;
    public array $scannedFiles = [];

    /**
     * Initialize CMS model with default TTL.
     *
     * Sets the default cache TTL to 30 days (84600 seconds * 30).
     */
    function __construct(int $ttl = 84600 * 30)
    {
        tiny::helpers(['markdown']);
        $this->ttl = $ttl;
    }

    /**
     * Convert a path to a cache key.
     *
     * @param string $path The path to convert
     * @param bool $addColon Whether to add a colon to the end of the key
     * @return string The cache key
     */
    private function pathToKey(string $path = '', bool $addColon = true): string
    {
        return ltrim($path ? str_replace('/', ':', $path) . ($addColon ? ':' : '') : '', ':');
    }

    /**
     * Scan the CMS directory and get all the files.
     *
     * @param string|null $path The path to scan
     * @param int|null $ttl The time to live for the cached content
     * @return int
     */
    public function scanCMS(?string $path = null, ?int $ttl = null): int
    {
        // Use provided TTL or fall back to default
        $ttl = $ttl ?? $this->ttl;

        // Build the CMS path
        $cmsPath = tiny::config()->cms_path;
        if ($path) {
            $cmsPath .= '/' . $path;
        }
        $cmsPath = str_replace('//', '/', $cmsPath); // Ensure no double slashes

        // Check if directory exists
        if (!is_dir($cmsPath)) {
            error_log("CMS path not found: {$cmsPath}");
            return 0;
        }

        // Check if directory is readable
        if (!is_readable($cmsPath)) {
            error_log("CMS path not readable: {$cmsPath}");
            return 0;
        }

        // Scan the CMS directory
        $dir_contents = scandir($cmsPath);

        // Filter out the files that are not markdown files or directories
        $files = array_filter($dir_contents, function ($file) use ($cmsPath) {
            return ($file !== '.' && $file !== '..') && (pathinfo($file, PATHINFO_EXTENSION) === 'md');
        });

        // Loop through the files and add them to the scanned files array
        foreach ($files as $file) {
            // Build the file path
            $filePath = ($path ? $path . '/' : '') . $file;
            // Add the file path to the scanned files array
            $this->scannedFiles[] = $filePath;
            // Cache the page content
            $this->getPage($filePath, $ttl);
        }

        $directories = array_filter($dir_contents, function ($file) use ($cmsPath) {
            return ($file !== '.' && $file !== '..') && is_dir($cmsPath . '/' . $file);
        });

        // Loop through the files and add them to the scanned files array
        foreach ($directories as $directory) {
            $this->scanCMS($path . '/' . $directory);
        }

        // Return the number of scanned files
        return count($this->scannedFiles);
    }

    /**
     * Get all cache keys for a specific path and type.
     *
     * Retrieves all cache keys that match the given path prefix.
     * Useful for listing all cached items in a path.
     *
     * @param string $path Path identifier (e.g., 'docs', 'blog')
     * @return array Array of matching cache keys
     */
    public function getPath(string $path = ''): array
    {
        // Build cache key prefix: cms:path
        $key = 'cms:' . $this->pathToKey($path);
        // Return all keys matching this prefix
        return tiny::cache()->getByPrefix($key);
    }

    /**
     * Get all pages in a specific path.
     *
     * Retrieves all page objects for markdown files in the given path.
     * Returns an array of page objects with raw markdown, HTML, and metadata.
     *
     * @param string $path Path to get pages from (e.g., 'blog', 'help/guides')
     * @param int|null $since Optional timestamp to filter pages created after this time
     * @return array Array of page objects
     */
    public function getPathPages(string $path = '', null|string|int $since = null): array
    {
        $since = $since ?: 0;
        if (is_string($since)) {
            $since = strtotime($since);
        }

        $pages = [];
        $keys = $this->getPath($path);

        foreach ($keys as $key) {
            // Extract file from cache key
            // Remove 'cms:' prefix and path prefix to get just the filename
            $pathPrefix = 'cms:' . $this->pathToKey($path);
            $filename = str_replace($pathPrefix, '', $key);

            // Convert cache key format back to filename (replace : with /)
            $filename = str_replace(':', '/', $filename);

            // Build full file path
            $filePath = $path ? $path . '/' . $filename : $filename;

            // Load the page
            $page = $this->getPage($filePath);
            if ($page) {
                $pages[$page->created .':'. $page->hash] = $page;
            }
        }

        // sort by key descending
        krsort($pages);

        return array_filter($pages, function ($page) use ($since) {
            return $page->created > $since;
        });
    }

    /**
     * Refresh (invalidate) cache for a specific file.
     *
     * Deletes the cached entry for a file, forcing it to be regenerated
     * on next access. Uses MD5 hash of filename for cache key.
     *
     * @param string $file Filename relative to app/cms directory
     * @param string|null $path Optional section identifier
     * @return bool True if deletion succeeded, false otherwise
     */
    public function refreshFile(string $file, ?string $path = null): bool
    {
        // Add colon suffix if section is provided
        $key = 'cms:' . $this->pathToKey($path) . tiny::dirify($file);
        // Delete the specific cache entry
        return tiny::cache()->delete($key);
    }

    /**
     * Refresh all cached content for a section.
     *
     * Invalidates all cached markdown and HTML content for the given
     * section. Returns count of deleted entries for each type.
     *
     * @param string $path Section identifier to refresh
     * @return void
     */
    public function refreshPath(string $path = ''): void
    {
        // Add colon suffix for prefix matching
        $key = 'cms:' . $this->pathToKey($path);
        // Delete all markdown and HTML cache entries for this section
        tiny::cache()->deleteByPrefix($key);
    }

    /**
     * Get raw markdown page content from file with caching.
     *
     * Retrieves markdown file content, caching it for the specified TTL.
     * Returns null if file doesn't exist or is empty.
     *
     * @param string $file Filename relative to app/cms directory
     * @param int|null $ttl Time-to-live in seconds (uses default if null)
     * @return object|null HTML, raw markdown, and metadata or null if file not found
     */
    public function getPage(string $file, ?int $ttl = null): ?object
    {
        // Use provided TTL or fall back to default
        $ttl = $ttl ?? $this->ttl;

        // Build full file path
        $filePath = tiny::config()->cms_path . '/' . $file;
        // Return null if file doesn't exist
        if (!file_exists($filePath)) {
            return null;
        }

        $path = '';
        if (str_contains($file, '/')) {
            $parts = explode('/', $file);
            $file = array_pop($parts);  // Get filename (last part)
            $path = implode('/', $parts); // Get directory path
        }

        $key = 'cms:' . $this->pathToKey($path) . tiny::dirify($file);

        // Return cached page if it exists
        return tiny::cache()->remember($key, $ttl, function () use ($filePath, $file, $path) {

            // Read file contents
            $content = file_get_contents($filePath);

            // Return null if file is empty
            if (!$content) {
                return null;
            }

            // Trim the content
            $content = trim($content);

            // Extract frontmatter metadata if present
            $metadata = [
                'ogimage' => null,
            ];
            if (str_starts_with($content, "---")) {
                // Split frontmatter from content
                $content = explode("\n---\n", str_replace("\n-----\n", "\n---\n", $content));

                $metadata = [];
                // Parse frontmatter lines (key: value format)
                $meta = explode("\n", trim(str_replace("---\n", '', $content[0]), '--'));
                foreach ($meta as $line) {
                    // Split on ': ' to get key-value pairs
                    $line = explode(': ', $line, 2);
                    $metadata[trim($line[0])] = trim($line[1]);
                }

                // Process tags
                if (isset($metadata['tags'])) {
                    // tiny::dd($metadata);
                    $metadata['tags'] = str_contains(',', $metadata['tags']) ? explode(',', $metadata['tags']) : explode(' ', $metadata['tags']);
                    $metadata['tags'] = array_map('strtolower', $metadata['tags']);
                    $metadata['tags'] = array_map('trim', $metadata['tags']);
                    $metadata['tags'] = array_unique($metadata['tags']);
                }

                $metadata['description'] = $metadata['description'] ?? $metadata['ogdescription'] ?? null;
                $metadata['title'] = $metadata['title'] ?? $metadata['ogtitle'] ?? null;

                // Remove frontmatter from src array and rejoin content
                unset($content[0]);
                $content = trim(implode("\n---\n", $content));
            }

            // Remove .md extension from markdown links
            // Matches [text](path.md) and removes .md
            $re = '/(\[.*\])(\(.*).md(\))/mi';
            $content = preg_replace($re, '$1$2$3', $content);
            $content = trim($content);

            // Fix angle brackets and links in code blocks
            // Escapes HTML and removes internal doc links from code examples
            $re = '/(```(.*?)\n)([\s\S]*?)\n```/mi';
            $content = preg_replace_callback($re, function ($matches) {
                return $matches[1] . $matches[3] . "\n```";
            }, $content);

            // Process card syntax: (path)[[card optional-text]]
            // Converts to internal link with card formatting
            $re = '/(\((.*)\))(\[\[card(\s(.*?))?\]\])/mi';
            $content = preg_replace($re, '(' . tiny::getHomeURL() . '$2)$3', $content);

            // cards fix hack
            $content = preg_replace_callback('/\(((?!https?:\/\/|\/)[^)]+)\)\[\[/m', function ($matches) use ($path) {
                return '('. tiny::normalizeUrl(tiny::getHomeURL('/' . trim($path . '/' . $matches[1], '/'))) .')[[';
            }, $content) ?? '';

            if (@$metadata['title'] && $metadata['description']) {
                // generate open-graph image
                $opengraph = tiny::opengraph();
                $opengraph->setTemplate('template.png', 500, 270, 640);

                $opengraph->setTitle($metadata['title']);
                $opengraph->setTitleOptions(32, 1.5, [22, 21, 24], 'SF-Pro-Display-Bold.ttf');

                $opengraph->setDescription($metadata['description']);
                $opengraph->setDescriptionOptions(21, 1.8, [100, 100, 100], 'SF-Pro-Display-Medium.ttf', 20);

                $filename = str_replace('/', '-', trim(str_replace('posts/', '', str_replace('.md', '', $path ? $path . '/'. $file :$file)), '/')) . '.webp';
                $opengraph->render(80, tiny::config()->public_path . '/' . tiny::config()->static_dir . '/og/' . $filename);
                $metadata['ogimage'] = tiny::getStaticURL('/og/' . $filename, true);                
            }

            // generate html and excerpt from article body
            $html = trim(tiny::markdown()->transform($content, true, false)) . '';
            if (isset($metadata['lead'])) {
                $excerpt = $metadata['lead'];
            } else {
                try {
                    $bod = str_replace('<p>', "\n", str_replace('</p>', '', $html));
                    $excerpt = trim(explode("\n", trim($bod))[0]);
                    $excerpt = strip_tags($excerpt, '<a><strong><em>'); // Allow some HTML tags in excerpt
                } catch (Exception $e) {
                    $excerpt = '';
                }
            }

            // Return content and metadata as an object
            return (object) [
                'raw' => $content,
                'html' => $html,
                'excerpt' => $excerpt,
                'metadata' => $metadata,
                'created' => $metadata['created'] ?? filemtime($filePath),
                'path' => str_replace('.md', '', $path ? $path . '/'. $file :$file),
                'hash' => md5($file)
            ];
        });
    }

    /**
     * Get the most likely page for a given file path, with fallback to similar filenames.
     *
     * First attempts to retrieve the exact file. If not found, scans the directory for files
     * that end with the requested filename (e.g., 'pricing.md' would match '2024-01-pricing.md').
     * Returns the page object if found, or null if no matches are found.
     *
     * @param string $file Filename relative to app/cms directory
     * @param int|null $ttl Time-to-live in seconds for caching (uses default if null)
     * @return object|null HTML, raw markdown, and metadata of the matched page, or
     * null if no matching file is found
     */
    public function getLikelyPage(string $file, ?int $ttl = null): ?object
    {
        // Use provided TTL or fall back to default
        $directHit = $this->getPage($file, $ttl);

        if ($directHit) {
            return $directHit;
        }

        // Build full file path
        $filePathParts = explode('/', $file);

        $fileName = array_pop($filePathParts);
        $filePath = implode('/', $filePathParts);
        $dir_contents = scandir(tiny::config()->cms_path . '/' . $filePath);

        // Filter out the files that are not markdown files or directories
        $matches = array_filter($dir_contents, function ($file) use ($fileName) {
            return str_contains($file, $fileName);
        });

        if (count($matches) === 0) {
            return null;
        }

        return $this->getPage($filePath . '/' . array_values($matches)[0], $ttl);
    }
}
