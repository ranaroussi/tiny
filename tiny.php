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

/* -------------------------------------- */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ext/utils.php';
/* -------------------------------------- */
session_name('tiny');
session_start();
/* -------------------------------------- */


class tiny
{
    use TinyUtils;

    private static object $config;
    private static ?TinyCache $cache = null;
    private static ?DB $db = null;
    private static object $router;

    /**
     * Initializes the Tiny framework.
     * Sets up configuration, database, router, and loads helpers and middleware.
     */
    public static function init(): void
    {
        self::timer();

        if (isset(self::$config->initialized)) {
            return;
        }

        self::$router = new stdClass();

        self::$config = new stdClass();
        self::$config->initialized = true;

        // Load configuration
        self::loadConfig();

        // Initialize database
        self::initDB();

        // Clean input
        $_GET = self::cleanObjectTypes($_GET);
        $_POST = self::cleanObjectTypes($_POST);

        // Setup router
        self::routerSetup();

        // Load helpers and middleware
        self::loadHelpers();
        self::loadMiddleware();

        // Setup components
        self::setupComponents();
    }

    /**
     * Loads the configuration for the Tiny framework.
     * Caches the configuration for improved performance.
     */
    private static function loadConfig(): void
    {
        $cacheKey = 'tiny_init_config';
        $cachedConfig = self::cache()->get($cacheKey);

        if ($cachedConfig === null) {
            self::$config->app_dir = $_SERVER['APP_DIR'] ?? 'app';
            self::$config->tiny_dir = $_SERVER['TINY_DIR'] ?? 'tiny';
            self::$config->homepage = $_SERVER['HOMEPAGE'] ?? 'home';
            self::$config->static_dir = $_SERVER['STATIC_DIR'] ?? 'static';

            $basePath = $_SERVER['APP_PATH'] ?? '/' . trim(dirname(__FILE__, 2), '/') . '/';
            self::$config->app_path = $_SERVER['APP_PATH'] ?? $basePath . self::$config->app_dir;
            self::$config->tiny_path = $_SERVER['TINY_PATH'] ?? $basePath . self::$config->tiny_dir;
            self::$config->url_path = $_SERVER['URL_PATH'] ?? str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
            self::$config->cookie_path = $_SERVER['COOKIE_PATH'] ?? str_replace('.php', '', self::$config->url_path);

            self::cache()->set($cacheKey, self::$config, 3600);
        } else {
            self::$config = $cachedConfig;
        }
    }

    /**
     * Retrieves the version of the Tiny framework.
     *
     * This method reads the version from a .version file in the framework's directory.
     * The version is cached after the first read to improve performance.
     *
     * @return string The version of the Tiny framework, or null if the version file is not found.
     */
    public static function version(): ?string
    {
        static $version = null;
        if ($version === null) {
            $versionFile = __DIR__ . '/.version';
            $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
        }
        return $version;
    }

    /**
     * Initializes the database connection based on the configuration.
     * Supports MySQL, PostgreSQL, and SQLite.
     */
    private static function initDB(): void
    {
        if (!isset($_SERVER['DB_TYPE']) || empty($_SERVER['DB_TYPE']) || isset(self::$db)) {
            return;
        }

        require_once __DIR__ . '/ext/database.php';

        $dbType = strtolower($_SERVER['DB_TYPE']);

        $dbConfig = [
            'host'     => $_SERVER['DB_HOST'] ?? 'localhost',
            'port'     => $_SERVER['DB_PORT'] ?? ($dbType === 'mysql' ? 3306 : 5432),
            'dbname'   => $_SERVER['DB_NAME'] ?? 'tiny',
            'user'     => $_SERVER['DB_USER'] ?? 'root',
            'password' => $_SERVER['DB_PASS'] ?? '',
        ];

        self::$db = match ($dbType) {
            'mysql'      => new TinyDB('mysql', $dbConfig),
            'pgsql'      => new TinyDB('pgsql', $dbConfig),
            'postgresql' => new TinyDB('postgresql', $dbConfig),
            'sqlite'     => new TinyDB('sqlite', [
                'db_path'   => $_SERVER['DB_SQLITE_FILE'] ?? self::$config->app_path . '/database.db',
                'db_scheme' => $_SERVER['DB_SQLITE_SCHEMA'] ?? null
            ]),
            default => throw new \RuntimeException("Unsupported database type: $dbType"),
        };
    }

    /**
     * Sets up the router for handling HTTP requests.
     * Parses the URL and determines the appropriate controller and action.
     */
    private static function routerSetup(): void
    {
        if (self::isCLI()) {
            return;
        }

        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $cacheKey = 'router_' . md5($url);

        self::$router = self::cache()->remember($cacheKey, 3600, function () use ($url) {
            $router = [
                'uri' => $url,
                'homepage' => self::$config->url_path,
                'controller' => self::$config->homepage,
                'section' => '',
                'slug' => '',
                'query' => [],
                'htmx' => isset($_SERVER['HTTP_HX_REQUEST']),
            ];

            foreach (@$_GET as $key => $value) {
                if ($value !== '') {
                    $router['query'][$key] = $value;
                }
            }

            // compensate for malform proxy requests
            if (empty($router['query'])) {
                $gets = explode('?', $_SERVER['REQUEST_URI']);
                if (isset($gets[1])) {
                    $gets = explode('&', $gets[1]);
                    if (count($gets)) {
                        foreach ($gets as $item) {
                            @list($k, $v) = explode('=', $item);
                            $router['query'][$k] = $v;
                        }
                    }
                    unset($router['query'][$router['root']]);
                }
            }

            if ($url !== '/') {
                $parts = explode('/', trim(str_replace(self::$config->url_path, '', $url), '/'), 3);
                $router['controller'] = $parts[0] ?: self::$config->homepage;
                $router['section'] = $parts[1] ?? '';
                $router['slug'] = $parts[2] ?? '';
            }

            $router['permalink'] = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            $router['path'] = self::resolveControllerPath($router);
            $router['worker'] = [$router['path']];

            return (object)$router;
        });
    }

    /**
     * Resolves the controller path based on the current request.
     *
     * @param array $router The router configuration
     * @return string The resolved controller path
     */
    private static function resolveControllerPath(array $router): string
    {
        $basePath = self::$config->app_path . '/controllers/';
        $hyphenSlug = str_replace('/', '-', $router['slug']);
        $paths = [
            "{$router['controller']}/{$router['section']}/{$router['slug']}",
            "{$router['controller']}/{$router['section']}/{$hyphenSlug}",
            "{$router['controller']}/{$router['section']}-{$router['slug']}",
            "{$router['controller']}/{$router['section']}-{$hyphenSlug}",
            "{$router['controller']}/{$router['section']}",
            "{$router['controller']}/index",
            $router['controller'],
        ];

        foreach ($paths as $path) {
            if (file_exists($basePath . $path . '.php')) {
                return $path;
            }
        }

        return '404';
    }

    /**
     * Loads helper functions based on configuration.
     */
    private static function loadHelpers(): void
    {
        // die('asd');
        $helpers = $_SERVER['AUTOLOAD_HELPERS'] ?? '';
        if ($helpers === '*') {
            self::requireAll('/helpers/');
        } elseif ($helpers !== '') {
            self::helpers(explode(',', str_replace(' ', '', $helpers)));
        }
        if (file_exists(self::$config->app_path . '/common.php')) {
            require_once self::$config->app_path . '/common.php';
        }
    }

    /**
     * Loads middleware for non-CLI requests.
     */
    private static function loadMiddleware(): void
    {
        if (PHP_SAPI !== 'cli') {
            self::requireAll('/middleware/');
        }
    }

    /**
     * Sets up components and layouts for views.
     */
    private static function setupComponents(): void
    {
        require_once __DIR__ . '/ext/component.php';
        define('Component', new Component(self::$config->app_path . '/views/components'));
        define('Layout', new Layout(self::$config->app_path . '/views/layouts'));
    }

    /**
     * Retrieves configuration values.
     *
     * @param string|null $key The configuration key to retrieve (optional)
     * @return mixed The configuration value or the entire configuration object
     */
    public static function config(string $key = null)
    {
        return $key === null ? self::$config : (self::$config[$key] ?? null);
    }

    /**
     * Returns the cache instance.
     *
     * @return TinyCache The cache instance
     */
    public static function cache(string $engine = 'apcu'): TinyCache
    {
        require_once __DIR__ . '/ext/cache.php';

        if (self::$cache === null) {
            $config = self::$config->memcached ?? [];
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? 11211;
            self::$cache = new TinyCache($engine, $host, $port);
        }
        return self::$cache;
    }

    /**
     * Returns the database instance.
     *
     * @return DB|null The database instance or null if not initialized
     */
    public static function db(): ?DB
    {
        return self::$db;
    }

    /**
     * Retrieves router information.
     *
     * @param string|null $key The router key to retrieve (optional)
     * @return mixed The router value or the entire router object
     */
    public static function router(string $key = null)
    {
        return $key === null ? self::$router : (self::$router->$key ?? null);
    }

    /**
     * Requires one or more PHP files.
     *
     * @param array|string $file_or_files The file(s) to require
     * @param string|null $basePath The base path for the files (optional)
     * @param bool|null $once Whether to use require_once (optional)
     */
    public static function require(array|string $file_or_files, ?string $basePath = null, ?bool $once = true): void
    {
        $files = is_array($file_or_files) ? $file_or_files : [$file_or_files];
        $basePath = $basePath ?: self::$config->app_path . '/';

        foreach ($files as $file) {
            $filePath = $basePath . str_replace('.php', '', $file) . '.php';
            if ($once) {
                require_once $filePath;
            } else {
                require $filePath;
            }
        }
    }

    /**
     * Requires all PHP files in a specified directory.
     *
     * @param string $path The directory path
     * @param bool|null $once Whether to use require_once (optional)
     */
    public static function requireAll(string $path, ?bool $once = true): void
    {
        $basePath = self::$config->app_path . $path;
        if ($handle = opendir($basePath)) {
            $filesToInclude = [];
            while (false !== ($file = readdir($handle))) {
                if (str_ends_with($file, '.php')) {
                    $filesToInclude[] = $file;
                }
            }
            closedir($handle);

            sort($filesToInclude);
            self::require($filesToInclude, $basePath, $once);
        }
    }

    /**
     * Loads helper functions.
     *
     * @param array|string $helper_or_helpers The helper(s) to load
     */
    public static function helpers(array|string $helper_or_helpers): void
    {
        $helpers = is_array($helper_or_helpers) ? $helper_or_helpers : [$helper_or_helpers];
        $helperFiles = array_map(fn($helper) => '/helpers/' . self::trim($helper), $helpers);
        self::require($helperFiles, __DIR__, true);
    }

    /**
     * Loads and executes a controller.
     *
     * @param string $file The controller file to load (optional)
     * @param bool $die Whether to exit after loading the controller (optional)
     */
    public static function controller(string $file = '', bool $die = false): void
    {
        self::sendContentTypeHeader('auto');
        $file = $file ?: end(self::$router->worker);
        $filePath = self::$config->app_path . '/controllers/' . $file . '.php';

        if (!file_exists($filePath)) {
            self::data()->error = "Controller for /$file cannot be found on the server";
            if (file_exists(self::$config->app_path . '/controllers/404.php')) {
                require_once self::$config->app_path . '/controllers/404.php';
            } else {
                die(self::data()->error);
            }
            exit;
        }

        if ($file !== end(self::$router->worker)) {
            self::$router->worker[] = $file;
        }

        require_once __DIR__ . '/ext/controller.php';
        require_once $filePath;

        try {
            $class = str_replace([' ', '-', '_', '.'], '', ucwords(str_replace('/', ' ', $file)));
            $class = preg_replace('/Index$/', '', $class);

            if (class_exists($class)) {
                $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'GET');
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    $instance->$method(self::request(), self::response());
                }
            }
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
        }

        if ($die) {
            exit;
        }
    }

    /**
     * Renders a view file.
     *
     * @param string $file The view file to render (optional)
     * @param bool $die Whether to exit after rendering the view (optional)
     */
    public static function render(string $file = '', bool $die = false): void
    {
        $file = $file ?: end(self::$router->worker);
        $filePath = self::$config->app_path . '/views/' . $file . '.php';
        if (!file_exists($filePath)) {
            self::data()->error = "View for /$file cannot be found on the server";
            if (file_exists(self::$config->app_path . '/views/404.php')) {
                self::render('404', true);
            } else {
                die(self::data()->error);
            }
        }

        if (count(self::$router->worker) > 1 && $file === end(self::$router->worker)) {
            array_pop(self::$router->worker);
        }

        require $filePath;
        if ($die) {
            self::timer(true);
            exit;
        }
    }

    /**
     * Includes a PHP file.
     *
     * @param string $file The file to include
     * @param bool $die Whether to exit after including the file (optional)
     */
    public static function include(string $file, bool $die = false): void
    {
        $filePath = self::$config->app_path . '/' . rtrim($file, '.php') . '.php';

        if (!file_exists($filePath)) {
            die("<code>ERROR: File /$filePath cannot be found on the server</code>");
        }

        require $filePath;
        if ($die) {
            exit;
        }
    }

    /**
     * Returns the response object for handling HTTP responses.
     *
     * @return TinyRequest The Request object
     */
    public static function request(): TinyRequest
    {
        require_once __DIR__ . '/ext/request.php';
        static $request = null;
        return $request ??= new TinyRequest();
    }

    /**
     * Returns the response object for handling HTTP responses.
     *
     * @return TinyResponse The response object
     */
    public static function response(): TinyResponse
    {
        require_once __DIR__ . '/ext/response.php';
        static $response = null;
        return $response ??= new TinyResponse();
    }

    /**
     * Returns the sse object for handling HTTP SSE.
     *
     * @return TinySSE The SSE object
     */
    public static function sse(): TinySSE
    {
        require_once __DIR__ . '/ext/sse.php';
        static $sse = null;
        return $sse ??= new TinySSE();
    }

    /**
     * Returns the scheduler object for handling scheduled tasks.
     *
     * @return TinyScheduler The scheduler object
     */
    public static function scheduler(): TinyScheduler
    {
        require_once __DIR__ . '/ext/scheduler.php';
        static $scheduler = null;
        return $scheduler ??= new TinyScheduler();
    }

    /**
     * Sets a value in the data object.
     *
     * @param string $key The key to set
     * @param mixed $value The value to set
     */
    public static function set(string $key, mixed $value): void
    {
        self::data()->$key = $value;
    }

    /**
     * Gets a value from the data object.
     *
     * @param string $key The key to retrieve
     * @return mixed The value associated with the key
     */
    public static function get(string $key): mixed
    {
        return self::data()->$key ?? null;
    }

    /**
     * Returns the data object for storing application-wide data.
     *
     * @return object The data object
     */
    public static function data(): object
    {
        static $data = null;
        return $data ??= new stdClass();
    }

    /**
     * Returns a model instance.
     *
     * @param string $model The name of the model
     * @return object The model instance
     */
    public static function model(string $model): object
    {
        require_once __DIR__ . '/ext/model.php';

        static $models = [];
        if (!isset($models[$model])) {
            require_once self::$config->app_path . '/models/' . $model . '.php';
            $models[$model] = new $model();
        }
        return $models[$model];
    }

    /**
     * Returns a cookie instance.
     *
     * @param string $name The name of the cookie (optional)
     * @param array $values The values for the cookie (optional)
     * @return TinyCookie The cookie instance
     */
    public static function cookie(string $name = 'default', array $values = []): TinyCookie
    {
        require_once __DIR__ . '/ext/cookie.php';
        return new TinyCookie($name, $values);
    }

    /**
     * Gets or sets the current user.
     *
     * @param null|object|array $user The user data to set (optional)
     * @return object The user object
     */
    public static function user(null|object|array $user = null): object
    {
        static $data = null;
        if ($data === null) {
            $data = new stdClass();
        }
        if ($user === null && isset(self::data()->user)) {
            return self::data()->user;
        }
        self::data()->user = json_decode(json_encode($user));
        return $data;
    }

    /**
     * Generates a home URL.
     *
     * @param string $path The path to append to the home URL (optional)
     * @param bool $full Whether to return a full URL (optional)
     * @param string $scheme The URL scheme to use (optional)
     * @return string The generated URL
     */
    public static function getHomeURL($path = '/', $full = false, $scheme = '')
    {
        if (strpos($path, 'http') === 0) {
            return $path;
        }

        if (!$full) {
            return str_replace('//', '/', tiny::config()->url_path . $path);
        }

        if ($scheme == '') {
            $scheme = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://');
        } else {
            $scheme = str_replace('/', '', str_replace(':', '', tiny::trim($scheme, '://'))) . '://';
        }
        $path = str_replace('//', '/', tiny::trim(tiny::config()->url_path, '/') . '/' . tiny::ltrim($path));
        return $scheme . $_SERVER['HTTP_HOST'] . '/' . tiny::ltrim($path, '/');
    }

    /**
     * Echoes a home URL.
     *
     * @param string $path The path to append to the home URL (optional)
     * @param bool $full Whether to echo a full URL (optional)
     * @param string $scheme The URL scheme to use (optional)
     */
    public static function homeURL($path = '/', $full = false, $scheme = '')
    {
        echo tiny::getHomeURL($path, $full, $scheme);
    }

    /**
     * Echoes a static URL.
     *
     * @param string $file The file path to append to the static URL (optional)
     * @param bool $full Whether to echo a full URL (optional)
     * @param string $scheme The URL scheme to use (optional)
     */
    public static function staticURL($file = '', $full = false, $scheme = '')
    {
        echo tiny::getHomeURL(tiny::config()->static_dir . '/' . ltrim($file, '/'), $full, $scheme);
    }

    /**
     * Generates a static URL.
     *
     * @param string $file The file path to append to the static URL (optional)
     * @param bool $full Whether to return a full URL (optional)
     * @param string $scheme The URL scheme to use (optional)
     * @return string The generated static URL
     */
    public static function getStaticURL($file = '', $full = false, $scheme = '')
    {
        return tiny::getHomeURL(tiny::config()->static_dir . '/' . ltrim($file, '/'), $full, $scheme);
    }
}

// Initialize Tiny
tiny::init();
