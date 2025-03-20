<?php

declare(strict_types=1);

use Twilio\Rest\Client;

class TwilioHelper
{
    private $client;

    public function __construct()
    {
        // Get credentials from environment or config
        $sid = $_SERVER['TWILIO_ACCOUNT_SID'] ?? '';
        $token = $_SERVER['TWILIO_AUTH_TOKEN'] ?? '';

        // Initialize client
        $this->client = new Client($sid, $token);
    }

    /**
     * Send an SMS message
     *
     * @param string $to Recipient phone number with country code
     * @param string $body Message content
     * @param string $from Optional sender phone number (uses default if not provided)
     * @return object Twilio message response
     */
    public function send($to, $body, $from = null)
    {
        error_reporting(~E_DEPRECATED);
        $from = $from ?? $_SERVER['TWILIO_PHONE_NUMBER'] ?? '';

        $to = '+' . trim(str_replace('+', '', $to));
        $from = '+' . trim(str_replace('+', '', $from));
        try {
            $this->client->messages->create(
                $to,
                [
                    'body' => $body,
                    'from' => $from
                ]
            );
        } catch (\Exception $e) {
            // tiny::debug($e);
        }
    }
}

tiny::registerHelper('twilio', function () {
    return new TwilioHelper();
});
