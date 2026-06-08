<?php

declare(strict_types=1);

/**
 * Mixpanel Analytics Helper
 * 
 * Server-side Mixpanel integration for tracking events and managing user profiles.
 * Uses the Mixpanel Ingestion API (https://developer.mixpanel.com/reference/ingestion-api).
 * 
 * Environment variables required:
 *   - TINY_MIXPANEL_PROJECT_TOKEN: Your Mixpanel project token (not the API secret)
 *   - TINY_MIXPANEL_ENABLED: Set to 'true' or '1' to enable tracking
 * 
 * Usage:
 *   tiny::mixpanel()->track($userId, 'event_name', ['prop' => 'value']);
 *   tiny::mixpanel()->identify($userId, ['$name' => 'John']);
 *   tiny::mixpanel()->alias($userId, 'new_alias');
 * 
 * Privacy: IP addresses are passed to Mixpanel for geo derivation only.
 * Mixpanel extracts city/country and discards the IP (not stored).
 */
class Mixpanel
{
    /** @var string Mixpanel Ingestion API base URL */
    private const API_URL = 'https://api.mixpanel.com';
    
    /** @var string|null Client IP address for geo derivation */
    private $userIp;

    /**
     * Initialize Mixpanel helper and capture client IP.
     * 
     * IP is captured once at construction to ensure consistent geo data
     * across multiple tracking calls in the same request.
     */
    public function __construct()
    {
        $this->userIp = tiny::getClientRealIP();
    }

    /**
     * Get HTTP headers for Mixpanel API requests.
     * 
     * @return array Headers array for JSON API calls
     */
    private function headers(): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
    }

    /**
     * Set or update user profile properties in Mixpanel.
     * 
     * Uses the /engage endpoint to update user profiles. Properties prefixed
     * with $ are Mixpanel reserved properties (e.g., $name, $email).
     * 
     * @param string $distinct_id Unique user identifier (e.g., install_hash)
     * @param array $props Properties to set on the user profile
     * @return object|false API response object, or false if disabled
     * 
     * @example
     *   tiny::mixpanel()->identify('user_123', [
     *       '$name' => 'John Doe',
     *       '$email' => 'john@example.com',
     *       'plan' => 'pro'
     *   ]);
     */
    public function identify(string $distinct_id, array $props = []): object|false
    {
        if (!$this->isMixpanelEnabled()) {
            return false;
        }

        // Engage endpoint uses $ prefix for special properties
        $payload = [
            '$token' => $_SERVER['TINY_MIXPANEL_PROJECT_TOKEN'] ?? '',
            '$distinct_id' => $distinct_id,
            '$ip' => $this->userIp, // For geo derivation (not stored by Mixpanel)
            '$set' => $props,       // Properties to set/update on profile
        ];

        return $this->sendRequest('engage', $payload);
    }

    /**
     * Track an event in Mixpanel.
     * 
     * Events are the core of Mixpanel analytics. Each event has a name and
     * optional properties that describe the event context.
     * 
     * @param string $distinct_id Unique user identifier (e.g., install_hash)
     * @param string $event Event name (e.g., 'page_view', 'docs_feedback')
     * @param array $props Event properties (key-value pairs)
     * @return object|false API response object, or false if disabled
     * 
     * @example
     *   tiny::mixpanel()->track('user_123', 'docs_feedback', [
     *       'page_path' => '/docs/install',
     *       'helpful' => true,
     *       'reason' => 'guide_worked'
     *   ]);
     */
    public function track(string $distinct_id, string $event, array $props = []): object|false
    {
        if (!$this->isMixpanelEnabled()) {
            return false;
        }

        // Track endpoint expects event name and properties object
        $payload = [
            'event' => $event,
            'properties' => array_merge([
                'token' => $_SERVER['TINY_MIXPANEL_PROJECT_TOKEN'] ?? '',
                'distinct_id' => $distinct_id,
                'ip' => $this->userIp, // For geo derivation (not stored by Mixpanel)
            ], $props),
        ];

        return $this->sendRequest('track', $payload);
    }

    /**
     * Create an alias to link two distinct IDs together.
     * 
     * Useful when a user transitions from anonymous to identified state.
     * Links the old ID to a new canonical ID so all events are merged.
     * 
     * Note: Aliasing is permanent and cannot be undone. Use carefully.
     * 
     * @param string $distinct_id The current/canonical user ID
     * @param string $alias The ID to link (e.g., anonymous session ID)
     * @return object|false API response object, or false if disabled
     * 
     * @example
     *   // Link anonymous session to logged-in user
     *   tiny::mixpanel()->alias('user_123', 'anon_abc');
     */
    public function alias(string $distinct_id, string $alias): object|false
    {
        if (!$this->isMixpanelEnabled()) {
            return false;
        }

        // Alias uses the track endpoint with special $create_alias event
        $payload = [
            'event' => '$create_alias',
            'properties' => [
                'token' => $_SERVER['TINY_MIXPANEL_PROJECT_TOKEN'] ?? '',
                'distinct_id' => $distinct_id, // Canonical ID to keep
                'alias' => $alias,             // ID to merge into distinct_id
                'ip' => $this->userIp,
            ],
        ];

        return $this->sendRequest('track', $payload);
    }

    /**
     * Check if Mixpanel tracking is enabled.
     * 
     * Controlled by TINY_MIXPANEL_ENABLED environment variable.
     * Accepts 'true', true, '1', or 1 as enabled values.
     * 
     * @return bool True if tracking is enabled
     */
    private function isMixpanelEnabled(): bool
    {
        return in_array($_SERVER['TINY_MIXPANEL_ENABLED'] ?? '', ['true', true, '1', 1], true);
    }

    /**
     * Send a request to the Mixpanel Ingestion API.
     * 
     * Uses the modern JSON API format (not the legacy base64 format).
     * The ?ip=1 parameter tells Mixpanel to use the provided IP for geo.
     * 
     * @param string $endpoint API endpoint ('track' or 'engage')
     * @param array $payload Request payload (will be JSON encoded)
     * @return object API response from tiny::http()->post()
     */
    private function sendRequest(string $endpoint, array $payload): object
    {
        // ?ip=1 tells Mixpanel to use IP for geo derivation
        $url = self::API_URL . "/$endpoint?ip=1";

        // Mixpanel batch format: wrap single payload in array
        $body = json_encode([$payload]);

        return tiny::http()->post($url, [
            'body' => $body,
            'headers' => $this->headers()
        ]);
    }
}

// Register as tiny helper for easy access: tiny::mixpanel()->track(...)
tiny::registerHelper('mixpanel', function () {
    return new Mixpanel();
});
