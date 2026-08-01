<?php

declare(strict_types=1);

/**
 * Tiny helper for Emailable (https://emailable.com) email verification.
 *
 *   $res = tiny::emailable()->verify('alice@example.com');
 *   // ['safe' => bool, 'response' => array]  (response is the raw payload,
 *   //  or ['error' => ...] on a transport/API failure — which fails closed)
 */
class Emailable
{
    /** Minimum deliverability score (exclusive) required to be considered safe. */
    public const MIN_SCORE = 50;

    /**
     * Providers whose catch-all ("accept_all") domains we trust. A catch-all
     * domain can never return state 'deliverable' — Emailable can't probe an
     * individual mailbox — so valid addresses on them come back as 'risky'.
     * Many legitimate business users run catch-all on Google Workspace, so we
     * extend the benefit of the doubt to these providers only.
     */
    public const TRUSTED_ACCEPT_ALL_PROVIDERS = ['Google'];

    public function __construct(private string $apiKey)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Emailable apiKey is required');
        }
    }

    /**
     * An address is "safe" when it clears the base guards — not disposable,
     * mailbox not full, score > MIN_SCORE, and a present non-numeric local part
     * (rejects phone gateways like 3059627407@…) — AND its state is acceptable:
     * either 'deliverable', or 'risky' solely because it sits on a trusted
     * provider's catch-all domain (accept_all + smtp_provider allow-listed).
     *
     * Boolean-ish fields (disposable, mailbox_full, accept_all) arrive
     * inconsistently as true / 1 / false / null / "", so they're read with
     * loose truthy checks rather than a strict === comparison.
     *
     * @return array{safe: bool, response: array}
     */
    public function verify(string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('email is required');
        }

        $url = 'https://api.emailable.com/v1/verify?' . http_build_query([
            'email'   => $email,
            'api_key' => $this->apiKey,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Fail closed: a transport/API failure must never approve an address.
        if ($errno !== 0) {
            return ['safe' => false, 'response' => ['error' => curl_strerror($errno)]];
        }
        $r = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300 || !is_array($r)) {
            return ['safe' => false, 'response' => ['error' => "Emailable API {$status}", 'body' => $body]];
        }

        $user  = $r['user'] ?? null;
        $state = $r['state'] ?? null;

        // Guards that hold regardless of state.
        $baseOk = empty($r['disposable'])
            && empty($r['mailbox_full'])
            && is_numeric($r['score'] ?? null) && (float) $r['score'] > self::MIN_SCORE
            && is_string($user) && $user !== '' && !ctype_digit($user);

        // 'risky' is acceptable only when the risk is purely a trusted provider's
        // catch-all domain (e.g. a Google Workspace business running accept-all).
        $trustedCatchAll = $state === 'risky'
            && !empty($r['accept_all'])
            && in_array($r['smtp_provider'] ?? '', self::TRUSTED_ACCEPT_ALL_PROVIDERS, true);

        $safe = $baseOk && ($state === 'deliverable' || $trustedCatchAll);

        return ['safe' => $safe, 'response' => $r];
    }
}

tiny::registerHelper('emailable', function () {
    // Read from $_SERVER first (where PHP-FPM 'env[]' directives land),
    // then fall back to getenv() for CLI / shell-exported environments.
    $key = $_SERVER['TINY_EMAILABLE_API_KEY'] ?? getenv('TINY_EMAILABLE_API_KEY');
    return new Emailable((string) ($key ?: ''));
});
