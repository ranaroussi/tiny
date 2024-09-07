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

// Define constants once, outside of any function or method
define('INTERNAL_FUNCTIONS', array_merge(
    get_defined_functions()['internal'],
    ['dump', 'debug', 'dd', 'ddump', 'log', 'require', 'include', 'require_once', 'include_once']
));

trait TinyDebugger
{
    /**
     * Generates a trace array containing file, line, and function information.
     *
     * @return array Associative array with 'file', 'line', and 'function' keys.
     */
    private static function trace(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4);
        $caller = $backtrace[1]['file'] !== __FILE__ ? $backtrace[1] : ($backtrace[2] ?? $backtrace[1]);

        $path_prefix = ('/' . trim(tiny::config()->tiny_path, '/tiny')) ?? $_SERVER['DOCUMENT_ROOT'] ?? '';

        $func_caller = $backtrace[3] ?? $backtrace[2] ?? $backtrace[1];
        $function = '';
        if (isset($func_caller['function'])) {
            $function = $func_caller['function'];
            if (isset($func_caller['class'])) {
                $function = $func_caller['class'] . '::' . $function;
            }
        }

        return [
            'file' => str_replace($path_prefix, '', $caller['file'] ?? 'unknown'),
            'line' => $caller['line'] ?? 'unknown',
            'function' => !in_array($function, INTERNAL_FUNCTIONS) ? $function : '',
        ];
    }

    /**
     * Formats the debug output with HTML styling.
     *
     * @param array $trace The trace information.
     * @param string $content The content to be displayed.
     * @return string Formatted HTML string.
     */
    private static function formatOutput(array $trace, string $content): string
    {
        $content = trim($content);
        $commonStyles = 'font-smoothing:antialiased;color:#bbb;background-color:#1c1f23;z-index:2147483647;position:relative;';
        $divStyles = $commonStyles . 'font-family:system-ui,sans-serif;font-size:14px!important;margin:20px;padding:20px 20px 15px;border-radius:5px;position:relative;z-index:2147483647;';
        $preStyles = $commonStyles . 'border-radius:0;font-family:ui-monospace,monospace;font-size:13.5px!important;padding-left:20px;border-left:2px solid #666;margin:10px 0 10px 1px;';

        return "<div style='$divStyles'>"
            . '<span>' . date('[H:i:s] ')
            . "{$trace['file']}:{$trace['line']}</span>\n"
            . "<pre style='$preStyles'>"
            . ($trace['function'] ? "in: <strong style='color:#ef5a6f'>{$trace['function']}()</strong>\n" : '')
            . "\n$content</pre></div>";
    }

    /**
     * Internal method to handle both debug and dump functionality.
     *
     * @param string $which Determines whether to use 'debug' or 'dump' behavior.
     * @param mixed ...$vars Variables to be debugged or dumped.
     */
    private static function dump_debug($which, ...$vars): array
    {
        $trace = self::trace();
        $content = '';

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $file = file($backtrace['file']);
        $line = $file[$backtrace['line'] - 1];
        preg_match('/[dump|debug|dd|log]\s*\((.*?)\)/', $line, $matches);
        $argNames = $matches[1] ?? '';
        $argNames = array_map('trim', explode(',', $argNames));

        foreach ($vars as $index => $var) {
            $ix = $index + 1;
            $name = trim($argNames[$index] ?? "var{$ix}", '(');
            $type = gettype($var);
            ob_start();
            if ($type === 'string' && $name == "'$var'") {
                $content .= trim($var) . "\n";
            } else {
                $content .= "→ <strong style='color:#5767e7'>$name</strong> <span style='color:#c47622'>($type)</span>\n";
                $content .= ($which === 'debug') ? '  ' . str_replace("\n", "\n  ", print_r($var, true)) : var_dump($var, true);
            }
            $content .= ob_get_clean() . "\n";
        }

        return [$trace, trim(str_replace("}\nbool(true)", "}\n  ", $content))];
    }

    /**
     * Outputs debug information about variables.
     *
     * @param mixed ...$vars Variables to be debugged.
     */
    public static function debug(...$vars): void
    {
        [$trace, $content] = self::dump_debug('debug', ...$vars);
        echo self::formatOutput($trace, $content);
    }

    /**
     * Dumps variables and exits the script.
     *
     * @param mixed ...$vars Variables to be dumped.
     * @return never
     */
    public static function dd(...$vars): never
    {
        [$trace, $content] = self::dump_debug('debug', ...$vars);
        echo self::formatOutput($trace, $content);
        exit(1);
    }

    /**
     * Dumps detailed information about variables.
     *
     * @param mixed ...$vars Variables to be dumped.
     */
    public static function dump(...$vars): void
    {
        [$trace, $content] = self::dump_debug('dump', ...$vars);
        echo self::formatOutput($trace, $content);
    }

    /**
     * Dumps detailed information about variables and exits the script.
     *
     * @param mixed ...$vars Variables to be dumped.
     * @return never
     */
    public static function ddump(...$vars): never
    {
        [$trace, $content] = self::dump_debug('dump', ...$vars);
        echo self::formatOutput($trace, $content);
        exit(1);
    }

    /**
     * Logs variable information to a file.
     *
     * @param mixed ...$vars Variables to be logged.
     */
    public static function log(...$vars): void
    {
        $logFile = $_SERVER['LOG_FILE'] ?? '/tmp/tiny.log';
        [$trace, $content] = self::dump_debug('dump', ...$vars);

        $output = date('[Y-m-d H:i:s] ') .
            "{$trace['file']}:{$trace['line']}\n\n" .
            ($trace['function'] ? "in: {$trace['function']}()\n" : '')
            . "\n" . strip_tags($content);

        $output .= "\n\n" . str_repeat('-', 80) . "\n\n";
        file_put_contents($logFile, $output, FILE_APPEND);
    }
}
