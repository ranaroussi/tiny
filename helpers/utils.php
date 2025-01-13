<?php
function object_merge($obj1, $obj2)
{
    $obj1 = (array)$obj1;
    $obj2 = (array)$obj2;
    return (object)array_merge($obj1, $obj2);
}

function javascript_encode($arr, $allow_nulls = true)
{
    $js = str_replace('"', "'", addcslashes(json_encode($arr), "'"));
    $js = preg_replace('/(\'([a-zA-Z_]+)\':)/m', "$2:", $js);
    if (!$allow_nulls) {
        $js = str_replace(':null,', ":'',", $js);
    }
    return $js;
}

function json_array($arr)
{
    return str_replace('"', "'", addcslashes(json_encode(array_values($arr)), "'"));
}

class Utils {
    public function objectMmerge($obj1, $obj2)
    {
        return object_merge($obj1, $obj2);
    }

    public function javascriptEncode($arr, $allow_nulls = true)
    {
        return javascript_encode($arr, $allow_nulls);
    }

    public function jsonArray($arr)
    {
        return json_array($arr);
    }

    public static function getDNS($domain, $type = DNS_NS)
    {
        // DNS_A, DNS_CNAME, DNS_HINFO, DNS_CAA, DNS_MX,
        // DNS_NS, DNS_PTR, DNS_SOA, DNS_TXT, DNS_AAAA,
        // DNS_SRV, DNS_NAPTR, DNS_A6, DNS_ALL or DNS_ANY
        $dns = dns_get_record($domain, $type);
        return array_column($dns, 'target', 'ip');
    }

    public static function zeroFill($x)
    {
        return ($x == null || $x == '') ? 0 : $x;
    }

    public static function scanDirRecrusive($dir, $exclude = [], $dotfiles = true)
    {
        $IGNORE_FILES = [
            '.DS_Store',
            '.env',
            '.idea',
            '.ssh',
            '.vim',
            '.vscode',
            'composer.lock',
            'Icon',
            'package-lock.json',
            'package.lock',
            'Thumbs.db',
        ];
        $IGNORE_PATTERNS = [
            '__pycache__',
            '.pyc',
            '.swp',
            'node_modules',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $collection = [];
        foreach ($iterator as $file) {
            $filename = $file->getFilename();
            $filepath = $file->getPathname();

            if (!$dotfiles && $filename[0] === '.') {
                continue;
            }

            if (in_array($filename, $IGNORE_FILES) || in_array($filename, $exclude)) {
                continue;
            }

            if (array_reduce($IGNORE_PATTERNS, function ($carry, $pattern) use ($filepath) {
                return $carry || strpos($filepath, $pattern) !== false;
            }, false)) {
                continue;
            }

            $collection[] = $filepath;
        }

        return $collection;
    }

    public static function downloadArrayAsCSV($array, $filename = "export.csv", $delimiter = ",")
    {
        $f = fopen('php://memory', 'w');
        $first = true;
        foreach ($array as $line) {
            if ($first) {
                fputcsv($f, array_keys($line), $delimiter);
            }
            fputcsv($f, $line, $delimiter);
            $first = false;
        }
        fseek($f, 0);
        tiny::header('Content-Type: text/csv');
        tiny::header('Content-Disposition: attachment; filename="' . $filename . '";');
        fpassthru($f);
    }

    public static function inString($haystack, $needles = array(), $lowercase = false, $return_needle = false)
    {
        if (!is_array($needles)) {
            $needles = array($needles);
        }
        if ($lowercase) {
            $haystack = mb_strtolower($haystack);
        }
        foreach ($needles as $needle) {
            $needle = ($lowercase) ? mb_strtolower($needle) : $needle;
            if (str_contains($haystack, $needle)) {
                return $return_needle ? $needle : true;
            }
        }
        return false;
    }

    public static function getRequestHeaders()
    {
        return array_filter($_SERVER, function ($key) {
            return strpos($key, 'HTTP_') === 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    public static function forceAuthenticationHeaders($header, $value)
    {
        $headers = self::getRequestHeaders();

        if (!isset($headers[$header])) {
            tiny::header('HTTP/1.1 401 Unauthorized');
            tiny::die('Unauthorized');
        }

        if ($headers[$header] !== $value) {
            tiny::header('HTTP/1.1 401 Unauthorized');
            tiny::die('Bad credentials');
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    public static function timeElapsedString($datetime, $full = false)
    {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }

        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }

        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    public static function generateRandomUsername($string)
    {
        $string = mb_strtolower(preg_replace('/[^A-Za-z0-9_ ]/', '', str_replace('@', ' ', $string)));
        $username = substr($string, 0, 8);
        if (str_contains($string, ' ')) {
            $firstPart = substr(strstr($string, ' ', true), 0, 3);
            $secondPart = substr(strstr($string, ' ', false), 0, 6);
            $username = tiny::trim($firstPart) . tiny::trim($secondPart);
        }
        $nrRand = random_int(11, 99);
        $username .= tiny::trim($nrRand);
        return preg_replace('/\s+/', '', $username);
    }

    public static function generateRandomString($length = 10)
    {
        return bin2hex(random_bytes($length));
    }

    public static function generateToken()
    {
        $time = hrtime(true);
        return tiny::baseSet((int)($time / 1e6));
    }

    public static function makeInviteeHash($name, $email)
    {
        $hash = base64_encode("$name;$email");
        $hash = explode('=', $hash);
        return $hash[0] . '!' . (count($hash) - 1);
    }

    public static function parseInviteeHash($hash)
    {
        $b64 = explode('!', tiny::trim($hash));
        if (count($b64) == 1) {
            $b64 = $b64[0];
        } else {
            $b64 = $b64[0] . str_repeat('=', (float)$b64[1]);
        }
        $hash = base64_decode($b64);

        $invitee = ['email' => '', 'fname' => '', 'lname' => '', 'name' => ''];
        foreach (explode(';', $hash) as $value) {
            if (str_contains($value, '@')) {
                $invitee['email'] = $value;
            } else {
                $invitee['name'] = $value;
            }

            list($invitee['fname'], $invitee['lname']) = explode(' ', trim($invitee['name']), 1);
        }

        return (object)$invitee;
    }

    /**
     * @throws DateMalformedStringException
     */
    public static function getBillingCycle($subscribedAt, $billingCycle = 'monthly')
    {
        $subscribedDate = new DateTime($subscribedAt . '');
        $lookupDate = clone $subscribedDate;
        $currentDate = new DateTime();

        $billingCycle = $billingCycle == 'monthly' ? 'month' : 'year';
        if ($lookupDate->format('Y-m-d') == $currentDate->format('Y-m-d')) {
            $lookupDate->modify("+1 $billingCycle");
            return [
                'start' => $currentDate->format('Y-m-d'),
                'end' => $lookupDate->format('Y-m-d'),
            ];
        }

        while ($lookupDate < $currentDate) {
            $lookupDate->modify("+1 $billingCycle");
        }

        $next = $lookupDate->format('Y-m-d');
        $lookupDate->modify("-1 $billingCycle");
        return [
            'start' => $lookupDate->format('Y-m-d'),
            'end' => $next,
        ];
    }

    public static function numberToWords($number)
    {
        if ($number > 5) {
            return number_format($number);
        }
        $words = array(
            0 => 'zero',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
        );

        return $words[$number];
    }

    public static function numberToTimes($number)
    {
        if ($number > 2) {
            return number_format($number) . ' times';
        }
        $words = array(
            0 => 'zero times',
            1 => 'once',
            2 => 'twice',
        );

        return $words[$number];
    }
}

tiny::registerHelper('utils', function() {
    return new Utils();
});
