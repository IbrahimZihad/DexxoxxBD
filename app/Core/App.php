<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Services\CartService;
use Throwable;

/**
 * Application kernel. Boot reads configuration, handle() serves one request, and
 * nothing else is allowed to touch globals.
 */
final class App
{
    private static ?self $instance = null;

    private string $basePath;

    private View $views;

    private Router $router;

    private Request $request;

    private string $cspNonce = '';

    /** @var array<string, mixed> */
    private array $viewGlobals = [];

    private bool $booted = false;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public static function instance(string $basePath = ''): self
    {
        if (self::$instance === null) {
            // Without an explicit path, two levels up from app/Core is the root.
            $fallback = $basePath !== '' ? $basePath : dirname(__DIR__, 2);
            self::$instance = new self($fallback);
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Env::load($this->basePath . '/.env');
        Config::load($this->basePath . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));

        $this->cspNonce = base64_encode(random_bytes(16));

        $this->views = new View($this->basePath . '/resources/views');
        $this->router = new Router();
        $this->request = Request::capture();

        $this->ensureStorage();
        $this->loadRoutes();
        self::registerFatalHandler();

        $this->booted = true;

        if (random_int(1, 40) === 7) {
            RateLimiter::garbageCollect();
        }
    }

    public function handle(): Response
    {
        $this->boot();

        // Behind a TLS-terminating proxy the app itself sees plain HTTP, so the
        // redirect is opt-in and never assumed.
        if ((bool) Config::get('app.force_https', false) && !$this->request->isSecure()) {
            return Response::redirect(
                'https://' . $this->request->host() . $this->request->basePath() . '/' . ltrim($this->request->path(), '/')
            );
        }

        Session::start($this->sessionCookiePath(), $this->request->isSecure());

        // Shared before dispatch: controllers render during dispatch and so does
        // the error page, so a view reading $authUser or $errors must not find
        // them missing.
        $this->shareViewGlobals();

        try {
            $response = $this->router->dispatch($this->request);
        } catch (Throwable $exception) {
            $response = ErrorHandler::toResponse($exception, $this->request);
        }

        return $response->withSecurityHeaders($this->cspNonce);
    }

    /** @return list<string> */
    /** @return list<string> */
    public function bootDiagnostics(): array
    {
        $problems = [];

        if (!Crypto::keyPresent()) {
            $problems[] = 'APP_KEY is empty. Credential notes will be stored unencrypted - run "composer key".';
        }

        if (!Database::isAvailable()) {
            $problems[] = 'Cannot reach MySQL. Check DB_* in .env and that the database exists.';
        }

        return $problems;
    }

    public function view(): View
    {
        return $this->views;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function replaceRequest(Request $request): void
    {
        $this->request = $request;
    }

    public static function cspNonce(): string
    {
        return self::instance()->cspNonce;
    }

    public function basePath(string $append = ''): string
    {
        return $this->basePath . ($append === '' ? '' : '/' . trim($append, '/\\'));
    }

    public function storagePath(string $append = ''): string
    {
        return $this->basePath('storage' . ($append === '' ? '' : '/' . trim($append, '/\\')));
    }

    public function publicPath(string $append = ''): string
    {
        return $this->basePath('public' . ($append === '' ? '' : '/' . trim($append, '/\\')));
    }

    private function sessionCookiePath(): string
    {
        $base = $this->request->basePath();

        return $base === '' ? '/' : $base;
    }

    private function ensureStorage(): void
    {
        foreach (['logs', 'cache', 'rate', 'backups', 'keys'] as $directory) {
            $path = $this->storagePath($directory);

            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }

    private function loadRoutes(): void
    {
        foreach (['routes/web.php', 'routes/api.php'] as $file) {
            $path = $this->basePath($file);

            if (is_file($path)) {
                $router = $this->router;
                require $path;
            }
        }
    }

    private function shareViewGlobals(): void
    {
        $errors = Session::get('_errors', []);
        Session::forget('_errors');

        $this->viewGlobals = [
            'request' => $this->request,
            'authUser' => Auth::user(),
            'errors' => is_array($errors) ? $errors : [],
            'old' => Session::pullOld(),
            'flash' => Session::pullFlash(),
            'cspNonce' => $this->cspNonce,
            'cartCount' => CartService::count(),
            'currentPath' => $this->request->path(),
        ];

        foreach ($this->viewGlobals as $key => $value) {
            $this->views->share($key, $value);
        }
    }

    /** @return array<string, mixed> */
    public function viewGlobals(): array
    {
        return $this->viewGlobals;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }

    /** Register a shutdown handler so a fatal error still produces a real page. */
    public static function registerFatalHandler(): void
    {
        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            try {
                Logger::error('fatal', [
                    'message' => $error['message'],
                    'at' => $error['file'] . ':' . $error['line'],
                ]);
            } catch (Throwable) {
                // Logging must never be the reason nothing is printed.
            }

            if (!headers_sent()) {
                http_response_code(500);
            }

            if (Config::get('app.debug', false)) {
                echo '<pre style="font:13px/1.5 ui-monospace,monospace;padding:1rem;background:#0b1220;color:#fecaca">'
                    . e($error['message'] . "\n" . $error['file'] . ':' . $error['line'])
                    . '</pre>';
            }
        });
    }
}
