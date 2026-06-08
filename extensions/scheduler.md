[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Scheduler

Tiny ships with a fluent, cron-style scheduler. You define jobs in PHP, register them in `app/scheduler.php`, and add one cron entry — that's the whole install. The scheduler supports **second-level**, minute, hourly, daily, weekday, monthly, and arbitrary cron expressions.

## Quick start

`app/scheduler.php`:

```php
<?php
require_once __DIR__ . '/../tiny/tiny.php';

tiny::scheduler()->job('Reports/daily')->daily('02:00');
tiny::scheduler()->job('Cache/warm', ['users'])->everyMinute(5);
tiny::scheduler()->job('Heartbeat/ping')->everySecond(5);
tiny::scheduler()->job('Cleanup/temp')->at('*/15 * * * *');

tiny::scheduler()->run();
```

Cron entry:

```cron
# Minute-level only
* * * * * /usr/bin/php /var/www/app/scheduler.php >/dev/null 2>&1
```

For **second-level** jobs, use the bundled wrapper instead:

```cron
* * * * * /var/www/tiny/scheduler.sh >/dev/null 2>&1
```

`scheduler.sh` invokes `scheduler.php` every 500ms for one minute, which is what gives `->everySecond()` its resolution.

## Defining jobs

`tiny::scheduler()->job($target, $args = [])` returns a `Job` you chain timing methods onto.

`$target` can be:

| Form | Calls |
|---|---|
| `'function_name'` | Global function `function_name(...$args)` |
| `'ClassName/method'` | `(new ClassName())->method(...$args)` |
| `'ClassName::staticMethod'` | `ClassName::staticMethod(...$args)` |

Job classes live in `app/jobs/`. The framework autoloads all files in that directory at scheduler boot.

```php
// app/jobs/reports.php
<?php
class Reports
{
    public function daily(): string
    {
        tiny::mailgun()->send('admin@example.com', 'Daily report', '…');
        return 'sent';
    }
}
```

## Timing API

### Sub-minute

```php
$job->everySecond();      // every second
$job->everySecond(5);     // every 5 seconds
```

> Requires `scheduler.sh` (not a bare `php scheduler.php` cron entry).

### Minute / hour

```php
$job->everyMinute();
$job->everyMinute(5);     // every 5 minutes (at :00, :05, :10, …)
$job->hourly();           // every hour at :00
$job->hourly(15);         // every hour at :15
```

### Daily

```php
$job->daily();            // every day at 00:00
$job->daily('22:03');     // every day at 22:03
$job->daily(22, 3);       // same, two-arg form
```

### Weekday

```php
$job->sunday();           // Sundays at 00:00
$job->monday('09:00');
$job->friday(18);
$job->saturday(12, 30);
```

All days are available: `sunday`, `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday`.

### Month

```php
$job->january();              // Jan 1 at 00:00
$job->december(25);           // Dec 25 at 00:00
$job->august(15, 20, 30);     // Aug 15 at 20:30
```

All months are available.

### Cron expression

```php
$job->at('*/5 * * * *');      // every 5 minutes
$job->at('30 2 * * 1-5');     // weekdays at 02:30
```

The cron expression is parsed by `dragonmantank/cron-expression`.

### One-off date

```php
$job->date('2024-12-31 23:59:59');
```

## Running

```php
tiny::scheduler()->run();
```

Iterates over registered jobs, executes the ones whose schedule matches *now*. Idempotent on jobs that aren't due.

## Testing jobs locally

Use the test harness for manual debugging without waiting for cron:

```php
// app/controllers/test-scheduler.php — only works when ENV=local
<?php
class TestScheduler extends TinyController
{
    public function get($request, $response)
    {
        tiny::initTestScheduler();
        $job = new Reports();
        echo $job->daily();
    }
}
```

Visit `/test-scheduler` and the job runs immediately. The harness refuses to run outside `ENV=local`.

## Where jobs live

The conventional layout:

```
app/
├── jobs/
│   ├── reports.php          # class Reports
│   ├── cleanup.php          # class Cleanup
│   └── …
├── scheduler.php            # registers + runs jobs
└── scheduler.sh             # optional, copy from tiny/ for second-level
```

## Best practices

1. **One responsibility per job class.** Mirror your domain (Reports, Cleanup, Notifications, Imports).
2. **Don't share state across jobs.** Each `scheduler.php` invocation is a fresh process.
3. **Make jobs idempotent.** Cron will occasionally re-run on clock drift / restarts.
4. **Log explicitly.** `tiny::log()` writes to `TINY_LOG_FILE`.
5. **Use `TINY_DB_AUTOCONNECT=false`** for jobs that don't touch the database.
6. **Guard production-only jobs** with `$_SERVER['ENV'] === 'prod'` if needed.
