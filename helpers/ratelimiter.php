<?php
declare(strict_types=1);
/**
 * RateLimiter class for managing rate limiting in the application.
 *
 * This class allows setting up multiple rate limits and checking if a given
 * identifier has exceeded those limits.
 *
 * Example usage:
 * --------------
 * $rateLimit = new RateLimiter("api", 10, 1); // 10 requests per second
 * $rateLimit->add(1000, 3600); // Add another limit: 1000 requests per hour
 *
 * $userId = "user123";
 * if ($rateLimit->check($userId)) {
 *      // Allow the request
 * } else {
 *     // Limit exceeded, return an error response
 * }
 */

class RateLimiter
{
    private string $name;
    private array $limits = [];
    private int $maxWindow;

    /**
     * Constructor for the RateLimiter class.
     *
     * @param string $name     A unique name for this rate limiter instance
     * @param int    $requests The number of requests allowed
     * @param int    $seconds  The time window in seconds for the requests
     */
    public function __construct(string $name, int $requests, int $seconds)
    {
        $this->name = $name;
        $this->maxWindow = $seconds;
        $this->limits[] = [$requests, $seconds];
    }

    /**
     * Add an additional rate limit to this instance.
     *
     * @param int $requests The number of requests allowed
     * @param int $seconds  The time window in seconds for the requests
     */
    public function add(int $requests, int $seconds): void
    {
        $this->limits[] = [$requests, $seconds];
        $this->maxWindow = max($this->maxWindow, $seconds);
    }

    /**
     * Check if the given identifier has exceeded the rate limit.
     *
     * @param string $id The identifier to check (e.g., user ID, IP address)
     * @return bool True if the request is allowed, false if the limit is exceeded
     */
    public function check(string $id): bool
    {
        $key = "{$this->name}:{$id}";
        $now = time();
        $cutoff = $now - $this->maxWindow;

        $attempts = tiny::cache()->get($key) ?? [];
        $attempts = array_filter($attempts, fn($timestamp) => $timestamp > $cutoff);

        foreach ($this->limits as [$requests, $seconds]) {
            $windowCutoff = $now - $seconds;
            $windowAttempts = count(array_filter($attempts, fn($timestamp) => $timestamp > $windowCutoff));
            if ($windowAttempts >= $requests) {
                tiny::cache()->set($key, $attempts, $this->maxWindow);
                return false;
            }
        }

        $attempts[] = $now;
        tiny::cache()->set($key, $attempts, $this->maxWindow);
        return true;
    }
}
