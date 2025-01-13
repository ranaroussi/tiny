<?php
if (!extension_loaded('swoole')) {
    throw new \RuntimeException('Swoole extension is required for coroutines');
}

require_once 'tiny.php';

// Start Swoole server
tiny::swoole()->start();
