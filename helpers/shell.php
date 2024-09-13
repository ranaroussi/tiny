<?php
declare(strict_types=1);
/**
 * Executes a console command in a Linux or Windows environment without waiting for the result.
 * Useful for executing extensive tasks.
 *
 * @param string $cmd The command to execute
 * @param bool $async Whether to run the command asynchronously
 * @return void
 */
function executeShellCommand(string $cmd, bool $async = false): void
{
    $isWindows = mb_strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    if ($isWindows) {
        $command = $async ? "$cmd >NUL 2>NUL" : $cmd;
        pclose(popen('start /B cmd /C "' . $command . '"', 'r'));
    } else {
        $command = $async ? "$cmd > /dev/null 2>&1 &" : $cmd;
        exec($command);
    }
}
