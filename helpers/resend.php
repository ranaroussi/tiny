<?php

declare(strict_types=1);

/**
 * Helper for interacting with Resend's email APIs.
 */
class ResendMail
{
    /**
     * Fetch the API key from environment variables.
     *
     * @return string
     */
    private function getApiKey(): string
    {
        return $_SERVER['APP_RESEND_API_KEY'] ?? '';
    }


    /**
     * Build a Resend SDK client using the configured API key.
     *
     * @return \Resend
     */
    public function client()
    {
        return Resend::client($this->getApiKey());
    }


    /**
     * Add a contact to an audience via the Resend REST API.
     *
     * @param string $audienceId
     * @param array $parameters
     * @return object
     */
    public function addContact(string $audienceId, array $parameters)
    {
        // Build endpoint and forward request through tiny's HTTP helper.
        $endpoint = "https://api.resend.com/audiences/$audienceId/contacts";
        return tiny::http()->postJSON($endpoint, $parameters, [
            'headers' => [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json',
            ]
        ]);
    }

    /**
     * Send a plain-text email via Resend.
     *
     * @param array|string $to_email
     * @param string $subject
     * @param string $text
     * @param string|null $to_name
     * @param string|null $from_email
     * @param string|null $from_name
     * @param string|null $reply_to
     * @param array|null $tags
     * @return object
     */
    public function sendTextEmail(
        array|string $to_email,
        string $subject,
        string $text,
        ?string $to_name = null,
        ?string $from_email = null,
        ?string $from_name = null,
        ?string $reply_to = null,
        ?array $tags = null
    ): object {
        // Instantiate the client using the create method.
        $resend = Resend::client($this->getApiKey());

        // Apply defaults so the caller can omit sender fields.
        $from_email = $from_email ?? $_SERVER['APP_RESEND_FROM_ADDRESS'];
        $from_name = $from_name ?? $_SERVER['APP_RESEND_FROM_NAME'];
        $reply_to = $reply_to ?? $_SERVER['APP_RESEND_REPLY_TO'] ?? null;

        // Build payload expected by Resend.
        $params = [
            'from' => $from_name . ' <' . $from_email . '>',
            'to' => $to_name ? $to_name . ' <' . $to_email . '>' : $to_email,
            'subject' => $subject,
            'text' => $text
        ];

        if ($reply_to !== null) {
            $params['replyTo'] = $reply_to;
        }

        if ($tags !== null) {
            $params['tags'] = $tags;
        }

        try {
            return $resend->emails->send($params);
        } catch (\Exception $e) {
            tiny::debug($e->getMessage());
            return (object) [];
        }
    }

    /**
     * Send an HTML email via Resend, optionally with text fallback.
     *
     * @param array|string $to_email
     * @param string $subject
     * @param string $html
     * @param string|null $to_name
     * @param string|null $text
     * @param string|null $from_email
     * @param string|null $from_name
     * @param string|null $reply_to
     * @param array|null $tags
     * @return object
     */
    public function sendEmail(
        array|string $to_email,
        string $subject,
        string $html,
        ?string $to_name = null,
        ?string $text = null,
        ?string $from_email = null,
        ?string $from_name = null,
        ?string $reply_to = null,
        ?array $tags = null
    ): object {
        $from_email = $from_email ?? $_SERVER['APP_RESEND_FROM_ADDRESS'];
        $from_name = $from_name ?? $_SERVER['APP_RESEND_FROM_NAME'];
        $domain = $domain ?? $_SERVER['APP_RESEND_DOMAIN'];

        $reply_to = $reply_to ?? $_SERVER['APP_RESEND_REPLY_TO'] ?? null;

        $params = [
            'from' => $from_name . ' <' . $from_email . '>',
            'to' => $to_name ? $to_name . ' <' . $to_email . '>' : $to_email,
            'subject' => $subject,
            'html' => $html
        ];

        if ($reply_to !== null) {
            $params['replyTo'] = $reply_to;
        }

        if ($text !== null) {
            $params['text'] = $text;
        }

        if ($tags !== null) {
            $params['tags'] = $tags;
        }

        // $res = tiny::http()->postJSON('https://api.resend.com/emails', $params, [
        //     'headers' => [
        //         'Authorization: Bearer '. $this->getApiKey(),
        //         'Content-Type: application/json',
        //     ]
        // ]);
        // tiny::dd($res);
        // return $res->status_code < 300;

        try {
            $resend = Resend::client($this->getApiKey());
            return $resend->emails->send($params);
        } catch (\Exception $e) {
            tiny::debug($e->getMessage());
            return (object) [];
        }
    }
}

tiny::registerHelper('resend', function (?string $resendKey = null) {
    return new ResendMail($resendKey);
});
