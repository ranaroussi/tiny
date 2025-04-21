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


use Cron\CronExpression;

class Job
{
    private ?string $class = null;
    private string $command;
    private array $args;
    private CronExpression $executionTime;
    private ?int $executionYear = null;

    private bool $isSecondJob = false;
    private ?int $secondInterval = null;

    /**
     * Constructor for the Job class.
     * Initializes a new job with the given command and arguments.
     *
     * @param string $command The command to be executed (can be a function name or a class/method combination)
     * @param array $args The arguments to be passed to the command (default: [])
     */
    public function __construct(string $command, array $args = [])
    {
        tiny::requireAll('/jobs/');

        $parts = explode('/', $command);
        if (count($parts) === 2) {
            [$this->class, $this->command] = $parts;
        } else {
            $this->command = $command;
        }
        $this->args = $args;
    }

    /**
     * Checks if the job is due to run at the given date and time.
     *
     * @param \DateTime|null $date The date and time to check (default: current date/time)
     * @return bool True if the job is due to run, false otherwise
     */
    public function isDue(?\DateTime $date = null): bool
    {
        if (!isset($this->executionTime)) {
            $this->at('* * * * *');
        }

        $date ??= new \DateTime();

        if ($this->executionYear !== null && $this->executionYear !== (int)$date->format('Y')) {
            return false;
        }

        // Handle second-level scheduling
        if ($this->isSecondJob) {
            if ($this->secondInterval === null || $this->secondInterval === 0) {
                return true;
            }

            // Create a unique identifier for this job
            $jobId = md5(($this->class ?? '') . $this->command . serialize($this->args));
            $stateFile = sys_get_temp_dir() . "/tiny_scheduler_job_{$jobId}.txt";

            $now = time();
            $lastRun = 0;

            // Only read the file if it exists to avoid unnecessary I/O
            if (file_exists($stateFile)) {
                $lastRun = (int)file_get_contents($stateFile);
            }

            // First run - initialize the state file and skip execution
            if ($lastRun === 0) {
                // Align to the next interval boundary
                $nextRun = floor($now / $this->secondInterval) * $this->secondInterval;
                file_put_contents($stateFile, $nextRun);
                return false;
            }
            // Calculate if enough time has passed since last run
            $elapsedTime = $now - $lastRun;
            if ($elapsedTime >= $this->secondInterval) {
                // Update the last run time
                file_put_contents($stateFile, $now);
                return true;
            }
            return false;
        }

        // Regular minute-level (and up) cron jobs
        return $this->executionTime->isDue($date);
    }

    /**
     * Runs the job if it is due.
     */
    public function run(): void
    {
        if ($this->isDue()) {
            $result = $this->executeJob();
            echo $result . PHP_EOL;
        }
    }

    /**
     * Executes the job's command.
     *
     * @return mixed The result of the job execution
     */
    private function executeJob(): mixed
    {
        if ($this->class !== null) {
            $class = new $this->class();
            return $class->{$this->command}(...$this->args);
        }
        return ($this->command)(...$this->args);
    }


    // ----- internals -----

    /**
     * Sets the execution time for the job using a cron expression.
     *
     * @param string $expression The cron expression
     * @return static
     */
    public function at(string $expression): static
    {
        $this->executionTime = new CronExpression($expression);
        return $this;
    }

    /**
     * Sets the job to run at a specific date and time.
     *
     * @param \DateTime|string $date The date and time for the job to run
     * @return static
     */
    public function date(\DateTime|string $date): static
    {
        if (!$date instanceof \DateTime) {
            $date = new \DateTime($date);
        }

        $this->executionYear = (int)$date->format('Y');

        return $this->at("{$date->format('i')} {$date->format('H')} {$date->format('d')} {$date->format('m')} *");
    }

    /**
     * Sets the job to run every second or at specific second intervals.
     *
     * @param int|null $seconds The interval in seconds (default: null for every second)
     * @return static
     */
    public function everySecond(?int $seconds = null): static
    {
        // Flag this job for per-second execution
        $this->isSecondJob = true;

        // Store the second interval (null means every second)
        $this->secondInterval = $seconds;

        // Set standard cron to run every minute
        return $this->at('* * * * *');
    }

    /**
     * Sets the job to run every minute or at specific minute intervals.
     *
     * @param int|string|null $minute The minute interval (default: null for every minute)
     * @return static
     */
    public function everyMinute(int|string|null $minute = null): static
    {
        $minuteExpression = '*';
        if ($minute !== null) {
            $c = $this->validateCronSequence($minute);
            $minuteExpression = '*/' . $c['minute'];
        }

        return $this->at($minuteExpression . ' * * * *');
    }

    // Shorthand for everyMinute
    public function everyTwoMinutes(): static { return $this->everyMinute(2); }
    public function everyThreeMinutes(): static { return $this->everyMinute(3); }
    public function everyFiveMinutes(): static { return $this->everyMinute(5); }
    public function everyTenMinutes(): static { return $this->everyMinute(10); }
    public function everyFifteenMinutes(): static { return $this->everyMinute(15); }
    public function everyThirtyMinutes(): static { return $this->everyMinute(30); }

    /**
     * Sets the job to run hourly at a specific minute.
     *
     * @param int|string $minute The minute of the hour to run the job
     * @return static
     */
    public function hourly(int|string $minute = 0): static
    {
        $c = $this->validateCronSequence($minute);

        return $this->at("{$c['minute']} * * * *");
    }

    /**
     * Sets the job to run daily at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function daily(int|string $hour = 0, int|string $minute = 0): static
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = $parts[1] ?? '0';
        }

        $c = $this->validateCronSequence($minute, $hour);

        return $this->at("{$c['minute']} {$c['hour']} * * *");
    }

    /**
     * Sets the job to run weekly on a specific day and time.
     *
     * @param int|string $weekday The day of the week (0-6, where 0 is Sunday)
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function weekly(int|string $weekday = 0, int|string $hour = 0, int|string $minute = 0): static
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = $parts[1] ?? '0';
        }

        $c = $this->validateCronSequence($minute, $hour, null, null, $weekday);

        return $this->at("{$c['minute']} {$c['hour']} * * {$c['weekday']}");
    }

    /**
     * Sets the job to run monthly on a specific day and time.
     *
     * @param int|string $month The month (1-12 or '*' for every month)
     * @param int|string $day The day of the month (-1 for last day)
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function monthly(int|string $month = '*', int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = $parts[1] ?? '0';
        }

        // Handle -1 as last day of month
        if ($day === -1) {
            $day = 'L'; // 'L' is a special character in cron that means "last day of month"
        }

        $c = $this->validateCronSequence($minute, $hour, $day, $month);

        return $this->at("{$c['minute']} {$c['hour']} {$c['day']} {$c['month']} *");
    }

    /**
     * Sets the job to run on Sundays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function sunday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(0, $hour, $minute);
    }

    /**
     * Sets the job to run on Mondays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function monday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(1, $hour, $minute);
    }

    /**
     * Sets the job to run on Tuesdays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function tuesday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(2, $hour, $minute);
    }

    /**
     * Sets the job to run on Wednesdays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function wednesday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(3, $hour, $minute);
    }

    /**
     * Sets the job to run on Thursdays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function thursday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(4, $hour, $minute);
    }

    /**
     * Sets the job to run on Fridays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function friday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(5, $hour, $minute);
    }

    /**
     * Sets the job to run on Saturdays at a specific time.
     *
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function saturday(int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->weekly(6, $hour, $minute);
    }

    /**
     * Sets the job to run on January at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function january(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(1, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on February at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function february(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(2, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on March at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function march(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(3, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on April at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function april(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(4, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on May at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function may(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(5, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on June at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function june(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(6, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on July at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function july(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(7, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on August at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function august(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(8, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on September at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function september(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(9, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on October at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function october(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(10, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on November at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function november(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(11, $day, $hour, $minute);
    }

    /**
     * Sets the job to run on December at a specific day and time.
     *
     * @param int|string $day The day of the month
     * @param int|string $hour The hour to run the job
     * @param int|string $minute The minute to run the job
     * @return static
     */
    public function december(int|string $day = 1, int|string $hour = 0, int|string $minute = 0): static
    {
        return $this->monthly(12, $day, $hour, $minute);
    }

    /**
     * Validates and formats the cron sequence components.
     *
     * @param int|string|null $minute The minute component
     * @param int|string|null $hour The hour component
     * @param int|string|null $day The day component
     * @param int|string|null $month The month component
     * @param int|string|null $weekday The weekday component
     * @return array An array of validated cron sequence components
     */
    private function validateCronSequence(
        int|string|null $minute = null,
        int|string|null $hour = null,
        int|string|null $day = null,
        int|string|null $month = null,
        int|string|null $weekday = null
    ): array {
        return [
            'minute' => $this->validateCronRange($minute, 0, 59),
            'hour' => $this->validateCronRange($hour, 0, 23),
            'day' => $this->validateCronRange($day, 1, 31),
            'month' => $this->validateCronRange($month, 1, 12),
            'weekday' => $this->validateCronRange($weekday, 0, 6),
        ];
    }

    /**
     * Validates a single cron sequence component against its allowed range.
     *
     * @param int|string|null $value The value to validate
     * @param int $min The minimum allowed value
     * @param int $max The maximum allowed value
     * @return string|int The validated value
     * @throws InvalidArgumentException if the value is invalid
     */
    private function validateCronRange(int|string|null $value, int $min, int $max): string|int
    {
        if ($value === null || $value === '*' || $value === 'L') {
            return $value ?? '*';
        }

        if (!is_numeric($value) || ($value < $min && $value !== -1) || $value > $max) {
            throw new \InvalidArgumentException(
                "Invalid value: it should be '*', 'L', -1, or between {$min} and {$max}."
            );
        }

        return (int)$value;
    }
}
