<?php

declare(strict_types=1);

class ResendMail
{
    private function getApiKey(): string
    {
        return $_SERVER['RESEND_API_KEY'] ?? '';
    }


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
        // Instantiate the client using the create method
        $resend = Resend::client($this->getApiKey());

        $from_email = $from_email ?? $_SERVER['RESEND_FROM_ADDRESS'];
        $from_name = $from_name ?? $_SERVER['RESEND_FROM_NAME'];
        $reply_to = $reply_to ?? $_SERVER['RESEND_REPLY_TO'] ?? null;

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
        $from_email = $from_email ?? $_SERVER['RESEND_FROM_ADDRESS'];
        $from_name = $from_name ?? $_SERVER['RESEND_FROM_NAME'];
        $domain = $domain ?? $_SERVER['RESEND_DOMAIN'];

        $reply_to = $reply_to ?? $_SERVER['RESEND_REPLY_TO'] ?? null;

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
