<?php

declare(strict_types=1);

/**
 * CMS Model for managing markdown content with caching.
 *
 * Handles retrieval, parsing, and caching of markdown files from the
 * static/md directory. Supports section-based organization and cache
 * invalidation.
 */
class TinyCMS
{
    /**
     * Default time-to-live for cached content in seconds.
     * Set to 30 days (84600 seconds * 30).
     */
    public $ttl;

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
     * Get all cache keys for a specific section and type.
     *
     * Retrieves all cache keys that match the given section prefix.
     * Useful for listing all cached items in a section.
     *
     * @param string $section Section identifier (e.g., 'docs', 'blog')
     * @param string $type Content type ('md' for markdown, 'html' for HTML)
     * @return array Array of matching cache keys
     */
    public function getSection(string $section, string $type = 'md'): array
    {
        // Build cache key prefix: cms:type:section
        $key = 'cms:' . $type . ':' . $section;
        // Return all keys matching this prefix
        return tiny::cache()->getByPrefix($key);
    }

    /**
     * Refresh (invalidate) cache for a specific file.
     *
     * Deletes the cached entry for a file, forcing it to be regenerated
     * on next access. Uses MD5 hash of filename for cache key.
     *
     * @param string $file Filename relative to app/cms directory
     * @param string|null $section Optional section identifier
     * @param string $type Content type ('md' or 'html')
     * @return bool True if deletion succeeded, false otherwise
     */
    public function refreshFile(string $file, ?string $section = null, string $type = 'md'): bool
    {
        // Add colon suffix if section is provided
        $section = $section ? $section . ':' : '';
        // Build cache key: cms:type:section:md5hash
        $key = 'cms:' . $type . ':' . $section . md5($file);
        // Delete the specific cache entry
        return tiny::cache()->delete($key);
    }

    /**
     * Refresh all cached content for a section.
     *
     * Invalidates all cached markdown and HTML content for the given
     * section. Returns count of deleted entries for each type.
     *
     * @param string $section Section identifier to refresh
     * @return array Associative array with 'md' and 'html' deletion results
     */
    public function refreshSection(string $section): array
    {
        // Add colon suffix for prefix matching
        $section = $section ? $section . ':' : '';
        // Delete all markdown and HTML cache entries for this section
        return [
            'md' => tiny::cache()->deleteByPrefix('cms:md:' . $section),
            'html' => tiny::cache()->deleteByPrefix('cms:html:' . $section),
        ];
    }

    /**
     * Get raw markdown content from file with caching.
     *
     * Retrieves markdown file content, caching it for the specified TTL.
     * Returns null if file doesn't exist or is empty.
     *
     * @param string $file Filename relative to app/cms directory
     * @param string|null $section Optional section identifier for cache key
     * @param int|null $ttl Time-to-live in seconds (uses default if null)
     * @return object|null Raw markdown content and metadata or null if file not found
     */
    public function getMarkdownPage(string $file, ?string $section = null, ?int $ttl = null): ?object
    {
        // Use provided TTL or fall back to default
        $ttl = $ttl ?? $this->ttl;

        // Build full file path
        $filePath = tiny::config()->cms_path . '/' . $file;
        // Return null if file doesn't exist
        if (!file_exists($filePath)) {
            return null;
        }

        // Add colon suffix if section is provided
        $section = $section ? $section . ':' : '';
        // Build cache key using MD5 hash of filename
        $key = 'cms:md:' . $section . md5($file);

        // Try cache first, then read file if not cached
        return tiny::cache()->remember($key, $ttl, function () use ($filePath) {
            // Read file contents
            $content = file_get_contents($filePath);

            // Return null if file is empty
            if (!$content) {
                return null;
            }

            $content = trim($content);;

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
                'metadata' => $metadata,
            ];
        });
    }

    /**
     * Get parsed HTML content from markdown file with caching.
     *
     * Retrieves markdown content, parses it to HTML, and caches both
     * the raw markdown and parsed HTML. Returns null if file not found.
     *
     * @param string $file Filename relative to static/md directory
     * @param string|null $section Optional section identifier for cache key
     * @param int|null $ttl Time-to-live in seconds (uses default if null)
     * @return object|null Parsed HTML content and metadata or null if file not found

     */
    public function getPage(string $file, ?string $section = null, ?int $ttl = null): ?object
    {
        // Use provided TTL or fall back to default
        $ttl = $ttl ?? $this->ttl;

        // First get the raw markdown content (this also caches it)
        $page = $this->getMarkdownPage($file, $section, $ttl);
        // Return null if markdown file doesn't exist
        if (!$page) {
            return null;
        }

        // Add colon suffix if section is provided
        $section = $section ? $section . ':' : '';
        // Build cache key for parsed HTML version
        $key = 'cms:html:' . $section . md5($file);

        // Try cache first, then parse markdown if not cached
        return tiny::cache()->remember($key, $ttl, function () use ($page) {
            // Transform markdown to HTML (true = enable HTML, false = no breaks)
            $page->html = tiny::markdown()->transform($page->raw, true, false);
            return $page;
        });
    }
}
