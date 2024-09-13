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

require_once __DIR__ . '/scheduler-job.php';

class TinyScheduler
{
    private array $jobs = [];

    /**
     * Adds a new job to the scheduler.
     *
     * @param string $fn The function or class/method to be scheduled
     * @param array $args The arguments to be passed to the function (default: [])
     * @return Job The newly created Job instance
     */
    public function job(string $fn, array $args = []): Job
    {
        $job = new Job($fn, $args);
        $this->jobs[] = $job;
        return $job;
    }

    /**
     * Runs all scheduled jobs that are due.
     */
    public function run(): void
    {
        foreach ($this->jobs as $job) {
            $job->run();
        }
    }
}
