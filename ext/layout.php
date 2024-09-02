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

class Layout
{
    private array $layoutProps = [];
    private array $_props = [];

    /**
     * Constructor for the Layout class.
     *
     * @param string $path The base path for layout files
     */
    public function __construct(private string $path = './views/layouts/')
    {
        $this->path = rtrim($path, '/');
    }

    /**
     * Magic method to handle layout rendering.
     *
     * @param string $layout The name of the layout to render
     * @param array $props Additional properties to pass to the layout
     * @throws \RuntimeException if the layout file is not found
     */
    public function __call(string $layout, array $props): void
    {
        tiny::suppressUndefinedError(true);

        $file = $this->determineFileToInclude($layout, $props);
        $this->_props = $props;

        $filePath = "{$this->path}/{$layout}/{$file}.php";
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Layout file not found: {$filePath}");
        }
        include $filePath;

        tiny::suppressUndefinedError(false);
    }

    /**
     * Determines which file to include for a layout.
     *
     * @param string $layout The name of the layout
     * @param array &$props Reference to the properties array
     * @return string The file to include ('open' or 'close')
     */
    private function determineFileToInclude(string $layout, array &$props): string
    {
        if (!isset($this->layoutProps[$layout])) {
            $this->layoutProps[$layout] = $props;
            return 'open';
        } else {
            $props = array_merge($this->layoutProps[$layout], $props);
            unset($this->layoutProps[$layout]);
            return 'close';
        }
    }

    /**
     * Retrieves a property value for the current layout.
     *
     * @param string $prop The name of the property to retrieve
     * @param mixed $fallback The fallback value if the property is not set
     * @return mixed The property value or fallback
     */
    public function props(string $prop, mixed $fallback = ''): mixed
    {
        return $this->_props[$prop] ?? $fallback;
    }
}

// define('Component', new Component());
// define('Layout', new Layout());
