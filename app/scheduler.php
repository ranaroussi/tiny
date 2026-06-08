<?php
/* ------------------------------
To run this file, add this line to your crontab:
* * * * * php path/to/tiny/scheduler.php 1>> /dev/null 2>&1
------------------------------ */
$tiny_path = isset($_SERVER['TINY_PATH']) ? trim($_SERVER['TINY_PATH']) : implode('/', explode('/', __DIR__, -1)) . '/tiny';
require_once $tiny_path . '/tiny.php';

// ------------------------------
// list your jobs
// tiny::scheduler()->job(...)->everyMinute();
// tiny::scheduler()->job(...)->everyMinute(5);
// tiny::scheduler()->job(...)->hourly();
// tiny::scheduler()->job(...)->hourly(53);
// tiny::scheduler()->job(...)->daily();
// tiny::scheduler()->job(...)->daily(22, 03);
// tiny::scheduler()->job(...)->daily('22:03');
// tiny::scheduler()->job(...)->saturday();
// tiny::scheduler()->job(...)->friday(18);
// tiny::scheduler()->job(...)->sunday(12, 30);
// tiny::scheduler()->job(...)->january();
// tiny::scheduler()->job(...)->december(25);
// tiny::scheduler()->job(...)->august(15, 20, 30);
// tiny::scheduler()->job('class::static', ['arg1', 'arg2'])->at('* * * * *');
// tiny::scheduler()->job('function-in-index.php', ['arg1', 'arg2'])->at('* * * * *');
// tiny::scheduler()->job('class/function', ['arg1', 'arg2'])->at('* * * * *');
// ------------------------------

// ------------------------------
// run the scheduler
// ------------------------------
tiny::scheduler()->run();
// ------------------------------
