# NikanzoPHP — Coding Rules

## Language & Style
- `declare(strict_types=1)` at the top of every PHP file. No exceptions.
- Follow PSR-12. Enforced by `php-cs-fixer` (`composer php-cs-fixer:fix`).
- All public methods must have type hints on parameters **and** return types.
- Use `readonly` properties where the value never changes after construction.
- Prefer `final` classes unless extension is intentional.
- No global state — no `static` mutable properties (see `RateLimitMiddleware` for the pattern).

## PSR Standards
- Use PSR interfaces wherever one exists: PSR-3 (logger), PSR-7 (HTTP), PSR-11 (container), PSR-14 (events), PSR-15 (middleware), PSR-16 (cache).
- Never define your own HTTP, container, logger, cache, or event interfaces.

## Routing
- Define routes with `#[Route('/path/{param}', methods: ['GET'])]` on controller methods.
- Register every controller in `bootstrap.php` (`$container->register()` + `$router->registerController()`).
- Use `new Router('/api/v1')` prefix for versioned APIs.
- Warm the route cache in production: `php nikan route:cache` + `NIKANZO_FAST_ROUTER=1`.

## Controllers
- Extend `AbstractController` for convenience helpers (`json()`, `error()`, `redirect()`, etc.).
- Controllers must be in the `Nikanzo\Application\` namespace (or a sub-namespace) so autoloading resolves correctly.
- Return `ResponseInterface` from every action — never `echo` or `header()` directly.
- Parse the request body via `$request->getParsedBody()` (populated by `RequestBodyParserMiddleware`).

## Dependency Injection
- Use `#[Singleton]` for shared stateless services.
- Use `#[Service(lazy: true)]` for expensive services that may not always be needed.
- Use `#[Inject]` for property/parameter injection by type or named service ID.
- Protect route methods with `#[RequiredScope('scope')]` — never check scopes inside controller logic.

## Security
- **JWT:** Set `NIKANZO_JWT_SECRET` (32+ chars). Add `JwtAuthMiddleware` before any authenticated routes.
- **CSRF:** Add `CsrfMiddleware` for HTML form endpoints only. Skip for pure JSON/JWT APIs.
- **Security headers:** `SecurityHeadersMiddleware` must be the outermost middleware (first `addMiddleware` call).
- **SQL:** Use `QueryBuilder` or PDO prepared statements exclusively. No raw string interpolation of user input.
- **Errors:** `APP_DEBUG=false` in production — never expose stack traces.
- **Secrets:** Never commit `.env`. Use `.env.example` as a template only.

## Database
- Use `QueryBuilder` for all database operations — never raw SQL with interpolated values.
- Keep migrations in `database/migrations/` with timestamp prefix `YYYYMMDDHHMMSS_`.
- Each migration file returns an anonymous class implementing `MigrationInterface`.
- Run `php nikan db:migrate` as part of every deployment.

## Caching
- Use `FileCache` (PSR-16) for request-level caching.
- For multi-process environments use an APCu or Redis adapter implementing `Psr\SimpleCache\CacheInterface`.
- Never cache sensitive data (tokens, passwords, PII) without encryption.

## Events
- Use `EventDispatcher` (PSR-14) for cross-cutting concerns; never call listeners directly.
- Legacy `HookDispatcher` is kept for backwards compatibility — prefer PSR-14 in new code.

## Logging
- Inject `Psr\Log\LoggerInterface` — never `echo`, `error_log()`, or write files directly.
- Use appropriate log levels: `debug` for dev traces, `info` for business events, `warning` for recoverable issues, `error` for exceptions.
- Set `NIKANZO_LOG_LEVEL=warning` or higher in production.

## Testing
- Use `TestClient` for HTTP-level integration tests.
- Every new endpoint needs at least a 200/happy-path test.
- Run `vendor/bin/phpunit` before every commit. CI will also run it.
- Do not use `static` state in middleware — it causes test cross-contamination.

## Environment
- All configuration is via `NIKANZO_*` env vars (and `APP_ENV`, `APP_DEBUG`).
- Copy `.env.example` → `.env`; never modify `.env.example` with real secrets.
- Use `getenv()` to read env vars in config files; `vlucas/phpdotenv` loads `.env` at boot.
