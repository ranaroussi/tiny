<?php
declare(strict_types=1);
/**
 * FrankenPHP worker entry for Tiny.
 * This keeps the app booted in memory and handles each request in a clean sandbox.
 */

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional interface for request-scoped caches you may want to clear each request.
 */
interface ResetsBetweenRequests { public function reset(): void; }

final class ResetRegistry {
    /** @var ResetsBetweenRequests[] */
    private array $items = [];
    public function add(ResetsBetweenRequests $r): void { $this->items[] = $r; }
    public function resetAll(): void { foreach ($this->items as $r) { $r->reset(); } }
}

return static function (array $context): callable {
    // Boot Tiny once and keep it warm
    $app = Tiny\App::boot(dirname(__DIR__));

    // Register any components that hold request-scoped state (if any)
    $resets = new ResetRegistry();
    // $resets->add($app->router());
    // $resets->add($app->view());

    return static function () use ($app, $resets): Response {
        // Start from a clean slate
        header_remove();
        while (ob_get_level() > 0) { @ob_end_clean(); }
        set_error_handler(null);
        set_exception_handler(null);

        $req = Request::createFromGlobals();

        try {
            $result = $app->handle($req);
            return $result instanceof Response
                ? $result
                : new Response((string) $result, 200, ['Content-Type' => 'text/html']);
        } finally {
            // Sessions
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }

            // Add DB/Redis cleanups here if you keep global handles:
            // if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // if (isset($redis) && method_exists($redis, 'discard')) { $redis->discard(); }

            // Clear request caches
            $resets->resetAll();

            // If you touched superglobals directly and mutated them, you can zero them:
            $_GET = $_POST = $_COOKIE = $_FILES = $_REQUEST = [];

            while (ob_get_level() > 0) { @ob_end_clean(); }
            if (function_exists('gc_collect_cycles')) { gc_collect_cycles(); }
        }
    };
};
