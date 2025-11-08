<?php

declare(strict_types=1);

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
    public $ttl;
    public $scannedFiles = [];

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
        return ($path ? str_replace('/', ':', $path) . ($addColon ? ':' : '') : '');
    }

    /**
     * Scan the CMS directory and get all the files.
     *
     * @param string|null $path The path to scan
     * @param int|null $ttl The time to live for the cached content
     * @return void
     */
    public function scanCMS(?string $path = null, ?int $ttl = null): int
    {
        $this->scannedFiles = [];

        // Use provided TTL or fall back to default
        $ttl = $ttl ?? $this->ttl;

        // Build the CMS path
        $cmsPath = tiny::config()->cms_path;
        if ($path) {
            $cmsPath .= '/' . $path;
        }

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
        $files = scandir($cmsPath);

        // Filter out the files that are not markdown files or directories
        $files = array_filter($files, function ($file) use ($cmsPath) {
            return ($file !== '.' && $file !== '..') && (pathinfo($file, PATHINFO_EXTENSION) === 'md' || is_dir($cmsPath . '/' . $file));
        });

        // Loop through the files and add them to the scanned files array
        foreach ($files as $file) {
            if (is_dir($cmsPath . '/' . $file)) {
                // Recursively scan the subdirectory
                $this->scanCMS($path . '/' . $file);
            } else {
                // Build the file path
                $filePath = ($path ? $path . '/' : '') . $file;
                // Add the file path to the scanned files array
                $this->scannedFiles[] = $filePath;
                // Cache the page content
                $this->getPage($filePath, $path, $ttl);
            }
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
     * @param int|null $ttl Time-to-live in seconds (uses default if null)
     * @return array Array of page objects
     */
    public function getPathPages(string $path = '', ?int $ttl = null): array
    {
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
            $page = $this->getPage($filePath, $ttl);
            if ($page) {
                $pages[$filename] = $page;
            }
        }

        return $pages;
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
     * @return bool True if deletion succeeded, false otherwise
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
     * @return object|null HTML, waw markdown, and metadata or null if file not found
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
        return tiny::cache()->remember($key, $ttl, function () use ($filePath) {

            // Read file contents
            $content = file_get_contents($filePath);

            // Return null if file is empty
            if (!$content) {
                return null;
            }

            // Trim the content
            $content = trim($content);

            // Extract frontmatter metadata if present
            $metadata = [];
            if (str_starts_with($content, "---\n")) {
                // Split frontmatter from content
                $content = explode("\n---\n", $content);

                $metadata = [];
                // Parse frontmatter lines (key: value format)
                $meta = explode("\n", str_replace("---\n", '', $content[0]));;
                foreach ($meta as $line) {
                    // Split on ': ' to get key-value pairs
                    $line = explode(': ', $line, 2);
                    $metadata[trim($line[0])] = trim($line[1]);
                }

                // Process tags
                if (isset($metadata['tags'])) {
                    $metadata['tags'] = explode(',', $metadata['tags']);
                    $metadata['tags'] = array_map('strtolower', $metadata['tags']);
                    $metadata['tags'] = array_map('trim', $metadata['tags']);
                    $metadata['tags'] = array_unique($metadata['tags']);
                }

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
            $re = '/(```(.*?)\n)((.|\n)*?)\n```/mi';
            $content = preg_replace_callback($re, function ($matches) {
                return $matches[1] . $matches[3] . "\n```";
            }, $content);

            // Process card syntax: (path)[[card optional-text]]
            // Converts to internal link with card formatting
            $re = '/(\((.*)\))(\[\[card(\s(.*?))?\]\])/mi';
            $content = preg_replace($re, '(' . tiny::getHomeURL() . '$2)$3', $content);

            // Return content and metadata as an object
            return (object) [
                'raw' => $content,
                'html' => tiny::markdown()->transform($content, true, false),
                'metadata' => $metadata,
            ];
        });
    }
}
