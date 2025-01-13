<?php

declare(strict_types=1);

class TinySwoole
{
    private static ?self $instance = null;
    private $server;
    private $config;

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
            'enable_coroutine' => true
        ]);

        $this->server->on('request', [$this, 'handleRequest']);
        $this->server->start();
    }

    public function handleRequest($request, $response): void
    {
        $_SERVER['REQUEST_METHOD'] = $request->server['request_method'];
        $_SERVER['REQUEST_URI'] = $request->server['request_uri'];
        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_FILES = $request->files ?? [];
        $_COOKIE = $request->cookie ?? [];

        ob_start();
        tiny::controller();
        $content = ob_get_clean();

        $response->end($content);
    }

    public function co(callable $callback)
    {
        return \Swoole\Coroutine::create($callback);
    }
}
