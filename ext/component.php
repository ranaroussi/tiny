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


/**
 * Suppresses undefined variable and array key errors.
 *
 * @param bool $suppress Whether to suppress the errors or not
 */
function suppress_undefined_error($suppress = true): void
{
    set_error_handler(function (int $errno, string $errstr) use ($suppress) {
        if ((!str_contains($errstr, 'Undefined array key')) && (!str_contains($errstr, 'Undefined variable'))) {
            return !$suppress;
        } else {
            return (bool)$suppress;
        }
    }, E_WARNING);
}

class Component
{
    private array $components;

    /**
     * Constructor for the Component class.
     *
     * @param string $path The base path for component files
     */
    public function __construct(private string $path = './views/components/')
    {
        $this->components = [];
        $this->path = rtrim($path, '/');
    }

    /**
     * Requires one or more component files.
     *
     * @param string|array $comp The component(s) to require
     */
    public function require(string|array $comp): void
    {
        if (is_array($comp)) {
            foreach ($comp as $c) {
                $this->requireSingle($c);
            }
            return;
        }
        $this->requireSingle($comp);
    }

    /**
     * Requires a single component file.
     *
     * @param string $comp The component to require
     * @throws \RuntimeException if the component file is not found
     */
    private function requireSingle(string $comp): void
    {
        $filePath = "{$this->path}/{$comp}.php";
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Component file not found: {$filePath}");
        }
        include_once $filePath;
    }

    /**
     * Registers a component function.
     *
     * @param string $name The name of the component
     * @param callable $func The component function
     */
    public function register(string $name, callable $func): void
    {
        $this->components[$name] = $func;
    }

    /**
     * Renders a component and echoes the result.
     *
     * @param string $_func The name of the component to render
     * @param mixed ...$props Additional properties to pass to the component
     */
    public function render(string $_func, ...$props): void
    {
        echo $this->executeComponent($_func, ...$props);
    }

    /**
     * Renders a component and returns the result.
     *
     * @param string $_func The name of the component to render
     * @param mixed ...$props Additional properties to pass to the component
     * @return mixed The result of the component execution
     */
    public function return(string $_func, ...$props): mixed
    {
        return $this->executeComponent($_func, ...$props);
    }

    /**
     * Magic method to allow direct calling of components.
     *
     * @param string $name The name of the component to render
     * @param array $arguments Additional arguments to pass to the component
     */
    public function __call(string $name, array $arguments): void
    {
        echo $this->executeComponent($name, ...$arguments);
    }

    /**
     * Executes a component function.
     *
     * @param string $name The name of the component to execute
     * @param mixed ...$props Additional properties to pass to the component
     * @return mixed The result of the component execution
     * @throws \RuntimeException if the component is not registered
     */
    private function executeComponent(string $name, ...$props): mixed
    {
        if (!isset($this->components[$name])) {
            throw new \RuntimeException("Component not registered: {$name}");
        }

        suppress_undefined_error(true);
        $result = call_user_func($this->components[$name], ...$props);
        suppress_undefined_error(false);

        return $result;
    }
}

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
        suppress_undefined_error(true);

        $file = $this->determineFileToInclude($layout, $props);
        $this->_props = $props;

        $filePath = "{$this->path}/{$layout}/{$file}.php";
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Layout file not found: {$filePath}");
        }
        include $filePath;

        suppress_undefined_error(false);
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
