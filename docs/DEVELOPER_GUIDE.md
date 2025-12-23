# NikanzoPHP Developer Guide (DE)

## Voraussetzungen
- PHP >= 8.3
- Composer

## Installation
```bash
git clone https://github.com/digi-soft-ug/NikanzoPHP.git
cd NikanzoPHP
composer install
```

## Schnelleinstieg
```bash
php -S 127.0.0.1:8000 -t public public/index.php
```
Ruf dann `http://127.0.0.1:8000/hello` im Browser oder per `curl` auf.

## Projektstruktur
- `public/` - Front-Controller (`index.php`)
- `src/Core/` - Kernel, Router, DI-Container, Middleware, Attribute
- `src/Application/` - Controller und Anwendungslogik
- `src/Domain/` - Domain-Modelle, Regeln
- `src/Infrastructure/` - Persistenz/Adapter
- `src/Modules/` - optionale Module, werden automatisch vom ModuleLoader geladen
- `database/` - Migrationen, Seeds, optionale Modelle
- `frontend/` - Platz fuer Vue/React Apps
- `config/` - Konfiguration
- `tests/` - PHPUnit-Tests
- `bootstrap.php` - erstellt Kernel, Container, Router
- `composer.json` - Abhaengigkeiten und Autoload

## Request-Flow
1) `public/index.php` liest `bootstrap.php` und baut Kernel.
2) Symfony `Request::createFromGlobals()` wird in Kernel uebergeben.
3) Kernel wandelt in PSR-7 um, durchlaeuft Middleware-Chain, ruft Router.
4) Router matched Attribute-Routen, DI-Container erzeugt Controller.
5) Rueckgabe ist PSR-7 `ResponseInterface`, faellt sonst auf JSON 200 zurueck.

## Routing per Attribute
- Attribut: `Nikanzo\Core\Attributes\Route`
- Beispiel:
```php
#[Route('/hello', methods: ['GET'])]
public function index(ServerRequestInterface $request): ResponseInterface
```
- Controller registrieren: `$router->registerController(HelloController::class);`
- Pfade werden normalisiert (`/foo/` => `/foo`).

## Dependency Injection
- Container: `Nikanzo\Core\Container\Container`
- Service registrieren: `$container->register(MyService::class);`
- Property-Injection: `#[Inject] private MyService $svc;`
- Optional `serviceId` im Attribut: `#[Inject(FooInterface::class)]`
- Singleton: `#[Singleton]` an der Klassendeklaration macht den Dienst shared.
- Methoden-Aufruf mit Autowiring: `$container->call($controller, 'action', ['request' => $req]);`

## CLI `nikan`
- Aufruf: `php nikan`
- Kommandos: `make:controller`, `make:usecase`, `db:migrate`, `db:seed`
- Beispiel: `php nikan make:controller DemoController`

## Migrationen und Seeds
- Migrationen in `database/migrations/*.php`, Datei gibt `MigrationInterface`-Instanz zurueck.
- Seeds in `database/seeds/*.php`, Datei gibt `SeederInterface`-Instanz zurueck.
- Befehle: `php nikan db:migrate`, `php nikan db:seed`
- Default: SQLite (`config/database.php`), konfigurierbar auf MySQL/PostgreSQL.

## ModuleLoader
- Module unter `src/Modules/<Name>/Module.php` anlegen.
- `Module` implementiert `Nikanzo\Core\ModuleInterface` und erhaelt Container und Router fuer Registrierungen.
- Wird beim Booten aus `bootstrap.php` geladen.

## Middleware (PSR-15)
- Implementiert `MiddlewareInterface::process()`.
- Beispiel `AuthMiddleware` prueft `Authorization: Bearer <token>` und liefert 401 JSON, sonst `handler->handle()`.
- Aktivieren: `$kernel->addMiddleware(new AuthMiddleware());`

## Tests
```bash
vendor/bin/phpunit
```
- Beispieltest: `tests/HelloControllerTest.php` prueft `/hello` (Status 200, JSON-Body).

## Docker (lokal)
- Start: `docker compose up --build`
- Composer im Container: `docker compose run --rm app composer install`
- CLI im Container: `docker compose run --rm app php nikan list`

## Erweiterungsideen
- Composer-Skripte fuer Server/Tests ergaenzen.
- Error-Handling/Logging Middleware hinzufuegen.
- Config-Layer fuer Service-Wiring (statt manueller Register-Aufrufe).