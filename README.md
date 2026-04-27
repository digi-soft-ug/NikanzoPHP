[![CI](https://github.com/digi-soft-ug/NikanzoPHP/actions/workflows/ci.yml/badge.svg)](https://github.com/digi-soft-ug/NikanzoPHP/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/digi-soft-ug/NikanzoPHP/branch/main/graph/badge.svg)](https://codecov.io/gh/digi-soft-ug/NikanzoPHP)
[![Packagist Downloads](https://img.shields.io/packagist/dt/digi-soft-ug/nikanzophp.svg)](https://packagist.org/packages/digi-soft-ug/nikanzophp)

# NikanzoPHP

A lean, fast, secure PHP 8.3 framework for REST APIs and web applications. Built on PSR-7/15 standards with zero magic, full type safety, and a composable middleware pipeline.

> **Target:** Faster boot time and smaller footprint than Symfony, with security-first defaults.

---

## Requirements

- PHP 8.3+
- Composer 2.x
- Extensions: `ext-pdo`, `ext-pdo_sqlite` (or pdo_mysql / pdo_pgsql)

---

## Installation

```bash
# As a standalone project
git clone https://github.com/digi-soft-ug/NikanzoPHP.git
cd NikanzoPHP
composer install
cp .env.example .env   # then edit .env

# As a Composer library in your project
composer require digi-soft-ug/nikanzophp
```

---

## Quickstart

```bash
# Start dev server
php -S 127.0.0.1:8000 -t public public/index.php

# Test it
curl http://127.0.0.1:8000/hello
```

---

## Core Concepts

### Attribute Routing

```php
use Nikanzo\Core\Attributes\Route;
use Nikanzo\Core\Controller\AbstractController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController extends AbstractController
{
    // GET /api/v1/users/{id}
    #[Route('/users/{id}', methods: ['GET'])]
    public function show(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->json(['id' => $id, 'name' => 'Alice']);
    }
}
```

Route params (`{id}`) are extracted automatically and injected both as method arguments and as request attributes.

### Middleware Pipeline (PSR-15)

```php
// bootstrap.php / index.php
$kernel->addMiddleware(new SecurityHeadersMiddleware());
$kernel->addMiddleware(new RequestBodyParserMiddleware());
$kernel->addMiddleware(new JwtAuthMiddleware());
$kernel->addMiddleware(new RateLimitMiddleware(limit: 60, intervalSeconds: 60));
$kernel->addMiddleware(new ErrorHandlerMiddleware(debug: true, logger: $logger));
```

Middleware runs outermost-first (LIFO stack). Add `SecurityHeadersMiddleware` first so headers are always applied.

### Dependency Injection

```php
use Nikanzo\Core\Attributes\Inject;
use Nikanzo\Core\Attributes\Service;
use Nikanzo\Core\Attributes\Singleton;

#[Singleton]
final class UserRepository
{
    public function __construct(private readonly PDO $db) {}
}

final class UserController extends AbstractController
{
    #[Inject]
    private UserRepository $repo;
}
```

### JWT Auth + Scope Guards

```php
use Nikanzo\Core\Attributes\RequiredScope;

final class AdminController extends AbstractController
{
    #[Route('/admin/users', methods: ['GET'])]
    #[RequiredScope('admin', 'users:read')]
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $claims = $request->getAttribute('auth.claims');
        return $this->json(['user' => $claims['sub']]);
    }
}
```

Set `NIKANZO_JWT_SECRET` in `.env`. Token claims are available as `$request->getAttribute('auth.claims')`.

---

## AbstractController helpers

| Method | Description |
|---|---|
| `json($data, $status)` | JSON response |
| `render($renderer, $tpl, $ctx)` | Twig HTML response |
| `text($body, $status)` | Plain-text response |
| `redirect($url, $status)` | Redirect response |
| `noContent()` | 204 No Content |
| `error($message, $status)` | JSON error response |
| `created($data, $location)` | 201 Created with Location header |

---

## Database

### QueryBuilder

```php
use Nikanzo\Core\Database\QueryBuilder;

$users = (new QueryBuilder($pdo, 'users'))
    ->select('id', 'name', 'email')
    ->where('active', 1)
    ->whereNotNull('email_verified_at')
    ->orderBy('name')
    ->limit(20)
    ->offset(0)
    ->get();

// Find by primary key
$user = (new QueryBuilder($pdo, 'users'))->find(42);

// Insert
$id = (new QueryBuilder($pdo, 'users'))->insert(['name' => 'Bob', 'email' => 'bob@example.com']);

// Update with WHERE
(new QueryBuilder($pdo, 'users'))->where('id', 42)->update(['name' => 'Robert']);

// Delete
(new QueryBuilder($pdo, 'users'))->where('id', 42)->delete();

// Count
$total = (new QueryBuilder($pdo, 'users'))->where('active', 1)->count();
```

All values go through PDO prepared statements — no raw interpolation.

### Migrations & Seeds

```bash
php nikan db:migrate   # runs database/migrations/*.php in timestamp order
php nikan db:seed      # runs database/seeds/*.php
```

```php
// database/migrations/20260101120000_create_posts_table.php
return new class implements \Nikanzo\Core\Database\MigrationInterface {
    public function up(\PDO $pdo): void {
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');
    }
    public function down(\PDO $pdo): void {
        $pdo->exec('DROP TABLE posts');
    }
};
```

---

## Caching (PSR-16)

```php
use Nikanzo\Core\Cache\FileCache;

$cache = new FileCache();          // uses NIKANZO_CACHE_PATH + NIKANZO_CACHE_TTL from .env

$cache->set('key', $value, 300);   // TTL in seconds
$value = $cache->get('key', null); // default if missing/expired
$cache->delete('key');
$cache->clear();
```

---

## Events (PSR-14)

```php
use Nikanzo\Core\Events\EventDispatcher;
use Nikanzo\Core\Events\ListenerProvider;

$provider = new ListenerProvider();
$provider->addListener(UserRegistered::class, function (UserRegistered $event): void {
    // send welcome email...
});

$dispatcher = new EventDispatcher($provider);
$dispatcher->dispatch(new UserRegistered($user));
```

---

## CSRF Protection

```php
// Add middleware (HTML forms only — skip for pure-API)
$kernel->addMiddleware(new CsrfMiddleware(new CsrfTokenManager()));

// In a controller, embed the token in a form
$token = $csrfManager->getToken();
// <input type="hidden" name="_csrf_token" value="<?= $token ?>">
// Or send via header: X-CSRF-Token: <token>
```

---

## Pagination

```php
use Nikanzo\Core\Support\Paginator;

$page      = (int) ($request->getQueryParams()['page'] ?? 1);
$total     = (new QueryBuilder($pdo, 'posts'))->count();
$paginator = new Paginator(total: $total, page: $page, perPage: 15);

$items = (new QueryBuilder($pdo, 'posts'))
    ->limit($paginator->perPage)
    ->offset($paginator->offset)
    ->get();

return $this->json(['data' => $items, 'pagination' => $paginator->toArray()]);
```

---

## Fast Route Cache

```bash
# Warm cache
NIKANZO_FAST_ROUTER=1 php nikan route:cache

# Enable at runtime
NIKANZO_FAST_ROUTER=1 php -S 127.0.0.1:8000 -t public
```

Routes are compiled to `var/cache/routes.php` — reflection is skipped on every request.

---

## CLI Commands

```bash
php nikan make:controller Api/UserController    # scaffold a controller
php nikan make:usecase    RegisterUser          # scaffold a use-case class
php nikan db:migrate                            # run pending migrations
php nikan db:seed                               # run seeders
php nikan route:cache                           # warm the route cache
php nikan list                                  # list all commands
```

---

## Testing

```php
use Nikanzo\Core\Testing\TestClient;

final class UserControllerTest extends TestCase
{
    private TestClient $client;

    protected function setUp(): void
    {
        $bootstrap    = require __DIR__ . '/../bootstrap.php';
        $this->client = new TestClient($bootstrap['kernel']);
    }

    public function testGetUser(): void
    {
        $response = $this->client->get('/users/1', headers: [
            'Authorization' => 'Bearer ' . $this->validToken(),
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $data = $this->client->json($response);
        $this->assertArrayHasKey('id', $data);
    }
}
```

---

## Docker

```bash
docker compose up --build          # start on http://localhost:8000
docker compose run --rm app php nikan db:migrate
docker compose run --rm app vendor/bin/phpunit
```

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `development` | `development` or `production` |
| `APP_DEBUG` | `true` | Show stack traces in error responses |
| `NIKANZO_DB_DRIVER` | `sqlite` | `sqlite`, `mysql`, `pgsql` |
| `NIKANZO_DB_DATABASE` | `database/database.sqlite` | DB name or file path |
| `NIKANZO_DB_HOST` | `127.0.0.1` | DB host (MySQL/PgSQL) |
| `NIKANZO_DB_PORT` | driver default | DB port |
| `NIKANZO_DB_USERNAME` | — | DB username |
| `NIKANZO_DB_PASSWORD` | — | DB password |
| `NIKANZO_FAST_ROUTER` | `0` | `1` to enable cached routing |
| `NIKANZO_JWT_SECRET` | — | HMAC-SHA256 secret for JWT signing |
| `NIKANZO_LOG_CHANNEL` | `app` | Monolog channel name |
| `NIKANZO_LOG_LEVEL` | `debug` | `debug`, `info`, `warning`, `error` |
| `NIKANZO_LOG_PATH` | `var/log/app.log` | Log file path |
| `NIKANZO_LOG_MAX_FILES` | `7` | Rotating log files to keep |
| `NIKANZO_CACHE_PATH` | `var/cache/data` | PSR-16 file cache directory |
| `NIKANZO_CACHE_TTL` | `3600` | Default cache TTL in seconds |

---

## License

MIT — see [LICENSE](LICENSE).
