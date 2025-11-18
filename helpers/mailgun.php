<?php

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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

    /**
     * Sends an HTML email using Mailgun API
     *
     * @param string $to_email Recipient email address
     * @param string $to_name Recipient name
     * @param string $subject Email subject
     * @param string $html HTML content of the email
     * @param string|null $from_email Sender email address (optional)
     * @param string|null $from_name Sender name (optional)
     * @param string|null $domain Mailgun domain to use (optional)
     * @return object Response from Mailgun API
     */
    public function sendEmail(
        string $to_email,
        string $to_name,
        string $subject,
        string $html,
        ?string $text = null,
        ?string $from_email = null,
        ?string $from_name = null,
        ?string $domain = null,
    ): object {
        // Instantiate the client using the create method
        $mailgun = Mailgun::create($this->getApiKey());

        $from_email = $from_email ?? $_SERVER['MAILGUN_FROM_ADDRESS'];
        $from_name = $from_name ?? $_SERVER['MAILGUN_FROM_NAME'];
        $domain = $domain ?? $_SERVER['MAILGUN_DOMAIN'];

        $params = [
            'from' => $from_name .' <'. $from_email .'>',
            'to' => $to_name .' <'. $to_email .'>',
            'subject' => $subject,
            'html' => $html
        ];

        if ($text !== null) {
            $params['text'] = $text;
        }

        try {
            return $mailgun->messages()->send($domain, $params);
        } catch (\Exception $e) {
            tiny::debug($e->getMessage());
            return (object) [];
        }
    }
}

tiny::registerHelper('mailgun', function () {
    return new MailgunSender();
});
