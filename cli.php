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

require_once __DIR__ . '/ext/utils.php';
class Tiny
{
    use TinyUtils;
}

if (PHP_SAPI === 'cli' && str_ends_with(__FILE__, $argv[0])) {

    if ($argc < 2) {
        echo "Usage: php tiny/cli.php [category] [?options]\n";
        exit(1);
    }

    $category = $argv[1];
    if ($category === 'create') {
        $path = str_replace('tiny/tiny', 'tiny', __DIR__ . '/tiny');
        foreach (['.git', '.github'] as $dir) {
            print_r($path . '/' . $dir);
            tiny::rrmdir($path . '/' . $dir);
        }
        foreach (['.git', '.gitignore', 'GitVersion.yml'] as $file) {
            unlink($path . '/' . $file);
        }
        $zip = new ZipArchive();
        if ($zip->open($path . '/sample-project.zip') === TRUE) {
            $zip->extractTo(__DIR__ . '/../');
            unlink($path . '/sample-project.zip');
            $zip->close();
        }
        echo "[✓] Project created successfully 🎉\n\nPlease run `composer install` to complete the setup.\n";
        exit(0);
    }

    if ($category === 'migrations') {
        require_once __DIR__ . '/ext/migration.php';

        $migration = new Migration();
        if ($argc < 3) {
            echo "Usage: php migration.php [create|up|down|remove] [name]\n";
            exit(1);
        }
        $command = $argv[2];
        switch ($command) {
            case 'create':
                if ($argc < 4) {
                    echo "Please provide a name for the migration.\n";
                    exit(1);
                }
                $migration->create($argv[3]);
                break;
            case 'up':
                $migration->up();
                break;
            case 'down':
                $migration->down();
                break;
            case 'remove':
                if ($argc < 4) {
                    echo "Please provide a name for the migration to remove.\n";
                    exit(1);
                }
                $migration->remove($argv[3]);
                break;
            default:
                echo "Invalid command. Use 'create', 'up', 'down', or 'remove'.\n";
                exit(1);
        }
    }
}
