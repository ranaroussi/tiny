<?php

declare(strict_types=1);

$_SERVER['ENV'] = 'test';
require __DIR__ . '/../../tiny/tiny.php';

$ctrl = tiny::test('home');
$response = tiny::response();

try {
    $ctrl->get(tiny::request(), $response);
} catch (TinyTestExit) {}

assert($response->renderedView === 'home', 'Home controller should render home view');

echo "PASS: home/get\n";
