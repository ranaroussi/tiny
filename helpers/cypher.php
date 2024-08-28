<?php

const NONCE_TTL = 60; // nonce time to live in seconds

function urlsafe_b64encode($string): array|string
{
    $data = base64_encode($string);
    return str_replace(array('+', '/', '='), array('-', '_', ''), $data);
}

function urlsafe_b64decode($string): false|string
{
    $data = str_replace(array('-', '_'), array('+', '/'), $string);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}

function cypher_encrypt($data, $secret): array|string
{
    $key = md5($secret);
    $iv = substr(strrev($key), 0, 16);
    return urlsafe_b64encode(@openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));
}

function cypher_decrypt($data, $secret): false|string
{
    $data = urlsafe_b64decode($data);
    $key = md5($secret);
    $iv = substr(strrev($key), 0, 16);
    return @openssl_decrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

function nonce(): string
{
    $nonce = explode(' ', microtime());
    return $nonce[1] . substr($nonce[0], 2, 3);
}

function cypher_encrypt_with_nonce($data, $secret): array|string
{
    $nonce = nonce();
    $data = $data . ';' . $nonce;
    return cypher_encrypt($data, $secret);
}

function cypher_decrypt_with_nonce($data, $secret, $ttl = NONCE_TTL): false|string
{
    try {
        [$data, $nonce] = explode(';', cypher_decrypt($data, $secret));
        $diff = (int)nonce() - (int)$nonce;
        if ($diff > $ttl * 1000) {
            return false;
        }
        return $data;
    } catch (Exception $e) {
        return false;
    }
}

// --------------------------------

// example usage

// $secret = '1a1c73fe1b244366bfb76e463c26c8fe';
// $data = 'string to hash';

// $output = cypher_encrypt($data, $secret);
// echo $output . "\n";

// $plaintext = cypher_decrypt($output, $secret);
// echo $plaintext . "\n";
