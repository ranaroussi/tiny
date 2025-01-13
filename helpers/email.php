<?php

declare(strict_types=1);

class EmailUtils
{
    private const EMAIL_PROVIDERS = [
        'Gmail' => ['gmail.com', 'googlemail.com'],
        'Yahoo! mail' => [
            'ymail.com', 'yahoo.ca', 'yahoo.co.id', 'yahoo.co.in', 'yahoo.co.jp',
            'yahoo.co.kr', 'yahoo.co.uk', 'yahoo.com', 'yahoo.com.ar', 'yahoo.com.br',
            'yahoo.com.mx', 'yahoo.com.ph', 'yahoo.com.sg', 'yahoo.de', 'yahoo.fr', 'yahoo.it'
        ],
        'Hotmail' => [
            'hotmail.be', 'hotmail.ca', 'hotmail.co.uk', 'hotmail.com', 'hotmail.com.ar',
            'hotmail.com.br', 'hotmail.com.mx', 'hotmail.de', 'hotmail.es', 'hotmail.fr', 'hotmail.it'
        ],
        'Live webmail' => [
            'live.be', 'live.co.uk', 'live.com', 'live.com.ar', 'live.com.mx',
            'live.de', 'live.fr', 'live.it'
        ],
        'Outlook' => ['outlook.com', 'outlook.com.br'],
        'Yandex' => ['yandex.com', 'yandex.ru'],
        'Proton Mail' => ['protonmail.ch', 'protonmail.com', 'pm.me'],
        'Mail.ru' => ['mail.ru'],
    ];

    public static function clean(string $email): string
    {
        return filter_var(mb_strtolower($email), FILTER_SANITIZE_EMAIL);
    }

    public static function validate(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isWebmail(string $email): object
    {
        $result = [
            'webmail' => false,
            'vendor' => '',
            'domain' => '',
        ];

        foreach (self::EMAIL_PROVIDERS as $provider => $domains) {
            foreach ($domains as $domain) {
                if (str_contains($email, $domain)) {
                    tiny::data()->user->webmail = $domain;
                    $result['webmail'] = true;
                    $result['vendor'] = match ($provider) {
                        'Yahoo! mail' => 'Yahoo!',
                        'Live webmail' => 'Live',
                        default => $provider,
                    };
                    $result['domain'] = match ($provider) {
                        'Yahoo! mail' => 'ymail.com',
                        'Live webmail' => 'login.live.com',
                        'Gmail' => 'gmail.com',
                        default => $domain,
                    };
                    break 2;
                }
            }
        }

        return (object)$result;
    }
}

tiny::registerHelper('emailUtils', function() {
    return new EmailUtils();
});
