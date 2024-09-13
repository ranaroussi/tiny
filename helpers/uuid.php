<?php

declare(strict_types=1);

/**
 * UUID class
 *
 * Generates RFC 4122 compliant Universally Unique IDentifiers (UUID) version 3, 4 and 5.
 * This is a pure PHP implementation.
 */
class UUID
{
    /**
     * Generate v3 UUID
     *
     * Version 3 UUIDs are named based. They require a namespace (another
     * valid UUID) and a value (the name). Given the same namespace and
     * name, the output is always the same.
     *
     * @param string $namespace
     * @param string $name
     * @return string|false
     */
    public static function v3(string $namespace, string $name): string|false
    {
        if (!self::isValid($namespace)) {
            return false;
        }

        // Get hexadecimal components of namespace
        $nhex = str_replace(['-', '{', '}'], '', $namespace);

        // Binary Value
        $nstr = '';

        // Convert Namespace UUID to bits
        for ($i = 0; $i < strlen($nhex); $i += 2) {
            $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        // Calculate hash value
        $hash = md5($nstr . $name);

        return self::formatUuid(
            $hash,
            [0, 8, 12, 16, 20, 32],
            [8, 4, 4, 4, 12],
            3
        );
    }

    /**
     * Generate v4 UUID
     *
     * Version 4 UUIDs are pseudo-random.
     */
    public static function v4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate v5 UUID
     *
     * Version 5 UUIDs are named based. They require a namespace (another
     * valid UUID) and a value (the name). Given the same namespace and
     * name, the output is always the same.
     *
     * @param string $namespace
     * @param string $name
     * @return string|false
     */
    public static function v5(string $namespace, string $name): string|false
    {
        if (!self::isValid($namespace)) {
            return false;
        }

        // Get hexadecimal components of namespace
        $nhex = str_replace(['-', '{', '}'], '', $namespace);

        // Binary Value
        $nstr = '';

        // Convert Namespace UUID to bits
        for ($i = 0; $i < strlen($nhex); $i += 2) {
            $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        // Calculate hash value
        $hash = sha1($nstr . $name);

        return self::formatUuid(
            $hash,
            [0, 8, 12, 16, 20, 32],
            [8, 4, 4, 4, 12],
            5
        );
    }

    /**
     * Check if a string is a valid UUID
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?' .
            '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
    }

    /**
     * Format UUID string
     */
    private static function formatUuid(string $hash, array $positions, array $lengths, int $version): string
    {
        $uuid = '';
        foreach ($positions as $index => $position) {
            $uuid .= substr($hash, $position, $lengths[$index]) . '-';
        }
        $uuid = rtrim($uuid, '-');

        // Replace the version number
        $uuid = substr_replace(
            $uuid,
            sprintf('%04x', hexdec(substr($uuid, 14, 4)) & 0x0fff | $version << 12),
            14,
            4
        );

        // Set bits 6-7 of clock_seq_hi_and_reserved to 01
        $uuid = substr_replace(
            $uuid,
            sprintf('%04x', hexdec(substr($uuid, 19, 4)) & 0x3fff | 0x8000),
            19,
            4
        );

        return $uuid;
    }
}
