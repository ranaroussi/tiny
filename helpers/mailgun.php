<?php

declare(strict_types=1);

use Mailgun\Mailgun;

class MailgunSender
{
    private function getApiKey(): string
    {
        return $_SERVER['MAILGUN_API_KEY'] ?? '';
    }


    public function sendTextEmail(
        string $to_email,
        string $to_name,
        string $subject,
        string $text,
        ?string $from_email,
        ?string $from_name,
        ?string $domain,
    ): object {
        // Instantiate the client using the create method
        $mailgun = Mailgun::create($this->getApiKey());

        $from_email = $from_email ?? $_SERVER['MAILGUN_FROM_ADDRESS'];
        $from_name = $from_name ?? $_SERVER['MAILGUN_FROM_NAME'];
        $domain = $domain ?? $_SERVER['MAILGUN_DOMAIN'];

        return $mailgun->messages()->send($domain, [
            'from' => $from_name .' <'. $from_email .'>',
            'to' => $to_name .' <'. $to_email .'>',
            'subject' => $subject,
            'text' => $text
        ]);
    }
}

tiny::registerHelper('mailgun', function () {
    return new MailgunSender();
});
