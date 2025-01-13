<?php

declare(strict_types=1);


class TinySwoole
{
    private static ?self $instance = null;
    private $server;
    private $config;
    private $publicDir;

    private function __construct()
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for coroutines');
        }
        $this->config = [
            'host' => $_ENV['SWOOLE_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['SWOOLE_PORT'] ?? 9501,
            'worker_num' => $_ENV['SWOOLE_WORKERS'] ?? 4
        ];
        $this->publicDir = explode('/tiny', __DIR__)[0] . '/html';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function start(): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is not installed');
        }

        $this->server = new \Swoole\Http\Server(
            $this->config['host'],
            (int)$this->config['port']
        );

        $this->server->set([
            'worker_num' => $this->config['worker_num'],
            'enable_coroutine' => true,
            'max_request' => 10000,
            'max_conn' => 10000,
            'buffer_output_size' => 32 * 1024 * 1024,
            'document_root' => rtrim(__DIR__, '/ext'),
            'enable_static_handler' => true,
            // 'static_handler_locations' => ['/static/css'],
        ]);

        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('request', [$this, 'handleRequest']);

        $this->server->start();
    }

    public function onWorkerStart(\Swoole\Server $server, int $workerId): void
    {
        // TODO: Use redis for session storage
        // if (!apcu_enabled()) {
        //     apcu_enable();
        // }
    }

    public function handleRequest($request, $response): void
    {
        $response->header('Server', 'TinySwoole');
        $response->header('X-Powered-By', 'Tiny');

        $this->initializeGlobalsFromRequest($request);
        $this->startSession();

        $uri = $_SERVER['REQUEST_URI'];
        $filePath = $this->publicDir . $uri;

        // $response->end($filePath);
        if (file_exists($filePath) && is_file($filePath)) {
            $this->handleFileRequest($filePath, $request, $response);
        } else {
            $this->handleTinyRequest($request, $response);
        }

        $this->saveSession();
    }

    private function handleTinyRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void
    {
        ob_start();

        try {
            tiny::routerSetup();
            tiny::controller();
        } catch (ExitException $e) {
            // Normal exit, do nothing
        } catch (Throwable $e) {
            $response->status(500);
            error_log("Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $response->end('Internal Server Error');
            return;
        }

        $content = ob_get_clean();

        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            $response->gzip(1);
        }

        $response->end($content);
    }

    public function co(callable $callback)
    {
        return \Swoole\Coroutine::create($callback);
    }

    public function redirect($goto, $header = null)
    {
        $this->server->redirect($goto, $header);
        tiny::exit();
    }

    public function header($url, $value)
    {
        return \Swoole\Http\Response::header($url, $value);
    }

    private function initializeGlobalsFromRequest(\Swoole\Http\Request $request)
    {
        $_SERVER = [];
        foreach ($request->server as $key => $value) {
            $_SERVER[strtoupper($key)] = $value;
        }
        $_SERVER['HOMEPAGE'] = $_SERVER['HOMEPAGE'] ?? 'home';
        $_SERVER['USE_SWOOLE'] = true;

        $_REQUEST = [];
        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_FILES = $request->files ?? [];
        $_COOKIE = $request->cookie ?? [];

        $_REQUEST = array_merge($_GET, $_POST);

        foreach ($request->header as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        if (isset($request->header['host'])) {
            $_SERVER['HTTP_HOST'] = $request->header['host'];
        }

        if (isset($request->cookie)) {
            foreach ($request->cookie as $key => $value) {
                $_COOKIE[$key] = $value;
            }
        }
    }


    private function startSession(): void
    {
        if (!isset($_COOKIE['PHPSESSID'])) {
            $sessionId = bin2hex(random_bytes(16));
            setcookie('PHPSESSID', $sessionId, [
                'expires' => time() + 86400,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            $_COOKIE['PHPSESSID'] = $sessionId;
        }

        $sessionId = $_COOKIE['PHPSESSID'];
        $sessionData = apcu_fetch("session:$sessionId");
        $_SESSION = $sessionData ? unserialize($sessionData) : [];
    }

    private function saveSession(): void
    {
        if (isset($_COOKIE['PHPSESSID'])) {
            $sessionId = $_COOKIE['PHPSESSID'];
            apcu_store("session:$sessionId", serialize($_SESSION), 3600);
        }
    }

    private function handleFileRequest($filePath, \Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        $fileInfo = pathinfo($filePath);
        $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';

        match ($extension) {
            'css' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'text/css');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'js' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'application/javascript');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'jpg', 'jpeg' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'image/jpeg');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'png' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'image/png');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'gif' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'image/gif');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'svg' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'image/svg+xml');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'woff' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'font/woff');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'woff2' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'font/woff2');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'ttf' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'font/ttf');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            'otf' => (function () use ($filePath, $response) {
                $content = file_get_contents($filePath);
                $response->header('Content-Type', 'font/otf');
                $response->header('Content-Length', strlen($content));
                $response->end($content);
            })(),
            default => $this->handleTinyRequest($request, $response)
        };
    }
}

class ExitException extends Exception
{
    public function __construct($message = "Stopping coroutine", $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
