# rules.md
- Always use strict_types and PHP type hints; follow PSR-12; no BOM.
- Prefer PSR interfaces (PSR-7/15) and Symfony components already in the stack.
- Handle errors with custom exceptions or ErrorHandlerMiddleware; return JSON errors.
- Attribute routing (`#[Route]`), cache via FastRouter when enabled.
- DI via container; `#[Singleton]` for shared; `#[Service(lazy: true|false, public: bool, shared: bool)]` to tune wiring.
- JWT auth via `JwtAuthMiddleware`; scope checks with `#[RequiredScope]` on controller methods.
- Keep tests in `tests/`; run `vendor/bin/phpunit` before commits.
- Config via env (`NIKANZO_*`); never commit secrets.
- Optional Twig templating; keep APIs JSON-first, templates under `templates/`.