<?php
declare(strict_types=1);
/**
 * OPcache preloading for Tiny
 * Place at project root and reference via php.ini:
 *   opcache.preload=/var/www/tiny/preload.php
 *   opcache.preload_user=www-data
 */

// Composer autoloader (ensures class map is available)
require __DIR__ . '/vendor/autoload.php';

// Compile core Tiny files you always use
@opcache_compile_file(__DIR__ . '/tiny/tiny.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/cache.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/component.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/controller.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/cookie.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/csrf.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/db. php');
@opcache_compile_file(__DIR__ . '/tiny/ext/debugger.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/flash.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/http.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/layout.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/migration.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/model.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/request.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/response.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/scheduler-job.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/scheduler.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/sse.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/swoole.php');
@opcache_compile_file(__DIR__ . '/tiny/ext/utils.php');

// Optionally compile all classmap entries
$classMapFile = __DIR__ . '/vendor/composer/autoload_classmap.php';
if (is_file($classMapFile)) {
    $classMap = require $classMapFile;
    foreach ($classMap as $file) {
        @opcache_compile_file($file);
    }
}

// If you have a tiny/bootstrap that is required on every request:
if (is_file(__DIR__ . '/tiny/bootstrap.php')) {
    @opcache_compile_file(__DIR__ . '/tiny/bootstrap.php');
}
