# Feature Matrix — NikanzoPHP

## PSR Compliance

| Standard | Status | Notes |
|---|---|---|
| PSR-1 Basic Coding Standard | ✅ Full | `declare(strict_types=1)` everywhere |
| PSR-2/12 Coding Style | ✅ Full | Enforced by `php-cs-fixer` |
| PSR-3 Logger Interface | ✅ Full | `LoggerFactory` → Monolog; `NullLogger` fallback |
| PSR-4 Autoloading | ✅ Full | `Nikanzo\` → `src/` via Composer |
| PSR-6 Cache (full) | ⚠️ Partial | Use `FileCache` for PSR-16; PSR-6 not implemented |
| PSR-7 HTTP Messages | ✅ Full | `nyholm/psr7` throughout |
| PSR-11 Container | ✅ Full | `NotFoundException` + `ContainerException` implementing PSR interfaces |
| PSR-14 Event Dispatcher | ✅ Full | `EventDispatcher` + `ListenerProvider`; parent class & interface listener discovery |
| PSR-15 HTTP Middleware | ✅ Full | Pipeline in `Kernel`; 7 built-in middleware |
| PSR-16 Simple Cache | ✅ Full | `FileCache` implements `CacheInterface` |

---

## Framework Features

| Feature | Status | Details |
|---|---|---|
| Attribute routing `#[Route]` | ✅ | Path params `{id}`, custom regex `{slug:[a-z]+}` |
| API versioning prefix | ✅ | `new Router('/api/v1')` |
| Route caching (FastRouter) | ✅ | `NIKANZO_FAST_ROUTER=1` → `var/cache/routes.php` |
| Route parameter injection | ✅ | Auto-injected as method args + request attributes |
| PSR-15 middleware pipeline | ✅ | LIFO, correct short-circuit behavior |
| PSR-11 DI Container | ✅ | Auto-wiring, `#[Inject]`, `#[Singleton]`, `#[Service]` |
| Lazy service loading | ✅ | `#[Service(lazy: true)]` |
| PSR-14 Events | ✅ | Parent/interface listener discovery; stoppable events |
| PSR-16 File Cache | ✅ | SHA-256 keyed files, TTL, `DateInterval` support |
| PSR-3 Logging (Monolog) | ✅ | `LoggerFactory`, rotating file handler, env-configured |
| JWT Auth (firebase/php-jwt) | ✅ | HS256, named catch, env secret |
| Scope guards `#[RequiredScope]` | ✅ | Per-method or per-class |
| CSRF protection | ✅ | `CsrfMiddleware` + `CsrfTokenManager`; `X-CSRF-Token` header or form field |
| Security headers | ✅ | CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |
| Rate limiting | ✅ | Sliding window, `X-RateLimit-*` headers; per-instance (no static state) |
| Request body parser | ✅ | Auto-parse `application/json` → `getParsedBody()` |
| Content negotiation | ✅ | `accept.format` attribute (`json`/`html`/`text`/`xml`) |
| Fluent QueryBuilder | ✅ | PDO-backed, prepared statements only; `where`, `whereIn`, `whereNull`, `orderBy`, `limit`, `offset`, `count`, `insert`, `update`, `delete` |
| Database migrations | ✅ | Timestamped files, transaction-wrapped, all drivers |
| Database seeders | ✅ | Per-file seeders |
| Multi-driver DB | ✅ | SQLite (default), MySQL, PostgreSQL |
| Twig templating | ✅ | `TemplateRenderer`, auto-escape, file caching |
| AbstractController | ✅ | `json()`, `render()`, `text()`, `redirect()`, `noContent()`, `error()`, `created()` |
| Paginator | ✅ | Offset-based, `toArray()`, `links()` callback |
| `.env` support | ✅ | `vlucas/phpdotenv`, `safeLoad()` |
| HTTP TestClient | ✅ | Drives `Kernel` directly; `get()`, `post()`, `put()`, `patch()`, `delete()`, `json()` |
| Module system | ✅ | `ModuleLoader` auto-discovers `src/Modules/*/Module.php` |
| CLI scaffolding | ✅ | `make:controller`, `make:usecase`, `db:migrate`, `db:seed`, `route:cache` |
| Fiber async file reader | ✅ | `AsyncFileReader` (PHP 8.1+ Fibers) |
| Docker support | ✅ | `docker-compose.yml` + `Dockerfile` |
| GitHub Actions CI | ✅ | PHPUnit + Codecov |
| Static analysis | ✅ | PHPStan + Psalm |
| Code style | ✅ | `php-cs-fixer` |

---

## Light vs. Premium

| Feature | Open Source | Premium |
|---|---|---|
| All core features above | ✅ | ✅ |
| Basic middleware | ✅ | ✅ |
| Advanced middleware (CORS, IP allowlist, signed URLs) | ❌ | ✅ |
| ORM / Active Record | ❌ | ✅ |
| Advanced QueryBuilder (joins, subqueries, relations) | ❌ | ✅ |
| Redis / APCu cache adapter | ❌ | ✅ |
| Distributed rate limiter (Redis) | ❌ | ✅ |
| Queue / job processing | ❌ | ✅ |
| WebSocket support | ❌ | ✅ |
| OpenAPI / Swagger generation | ❌ | ✅ |
| Admin panel scaffold | ❌ | ✅ |
| Multi-tenancy support | ❌ | ✅ |
| Premium documentation & support | ❌ | ✅ |
| Commercial license | ❌ | ✅ |
| Community support | ✅ | ❌ |

---

## Planned / Roadmap

| Feature | Priority |
|---|---|
| Redis cache adapter | High |
| APCu cache adapter | High |
| Distributed rate limiter | High |
| GraphQL support | Medium |
| Queue / job abstraction | Medium |
| Repository pattern base class | Medium |
| OpenAPI annotation support | Low |
| WebSocket handler | Low |
| Event sourcing support | Low |
