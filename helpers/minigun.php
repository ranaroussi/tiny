<?php

declare(strict_types=1);

/**
 * Unofficial PHP SDK for MiniGun (https://github.com/ranaroussi/minigun).
 *
 * Construct once with the API base URL and bearer token, then call the
 * verb-shaped methods. All methods throw MinigunTransportException on
 * network failure or MinigunApiException on a 4xx/5xx response — catch
 * MinigunException to handle either.
 *
 *   $mg = new Minigun(getenv('MINIGUN_API_URL'), getenv('MINIGUN_API_TOKEN'));
 *   $mg->addContact('newsletter', 'alice@example.com', ['first_name' => 'Alice']);
 *   $res = $mg->sendBulk(list: 'newsletter', subject: 'Hi', from: 'Ran <r@x.com>', md: '...');
 */

class MinigunException extends \RuntimeException {}

class MinigunTransportException extends MinigunException {}

class MinigunApiException extends MinigunException
{
    public function __construct(
        public readonly int $status,
        public readonly mixed $body,
        string $message
    ) {
        parent::__construct($message);
    }
}

class Minigun
{
    public const UNSUB_LOCAL    = 'local';
    public const UNSUB_REDIRECT = 'redirect';
    public const UNSUB_EXTERNAL = 'external';

    private string $baseUrl;
    private string $token;
    private int    $connectTimeout;
    private int    $timeout;
    private string $userAgent;

    public function __construct(
        string $baseUrl,
        string $token = '',
        int    $connectTimeout = 10,
        int    $timeout = 120,
        string $userAgent = 'minigun-php/0.1'
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }
        $this->baseUrl        = rtrim($baseUrl, '/');
        $this->token          = $token;
        $this->connectTimeout = $connectTimeout;
        $this->timeout        = $timeout;
        $this->userAgent      = $userAgent;
    }

    // ---------------------------------------------------------------
    // Contacts
    // ---------------------------------------------------------------

    /**
     * Upsert a contact and (re-)subscribe them to a list.
     *
     * Safe to call repeatedly with the same email: existing contacts
     * get their `params` merged and any prior unsubscribe is cleared.
     *
     * @return array{contact: array, subscription: array}
     */
    public function addContact(string $list, string $email, ?array $params = null): array
    {
        $path = '/lists/' . rawurlencode($list) . '/contacts';
        return $this->post($path, [
            'email'  => $email,
            // Force object encoding so empty {} doesn't become [] on the wire.
            'params' => $params === null ? null : (object) $params,
        ]);
    }

    /**
     * Admin-side unsubscribe by email (no token required).
     */
    public function unsubscribeContact(string $list, string $email): array
    {
        $path = '/lists/' . rawurlencode($list) . '/unsubscribe';
        return $this->post($path, ['email' => $email]);
    }

    /**
     * Paginated contacts for a list.
     */
    public function listContacts(string $list, ?string $cursor = null, int $limit = 50): array
    {
        $qs = http_build_query(array_filter([
            'cursor' => $cursor,
            'limit'  => $limit,
        ], static fn($v) => $v !== null && $v !== ''));
        $path = '/lists/' . rawurlencode($list) . '/contacts' . ($qs !== '' ? '?' . $qs : '');
        return $this->get($path);
    }

    // ---------------------------------------------------------------
    // Sends
    // ---------------------------------------------------------------

    /**
     * Send a single transactional email.
     *
     * Required: $to, $from, $subject, $company, and one of $md/$mdFile
     * or $html/$htmlFile. $company is the company id or slug — MiniGun
     * resolves the sending domain from it. Pass $domain to override for
     * this one send.
     *
     * Each body part has a "string or file path" pair. Pass at most one
     * of each pair; passing both throws. Files are read via
     * file_get_contents() at call time.
     *
     * Returns immediately (202). The worker performs the Mailgun POST
     * in the background; poll getSend() if you need the terminal status.
     *
     * @return array{send_id: string, status: string}
     */
    public function sendSingle(
        string  $to,
        string  $from,
        string  $subject,
        string  $company,
        ?string $md           = null,
        ?string $mdFile       = null,
        ?string $html         = null,
        ?string $htmlFile     = null,
        ?string $text         = null,
        ?string $textFile     = null,
        ?string $template     = null,
        ?string $templateFile = null,
        ?string $preheader    = null,
        ?string $replyTo      = null,
        ?string $domain       = null,
        ?string $list         = null,
        bool    $testMode     = false
    ): array {
        $md       = $this->resolveBody('md',       $md,       $mdFile);
        $html     = $this->resolveBody('html',     $html,     $htmlFile);
        $text     = $this->resolveBody('text',     $text,     $textFile);
        $template = $this->resolveBody('template', $template, $templateFile);

        if ($md === null && $html === null) {
            throw new \InvalidArgumentException('either $md/$mdFile or $html/$htmlFile is required');
        }

        return $this->post('/send/single', [
            'to'        => $to,
            'from'      => $from,
            'subject'   => $subject,
            'preheader' => $preheader ?? '',
            'company'   => $company,
            'list'      => $list      ?? '',
            'reply_to'  => $replyTo   ?? '',
            'domain'    => $domain    ?? '',
            'md'        => $md        ?? '',
            'html'      => $html      ?? '',
            'text'      => $text      ?? '',
            'template'  => $template  ?? '',
            'test_mode' => $testMode,
        ]);
    }

    /**
     * Trigger a bulk send to a list.
     *
     * Required: $list (slug or id), $subject, $from, and one of $md / $html.
     * Returns 202 with a send_id while the worker drives batches in the
     * background. The first batch runs inline before the 202, so the
     * response time scales with batch_size + Mailgun's latency.
     *
     * @return array{send_id: string, status: string, total_recipients: int}
     */
    public function sendBulk(
        string  $list,
        string  $subject,
        string  $from,
        ?string $md           = null,
        ?string $mdFile       = null,
        ?string $html         = null,
        ?string $htmlFile     = null,
        ?string $text         = null,
        ?string $textFile     = null,
        ?string $template     = null,
        ?string $templateFile = null,
        ?string $replyTo      = null,
        ?string $preheader    = null,
        ?string $domain       = null,
        int     $batchSize    = 500,
        int     $throttleMs   = 1000,
        ?string $notifyTo     = null,
        string  $unsubMode    = self::UNSUB_LOCAL,
        ?string $unsubRedir   = null,
        ?string $unsubUrl     = null,
        bool    $testMode     = false
    ): array {
        $md       = $this->resolveBody('md',       $md,       $mdFile);
        $html     = $this->resolveBody('html',     $html,     $htmlFile);
        $text     = $this->resolveBody('text',     $text,     $textFile);
        $template = $this->resolveBody('template', $template, $templateFile);

        if ($md === null && $html === null) {
            throw new \InvalidArgumentException('either $md/$mdFile or $html/$htmlFile is required');
        }
        if (!in_array($unsubMode, [self::UNSUB_LOCAL, self::UNSUB_REDIRECT, self::UNSUB_EXTERNAL], true)) {
            throw new \InvalidArgumentException("unsubMode must be 'local', 'redirect', or 'external'");
        }
        if ($unsubMode === self::UNSUB_REDIRECT && ($unsubRedir === null || $unsubRedir === '')) {
            throw new \InvalidArgumentException("unsubRedir is required when unsubMode='redirect'");
        }
        if ($unsubMode === self::UNSUB_EXTERNAL && ($unsubUrl === null || $unsubUrl === '')) {
            throw new \InvalidArgumentException("unsubUrl is required when unsubMode='external'");
        }

        return $this->post('/send/bulk', [
            'list'         => $list,
            'subject'      => $subject,
            'from'         => $from,
            'reply_to'     => $replyTo    ?? '',
            'preheader'    => $preheader  ?? '',
            'domain'       => $domain     ?? '',
            'md'           => $md         ?? '',
            'html'         => $html       ?? '',
            'text'         => $text       ?? '',
            'template'     => $template   ?? '',
            'batch_size'   => $batchSize,
            'throttle_ms'  => $throttleMs,
            'notify_email' => $notifyTo   ?? '',
            'unsub_mode'   => $unsubMode,
            'unsub_redir'  => $unsubRedir ?? '',
            'unsub_url'    => $unsubUrl   ?? '',
            'test_mode'    => $testMode,
        ]);
    }

    /**
     * Resolve a body-or-file pair. Throws if both are supplied or if the
     * file path is unreadable. Returns null when neither is supplied so
     * the caller can decide whether the field is required.
     */
    private function resolveBody(string $name, ?string $direct, ?string $file): ?string
    {
        if ($direct !== null && $file !== null) {
            throw new \InvalidArgumentException(
                "pass only one of \${$name} or \${$name}File, not both"
            );
        }
        if ($file === null) {
            return $direct;
        }
        if (!is_file($file) || !is_readable($file)) {
            throw new \InvalidArgumentException(
                "{$name}File '{$file}' does not exist or is not readable"
            );
        }
        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException("failed to read {$name}File '{$file}'");
        }
        return $contents;
    }

    /**
     * One-shot send status + progress snapshot.
     */
    public function getSend(string $sendId): array
    {
        return $this->get('/send/' . rawurlencode($sendId));
    }

    /**
     * Aggregate stats (DB-backed; falls back to live Mailgun for fresh sends).
     */
    public function getSendStats(string $sendId): array
    {
        return $this->get('/send/' . rawurlencode($sendId) . '/stats');
    }

    /**
     * Resume a paused / failed send. Pass $force=true only if any batch
     * was left in_flight (Mailgun may already have accepted it, so a
     * retry can duplicate-send).
     */
    public function resumeSend(string $sendId, bool $force = false): array
    {
        $path = '/send/' . rawurlencode($sendId) . '/resume' . ($force ? '?force=1' : '');
        return $this->post($path, []);
    }

    // ---------------------------------------------------------------
    // Transport
    // ---------------------------------------------------------------

    private function get(string $path): array
    {
        return $this->request('GET', $path, null);
    }

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    private function request(string $method, string $path, ?array $body): array
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'Accept: application/json',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);

        try {
            $resBody = curl_exec($ch);
            $errno   = curl_errno($ch);
            $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } finally {
            // curl_close($ch);
        }

        if ($errno !== 0) {
            throw new MinigunTransportException('curl error: ' . curl_strerror($errno));
        }

        $decoded = json_decode((string) $resBody, true);

        if ($status < 200 || $status >= 300) {
            $msg = is_array($decoded) && isset($decoded['error'])
                ? $decoded['error']
                : (string) $resBody;
            throw new MinigunApiException($status, $decoded ?? $resBody, "MiniGun API {$status}: {$msg}");
        }

        return is_array($decoded) ? $decoded : [];
    }
}

tiny::registerHelper('minigun', function () {
    // Read from $_SERVER first (where PHP-FPM 'env[]' directives land),
    // then fall back to getenv() for CLI / shell-exported environments.
    // Accepts either TINY_MINIGUN_* (preferred, matches tiny convention)
    // or MINIGUN_API_* (matches the upstream CLI's env contract).
    $resolve = static function (string ...$keys): string {
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                return (string) $_SERVER[$k];
            }
            $v = getenv($k);
            if ($v !== false && $v !== '') {
                return $v;
            }
        }
        return '';
    };
    return new Minigun(
        baseUrl: $resolve('TINY_MINIGUN_URL',   'MINIGUN_API_URL'),
        token:   $resolve('TINY_MINIGUN_TOKEN', 'MINIGUN_API_TOKEN')
    );
});
