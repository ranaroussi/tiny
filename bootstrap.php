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

/* -------------------------------------- */
try {
    require __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    die('<code>ERROR: Cannot find composer autoloader</code>');
}
/* -------------------------------------- */
// Autoloader for Tiny framework
/* -------------------------------------- */
spl_autoload_register(function ($class) {
    $classFile = __DIR__ . '/ext/' . str_replace('tiny', '', mb_strtolower($class)) . '.php';
    if (file_exists($classFile)) {
        include $classFile;
    }
});

/* -------------------------------------- */
// Required for Dockerized apps
/* -------------------------------------- */
try {
    $_SERVER['SERVER_PORT'] = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? @$_SERVER['SERVER_PORT'];
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? @$_SERVER['HTTP_HOST'];
    if (@$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
        $_SERVER['HTTPS'] = 'on';
    }
} catch (Exception $e) {
}

/* -------------------------------------- */
// Load configuration
/* -------------------------------------- */
$_SERVER['ENV'] = 'prod';
$env_file = __DIR__ . '/../env.php';
if (file_exists($env_file)) {
    try {
        require $env_file;
        $env = defined('ENV') ? ENV : 'prod';
        $_SERVER['ENV'] = $env;

        if (in_array($env, ['local', 'dev'])) {
            ini_set('memory_limit', -1);
        }

        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env.' . $env);
        $dotenv->load();
    } catch (Exception $e) {
        if (!isset($_SERVER['SITE_NAME'])) {
            die('<code>ERROR: Missing environment variables!</code>');
        }
    }
}
error_reporting($_SERVER['ENV'] != 'prod' ? E_ALL : 0);

/* -------------------------------------- */
// PHP Enviroment
foreach ($_SERVER as $key => $value) {
    if (is_string($value)) {
        $_SERVER[$key] = trim($value, "'");
    }
}
$_SERVER['CALC_TIMER'] = $_SERVER['CALC_TIMER'] ?? true;
putenv('TZ=' . isset($_SERVER['TIMEZONE']) ? $_SERVER['TIMEZONE'] : 'UTC');
@ini_set('register_globals', 'off');
@ini_set('magic_quotes_gpc', 'off');
@ini_set('short_open_tag', 1);
@ini_set('session.cookie_httponly', 1); // Prevents session hijacking (JS XSS attacks aimed to steal the session ID)
@ini_set('session.use_only_cookies', 1); // Prevent session fixation (session ID cannot be passed through URLs)
@ini_set('session.cookie_secure', 1); // Uses a secure connection (HTTPS) if possible
if (@$_SERVER['TINY_MINIFY_OUTPUT'] == 'true') {
    @ob_start('minifyOutput'); // Minify output
}

/* -------------------------------------- */
// Sentry stuff
if (@$_SERVER['ENV'] != 'local' && isset($_SERVER['SENTRY_DSN'])) {
    \Sentry\init(['dsn' => $_SERVER['SENTRY_DSN']]);
    \Sentry\captureLastError();
}

/* -------------------------------------- */

// html output minifier
function minifyOutput($buffer): array|string
{
    $search = array(
        '/\>[^\S ]+/s', // strip whitespaces after tags, except space
        '/[^\S ]+\</s', // strip whitespaces before tags, except space
        '/(\s)+/s', // shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/', // Remove HTML comments
    );
    $replace = array('>', '<', '\\1', '');
    $buffer = preg_replace($search, $replace, $buffer);
    $buffer = str_replace('> ', '>', $buffer);
    $buffer = str_replace(' <', '<', $buffer);
    $buffer = str_replace("\n}", ' }', $buffer);
    return str_replace("}\n", '} ', $buffer);
}

