<?php

/**
 * Tiny: PHP Framework
 * https://github.com/ranaroussi/tiny
 *
 * Copyright 2013-2024 Ran Aroussi (@aroussi)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

declare(strict_types=1);


trait TinyUtils
{
    /**
     * Extracts the last part of a string after splitting it by a delimiter.
     *
     * This function splits a string by a specified delimiter and returns the last part.
     * Useful for extracting file extensions, last path segments, or the final part of any
     * delimited string.
     *
     * @param string $by The delimiter to split the string by
     * @param string $string The input string to be split
     * @return string The last part of the split string
     */
    public static function explodeAndPop(string $by, string $string): string
    {
        // Split the string into parts using the specified delimiter
        $parts = explode($by, $string);

        // Return the last element of the resulting array
        return array_pop($parts);
    }

    /**
     * Gets the full file path for a given relative path.
     *
     * @param string $path The relative path
     * @return string The full file path
     */
    public static function getFilePath(string $path): string
    {
        return rtrim(self::config()->app_path, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Suppresses undefined variable and array key errors.
     *
     * @param bool $suppress Whether to suppress the errors or not
     */
    public static function suppressUndefinedError($suppress = true): void
    {
        set_error_handler(function (int $errno, string $errstr) use ($suppress) {
            if ((!str_contains($errstr, 'Undefined array key')) && (!str_contains($errstr, 'Undefined variable'))) {
                return !$suppress;
            } else {
                return (bool)$suppress;
            }
        }, E_WARNING);
    }

    /**
     * Reads the contents of a file.
     *
     * @param string $path The relative path to the file
     * @return string The contents of the file
     */
    public static function readFile(string $path): string
    {
        $file = self::getFilePath($path);
        return trim(file_get_contents($file));
    }

    /**
     * Redirects to a specified URL with optional header.
     *
     * @param string $goto The URL to redirect to
     * @param mixed $header Optional header type (302, 301, 'javascript', or 'htmx')
     */
    public static function redirect(?string $goto = null, mixed $header = null): void
    {
        $goto = $goto ?? tiny::router()->permalink;
        if (!str_starts_with($goto, 'http://') && !str_starts_with($goto, 'https://')) {
            $goto = self::getHomeURL(str_replace(self::getHomeURL(), '/', $goto));
        }
        match ($header) {
            302 => self::header('HTTP/1.1 302 Moved Temporarily'),
            301 => self::header('HTTP/1.1 301 Moved Permanently'),
            'javascript' => self::javascriptRedirect($goto),
            'htmx' => self::htmxRedirect($goto),
            'csrf' => function () use ($goto) {
                tiny::csrf()->showError(nextPage: true);
                self::htmxRedirect($goto);
            },
            default => self::$router->htmx ? self::htmxRedirect($goto) : null
        };

        if (self::isUsingSwoole()) {
            self::swoole()->redirect($goto, $header);
        } else {
            self::header("Location: $goto");
            tiny::exit();
        }
    }

    /**
     * Performs a JavaScript-based redirect.
     *
     * @param string $goto The URL to redirect to
     */
    private static function javascriptRedirect(string $goto): void
    {
        echo "<html lang=\"en\"><head><title>...</title><meta name=\"robots\" content=\"noindex\">
        <script>window.onload=function(){try{window.location.replace(\"$goto\");}catch(err){window.location.href=\"$goto\";}}</script>
        <style>@keyframes spinner{to{transform:rotate(360deg);}} .spinner{position:absolute;top:50%;left:50%;width:20px;height:20px;margin-top:-10px;margin-left:-10px;border-radius:50%;border:2px solid #ccc;border-top-color:#000;animation:spinner .6s linear infinite;}</style>
        </head><body style=\"height:100%;background:#fff;\"><div class=\"spinner\"></div></body></html>";
        tiny::exit();
    }

    /**
     * Performs an HTMX-based redirect.
     *
     * @param string $goto The URL to redirect to
     */
    private static function htmxRedirect(string $goto): void
    {
        self::header("HX-Redirect: $goto");
        tiny::exit();
    }

    /**
     * Converts an array to an object.
     *
     * @param array $array The array to convert
     * @return object The resulting object
     */
    public static function arrayToObject(array $array): object
    {
        return json_decode(json_encode($array));
    }

    /**
     * Converts an object to an array.
     *
     * @param object $object The object to convert
     * @return array The resulting array
     */
    public static function objectToArray(object $object): array
    {
        return json_decode(json_encode($object), true);
    }

    /**
     * Converts a database record to an array.
     *
     * @param mixed $data The database record
     * @return array The resulting array
     */
    public static function recordToArray($data): array
    {
        $json = str_replace(['"f"', '"t"'], ['false', 'true'], json_encode($data));
        return json_decode($json, true);
    }

    /**
     * Converts a database record to an object.
     *
     * @param mixed $data The database record
     * @return object The resulting object
     */
    public static function recordToObject($data): object
    {
        $json = str_replace(['"f"', '"t"'], ['false', 'true'], json_encode($data));
        return json_decode($json);
    }

    /**
     * Sets a flash message.
     *
     * @param mixed $value The value to set
     * @param string $name The name of the flash message
     */
    public static function flashSet($value, string $name = 'flash_msg'): void
    {
        $value = serialize($value);
        $cookiePath = tiny::config()->cookie_path;
        if (headers_sent()) {
            echo "<script>document.cookie=\"$name=$value;path=$cookiePath;expires=Fri, 31 Dec 9999 23:59:59 GMT\";</script>";
        } else {
            setcookie($name, $value, time() + 3600, $cookiePath);
            $flashName = "flash_$name";
            tiny::data()->$flashName = $value;
        }
    }

    /**
     * Retrieves a flash message.
     *
     * @param string $name The name of the flash message
     * @param bool $keep Whether to keep the message after retrieval
     * @return null|string|array The flash message or null if not found
     */
    public static function flashGet(string $name = 'flash_msg', bool $keep = false): null|string|array
    {
        $flashData = $_COOKIE[$name] ?? null;
        $cookiePath = tiny::config()->cookie_path;

        if (!$keep) {
            if (headers_sent()) {
                echo "<script>document.cookie=\"$name=;path=$cookiePath;expires=Thu, 01 Jan 1970 00:00:01 GMT\";</script>";
            } else {
                unset($_COOKIE[$name]);
                setcookie($name, '', time() - 3600, $cookiePath);
            }
        }

        if (!$flashData) {
            $flashName = "flash_$name";
            $flashData = tiny::data()->$flashName ?? null;
        }

        if ($flashData) {
            return self::isSerialized($flashData) ? unserialize($flashData) : $flashData;
        }

        return null;
    }

    /**
     * Checks if a value is serialized.
     *
     * @param mixed $data The data to check
     * @param bool $strict Whether to use strict checking
     * @return bool True if the data is serialized, false otherwise
     */
    public static function isSerialized($data, bool $strict = true): bool
    {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = $data[strlen($data) - 1];
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');
            // Either ; or } must exist.
            if (false === $semicolon && false === $brace) {
                return false;
            }
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (!str_contains($data, '"')) {
                    return false;
                }
                // Fallthrough
            case 'a':
            case 'O':
                return (bool)preg_match("/^$token:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool)preg_match("/^$token:[0-9.E+-]+;$end/", $data);
        }
        return false;
    }

    /**
     * Checks if the current execution is via CLI.
     *
     * @return bool True if running via CLI, false otherwise
     */
    public static function isCLI(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Converts a string to a URL-friendly format.
     *
     * @param string $str The string to convert
     * @return string The URL-friendly string
     */
    public static function dirify(string $str): string
    {
        static $highASCII = null;
        if ($highASCII === null) {
            $highASCII = [
                "!\xc0!" => 'A',
                "!\xe0!" => 'a',
                "!\xc1!" => 'A',
                "!\xe1!" => 'a',
                "!\xc2!" => 'A',
                "!\xe2!" => 'a',
                "!\xc4!" => 'Ae',
                "!\xe4!" => 'ae',
                "!\xc3!" => 'A',
                "!\xe3!" => 'a',
                "!\xc8!" => 'E',
                "!\xe8!" => 'e',
                "!\xc9!" => 'E',
                "!\xe9!" => 'e',
                "!\xca!" => 'E',
                "!\xea!" => 'e',
                "!\xcb!" => 'Ee',
                "!\xeb!" => 'ee',
                "!\xcc!" => 'I',
                "!\xec!" => 'i',
                "!\xcd!" => 'I',
                "!\xed!" => 'i',
                "!\xce!" => 'I',
                "!\xee!" => 'i',
                "!\xcf!" => 'Ie',
                "!\xef!" => 'ie',
                "!\xd2!" => 'O',
                "!\xf2!" => 'o',
                "!\xd3!" => 'O',
                "!\xf3!" => 'o',
                "!\xd4!" => 'O',
                "!\xf4!" => 'o',
                "!\xd6!" => 'Oe',
                "!\xf6!" => 'oe',
                "!\xd5!" => 'O',
                "!\xf5!" => 'o',
                "!\xd8!" => 'Oe',
                "!\xf8!" => 'oe',
                "!\xd9!" => 'U',
                "!\xf9!" => 'u',
                "!\xda!" => 'U',
                "!\xfa!" => 'u',
                "!\xdb!" => 'U',
                "!\xfb!" => 'u',
                "!\xdc!" => 'Ue',
                "!\xfc!" => 'ue',
                "!\xc7!" => 'C',
                "!\xe7!" => 'c',
                "!\xd1!" => 'N',
                "!\xf1!" => 'n',
                "!\xdf!" => 'ss',
            ];
        }

        $str = preg_replace('/\s+/', ' ', self::trim($str));
        $str = str_replace('&', 'and', $str);

        $s = strtr($str, $highASCII);
        $s = mb_strtolower($s);
        $s = strip_tags($s);
        $s = preg_replace('/&[^;\s]+;/', '', $s);
        $s = preg_replace('/[^\w\s-_.]/', '', $s);
        $s = preg_replace('/\s+/', '-', $s);

        $s = str_replace('---', '-', $s);
        return (self::trim($s, '-')) ?: str_replace('+', '-', urlencode($str));
    }

    /**
     * Checks if the current request is from a mobile device.
     *
     * @return bool True if the request is from a mobile device, false otherwise
     */
    public static function isMobile(): bool
    {
        if (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])) {
            return $_SERVER['HTTP_SEC_CH_UA_MOBILE'] === '?1';
        }
        $browser = mb_strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        return (
            str_contains($browser, 'iphone') || str_contains($browser, 'ipod') ||
            (str_contains($browser, 'android') && str_contains($browser, 'mobile'))
        );
    }

    /**
     * Generates a nano ID.
     *
     * @param int $len The length of the ID
     * @param mixed $entropy The entropy type ('balanced' or false)
     * @param string $alphabet The alphabet to use for the ID
     * @return string The generated nano ID
     */
    public static function nanoId(int $len = 21, mixed $entropy = 'balanced', string $alphabet = '_-0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $id = '';
        $alphabet_len = strlen($alphabet);

        if ($entropy === false || $entropy === 'balanced') {
            $token = $entropy === 'balanced' ? $len / 2 : $len;
            while (strlen($id) < $token) {
                $id .= $alphabet[random_int(0, $alphabet_len - 1)];
                $id = trim(trim($id, '_'), '-');
            }
            if ($entropy !== 'balanced') {
                return $id;
            }
        }

        $mask = (2 << (int)(log($alphabet_len - 1) / M_LN2)) - 1;
        $step = (int)ceil(1.6 * $mask * $len / $alphabet_len);
        $bytes = random_bytes($step);
        for ($i = 0; $i < $step; $i++) {
            $byte = ord($bytes[$i]) & $mask;
            if (isset($alphabet[$byte])) {
                $id .= $alphabet[$byte];
                $id = trim(trim($id, '_'), '-');
                if (strlen($id) === $len) {
                    return $id;
                }
            }
        }
        return $id;
    }

    /**
     * Generates a UUID.
     *
     * @return string The generated UUID
     */
    public static function uuid(): string
    {
        $rand_str = mt_rand() . serialize($_SERVER);
        $hash = md5(uniqid($rand_str, true));

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x4000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    /**
     * Generates a UUID from an MD5 hash.
     *
     * @param string $string The string to hash
     * @return string The generated UUID
     */
    public static function md5asUUID(string $string): string
    {
        $string = md5($string);
        return substr($string, 0, 8) . '-' .
            substr($string, 8, 4) . '-' .
            substr($string, 12, 4) . '-' .
            substr($string, 16, 4) . '-' .
            substr($string, 20);
    }

    /**
     * Pads a number with leading zeros.
     *
     * @param int|string $no The number to pad
     * @return string The padded number
     */
    public static function pad(int|string $no): string
    {
        return (int)$no < 10 ? "0$no" : (string)$no;
    }

    /**
     * Converts a number to a different base.
     *
     * @param string $token The number to convert
     * @param int $base The base to convert to
     * @return string The converted number
     */
    public static function baseSet(string $token, int $base = 36): string
    {
        return base_convert($token, 10, $base);
    }

    /**
     * Converts a number from a different base to decimal.
     *
     * @param string $token The number to convert
     * @param int $base The base to convert from
     * @return string The converted number
     */
    public static function baseGet(string $token, int $base = 36): string
    {
        return base_convert($token, $base, 10);
    }

    /**
     * Generates pagination HTML.
     *
     * @param int $total_records The total number of records
     * @param int $per_page The number of records per page
     * @return string The pagination HTML
     */
    public static function paginate(int $total_records = 0, int $per_page = 10): string
    {
        $currPage = $_GET['page'] ?? 1;
        $qs = preg_replace('/(&?)p=\d&?/m', "$1", $_SERVER['QUERY_STRING'] ?? '') . '&page=';
        $totalPages = ceil($total_records / $per_page);

        if ($totalPages <= 1) {
            return '';
        }

        $start_page = max(1, min($currPage - 5, $totalPages - 9));
        $end_page = min($totalPages, $start_page + 9);

        $pagination = "<a href=\"?$qs" . max($currPage - 1, 1) . "\"" . ($currPage == 1 ? ' aria-disabled="true"' : '') . ">‹</a>";

        for ($i = $start_page; $i <= $end_page; $i++) {
            $pagination .= "<a href=\"?$qs$i\"" . ($i == $currPage ? ' aria-current="true"' : '') . ">$i</a>";
        }

        $pagination .= "<a href=\"?$qs" . min($currPage + 1, $totalPages) . "\"" . ($currPage == $totalPages ? ' aria-disabled="true"' : '') . ">›</a>";

        return $pagination;
    }

    /**
     * Converts a string to title case.
     *
     * @param string $str The string to convert
     * @return string The title-cased string
     */
    public static function titleize(string $str): string
    {
        $str = stripslashes($str);
        $str = str_replace(['_', '-'], ' ', $str);
        return ucwords(mb_strtolower($str));
    }

    /**
     * Converts a value to a float with a specified number of decimal places.
     *
     * @param mixed $val The value to convert
     * @param int $dec The number of decimal places
     * @return string The formatted float as a string
     */
    public static function makeFloat(mixed $val, int $dec = 2): string
    {
        $val = (float)preg_replace('/[^0-9.]+/', '', $val);
        return str_replace(',', '', number_format($val, $dec));
    }

    /**
     * Sorts a multi-dimensional array by a specific index.
     *
     * @param array $array The array to sort
     * @param string $index The index to sort by
     * @param string $order The sort order ('asc' or 'desc')
     * @param bool $natsort Whether to use natural sorting
     * @param bool $case_sensitive Whether the sorting should be case-sensitive
     * @return array The sorted array
     */
    public static function arrayMultiSort(array $array, string $index, string $order = 'desc', bool $natsort = false, bool $case_sensitive = false): array
    {
        if (empty($array)) {
            return [];
        }

        $temp = array_column($array, $index);

        if (!$natsort) {
            $order === 'asc' ? asort($temp) : arsort($temp);
        } else {
            $case_sensitive ? natsort($temp) : natcasesort($temp);
            if ($order !== 'asc') {
                $temp = array_reverse($temp, true);
            }
        }

        return array_replace($temp, $array);
    }

    /**
     * Checks if a value exists in multiple arrays.
     *
     * @param mixed $needle The value to search for
     * @param array $haystack_arrays The arrays to search in
     * @return bool True if the value is found, false otherwise
     */
    public static function isInArrays(mixed $needle, array $haystack_arrays): bool
    {
        return array_reduce($haystack_arrays, function ($carry, $array) use ($needle) {
            return $carry || in_array($needle, $array, true);
        }, false);
    }

    /**
     * Formats a database datetime string.
     *
     * @param string $datetime The datetime string to format
     * @param string $date_format The format to use ('r', 'ago', 'togo', or a date format string)
     * @return string The formatted date string
     */
    public static function db2date(string $datetime, string $date_format = "r"): string
    {
        $timestamp = match (strlen($datetime)) {
            14 => mktime(
                (int)substr($datetime, 8, 2),
                (int)substr($datetime, 10, 2),
                (int)substr($datetime, 12, 2),
                (int)substr($datetime, 4, 2),
                (int)substr($datetime, 6, 2),
                (int)substr($datetime, 0, 4)
            ),
            19 => mktime(
                (int)substr($datetime, 11, 2),
                (int)substr($datetime, 14, 2),
                (int)substr($datetime, 17, 2),
                (int)substr($datetime, 5, 2),
                (int)substr($datetime, 8, 2),
                (int)substr($datetime, 0, 4)
            ),
            default => null
        };

        if ($timestamp === null) {
            return 'Cannot format date...';
        }

        $stamp = (int)date('U', $timestamp);
        return match ($date_format) {
            'ago' => self::timeAgo($stamp),
            'togo' => self::timeToGo($stamp),
            default => date($date_format, $timestamp)
        };
    }

    /**
     * Formats a timestamp as a "time ago" string.
     *
     * @param int $stamp The timestamp to format
     * @return string The formatted "time ago" string
     */
    private static function timeAgo(int $stamp): string
    {
        $diff = time() - $stamp;
        $mins = round($diff / 60);
        $hours = round($diff / 3600);
        $days = round($diff / 86400);

        return match (true) {
            $diff <= 3600 => $mins <= 1 ? ($mins == 1 ? "1 minute" : "a few seconds") : "$mins minutes",
            $diff <= 86400 => $hours <= 1 ? "1 hour" : "$hours hours",
            default => $days <= 1 ? "1 day" : "$days days"
        } . " ago";
    }

    /**
     * Formats a timestamp as a "time to go" string.
     *
     * @param int $stamp The timestamp to format
     * @return string The formatted "time to go" string
     */
    private static function timeToGo(int $stamp): string
    {
        $diff = $stamp - time();
        $mins = round($diff / 60);
        $hours = round($diff / 3600);
        $days = round($diff / 86400);

        return match (true) {
            $diff <= 3600 => $mins <= 1 ? ($mins == 1 ? "in 1 minute" : "in less than 1 minute") : "in $mins minutes",
            $diff <= 86400 => $hours <= 1 ? "in 1 hour" : "in $hours hours",
            default => $days <= 1 ? ($days == 1 ? "today" : "tomorrow") : "in $days days"
        };
    }

    /**
     * Generates a random string.
     *
     * @param int $len The length of the string
     * @param string $salt The characters to use for generating the string
     * @return string The generated random string
     */
    public static function genRandomString(int $len = 10, string $salt = 'abchefghjkmnpqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $pass = '';
        for ($i = 0; $i < $len; $i++) {
            $pass .= $salt[random_int(0, strlen($salt) - 1)];
        }
        return $pass;
    }

    /**
     * Starts or stops a timer for measuring script execution time.
     *
     * @param bool $finish Whether to finish the timer
     * @param bool $echo Whether to echo the timer result
     * @return string|null The formatted timer result or null if the timer is not enabled
     */
    public static function timer(bool $finish = false, bool $echo = true): mixed
    {
        if (!($_SERVER['TINY_CALC_TIMER'] ?? false)) {
            return null;
        }

        static $start_time;
        if ($finish) {
            $time = microtime(true) - $start_time;
            $unit = match (true) {
                $time < 0.00001 => ['ns', 1_000_000], // nanoseconds
                $time < 0.01 => ['µs', 1_000], // microseconds
                $time < 1 => ['ms', 1_000], // milliseconds
                default => ['seconds', 1], // seconds
            };
            $formatted_time = round($time * $unit[1], 2);
            if ($echo) {
                echo "\n<!-- Generated in $formatted_time $unit[0] -->\n";
            } else {
                return $formatted_time .' '. $unit[0];
            }
        } else {
            $start_time = microtime(true);
        }
        return null;
    }

    /**
     * Gets the client's real IP address.
     *
     * @return string The client's IP address
     */
    public static function getClientRealIP(): string
    {
        $check = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_VIA',
            'HTTP_X_COMING_FROM',
            'HTTP_COMING_FROM',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($check as $item) {
            if (isset($_SERVER[$item])) {
                return $_SERVER[$item];
            }
        }
        return '0.0.0.0';
    }

    /**
     * Gets the client's operating system.
     *
     * @return string The client's operating system
     */
    public static function getClientOS(): string
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $os_platform = "Unknown";
        $os_array = [
            '/windows nt 12/i' => 'Windows 12',
            '/windows nt 11/i' => 'Windows 11',
            '/windows nt 10/i' => 'Windows 10',
            '/windows nt 6.3/i' => 'Windows 8.1',
            '/windows nt 6.2/i' => 'Windows 8',
            '/windows nt 6.1/i' => 'Windows 7',
            '/windows nt 6.0/i' => 'Windows Vista',
            '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
            '/windows nt 5.1/i' => 'Windows XP',
            '/windows xp/i' => 'Windows XP',
            '/windows nt 5.0/i' => 'Windows 2000',
            '/windows me/i' => 'Windows ME',
            '/win98/i' => 'Windows 98',
            '/win95/i' => 'Windows 95',
            '/win16/i' => 'Windows 3.11',
            '/macintosh|mac os x/i' => 'macOS',
            '/mac_powerpc/i' => 'Mac OS 9',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Ubuntu',
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
            '/blackberry/i' => 'BlackBerry',
            '/webos/i' => 'Mobile',
        ];

        foreach ($os_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $os_platform = $value;
                break;
            }
        }
        return $os_platform;
    }

    /**
     * Gets the client's browser.
     *
     * @return string The client's browser
     */
    public static function getClientBrowser(): string
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = "Unknown";
        $browser_array = [
            '/msie/i' => 'Internet Explorer',
            '/firefox/i' => 'Firefox',
            '/safari/i' => 'Safari',
            '/chrome/i' => 'Chrome',
            '/edge/i' => 'Edge',
            '/opera/i' => 'Opera',
            '/netscape/i' => 'Netscape',
            '/maxthon/i' => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i' => 'Handheld Browser',
        ];
        foreach ($browser_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $browser = $value;
                break;
            }
        }
        if ($browser === 'Handheld Browser' && self::getClientOS() === 'iPhone') {
            return 'Safari';
        }
        return $browser;
    }

    /**
     * Converts a currency string to a number.
     *
     * @param mixed $input The currency string to convert
     * @param int $dec The number of decimal places
     * @return string The converted number as a string
     */
    public static function currencyToNumber(mixed $input, int $dec = 2): string
    {
        $arr = explode('.', (string)$input);
        foreach ($arr as &$val) {
            $val = preg_replace('/[^0-9]+/', '', $val);
        }
        return number_format((float)implode('.', $arr), $dec);
    }

    /**
     * Parses and sanitizes a query string.
     *
     * @param string $var The query string to parse
     * @return ?string The sanitized query string or null if empty
     */
    public static function parseQuery(string $var): ?string
    {
        $query = mb_strtolower($var);
        $query = strip_tags(trim($query));
        $query = str_replace('+', '~', $query);
        $query = urldecode($query);
        $query = str_replace(['~', '- '], ['+', '-'], $query);
        return preg_replace('/[^A-Za-z0-9 +-.\'"]/', '', $query);
    }

    /**
     * Reads and parses the JSON body of a request.
     *
     * @param bool $associative Whether to return an associative array (true) or an object (false) (default: true)
     * @return array|object The parsed JSON body
     */
    public static function readJSONBody(bool $associative = true): array|object
    {
        static $res;
        if ($res === null) {
            $rawBody = file_get_contents('php://input') ?: '';
            $parsedBody = [];
            parse_str($rawBody, $parsedBody);
            $jsonBody = json_decode($rawBody, true);
            $body = $jsonBody ?? $parsedBody ?? [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $body = [...$_POST, ...$body];
            }

            $res = $associative ? $body : (object) $body;
        }
        return $res;
    }

    /**
     * Sends a text response with a specified HTTP status code.
     *
     * @param string $text The text content to send
     * @param int $code The HTTP status code (default: 200)
     * @param bool $die Whether to terminate the script after sending the response (default: true)
     */
    public static function textResponse(string $text, int $code = 200, bool $die = true): void
    {
        http_response_code($code);
        echo $text;
        if ($die) {
            tiny::exit();
        }
    }

    /**
     * Sends a file response with a specified HTTP status code.
     *
     * @param string $path The path to the file to send
     * @param int $code The HTTP status code (default: 200)
     * @param bool $die Whether to terminate the script after sending the response (default: true)
     */
    public static function fileResponse(string $path, int $code = 200, bool $die = true): void
    {
        http_response_code($code);
        echo file_get_contents($path);
        if ($die) {
            tiny::exit();
        }
    }

    /**
     * Sends a JSON response with a specified HTTP status code.
     *
     * @param mixed $data The data to be encoded as JSON
     * @param int $code The HTTP status code (default: 200)
     * @param bool $die Whether to terminate the script after sending the response (default: true)
     * @return void
     */
    public static function jsonResponse(mixed $data, int $code = 200, bool $die = true): void
    {
        tiny::header("Content-type: application/json; charset=utf-8", true, $code);
        echo json_encode($data, JSON_THROW_ON_ERROR);
        if ($die) {
            tiny::exit();
        }
    }

    /**
     * Checks if a value is empty, including arrays and objects.
     *
     * @param mixed $v The value to check
     * @return bool True if the value is empty, false otherwise
     */
    public static function isEmpty(mixed $v): bool
    {
        if (is_array($v)) {
            return empty($v);
        } elseif (is_object($v)) {
            return empty((array)$v);
        } else {
            return trim((string)$v) === '';
        }
    }

    /**
     * Parses a name into its components using TheIconic\NameParser\Parser.
     *
     * @param string $name The name to parse
     * @return array|string An array of name components or the original string if parsing fails
     */
    public static function parseName(string $name): array|string
    {
        try {
            $parser = new \TheIconic\NameParser\Parser();
            return $parser->parse($name)->getAll();
        } catch (\Exception) {
            return $name;
        }
    }

    /**
     * Converts a value to a boolean.
     *
     * @param mixed $val The value to convert
     * @return bool The boolean representation of the value
     */
    public static function asBool(mixed $val): bool
    {
        return $val !== true && in_array($val, ['true', '1', 1], true) && !in_array($val, ['false', '0', 0], true);
    }

    /**
     * Converts a value to an integer.
     *
     * @param mixed $val The value to convert
     * @return int The integer representation of the value
     */
    public static function asInt(mixed $val): int
    {
        return is_numeric($val) ? (int)$val : 0;
    }

    /**
     * Converts a value to a float.
     *
     * @param mixed $val The value to convert
     * @return float The float representation of the value
     */
    public static function asFloat(mixed $val): float
    {
        return is_numeric($val) ? (float)$val : 0.0;
    }

    /**
     * Converts a value to a string.
     *
     * @param mixed $val The value to convert
     * @return string The string representation of the value
     */
    public static function asString(mixed $val): string
    {
        return (string)$val;
    }

    /**
     * Converts a value to an array.
     *
     * @param mixed $val The value to convert
     * @return array The array representation of the value
     */
    public static function asArray(mixed $val): array
    {
        return is_array($val) ? $val : [$val];
    }

    /**
     * Converts a value to an object.
     *
     * @param mixed $val The value to convert
     * @return object The object representation of the value
     */
    public static function asObject(mixed $val): object
    {
        return is_object($val) ? $val : (object)$val;
    }

    /**
     * Converts a value to a JSON string.
     *
     * @param mixed $val The value to convert
     * @return string The JSON representation of the value
     * @throws JsonException If JSON encoding fails
     */
    public static function asJSON(mixed $val): string
    {
        return json_encode($val, JSON_THROW_ON_ERROR);
    }

    /**
     * Converts a JSON string to an associative array.
     *
     * @param string $val The JSON string to convert
     * @return array The associative array representation of the JSON string
     * @throws JsonException If JSON decoding fails
     */
    public static function asArrayJSON(string $val)
    {
        return json_decode($val, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Cleans object types by converting them to arrays or objects with numeric values.
     *
     * @param object|array $data The data to clean
     * @param bool|null $as_array Whether to return as an array (true) or object (false)
     * @return array The cleaned data
     */
    public static function cleanObjectTypes(object|array $data, ?bool $as_array = null): array
    {
        $array_flag = isset($as_array) ? $as_array : is_array($data);
        return json_decode(json_encode($data, JSON_NUMERIC_CHECK), $array_flag);
    }

    /**
     * Flushes output buffers and optionally finishes the request.
     *
     * @param bool $finish_request Whether to call fastcgi_finish_request() after flushing (default: false)
     */
    public static function flush(bool $finish_request = false): void
    {
        ob_flush();
        flush();
        if ($finish_request) {
            fastcgi_finish_request();
        }
    }

    /**
     * Sends a content type header for the specified file type.
     *
     * @param string $ftype The file type (e.g., 'javascript', 'json', 'zip', 'xml', 'csv')
     * @param bool $attachement Whether to send as an attachment
     */
    public static function sendContentTypeHeader(string $ftype, bool $attachement = false): void
    {
        $ctype = match ($ftype) {
            'javascript' => "application/javascript",
            'json' => "application/json",
            'zip' => "application/zip",
            'xml' => "text/xml",
            'csv' => "text/csv",
            default => "text/html",
        };

        // tiny::header("Content-type: $ctype; charset=utf-8", true, 200);

        if ($attachement) {
            tiny::header("Content-Disposition: attachment; filename=$attachement");
            tiny::header('Pragma: no-cache');
            tiny::header('Expires: 0');
        }
    }

    /**
     * Sends HTTP/2 103 Early Hints header with Link preload hints.
     *
     * @param string $url The URL to preload
     * @param string $rel The relationship type (default: 'preload')
     * @param string $as The resource type hint (default: 'style')
     * @param bool $nopush Whether to disable HTTP/2 server push (default: true)
     * @param bool $replace Whether to replace existing headers (default: false)
     */
    public static function sendEarlyHints(string $url, string $rel = 'preload', string $as = 'style', bool $nopush = true, $replace = false): void
    {
        $url = self::getHomeUrl($url);
        tiny::header('HTTP/2 103 Early Hints');
        if ($nopush) {
            tiny::header("Link: ‹$url>; rel=$rel; as=$as; nopush", $replace);
        } else {
            tiny::header("Link: ‹$url>; rel=$rel; as=$as", $replace);
        }
    }

    /**
     * Sets the Access-Control-Allow-Origin header.
     *
     * @param string $allow The value for the Access-Control-Allow-Origin header
     */
    public static function allowOrigin(string $allow = '*'): void
    {
        tiny::header("Access-Control-Allow-Origin: $allow", true);
    }


    /**
     * Trims whitespace (or other characters) from the beginning and end of a string.
     *
     * @param mixed $s The string to be trimmed
     * @param string $w The characters to be trimmed (default is whitespace)
     * @return string The trimmed string
     */
    public static function trim(mixed $s, string $w = " \n\r\t\v\x00"): string
    {
        return trim($s . '', $w);
    }

    /**
     * Trims whitespace (or other characters) from the beginning of a string.
     *
     * @param mixed $s The string to be trimmed
     * @param string $w The characters to be trimmed (default is whitespace)
     * @return string The trimmed string
     */
    public static function ltrim(mixed $s, string $w = " \n\r\t\v\x00"): string
    {
        return ltrim($s . '', $w);
    }

    /**
     * Trims whitespace (or other characters) from the end of a string.
     *
     * @param mixed $s The string to be trimmed
     * @param string $w The characters to be trimmed (default is whitespace)
     * @return string The trimmed string
     */
    public static function rtrim(mixed $s, string $w = " \n\r\t\v\x00"): string
    {
        return rtrim($s . '', $w);
    }

    /**
     * Creates a new TinyFlash instance.
     *
     * @param string $name The name of the flash message (default: 'flash_msg')
     * @return TinyFlash A new TinyFlash instance
     */
    public static function flash($name = 'flash_msg'): TinyFlash
    {
        // require_once __DIR__ . '/flash.php';
        return new TinyFlash($name);
    }

    /**
     * Recursively removes a directory and its contents.
     *
     * @param string $dir The directory to remove
     */
    public static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            // $objects = scandir($dir);
            // foreach ($objects as $object) {
            //     if ($object != "." && $object != "..") {
            //         if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
            //             self::rrmdir($dir . DIRECTORY_SEPARATOR . $object);
            //         } else {
            //             unlink($dir . DIRECTORY_SEPARATOR . $object);
            //         }
            //     }
            // }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }

            rmdir($dir);
        }
    }

    /**
     * Sets an HTTP header, handling both standard PHP and Swoole environments.
     *
     * @param string $header The HTTP header to set (format: "Name: Value")
     * @return void
     */
    public static function header(string $header): void
    {
        if (self::isUsingSwoole()) {
            $header = explode(':', $header);
            self::swoole()->header($header[0], $header[1], true);
        }
        header($header);
    }

    /**
     * Wraps content in an email layout template.
     *
     * @param string $content The email content to wrap
     * @param string $layout The path to the email layout template file
     * @return string The complete email with content inserted into the layout
     */
    public static function emailLayout(string $content, string $layout): string
    {
        // Remove leading spaces from the beginning of each line
        $lines = explode("\n", $content);
        $content = implode("\n", array_map('trim', $lines));
        $email = file_get_contents(tiny::config()->app_path . '/views/'. $layout);
        $email = str_replace('{{ content }}', $content, $email);
        return $email;
    }
}
