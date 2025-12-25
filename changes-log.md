# changes-log.md
- Added core framework scaffold (Kernel, Router, DI container, middleware, HelloController, PHPUnit test).
- Added CLI `nikan` with make:controller, make:usecase, db:migrate, db:seed, route:cache commands.
- Implemented migration/seed system (SQLite default, MySQL/PG supported) and sample migration/seeder.
- Added ModuleLoader and module folder for feature modules.
- Added FastRouter with caching and bootstrap integration plus cache warming.
- Added Docker setup (Dockerfile, docker-compose.yml) for local dev.
- Updated composer.json to `digi-soft-ug/nikanzophp`, MIT license, homepage.
- Added README and DEVELOPER_GUIDE with setup, CLI, Docker, cache notes.
- Added project meta docs: rules.md, architecture.md, usecases.md, changes-log.md.