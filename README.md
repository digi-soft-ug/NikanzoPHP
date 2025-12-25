# NikanzoPHP

Open-source, modular PHP framework with attribute routing, PSR-7/15, DI, CLI, and migrations. Hosted at https://github.com/digi-soft-ug/NikanzoPHP.

## Features
- Routing with PHP 8 attributes (`#[Route]`), PSR-7 responses
- PSR-15 middleware pipeline (e.g. AuthMiddleware)
- DI container with `#[Inject]` / `#[Singleton]`, built on Symfony DependencyInjection
- CLI `php nikan`: `make:controller`, `make:usecase`, `db:migrate`, `db:seed`
- Migration system (SQLite default, MySQL/PostgreSQL possible) with `migrations` status table
- ModuleLoader auto-loads `src/Modules/*/Module` for reuse

## Installation
```bash
git clone https://github.com/digi-soft-ug/NikanzoPHP.git
cd NikanzoPHP
composer install
```

## Quickstart
- Dev server: `php -S 127.0.0.1:8000 -t public public/index.php`
- Hello endpoint: `http://127.0.0.1:8000/hello`
- CLI help: `php nikan list`

## Example: HelloController
```php
<?php
use Nikanzo\Core\Attributes\Route;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HelloController
{
    #[Route('/hello', methods: ['GET'])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Hello from NikanzoPHP'], JSON_THROW_ON_ERROR));
    }
}
```

## Example: Run migrations
```bash
php nikan db:migrate   # runs database/migrations in order
php nikan db:seed      # runs database/seeds (e.g. UsersSeeder)
```

## ModuleLoader
- Put modules under `src/Modules/<Name>/Module.php`.
- `Module` implements `Nikanzo\Core\ModuleInterface` and receives `Container` + `Router` to register services/routes.
- `bootstrap.php` loads modules automatically.

## Fast router cache
- Enable cached routing via env: `NIKANZO_FAST_ROUTER=1` (uses `var/cache/routes.php`).
- Warm cache via CLI: `php nikan route:cache` (scans `src/Application` and `src/Modules`).
- When cache is missing in fast mode, bootstrap builds routes (including modules) and persists the cache automatically.

## Reuse via Composer
- Package is `type: "library"` and PSR-4 (`Nikanzo\\` -> `src/`).
- In another project (e.g. `cms-boilerplate`) require it with:
```json
{
  "require": {
    "digi-soft-ug/nikanzophp": "^0.2.0"
  }
}
```
- Then run `composer install` and call `vendor/bin/nikan` (or `php vendor/digi-soft-ug/nikanzophp/nikan`) for the CLI.

## License
MIT

## Docker (Local Dev)
- Build & start dev server: `docker compose up --build`
- Install deps inside container: `docker compose run --rm app composer install`
- Run CLI: `docker compose run --rm app php nikan make:controller DemoController`
- App served on http://localhost:8000 (built-in PHP server, docroot `public/`).
- SQLite file lives in `database/database.sqlite` (mounted volume). Override DB via env: `NIKANZO_DB_DRIVER`, `NIKANZO_DB_DATABASE`, etc.
