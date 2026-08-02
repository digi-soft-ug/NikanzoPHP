# NikanzoPHP — Architecture

## Overview

NikanzoPHP is a **request-to-response pipeline framework**. Every HTTP request enters through a single front controller, passes through a composable middleware stack, is dispatched to a matched controller, and exits as a PSR-7 response. The design favors explicit wiring over convention magic.

---

## Request Lifecycle

```
Browser / Client
      │
      ▼
public/index.php          (front controller)
      │ Symfony Request::createFromGlobals()
      ▼
bootstrap.php             (container, router, modules wired)
      │
      ▼
Kernel::handle(Request)
      │ HttpBridge::toPsr7()  →  PSR-7 ServerRequest
      │
      ▼
┌─────────────────────────────────────┐
│         Middleware Pipeline         │  (LIFO — outermost first)
│                                     │
│  SecurityHeadersMiddleware          │
│  ErrorHandlerMiddleware             │
│  RequestBodyParserMiddleware        │
│  ContentNegotiationMiddleware       │
│  JwtAuthMiddleware                  │
│  RateLimitMiddleware                │
│  CsrfMiddleware (optional)          │
└──────────────┬──────────────────────┘
               │
               ▼
         Core Handler
          │
          ├─ Router::match()        →  [ControllerClass, method, routeParams]
          │                             (RouteExtractor compiles #[Route] → regex)
          │
          ├─ Kernel::checkScopes()  →  403 if #[RequiredScope] not satisfied
          │
          ├─ Container::get()       →  resolved controller instance
          │
          └─ Container::call()      →  invoke method with injected args + routeParams
               │
               ▼
          ResponseInterface
               │
               ▼
public/index.php          (emit: http_response_code + headers + body)
```

---

## Layer Map

```
src/
├── Core/                  ← Framework kernel (never import Application/ or Domain/)
│   ├── Attributes/        ← PHP 8.3 attributes: Route, Inject, Service, Singleton, RequiredScope
│   ├── Cache/             ← PSR-16: FileCache, InvalidArgumentException
│   ├── Console/Command/   ← CLI commands (Symfony Console)
│   ├── Container/         ← PSR-11: Container, NotFoundException, ContainerException
│   ├── Controller/        ← AbstractController (optional base class)
│   ├── Database/          ← ConnectionFactory, QueryBuilder, MigrationRunner, SeederRunner
│   ├── Events/            ← PSR-14: EventDispatcher, ListenerProvider
│   ├── Hooks/             ← Legacy HookDispatcher (kept for BC; prefer PSR-14)
│   ├── Http/              ← HttpBridge (Symfony→PSR-7), RouteExtractor (reflection→regex)
│   ├── Logging/           ← LoggerFactory → Monolog (PSR-3)
│   ├── Middleware/        ← All PSR-15 middleware implementations
│   ├── Security/          ← CsrfTokenManager
│   ├── Support/           ← Paginator
│   ├── Template/          ← TemplateRenderer (Twig wrapper)
│   ├── Testing/           ← TestClient (drives Kernel in tests)
│   ├── FastRouter.php     ← RouterInterface impl with route-cache support
│   ├── Kernel.php         ← Pipeline orchestrator
│   ├── ModuleLoader.php   ← Discovers src/Modules/*/Module.php
│   ├── Router.php         ← RouterInterface impl with live reflection
│   └── RouterInterface.php
│
├── Application/           ← HTTP controllers (one class per resource/feature)
├── Domain/                ← Business models, value objects, domain services
│                             (no framework imports — pure PHP)
├── Infrastructure/        ← DB repositories, external API clients
└── Modules/               ← Optional feature packages (Blog, Shop, …)
    └── <Name>/
        ├── Module.php     ← Implements ModuleInterface; registers services + routes
        └── …
```

---

## Key Components

### Kernel

`src/Core/Kernel.php`

Accepts a `Request`, converts it via `HttpBridge`, builds the middleware pipeline as an `array_reduce` chain of `RequestHandlerInterface` adapters, and hands control to the core handler (router → scope check → controller dispatch).

The `checkScopes()` method is `public` so it can be tested directly without running the full pipeline.

### Router + FastRouter

Both implement `RouterInterface`. Internally they delegate all reflection to `RouteExtractor::extract()` which:
1. Reads `#[Route]` attributes from each controller method
2. Compiles path templates (`/users/{id}`) to named-capture regexes (`#^/users/(?P<id>[^/]+)$#`)
3. Returns a nested array `[HTTP_METHOD][pattern] → [class, method, paramNames]`

`FastRouter` persists this structure to `var/cache/routes.php` via `var_export`. On warm hits the entire reflection step is skipped.

### Container

`src/Core/Container/Container.php`

Wraps `Symfony\Component\DependencyInjection\ContainerBuilder`. Adds:
- `#[Inject]` property injection after retrieval
- `#[Singleton]` / `#[Service]` attribute parsing to control `shared`/`lazy`/`public`
- PSR-11 `NotFoundException` and `ContainerException`
- `call($instance, $method, $args)` — resolves method parameters by type, then by `#[Inject]`

### HttpBridge

`src/Core/Http/HttpBridge.php`

Static utility. Converts a `Symfony\Component\HttpFoundation\Request` into a `Psr7\ServerRequest`. Handles query params, parsed body, cookies, headers, and raw body stream.

### QueryBuilder

`src/Core/Database/QueryBuilder.php`

Fluent, non-ORM query builder. All values are bound via `PDO::prepare` / `execute`. No string interpolation of user data. Supports `where`, `orWhere`, `whereIn`, `whereNull`, `whereNotNull`, `orderBy`, `groupBy`, `limit`, `offset`, `select`, `count`, `find`, `get`, `first`, `insert`, `update`, `delete`, `raw`.

### EventDispatcher (PSR-14)

`src/Core/Events/`

`ListenerProvider` stores listeners keyed by exact class name. `getListenersForEvent()` also yields listeners registered for parent classes and interfaces — subtype polymorphism works automatically. Supports `StoppableEventInterface`.

---

## Design Principles

1. **PSR-first.** Every interface that has a PSR is used as a PSR. New code should never invent its own HTTP, container, logger, cache, or event interface.

2. **No global state.** `RateLimitMiddleware` uses instance state (not `static`). `HttpBridge` uses a lazily-created static factory only for the Nyholm factory (which is itself stateless). Tests get a clean slate by instantiating a fresh `Kernel`.

3. **Prepared statements only.** `QueryBuilder` never interpolates values. `RouteExtractor` never `eval()`s user input.

4. **Explicit wiring.** Routes, middleware, services, and modules are all registered explicitly in `bootstrap.php`. Nothing is auto-discovered by convention scanning (except modules via `ModuleLoader`).

5. **Thin core, composable edges.** The kernel has no opinion on auth scheme, templating, or persistence. Each concern is a swappable middleware or service.

---

## Extension Points

| What to extend | How |
|---|---|
| Add a route | `#[Route]` attribute on a method; register controller in bootstrap |
| Add middleware | `$kernel->addMiddleware(new MyMiddleware())` in bootstrap |
| Add a module | Create `src/Modules/<Name>/Module.php` implementing `ModuleInterface` |
| Add a CLI command | Extend `Symfony\Component\Console\Command\Command`; register in `nikan` |
| Replace the cache | Implement `Psr\SimpleCache\CacheInterface`; bind in container |
| Replace the logger | Implement `Psr\Log\LoggerInterface`; bind in container |
| Replace the event dispatcher | Implement `Psr\EventDispatcher\EventDispatcherInterface`; bind in container |
| Add event listeners | Call `ListenerProvider::addListener($class, $callable)` in bootstrap |
