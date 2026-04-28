# NikanzoPHP — Use Cases

## HTTP API Development

- Define RESTful routes with `#[Route('/resource/{id}', methods: ['GET','POST','PUT','DELETE'])]`.
- Route parameters (`{id}`, `{slug:[a-z-]+}`) are automatically extracted and injected into controller methods.
- Return structured JSON via `AbstractController::json()` or any `ResponseInterface` implementation.
- Scaffold a new controller: `php nikan make:controller Api/UserController`.
- Scaffold a use-case class: `php nikan make:usecase RegisterUser`.

## Authentication & Authorization

- Protect all endpoints with `JwtAuthMiddleware` (Bearer token, `NIKANZO_JWT_SECRET`).
- Add per-endpoint scope guards: `#[RequiredScope('users:write')]` on controller methods.
- Access decoded claims in the controller: `$request->getAttribute('auth.claims')`.
- Rotate the JWT secret periodically — all existing tokens immediately become invalid.

## Security-First Responses

- Add `SecurityHeadersMiddleware` as the outermost middleware to enforce CSP, HSTS, X-Frame-Options, etc.
- Enable `CsrfMiddleware` for any HTML form endpoints (skipped for JWT-authenticated APIs).
- Use `ErrorHandlerMiddleware` with `APP_DEBUG=false` in production — stack traces never reach clients.
- Rate-limit abuse with `RateLimitMiddleware`; response includes `X-RateLimit-Remaining` and `Retry-After`.

## Request Parsing & Content Negotiation

- Add `RequestBodyParserMiddleware` to automatically decode `application/json` request bodies.
- Add `ContentNegotiationMiddleware` to set `accept.format` attribute (`json`/`html`/`text`) on each request.
- Access parsed body in controllers: `$request->getParsedBody()` (array).
- Access query parameters: `$request->getQueryParams()`.

## Database Access

- Connect to SQLite, MySQL, or PostgreSQL via `ConnectionFactory::make($config)`.
- Build queries fluently with `QueryBuilder` — all values go through prepared statements.
- Paginate results: create a `Paginator` with `total`, `page`, `perPage`; use `$paginator->offset` for the DB query.
- Run schema migrations: `php nikan db:migrate`.
- Seed test data: `php nikan db:seed`.

## Caching

- Cache expensive operations with `FileCache` (PSR-16): `$cache->get('key', $default)`, `$cache->set('key', $value, $ttl)`.
- Configure cache path and TTL via `NIKANZO_CACHE_PATH` and `NIKANZO_CACHE_TTL` env vars.
- Swap to a Redis/APCu adapter implementing `Psr\SimpleCache\CacheInterface` for multi-process servers.

## Events & Hooks

- Dispatch domain events with `EventDispatcher::dispatch(new OrderPlaced($id))`.
- Register listeners: `$provider->addListener(OrderPlaced::class, $callable)`.
- Listeners for parent classes and interfaces are discovered automatically.
- Stop event propagation by implementing `StoppableEventInterface` in the event class.

## Logging & Observability

- Use `LoggerFactory::create()` for a pre-configured Monolog instance (rotating file, env-configured level).
- Inject `Psr\Log\LoggerInterface` anywhere via the DI container.
- `ErrorHandlerMiddleware` logs all uncaught exceptions automatically.
- Configure log channel, level, path, and rotation via `NIKANZO_LOG_*` env vars.

## HTML Rendering

- Render Twig templates via `AbstractController::render($renderer, 'template.html.twig', $context)`.
- Twig auto-escaping is enabled by default — XSS safe.
- Cache compiled templates via `NIKANZO_CACHE_PATH` (Twig) or `view.php` config.

## API Versioning

- Create a versioned router: `new Router('/api/v1')`.
- All `#[Route]` paths in registered controllers are automatically prefixed.
- Run multiple versioned kernels side-by-side for concurrent API versions.

## Testing

- Write integration tests using `TestClient` — no real HTTP needed.
- `$client->get('/users/1', ['Authorization' => 'Bearer ' . $token])`.
- `$client->post('/users', ['name' => 'Alice'])` auto-sends JSON body.
- Decode responses: `$client->json($response)` returns an array.

## Modularity

- Package features as modules in `src/Modules/<Name>/Module.php`.
- The `ModuleLoader` auto-discovers and registers all modules at bootstrap.
- Each module receives the `Container` and `Router` to register its own services and routes.

## Developer Tooling

- Start dev server: `php -S 127.0.0.1:8000 -t public public/index.php`.
- Docker: `docker compose up --build`.
- Warm route cache for production: `php nikan route:cache` + `NIKANZO_FAST_ROUTER=1`.
- Static analysis: `composer phpstan` / `composer psalm`.
- Code style fix: `composer php-cs-fixer:fix`.
- Run tests: `vendor/bin/phpunit`.
- Check security advisories: `composer audit`.
