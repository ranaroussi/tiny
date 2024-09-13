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


class TinyComponent
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

        tiny::suppressUndefinedError(true);
        $result = call_user_func($this->components[$name], ...$props);
        tiny::suppressUndefinedError(false);

        return $result;
    }
}
