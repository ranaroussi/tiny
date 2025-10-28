<?php

declare(strict_types=1);

/**
 * GitHub API Client
 *
 * Handles interactions with GitHub REST API for repository, release, and README operations.
 */
class GitHub
{
    private $token;
    private $userAgent;
    private $baseUrl = 'https://api.github.com';

    /**
     * Initialize GitHub API client
     *
     * @param string|null $token Personal access token or OAuth token (optional)
     * @param string|null $userAgent User agent string (optional)
     */
    public function __construct(?string $token = null, ?string $userAgent = 'TinyPHP-GitHub')
    {
        $this->token = $token;
        $this->userAgent = $userAgent;
    }

    /**
     * Set the personal access token
     *
     * @param string $token Personal access token or OAuth token
     * @return void
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Clear the personal access token
     *
     * @return void
     */
    public function clearToken(): void
    {
        $this->token = null;
    }

    /**
     * Set the user agent string
     *
     * @param string $userAgent User agent string
     * @return void
     */
    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * Make HTTP request to GitHub API
     *
     * @param string $endpoint API endpoint (e.g., /repos/user/repo)
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array|null $data Request body data
     * @return array Response data as associative array
     * @throws Exception on HTTP errors
     */
    private function request($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: '. $this->userAgent,
        ];

        if ($this->token) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }

        $response = match ($method) {
            'POST' => tiny::http()->post($url, ['headers' => $headers, 'data' => $data]),
            'PUT' => tiny::http()->put($url, ['headers' => $headers, 'data' => $data]),
            'PATCH' => tiny::http()->patch($url, ['headers' => $headers, 'data' => $data]),
            'DELETE' => tiny::http()->delete($url, ['headers' => $headers, 'data' => $data]),
            'GET' => tiny::http()->get($url, ['headers' => $headers, 'data' => $data]),
            default => throw new Exception("Invalid method: $method"),
        };

        if ($response->status_code >= 400) {
            throw new Exception("GitHub API error: HTTP `$response->status_code`: $response->body");
        }

        return $response->json;
    }

    /**
     * Get repository information
     *
     * @param string $repo Repository in format "owner/repo"
     * @return array Repository data
     * @throws Exception if repository not found
     */
    public function getRepo($repo)
    {
        return $this->request("/repos/$repo");
    }

    /**
     * Get repository README content
     *
     * @param string $repo Repository in format "owner/repo"
     * @return string README content (decoded from base64)
     * @throws Exception if README not found
     */
    public function getReadme($repo)
    {
        $data = $this->request("/repos/$repo/readme");
        return base64_decode($data['content']);
    }

    /**
     * Get latest release
     *
     * @param string $repo Repository in format "owner/repo"
     * @return array Release data
     * @throws Exception if no releases found
     */
    public function getLatestRelease($repo)
    {
        return $this->request("/repos/$repo/releases/latest");
    }

    /**
     * Get specific release by tag
     *
     * @param string $repo Repository in format "owner/repo"
     * @param string $version Version tag (with or without 'v' prefix)
     * @return array Release data
     * @throws Exception if release not found
     */
    public function getRelease($repo, $version)
    {
        $tag = strpos($version, 'v') === 0 ? $version : "v{$version}";
        return $this->request("/repos/$repo/releases/tags/{$tag}");
    }

    /**
     * Check if user has push permission to repository
     *
     * @param string $repo Repository in format "owner/repo"
     * @param string $username GitHub username to check
     * @return bool True if user has push access
     */
    public function checkRepoPermission($repo, $username)
    {
        try {
            // Check if user is a collaborator with push access
            $collab = $this->request("/repos/$repo/collaborators/{$username}/permission");
            $permission = $collab['permission'] ?? '';

            return in_array($permission, ['admin', 'write', 'maintain']);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Check if repository exists
     *
     * @param string $repo Repository in format "owner/repo"
     * @return bool True if repository exists
     */
    public function repoExists($repo)
    {
        try {
            $this->getRepo($repo);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get all releases for a repository
     *
     * @param string $repo Repository in format "owner/repo"
     * @param int $limit Maximum number of releases to return
     * @return array Array of release data
     */
    public function getReleases($repo, $limit = 10)
    {
        return $this->request("/repos/$repo/releases?per_page={$limit}");
    }

    /**
     * Check if user is member of organization
     *
     * @param string $org Organization name
     * @param string $username GitHub username to check
     * @return bool True if user is member of organization
     */
    public function isOrgMember($org, $username)
    {
        try {
            // Check org membership
            $this->request("/orgs/{$org}/members/{$username}");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create a new GitHub repository
     *
     * @param string $owner Username or organization name
     * @param string $name Repository name
     * @param array $options Additional options (description, private, etc.)
     * @return array Repository data
     * @throws Exception if creation fails
     */
    public function createRepo($owner, $name, $options = [])
    {
        // Determine if this is a user or org repo
        // If owner matches authenticated user, create under user
        // Otherwise, assume it's an org
        try {
            $user = $this->request('/user');
            $endpoint = ($owner === $user['login'])
                ? '/user/repos'
                : "/orgs/$owner/repos";
        } catch (Exception $e) {
            // Default to user repos if we can't determine
            $endpoint = '/user/repos';
        }

        return $this->request($endpoint, 'POST', array_merge([
            'name' => $name,
            'private' => false,
            'auto_init' => false
        ], $options));
    }

    /**
     * Create or update a file in repository using GitHub Contents API
     *
     * @param string $repo Repository in format "owner/repo"
     * @param string $path File path in repository
     * @param string $content File content
     * @param string $message Commit message
     * @param string|null $sha Existing file SHA (for updates)
     * @return array Response data
     * @throws Exception if operation fails
     */
    public function createOrUpdateFile($repo, $path, $content, $message, $sha = null)
    {
        $data = [
            'message' => $message,
            'content' => base64_encode($content)
        ];

        // If SHA provided, this is an update
        if ($sha) {
            $data['sha'] = $sha;
        }

        return $this->request("/repos/$repo/contents/$path", 'PUT', $data);
    }

    /**
     * Get file SHA from repository (needed for updates)
     *
     * @param string $repo Repository in format "owner/repo"
     * @param string $path File path in repository
     * @return string|null File SHA or null if not found
     */
    public function getFileSha($repo, $path)
    {
        try {
            $file = $this->request("/repos/$repo/contents/$path");
            return $file['sha'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Create a GitHub release
     *
     * @param string $repo Repository in format "owner/repo"
     * @param array $data Release data (tag_name, name, body, etc.)
     * @return array Release data
     * @throws Exception if creation fails
     */
    public function createRelease($repo, $data)
    {
        return $this->request("/repos/$repo/releases", 'POST', $data);
    }

    /**
     * Upload asset to GitHub release
     *
     * @param string $repo Repository in format "owner/repo"
     * @param int $releaseId Release ID
     * @param string $filePath Local file path to upload
     * @param string $fileName Name for the uploaded asset
     * @return array Asset data
     * @throws Exception if upload fails
     */
    public function uploadReleaseAsset($repo, $releaseId, $filePath, $fileName)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        // GitHub uses a different endpoint for uploads
        $uploadUrl = "https://uploads.github.com/repos/$repo/releases/$releaseId/assets?name=" . urlencode($fileName);

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: '. $this->userAgent,
            'Content-Type: application/zip'
        ];

        if ($this->token) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }

        $fileData = file_get_contents($filePath);

        $response = tiny::http()->post($uploadUrl, [
            'headers' => $headers,
            'data' => $fileData
        ]);

        if ($response->status_code >= 400) {
            throw new Exception("GitHub asset upload error: HTTP `$response->status_code`: $response->body");
        }

        return $response->json;
    }
}


tiny::registerHelper('github', function () {
    return new GitHub();
});
