# NikanzoPHP — Developer Guide

## Table of Contents

1. [Project Setup](#1-project-setup)
2. [Directory Structure](#2-directory-structure)
3. [Configuration & .env](#3-configuration--env)
4. [Routing](#4-routing)
5. [Controllers](#5-controllers)
6. [Middleware](#6-middleware)
7. [Dependency Injection](#7-dependency-injection)
8. [Database](#8-database)
9. [Caching (PSR-16)](#9-caching-psr-16)
10. [Events (PSR-14)](#10-events-psr-14)
11. [Security](#11-security)
12. [Templating](#12-templating)
13. [Logging (PSR-3)](#13-logging-psr-3)
14. [CLI Commands](#14-cli-commands)
15. [Testing](#15-testing)
16. [Modules](#16-modules)
17. [Performance Tuning](#17-performance-tuning)

---

## 1. Project Setup

```bash
git clone https://github.com/digi-soft-ug/NikanzoPHP.git
cd NikanzoPHP
composer install
cp .env.example .env
php -S 127.0.0.1:8000 -t public public/index.php
```

---

## 2. Directory Structure

```
NikanzoPHP/
├── bootstrap.php              # Container, router, module wiring
├── public/index.php           # Front controller
├── config/                    # Static config files (database, view, cli)
├── database/
│   ├── migrations/            # Timestamped migration files
│   └── seeds/                 # Seeder files
├── src/
│   ├── Application/           # Your HTTP controllers
│   ├── Core/
│   │   ├── Attributes/        # #[Route], #[Inject], #[Service], #[Singleton], #[RequiredScope]
│   │   ├── Cache/             # PSR-16 FileCache + InvalidArgumentException
│   │   ├── Console/Command/   # CLI commands (DbMigrate, DbSeed, MakeController, …)
│   │   ├── Container/         # PSR-11 Container + NotFoundException + ContainerException
│   │   ├── Controller/        # AbstractController base class
│   │   ├── Database/          # ConnectionFactory, QueryBuilder, MigrationRunner, SeederRunner
│   │   ├── Events/            # PSR-14 EventDispatcher + ListenerProvider
│   │   ├── Hooks/             # Legacy HookDispatcher (kept for BC)
│   │   ├── Http/              # HttpBridge, RouteExtractor
│   │   ├── Logging/           # LoggerFactory (Monolog)
│   │   ├── Middleware/        # All PSR-15 middleware
│   │   ├── Security/          # CsrfTokenManager
│   │   ├── Support/           # Paginator
│   │   ├── Template/          # TemplateRenderer (Twig)
│   │   ├── Testing/           # TestClient
│   │   ├── FastRouter.php
│   │   ├── Kernel.php
│   │   ├── ModuleLoader.php
│   │   ├── Router.php
│   │   └── RouterInterface.php
│   ├── Domain/                # Domain models / value objects
│   └── Modules/               # Optional feature modules
├── templates/                 # Twig templates
├── tests/                     # PHPUnit test cases
└── var/
    ├── cache/                 # Route cache, Twig cache, PSR-16 file cache
    └── log/                   # Application logs
```

---

## 3. Configuration & .env

Copy `.env.example` to `.env` and fill in values. The framework loads it automatically via `vlucas/phpdotenv` at bootstrap time.

All env vars are prefixed `NIKANZO_` except `APP_ENV` and `APP_DEBUG`. See README for the full table.

Static config files in `config/` are loaded by CLI commands and legacy code. New code should prefer env vars.

---

## 4. Routing

### Defining routes

Routes are declared with the `#[Route]` attribute on controller methods:

```php
#[Route('/articles/{slug:[a-z0-9-]+}', methods: ['GET'])]
public function show(ServerRequestInterface $request, string $slug): ResponseInterface
```

**Path parameters:**
- `{id}` — matches any non-slash characters: `[^/]+`
- `{slug:[a-z0-9-]+}` — custom regex constraint
- Parameters are injected as typed method arguments **and** as `$request->getAttribute('slug')`

### Registering controllers

```php
// bootstrap.php
$container->register(ArticleController::class);
$router->registerController(ArticleController::class);
```

### API versioning prefix

```php
// Use a versioned router
$router = new Router('/api/v1');
// Now #[Route('/users')] becomes /api/v1/users
```

### Route caching (production)

```bash
NIKANZO_FAST_ROUTER=1 php nikan route:cache
```

This writes `var/cache/routes.php`. Enable `NIKANZO_FAST_ROUTER=1` in production `.env`.

---

## 5. Controllers

Extend `AbstractController` for convenience helpers, or stay plain for maximum simplicity:

```php
final class ArticleController extends AbstractController
{
    public function __construct(private readonly PDO $db) {}

    #[Route('/articles', methods: ['GET'])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $items = (new QueryBuilder($this->db, 'articles'))->orderBy('created_at', 'DESC')->get();
        return $this->json($items);
    }
}
```

Available helpers: `json()`, `render()`, `text()`, `redirect()`, `noContent()`, `error()`, `created()`.

---

## 6. Middleware

All middleware implements PSR-15 `MiddlewareInterface`. Add to the kernel in order (outermost first):

```php
$kernel->addMiddleware(new SecurityHeadersMiddleware());          // always first
$kernel->addMiddleware(new ErrorHandlerMiddleware(debug: $debug, logger: $logger));
$kernel->addMiddleware(new RequestBodyParserMiddleware());        // parse JSON body
$kernel->addMiddleware(new ContentNegotiationMiddleware());       // set accept.format attribute
$kernel->addMiddleware(new JwtAuthMiddleware());                  // validate Bearer token
$kernel->addMiddleware(new RateLimitMiddleware(60, 60));         // 60 req/min per IP+path
// For HTML forms only:
$kernel->addMiddleware(new CsrfMiddleware(new CsrfTokenManager()));
```

### Built-in middleware

| Class | Purpose |
|---|---|
| `SecurityHeadersMiddleware` | CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| `ErrorHandlerMiddleware` | Catch all exceptions, log them, return JSON 500 |
| `RequestBodyParserMiddleware` | Parse `application/json` body into `getParsedBody()` |
| `ContentNegotiationMiddleware` | Set `accept.format` attribute (`json`/`html`/`text`) |
| `JwtAuthMiddleware` | Validate Bearer JWT, set `auth.claims` attribute |
| `AuthMiddleware` | Simple Bearer token presence check (no validation) |
| `CsrfMiddleware` | Validate CSRF token for POST/PUT/PATCH/DELETE |
| `RateLimitMiddleware` | Sliding-window rate limit per IP+path, adds `X-RateLimit-*` headers |

### Writing custom middleware

```php
final class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('Access-Control-Allow-Origin', '*');
    }
}
```

---

## 7. Dependency Injection

The DI container is built on Symfony DependencyInjection with a custom attribute layer.

### Attributes

| Attribute | Target | Description |
|---|---|---|
| `#[Singleton]` | class | Shared instance (equivalent to `shared: true`) |
| `#[Service(lazy, public, shared)]` | class | Fine-grained control |
| `#[Inject]` | property / parameter | Explicit injection by type or named service ID |
| `#[RequiredScope('scope')]` | method / class | OAuth scope enforcement |

### Manual registration

```php
$container->register(MyService::class);
$service = $container->get(MyService::class);
```

Auto-wiring resolves constructor dependencies by type. Use `#[Inject('service.id')]` for interface bindings.

---

## 8. Database

### Connection

```php
use Nikanzo\Core\Database\ConnectionFactory;

$pdo = ConnectionFactory::make(require __DIR__ . '/config/database.php');
```

### QueryBuilder

All methods return `$this` for chaining. Values are always bound via prepared statements.

```php
$qb = new QueryBuilder($pdo, 'users');

// SELECT
$qb->select('id', 'name')->where('active', 1)->orderBy('name')->limit(10)->get();
$qb->find(42);               // find by primary key (id)
$qb->first();                // first result
$qb->count();                // COUNT(*)

// WHERE variants
$qb->where('age', 18, '>=');
$qb->orWhere('role', 'admin');
$qb->whereIn('status', ['active', 'pending']);
$qb->whereNull('deleted_at');
$qb->whereNotNull('email_verified_at');

// WRITE
$qb->insert(['name' => 'Alice', 'email' => 'alice@example.com']);  // returns lastInsertId
$qb->where('id', 1)->update(['name' => 'Alicia']);                 // returns rowCount
$qb->where('id', 1)->delete();                                      // returns rowCount

// RAW (use sparingly)
$qb->raw('SELECT COUNT(*) AS c FROM users WHERE created_at > :d', [':d' => '2025-01-01']);
```

### Migrations

Files in `database/migrations/` named `YYYYMMDDHHMMSS_description.php`. Each returns an anonymous class implementing `MigrationInterface`.

```php
// database/migrations/20260101000000_add_slug_to_articles.php
return new class implements \Nikanzo\Core\Database\MigrationInterface {
    public function up(\PDO $pdo): void {
        $pdo->exec("ALTER TABLE articles ADD COLUMN slug TEXT");
    }
    public function down(\PDO $pdo): void {
        // SQLite doesn't support DROP COLUMN easily — handle per driver
    }
};
```

---

## 9. Caching (PSR-16)

```php
use Nikanzo\Core\Cache\FileCache;

$cache = new FileCache('/path/to/cache', defaultTtl: 600);

$data = $cache->get('my.key', fn() => expensiveOperation()); // miss → compute
$cache->set('my.key', $data, 300);
$cache->has('my.key');
$cache->delete('my.key');
$cache->clear(); // delete all entries
```

For production, swap `FileCache` with an APCu or Redis adapter that also implements `Psr\SimpleCache\CacheInterface`.

---

## 10. Events (PSR-14)

```php
use Nikanzo\Core\Events\{EventDispatcher, ListenerProvider};

// Define an event
final class OrderPlaced
{
    public function __construct(public readonly int $orderId) {}
}

// Wire up
$provider = new ListenerProvider();
$provider->addListener(OrderPlaced::class, function (OrderPlaced $event): void {
    // notify warehouse...
});

$dispatcher = new EventDispatcher($provider);
$dispatcher->dispatch(new OrderPlaced(orderId: 42));
```

Listeners for parent classes and interfaces are also discovered automatically.

---

## 11. Security

### JWT

Set `NIKANZO_JWT_SECRET` (at least 32 random characters). Add `JwtAuthMiddleware` to the pipeline. Issue tokens externally; the middleware only validates them.

Claims are available as `$request->getAttribute('auth.claims')`.

### Scope guards

```php
#[RequiredScope('orders:write')]
public function createOrder(ServerRequestInterface $request): ResponseInterface { ... }
```

Returns 403 if the token's `scopes` claim is missing the required scope.

### CSRF

Only needed for HTML form endpoints. For pure JSON APIs, skip `CsrfMiddleware` and rely on `JwtAuthMiddleware` + `Origin` / `Content-Type` checks.

### Security Headers

`SecurityHeadersMiddleware` adds:
- `Content-Security-Policy` — strict `default-src 'self'`
- `Strict-Transport-Security` — 1-year HSTS with `includeSubDomains`
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` — camera/mic/geolocation blocked

Override any directive by passing an array to the constructor:

```php
new SecurityHeadersMiddleware(
    overrides: ['Content-Security-Policy' => "default-src 'self' cdn.example.com"],
    hsts: false  // disable for local HTTP dev
)
```

---

## 12. Templating

```php
use Nikanzo\Core\Template\TemplateRenderer;

$renderer = new TemplateRenderer(
    templatesPath: __DIR__ . '/templates',
    cachePath:     __DIR__ . '/var/cache/twig',
);

// In a controller that extends AbstractController:
return $this->render($renderer, 'home.html.twig', ['title' => 'Home']);
```

Twig auto-escaping is enabled by default — XSS safe.

---

## 13. Logging (PSR-3)

```php
use Nikanzo\Core\Logging\LoggerFactory;

$logger = LoggerFactory::create();  // reads NIKANZO_LOG_* from env

$logger->info('User logged in', ['user_id' => 42]);
$logger->error('Payment failed', ['exception' => $e]);
```

Inject `Psr\Log\LoggerInterface` anywhere via the DI container.

---

## 14. CLI Commands

```bash
php nikan make:controller Products/ProductController
php nikan make:usecase     PlaceOrder
php nikan db:migrate
php nikan db:seed
php nikan route:cache
php nikan list
```

Add custom commands in `src/Core/Console/Command/` by extending `Symfony\Component\Console\Command\Command` and registering in the CLI entrypoint (`nikan`).

---

## 15. Testing

```php
use Nikanzo\Core\Testing\TestClient;
use PHPUnit\Framework\TestCase;

final class ArticleControllerTest extends TestCase
{
    private TestClient $client;

    protected function setUp(): void
    {
        $bootstrap    = require __DIR__ . '/../bootstrap.php';
        $this->client = new TestClient($bootstrap['kernel']);
    }

    public function testListArticles(): void
    {
        $response = $this->client->get('/articles');
        $this->assertSame(200, $response->getStatusCode());
        $body = $this->client->json($response);
        $this->assertArrayHasKey('data', $body);
    }

    public function testCreateArticle(): void
    {
        $response = $this->client->post('/articles', ['title' => 'Hello']);
        $this->assertSame(201, $response->getStatusCode());
    }
}
```

Run tests: `vendor/bin/phpunit`

---

## 16. Modules

Create `src/Modules/<Name>/Module.php` implementing `ModuleInterface`:

```php
namespace Nikanzo\Modules\Blog;

use Nikanzo\Core\Container\Container;
use Nikanzo\Core\ModuleInterface;
use Nikanzo\Core\Router;

final class Module implements ModuleInterface
{
    public function register(Container $container, Router $router): void
    {
        $container->register(PostController::class);
        $router->registerController(PostController::class);
    }
}
```

The `ModuleLoader` auto-discovers and loads all modules from `src/Modules/`.

---

## 17. Performance Tuning

| Technique | How |
|---|---|
| Route cache | `NIKANZO_FAST_ROUTER=1` + `php nikan route:cache` |
| Twig cache | Set `cache_path` in `config/view.php` or pass to `TemplateRenderer` |
| OPcache | Enable `opcache.preload` with your bootstrap |
| Lazy services | `#[Service(lazy: true)]` — service instantiated on first use |
| PSR-16 cache | Cache expensive queries or API calls with `FileCache` or Redis adapter |
| No debug middleware | Set `APP_DEBUG=false` in production |
