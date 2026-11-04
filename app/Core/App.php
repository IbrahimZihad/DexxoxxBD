<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Middleware;
use App\Services\CacheService;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\MailService;
use App\Services\RbacService;
use App\Services\SettingService;
use App\Services\Translator;
use Closure;
use Throwable;

/**
 * Application container and HTTP kernel.
 */
final class App
{
    private static ?self $instance = null;

    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    private Router $router;

    private Request $request;

    private ViewFactory $views;

    private Translator $translator;

    private string $cspNonce = '';

    private bool $booted = false;

    private function __construct(private string $basePath)
    {
        self::$instance = $this;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new HttpException(500, 'Application has not been bootstrapped.');
        }
        return self::$instance;
    }

    public static function bootstrap(?string $basePath = null): self
    {
        $app = new self($basePath ?? dirname(__DIR__, 2));
        $app->boot();
        return $app;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Env::load($this->basePath . '/.env');
        Config::load($this->basePath . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));
        mb_internal_encoding('UTF-8');
        setlocale(LC_ALL, 'en_US.UTF-8');

        $this->cspNonce = bin2hex(random_bytes(16));

        $this->views = new ViewFactory(Config::get('app.view_paths.main', $this->basePath . '/resources/views'));
        $this->views->addNamespace('admin', $this->basePath . '/resources/views/admin');
        $this->translator = new Translator(
            $this->basePath . '/resources/lang',
            (string) Config::get('app.locale', 'en'),
            (string) Config::get('app.fallback_locale', 'en')
        );

        $this->router = new Router();
        $this->request = new Request();

        $this->registerMiddleware();
        $this->registerServices();

        $this->loadRoutes();

        $this->booted = true;
    }

    private function registerServices(): void
    {
        $this->singleton('database', static fn (): Database => Database::connection());
        $this->singleton('cache', static fn (): CacheService => new CacheService());
        $this->singleton('rbac', static fn (): RbacService => new RbacService());
        $this->singleton('settings', static fn (): SettingService => new SettingService());
        $this->singleton('cart', static fn (): CartService => new CartService());
        $this->singleton('coupon', static fn (): CouponService => new CouponService());
        $this->singleton('mailer', static fn (): MailService => new MailService());
    }

    private function registerMiddleware(): void
    {
        $this->router->aliasMiddleware('web', Middleware\WebMiddleware::handle());
        $this->router->aliasMiddleware('auth', Middleware\AuthMiddleware::handle());
        $this->router->aliasMiddleware('guest', Middleware\GuestMiddleware::handle());
        $this->router->aliasMiddleware('admin', Middleware\AdminMiddleware::handle());
        $this->router->aliasMiddleware('permission', Middleware\PermissionMiddleware::handle());
        $this->router->aliasMiddleware('csrf', Middleware\CsrfMiddleware::handle());
        $this->router->aliasMiddleware('throttle', Middleware\ThrottleMiddleware::handle());
        $this->router->aliasMiddleware('locale', Middleware\LocaleMiddleware::handle());
        $this->router->aliasMiddleware('json', Middleware\JsonMiddleware::handle());
        $this->router->aliasMiddleware('verified', Middleware\VerifiedMiddleware::handle());
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        $web = $this->basePath . '/routes/web.php';
        $api = $this->basePath . '/routes/api.php';

        if (is_file($web)) {
            require $web;
        }
        if (is_file($api)) {
            require $api;
        }
    }

    /** Bind a factory, resolved once per application lifetime. */
    public function singleton(string $key, Closure $factory): void
    {
        $this->bindings[$key] = $factory;
    }

    public function bind(string $key, Closure $factory): void
    {
        $this->bindings[$key] = $factory;
        unset($this->instances[$key]);
    }

    public function setInstance(string $key, mixed $value): void
    {
        $this->instances[$key] = $value;
    }

    /** Resolve a binding; singleton bindings are memoised, bind() is not. */
    public function make(string $key): mixed
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        if (!isset($this->bindings[$key])) {
            throw new HttpException(500, 'No binding registered for: ' . $key);
        }

        $resolved = ($this->bindings[$key])($this);
        $this->instances[$key] = $resolved;

        return $resolved;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function views(): ViewFactory
    {
        return $this->views;
    }

    public function viewFactory(): ViewFactory
    {
        return $this->views;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function replaceRequest(Request $request): void
    {
        $this->request = $request;
    }

    public static function translator(): Translator
    {
        return self::instance()->translator;
    }

    public static function auth(): Auth
    {
        return Auth::instance();
    }

    public static function cart(): CartService
    {
        /** @var CartService $cart */
        $cart = self::instance()->make('cart');
        return $cart;
    }

    public static function settings(): array
    {
        /** @var SettingService $service */
        $service = self::instance()->make('settings');
        return $service->all();
    }

    public static function cache(): CacheService
    {
        /** @var CacheService $cache */
        $cache = self::instance()->make('cache');
        return $cache;
    }

    public static function mailer(): MailService
    {
        /** @var MailService $mailer */
        $mailer = self::instance()->make('mailer');
        return $mailer;
    }

    public static function rbac(): RbacService
    {
        /** @var RbacService $rbac */
        $rbac = self::instance()->make('rbac');
        return $rbac;
    }

    public static function coupon(): CouponService
    {
        /** @var CouponService $coupon */
        $coupon = self::instance()->make('coupon');
        return $coupon;
    }

    public static function cspNonce(): string
    {
        return self::instance()->cspNonce;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /**
     * Handle the current request end to end and return a response.
     */
    public function handle(): Response
    {
        // Production redirect before anything else can leak over plaintext.
        if (Config::get('security.force_https', false) && $this->request->scheme() === 'http') {
            $target = 'https://' . $this->request->host() . '/' . ltrim($this->request->path(), '/');
            $query = (string) parse_url($this->request->uri(), PHP_URL_QUERY);
            if ($query !== '') {
                $target .= '?' . $query;
            }
            return Response::redirect($target, 301)->withSecurityHeaders($this->cspNonce);
        }

        Session::start();

        try {
            $this->translator->setLocale(LocaleResolver::resolve($this->request));
            $response = $this->router->dispatch($this->request);
        } catch (Throwable $exception) {
            $response = ErrorHandler::toResponse($exception, $this->request);
        }

        $this->shareViewGlobals();

        return $response->withSecurityHeaders($this->cspNonce);
    }

    private function shareViewGlobals(): void
    {
        $this->views->share('app', $this);
        $this->views->share('request', $this->request);
        $this->views->share('locale', $this->translator->locale());
        $this->views->share('errors', Session::errorsFor());
        $this->views->share('messages', Session::takeMessages());
        $this->views->share('authUser', Auth::instance()->user());
    }

    public function run(): void
    {
        $this->handle()->send();
    }
}
