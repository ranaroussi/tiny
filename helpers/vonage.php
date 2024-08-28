<?php

declare(strict_types=1);

use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

function send_text(string $to, string $msg): bool
{
    $client = new Client(
        new Basic(
            $_SERVER['NEXMO_API_KEY'] ?? '',
            $_SERVER['NEXMO_API_SECRET'] ?? ''
        )
    );

    $message = new SMS(
        $to,
        $_SERVER['NEXMO_FROM'] ?? '',
        $msg
    );

    try {
        $response = $client->sms()->send($message);
        $currentMessage = $response->current();
        return $currentMessage->getStatus() === 0;
    } catch (Exception $e) {
        error_log('Vonage SMS Error: ' . $e->getMessage());
        return false;
    }
}
