<?php

declare(strict_types=1);


// function isValidEmail(string $email, bool $verbose = false): bool|array
// {
//     $validator = new EmailValidator($email);
//     return $validator->isValid($verbose);
// }

class EmailValidator
{
    private const CRLF = "\r\n";
    private const KNOWN_SERVERS = [
        'gmail.com', 'aol.com', 'msn.com', 'wanadoo.fr', 'orange.fr', 'comcast.net',
        'rediffmail.com', 'free.fr', 'gmx.de', 'web.de', 'yandex.ru', 'ymail.com',
        'libero.it', 'outlook.com', 'uol.com.br', 'bol.com.br', 'mail.ru', 'cox.net',
        'sbcglobal.net', 'sfr.fr', 'verizon.net', 'googlemail.com', 'ig.com.br',
        'bigpond.com', 'terra.com.br', 'neuf.fr', 'alice.it', 'rocketmail.com',
        'att.net', 'laposte.net', 'facebook.com', 'bellsouth.net', 'charter.net',
        'rambler.ru', 'tiscali.it', 'shaw.ca', 'sky.com', 'earthlink.net',
        'optonline.net', 'freenet.de', 't-online.de', 'aliceadsl.fr', 'virgilio.it',
        'home.nl', 'qq.com', 'telenet.be', 'me.com', 'tiscali.co.uk', 'voila.fr',
        'gmx.net', 'mail.com', 'planet.nl', 'tin.it', 'ntlworld.com', 'arcor.de',
        'frontiernet.net', 'hetnet.nl', 'zonnet.nl', 'club-internet.fr', 'juno.com',
        'optusnet.com.au', 'blueyonder.co.uk', 'bluewin.ch', 'skynet.be',
        'sympatico.ca', 'windstream.net', 'mac.com', 'centurytel.net', 'chello.nl',
        'aim.com', 'bigpond.net.au'
    ];

    private string $email;
    private string $domain;
    private int $maxConnectionTimeout = 25;
    private int $streamTimeout = 5;
    private $stream = false;
    private array $mxhosts = [];

    public function __construct(string $email)
    {
        $this->email = filter_var(mb_strtolower($email), FILTER_SANITIZE_EMAIL);
        [$this->user, $this->domain] = explode('@', $this->email, 2);
    }

    public function isValid(bool $verbose = false, bool $has_mx_records = false, bool $server_alive = false, bool $bounced = false): bool|array
    {
        $record = [
            'valid_format' => $this->isValidFormat(),
            'disposable' => !$this->isDisposable(),
            'has_mx_records' => $has_mx_records,
            'server_alive' => $server_alive,
            'bounced' => $bounced,
        ];

        if (!$record['valid_format'] || !$record['disposable']) {
            return $verbose ? $record : false;
        }

        $knownIsp = $this->isKnownIsp();

        if (!$knownIsp) {
            $this->mxhosts = $this->getMXRecords();
            $record['has_mx_records'] = $this->isServerHasMXRecords();
            if (!$record['has_mx_records']) {
                return $verbose ? $record : false;
            }
        }

        $record['server_alive'] = $this->isServerMXRecordsAlive();
        if (!$record['server_alive']) {
            return $verbose ? $record : false;
        }

        $record['bounced'] = $this->isBouncing();

        return $verbose ? $record : !$record['bounced'];
    }

    public function isValidFormat(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function isKnownIsp(): bool
    {
        return str_starts_with($this->domain, 'yahoo.') ||
               str_starts_with($this->domain, 'hotmail.') ||
               str_starts_with($this->domain, 'live.') ||
               in_array($this->domain, self::KNOWN_SERVERS, true);
    }

    public function isServerHasMXRecords(): bool
    {
        return !empty($this->mxhosts) || checkdnsrr($this->domain, 'MX');
    }

    public function getMXRecords(int $max = 5): array
    {
        $mxhosts = [];
        $mxweights = [];
        if (getmxrr($this->domain, $mxhosts, $mxweights)) {
            array_multisort($mxweights, $mxhosts);
            return array_slice(array_filter($mxhosts), 0, $max);
        }
        return [];
    }

    public function isServerMXRecordsAlive(): bool
    {
        if ($this->isKnownIsp()) {
            return true;
        }

        foreach ($this->mxhosts as $host) {
            if ($this->isGoogleOrOutlookHost($host)) {
                return true;
            }
        }

        $timeout = ceil($this->maxConnectionTimeout / count($this->mxhosts));
        foreach ($this->mxhosts as $host) {
            $this->stream = @stream_socket_client("tcp://$host:25", $errno, $errstr, $timeout);
            if ($this->stream !== false) {
                stream_set_timeout($this->stream, $this->streamTimeout);
                stream_set_blocking($this->stream, true);
                $response = $this->streamResponse();
                if ($this->streamCode($response) == 220) {
                    return true;
                }
                fclose($this->stream);
            }
        }

        return false;
    }

    public function isBouncing(): bool
    {
        if ($this->isKnownIsp() || $this->isGoogleOrOutlookHost($this->mxhosts[0] ?? '')) {
            return false;
        }

        $this->stream = @stream_socket_client("tcp://{$this->mxhosts[0]}:25", $errno, $errstr, $this->streamTimeout);
        if ($this->stream === false) {
            return true;
        }

        $this->streamRequest("HELO root@localhost");
        $this->streamResponse();
        $this->streamRequest("MAIL FROM: <root@localhost>");
        $this->streamResponse();
        $this->streamRequest("RCPT TO: <{$this->email}>");
        $code = $this->streamCode($this->streamResponse());

        fclose($this->stream);
        return in_array($code, ['250', '450', '451', '452', '554'], true);
    }

    public function isDisposable(): bool
    {
        include_once 'emailvalidator_disposables.php';
        return defined('DISPOSABLE_DOMAINS') && in_array($this->domain, DISPOSABLE_DOMAINS, true);
    }

    public function streamRequest(string $request): void
    {
        fwrite($this->stream, $request . self::CRLF);
    }

    public function streamResponse(): string
    {
        $response = '';
        while (($line = fgets($this->stream, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    public function streamCode(string $str): string
    {
        preg_match('/^(?<code>[0-9]{3})(\s|-)(.*)$/ims', $str, $matches);
        return $matches['code'] ?? '';
    }

    public function isGoogleOrOutlookHost(string $host): bool
    {
        return str_ends_with($host, 'google.com') ||
               str_ends_with($host, 'googlemail.com') ||
               str_ends_with($host, 'outlook.com');
    }
}


tiny::registerHelper('emailValidator', function($email) {
    return new EmailValidator($email);
});
