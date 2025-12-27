# Project Architecture

## Root Layout

- **public/**: Contains the front controller `index.php`, which is the entry point for all HTTP requests. Handles request dispatching to the application kernel.
- **bootstrap.php**: Initializes the dependency injection container, sets up the router (FastRouter if enabled), and loads all modules. This file is required by the front controller.
- **src/Core/**: Core framework logic, including:
  - **Kernel.php**: Main application kernel, handles request lifecycle, middleware pipeline, and response emission.
  - **Router.php/FastRouter.php**: Routing logic, supports both dynamic and cached (fast) routing.
  - **ModuleLoader.php**: Loads and registers modules.
  - **Middleware/**: PSR-15 middleware implementations.
  - **Console/**: CLI commands and integration with Symfony Console.
  - **Database/**: Database helpers and connection management.
  - **Attributes/**: Custom PHP attributes for routing, DI, etc.
  - **Template/**: Template rendering (Twig integration).
  - **Hooks/**: Event dispatcher and hooks for extensibility.
- **src/Application/**: Application-specific controllers and logic. Place your HTTP controllers and handlers here.
- **src/Domain/**: Domain models, business rules, and use cases. This is the core of your business logic, independent of frameworks and infrastructure.
- **src/Infrastructure/**: Adapters for persistence (e.g., database, cache), external APIs, and other integrations. Implements interfaces defined in Domain.
- **src/Modules/**: Optional feature modules. Each module can have its own `Module` class for registration and can provide controllers, services, and configuration.
- **src/Premium/**: Reserved for premium/enterprise features and modules, separated from the open core.
- **database/**: Contains migration scripts and seeders for database setup and test data.
- **config/**: Application configuration files (e.g., database.php, view.php, cli.php). These are loaded during bootstrap.
- **templates/**: Twig templates for rendering HTML views. Optional if using API-only or SPA frontend.
- **tests/**: PHPUnit test cases for unit, integration, and functional testing.
- **frontend/**: Placeholder for SPA (Single Page Application) assets, such as React or Vue builds.
- **docs/** and **premium-docs/**: Developer and user guides, feature documentation, and premium feature documentation.
- **var/cache/**: Stores route cache (for FastRouter) and optionally Twig cache for faster template rendering.
- **.github/workflows/**: Continuous Integration (CI) configuration files for GitHub Actions.
- **premium-config/**: Configuration overlays for premium features.

## Design Patterns & Practices

- **Attribute Routing**: Use PHP attributes like `#[Route]` to define routes directly on controller methods. Dependency injection is handled via Symfony Container and custom attributes like `#[Inject]`, `#[Singleton]`, and `#[Service]`.
- **Middleware Pipeline**: Implements PSR-7 (HTTP messages) and PSR-15 (middleware). The kernel processes requests through a middleware stack, allowing for cross-cutting concerns (auth, logging, etc.). Middleware can short-circuit the pipeline by returning a response early. Scope checks can be enforced using `#[RequiredScope]` attributes.
- **CLI Tools**: Uses Symfony Console for CLI commands. The `nikan` command provides scaffolding, database tasks, route cache warming, and more.
- **Database Migrations/Seeds**: Managed via PDO, with SQLite as the default database. Migrations and seeds are organized in the `database/` directory.
- **Extensibility**: Hooks and an event dispatcher allow modules and core logic to be extended without modifying core code. The module loader enables feature packs and modular development.
- **Testing**: PHPUnit is used for automated testing. Place tests in the `tests/` directory, following best practices for unit and integration tests.

## Application Flow

1. **Request Entry**: All HTTP requests enter via `public/index.php`.
2. **Bootstrap**: `bootstrap.php` initializes the container, router, and modules.
3. **Routing**: The router matches the request to a controller/action, using either dynamic or cached routes.
4. **Middleware**: The request passes through the middleware pipeline for processing (auth, logging, etc.).
5. **Controller Execution**: The matched controller handles the request, using injected dependencies.
6. **Response**: The response is returned through the middleware stack and emitted to the client.
7. **CLI/Console**: CLI commands are handled via the `nikan` entry point, supporting tasks like migrations, cache warming, and scaffolding.