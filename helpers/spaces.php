<?php

declare(strict_types=1);

use Aws\S3\S3Client;

class Spaces
{
    private static ?S3Client $client = null;

    /**
     * Get or create an S3Client instance.
     *
     * @return S3Client The S3Client instance.
     */
    public static function client(): S3Client
    {
        if (self::$client === null) {
            self::$client = new S3Client([
                'version' => 'latest',
                'region' => $_SERVER['S3_REGION'] ?? '',
                'endpoint' => $_SERVER['S3_ENDPOINT'] ?? '',
                'use_path_style_endpoint' => false,
                'credentials' => [
                    'key' => $_SERVER['S3_KEY'] ?? '',
                    'secret' => $_SERVER['S3_SECRET'] ?? '',
                ],
            ]);
        }
        return self::$client;
    }

    /**
     * Delete an object from the S3 bucket.
     *
     * @param string $file The key of the object to delete.
     * @return string The deleted file path.
     */
    public static function deleteObject(string $file): string
    {
        self::client()->deleteObject([
            'Bucket' => $_SERVER['S3_BUCKET'] ?? '',
            'Key' => $file,
        ]);
        return "/$file";
    }

    /**
     * Purge cache for given file(s) from the DigitalOcean CDN.
     *
     * @param string|array $fileOrFiles File(s) to purge from cache.
     * @return array The API response as JSON.
     */
    public static function purgeCache($fileOrFiles): array
    {
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $files = array_map([self::class, 'prefixPath'], $files);

        return tiny::http()->delete(
            'https://api.digitalocean.com/v2/cdn/endpoints/' . ($_SERVER['DO_CDN_ID'] ?? '') . '/cache',
            [
                'data' => ['files' => $files],
                'headers' => ['Authorization: Bearer ' . ($_SERVER['DO_TOKEN'] ?? '')]
            ]
        )->json;
    }

    /**
     * Prefix a path with the S3 path prefix if set.
     *
     * @param string $path The path to prefix.
     * @return string The prefixed path.
     */
    public static function prefixPath(string $path): string
    {
        $path = ltrim($path, '/');
        $prefix = $_SERVER['S3_PATH_PREFIX'] ?? '';
        if ($prefix !== '' && strpos($path, $prefix) !== 0) {
            $path = rtrim($prefix, '/') . '/' . $path;
        }
        return $path;
    }

    /**
     * Upload a file from disk to S3.
     *
     * @param string $file The local file path.
     * @param string $path The destination path in S3.
     * @param bool $gzip Whether to gzip the file before upload.
     * @param bool $public Whether the file should be publicly accessible.
     * @param array $headers Additional headers for the S3 object.
     * @return string The uploaded file path.
     */
    public static function uploadFromDisk(string $file, string $path, bool $gzip = true, bool $public = true, array $headers = []): string
    {
        $path = self::prefixPath($path);
        $payload = [
            'Bucket' => $_SERVER['S3_BUCKET'] ?? '',
            'Key' => $path,
            'SourceFile' => $file,
            'ACL' => $public ? 'public-read' : 'private',
        ];

        $contentType = detectContentType(!str_contains($file, '.') ? $path : $file);
        if ($contentType) {
            $payload['ContentType'] = $contentType;
        }

        foreach ($headers as $key => $value) {
            $payload[$key] = $value;
        }

        if ($gzip) {
            $payload['ContentEncoding'] = 'gzip';
            $contents = file_get_contents($file);
            $fp = gzopen($file, 'w6');
            gzwrite($fp, $contents);
            gzclose($fp);
        }

        $result = self::client()->putObject($payload);
        return "/$path";
    }

    /**
     * Upload a data URL to S3.
     *
     * @param string $data The data URL.
     * @param string $path The destination path in S3.
     * @param string|null $ext The file extension.
     * @param bool $gzip Whether to gzip the file before upload.
     * @param bool $public Whether the file should be publicly accessible.
     * @param array $headers Additional headers for the S3 object.
     * @return string The uploaded file path.
     */
    public static function uploadDataURL(string $data, string $path, ?string $ext = null, bool $gzip = true, bool $public = true, array $headers = []): string
    {
        $ext = $ext ?? explode('+', explode('/', explode(';', $data)[0])[1])[0];
        $mimetype = explode(':', explode(';', $data)[0])[1];
        $base64data = explode('base64,', $data)[1];
        $filename = "$path.$ext";
        $temp_file = sys_get_temp_dir() . '/' . trim(str_replace('/', '-', $filename), '/');
        file_put_contents($temp_file, base64_decode($base64data));
        $res = self::uploadFromDisk($temp_file, $filename, $gzip, $public, $headers);
        unlink($temp_file);
        return $res;
    }

    /**
     * Upload a remote file to S3.
     *
     * @param string $url The URL of the remote file.
     * @param string $path The destination path in S3.
     * @param bool $gzip Whether to gzip the file before upload.
     * @param bool $public Whether the file should be publicly accessible.
     * @param array $headers Additional headers for the S3 object.
     * @return string|false The uploaded file path or false on failure.
     */
    public static function uploadRemoteFile(string $url, string $path, bool $gzip = true, bool $public = true, array $headers = []): string|false
    {
        $temp_file = str_replace('//', '/', sys_get_temp_dir() . '/' . preg_replace('/[^0-9]/', '', microtime()));

        // download file
        $fp = fopen($temp_file, 'w');
        curl_setopt_array($ch = curl_init(), [
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE => $fp
        ]);
        curl_exec($ch);
        curl_close($ch);

        $ext = substr($path, strrpos($path, '.') + 1);
        if (!$ext) {
            $ext = explode('/', mime_content_type($temp_file));
            $ext = count($ext) > 1 ? explode('+', $ext[1])[0] : '';
            if ($ext) $path .= ".$ext";
        }

        // upload file
        if (file_exists($temp_file)) {
            $res = self::uploadFromDisk($temp_file, $path, $gzip, $public, $headers);
            unlink($temp_file);
            return $res;
        }

        return false;
    }

    /**
     * Build a URL for an S3 object.
     *
     * @param string|null $path The object path.
     * @param bool $useCDN Whether to use the CDN URL if available.
     * @param bool $useCache Whether to use the cache.
     * @return string The full URL to the object.
     */
    public static function buildURL(?string $path, bool $useCDN = true, bool $useCache = true): string
    {
        $path = $path == '' ? $path : '/' . ltrim($path, '/');
        if ($useCache) {
            return tiny::cache()->remember('spaces:url:' . md5($path . $useCDN), 3600, function () use ($path, $useCDN) {
                if ($useCDN && $_SERVER['S3_CDN'] ?? '') {
                    return $_SERVER['S3_CDN'] . $path;
                }
                return 'https://' . ($_SERVER['S3_BUCKET'] ?? '') . '.' . explode('://', $_SERVER['S3_ENDPOINT'] ?? '')[1] . $path;
            });
        }

        if ($useCDN && $_SERVER['S3_CDN'] ?? '') {
            return $_SERVER['S3_CDN'] . $path;
        }
        return 'https://' . ($_SERVER['S3_BUCKET'] ?? '') . '.' . explode('://', $_SERVER['S3_ENDPOINT'] ?? '')[1] . $path;
    }

    /**
     * Extract the path from a full S3 or CDN URL.
     *
     * @param string $path The full URL.
     * @param bool $useCDN Whether to consider the CDN URL if available.
     * @param bool $useCache Whether to use the cache.
     * @return string The extracted path.
     */
    public static function pathURL(string $path, bool $useCDN = true, bool $useCache = true): string
    {
        if ($useCache) {
            return tiny::cache()->remember('spaces:path:' . md5($path . $useCDN), 3600, function () use ($path, $useCDN) {
                if ($useCDN && $_SERVER['S3_CDN'] ?? '') {
                    return str_replace($_SERVER['S3_CDN'], '', $path . '');
                }
                return str_replace('https://' . ($_SERVER['S3_BUCKET'] ?? '') . '.' . explode('://', $_SERVER['S3_ENDPOINT'] ?? '')[1], '', $path);
            });
        }

        if ($useCDN && $_SERVER['S3_CDN'] ?? '') {
            return str_replace($_SERVER['S3_CDN'], '', $path . '');
        }
        return str_replace('https://' . ($_SERVER['S3_BUCKET'] ?? '') . '.' . explode('://', $_SERVER['S3_ENDPOINT'] ?? '')[1], '', $path);
    }

    /**
     * Generate JavaScript code for building S3 URLs.
     *
     * @return string JavaScript code for the tiny.getSpacesURL function.
     */
    public static function buildURLJS(): string
    {
        if ($_SERVER['S3_CDN'] ?? '') {
            return 'tiny.getSpacesURL = (path) => `' . $_SERVER['S3_CDN'] . '${path.slice(0, 1) === "/" ? "" : "/"}${path}`';
        }
        return 'tiny.getSpacesURL = (path) => `https://' . ($_SERVER['S3_BUCKET'] ?? '') . '.' . explode('://', $_SERVER['S3_ENDPOINT'] ?? '')[1] . '${path.slice(0, 1) === "/" ? "" : "/"}${path}`;';
    }
}

/**
 * Write gzipped content to a file.
 *
 * @param string $filename The file to write to.
 * @param string $data The data to write.
 * @param int $level The compression level (0-9).
 */
function file_put_gz_contents(string $filename, string $data, int $level = 6): void
{
    $f = gzopen($filename, 'w' . $level);
    gzwrite($f, $data);
    gzclose($f);
}

/**
 * Detect the content type of a file based on its extension or mime_content_type.
 *
 * @param string $file The file path or name.
 * @return string|null The detected content type or null if not detected.
 */
function detectContentType(string $file): ?string
{
    $mimeTypes = [
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'text/xml',
        'csv' => 'text/csv',
        'css' => 'text/css',
        'html' => 'text/html',
        'htm' => 'text/html',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
    ];

    // $ext = array_pop(explode('.', $file));
    $ext = mb_strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (isset($mimeTypes[$ext])) {
        return $mimeTypes[$ext];
    }

    try {
        return @mime_content_type($file) ?: null;
    } catch (Exception $e) {
        return null;
    }
}
