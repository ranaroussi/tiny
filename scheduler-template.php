<?php
/* ------------------------------
SCHEDULER IMPLEMENTATION INSTRUCTIONS

1. SETUP:
   - Save this file as scheduler.php in your application directory
   - Make sure tiny framework is accessible at ../tiny or set TINY_PATH environment variable

2. CONFIGURATION:
   - Define your scheduled jobs below using the examples provided
   - Jobs can be scheduled at second-level, minute-level, hourly, daily, or custom intervals
   - Each job calls a function or class method in your application

3. EXECUTION METHODS:
   A. For minute-level (or greater) jobs only:
      * * * * * /path/to/php /path/to/scheduler.php > /dev/null 2>&1

   B. For second-level execution (recommended):
      - Copy scheduler.sh in your application directory
      - Make it executable: chmod +x scheduler.sh
      - Add to crontab: * * * * * /path/to/scheduler.sh > /dev/null 2>&1

4. JOB IMPLEMENTATION:
   - Create classes/functions for your jobs in app/jobs/ directory
   - Each method should return a string with its result
   - Use class/method format for jobs (e.g., 'Reports/generate')

5. ERROR HANDLING:
   - Errors are written to the error log
   - For production, redirect output to a log file instead of /dev/null
------------------------------ */

// ------------------------------
// 1. include tiny framework
// ------------------------------
$tiny_path = isset($_SERVER['TINY_PATH']) ? tiny::trim($_SERVER['TINY_PATH']) : implode('/', explode('/', __DIR__, -1)) . '/tiny';
require_once $tiny_path . '/tiny.php';

// ------------------------------
// 2. list your jobs
// ------------------------------
// tiny::scheduler()->job(...)->everySecond();
// tiny::scheduler()->job(...)->everySecond(5);
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
// 3. run the scheduler
// ------------------------------
tiny::scheduler()->run();
// ------------------------------
