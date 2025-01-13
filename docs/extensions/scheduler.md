[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Scheduler Extension

The Scheduler extension provides a simple and flexible way to schedule recurring tasks in your application. It supports various scheduling intervals and patterns using a fluent interface.

## Basic Usage

```php
// Create a new job
$app->scheduler->job('MyJob/process', ['arg1', 'arg2'])
    ->daily('15:30');

// Run all due jobs
$app->scheduler->run();
```

## Job Scheduling

### Time-Based Scheduling

```php
// Run every minute
$app->scheduler->job('MyJob/cleanup')
    ->everyMinute();

// Run every N minutes
$app->scheduler->job('MyJob/check')
    ->everyMinute(5); // Every 5 minutes

// Run hourly at minute 15
$app->scheduler->job('MyJob/hourly')
    ->hourly(15);

// Run daily at specific time
$app->scheduler->job('MyJob/daily')
    ->daily('15:30'); // or ->daily(15, 30)

// Run weekly on Sunday at 00:00
$app->scheduler->job('MyJob/weekly')
    ->weekly(0, 0, 0);
```

### Day-Based Scheduling

```php
// Run on specific days at given time
$app->scheduler->job('MyJob/monday')
    ->monday('15:30');

$app->scheduler->job('MyJob/friday')
    ->friday('17:00');

// Available methods for all days:
// ->sunday()
// ->monday()
// ->tuesday()
// ->wednesday()
// ->thursday()
// ->friday()
// ->saturday()
```

### Month-Based Scheduling

```php
// Run monthly on the 1st at 00:00
$app->scheduler->job('MyJob/monthly')
    ->monthly('*', 1, 0, 0);

// Run on specific months
$app->scheduler->job('MyJob/january')
    ->january(1, '15:30');

// Available methods for all months:
// ->january()
// ->february()
// ->march()
// ->april()
// ->may()
// ->june()
// ->july()
// ->august()
// ->september()
// ->october()
// ->november()
// ->december()
```

### Custom Scheduling

```php
// Using cron expressions
$app->scheduler->job('MyJob/custom')
    ->at('*/5 * * * *'); // Every 5 minutes

// Using specific date/time
$app->scheduler->job('MyJob/once')
    ->date('2024-12-31 23:59:59');
```

## Job Implementation

Jobs can be implemented as standalone functions or class methods:

```php
// Function-based job
function myJob($arg1, $arg2) {
    // Job logic here
}

// Class-based job
class MyJob {
    public function process($arg1, $arg2) {
        // Job logic here
    }
}
```

## Setting Up the Scheduler

1. Create a scheduler script (e.g., `scheduler.php`):
```php
<?php

require_once 'tiny/bootstrap.php';

// Add jobs
$app->scheduler->job('MyJob/dailyBackup')
    ->daily('02:00');

$app->scheduler->job('MyJob/cleanup')
    ->hourly(30);

// Run due jobs
$app->scheduler->run();
```

2. Set up a cron job to run the scheduler:
```bash
* * * * * cd /path/to/your/app && php scheduler.php >> /dev/null 2>&1
```

## Best Practices

1. **Job Organization**
   - Keep jobs in a dedicated directory (e.g., `app/jobs/`)
   - Use descriptive names for job classes and methods
   - Group related jobs in the same class

2. **Error Handling**
   - Implement proper error handling in jobs
   - Log job execution results
   - Set up monitoring for failed jobs

3. **Performance**
   - Keep jobs lightweight and focused
   - Avoid long-running tasks in frequent jobs
   - Use appropriate intervals for different tasks

4. **Maintenance**
   - Monitor job execution times
   - Clean up completed/failed job logs
   - Document job purposes and dependencies
