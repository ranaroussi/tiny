<?php

declare(strict_types=1);

class Cypher
{
    private const NONCE_TTL = 60; // nonce time to live in seconds
    private const DEFAULT_CRYPTO_ALGO = 'aes-256-cbc';

    private function urlsafe_b64encode(string $string): string
    {
        $data = base64_encode($string);
        return str_replace(['+', '/', '='], ['-', '_', ''], $data);
    }

    private function urlsafe_b64decode(string $string): false|string
    {
        $data = str_replace(['-', '_'], ['+', '/'], $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    public function encrypt(string $data, string $secret): string
    {
        $key = md5($secret);
        $iv = substr(strrev($key), 0, 16);

        $algo = @$_SERVER['CRYPTO_ALGO'] ?? self::DEFAULT_CRYPTO_ALGO;
        return $this->urlsafe_b64encode(@openssl_encrypt($data, $algo, $key, OPENSSL_RAW_DATA, $iv));
    }

    public function decrypt(string $data, string $secret): false|string
    {
        $data = $this->urlsafe_b64decode($data);
        $key = md5($secret);
        $iv = substr(strrev($key), 0, 16);

        $algo = @$_SERVER['CRYPTO_ALGO'] ?? self::DEFAULT_CRYPTO_ALGO;
        return @openssl_decrypt($data, $algo, $key, OPENSSL_RAW_DATA, $iv);
    }

    private function nonce(): string
    {
        $nonce = explode(' ', microtime());
        return $nonce[1] . substr($nonce[0], 2, 3);
    }

    public function encryptWithNonce(string $data, string $secret): string
    {
        $nonce = $this->nonce();
        $data = $data . ';' . $nonce;
        return $this->encrypt($data, $secret);
    }

    public function decryptWithNonce(string $data, string $secret, int $ttl = self::NONCE_TTL): false|string
    {
        try {
            [$data, $nonce] = explode(';', $this->decrypt($data, $secret));
            $diff = (int)$this->nonce() - (int)$nonce;
            if ($diff > $ttl * 1000) {
                return false;
            }
            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }
}

// --------------------------------

// example usage

// $secret = '1a1c73fe1b244366bfb76e463c26c8fe';
// $data = 'string to hash';

// $output = tiny::cypher()->encrypt($data, $secret);
// echo $output . "\n";

// $plaintext = tiny::cypher()->decrypt($output, $secret);
// echo $plaintext . "\n";

tiny::registerHelper('cypher', function() {
    return new Cypher();
});
