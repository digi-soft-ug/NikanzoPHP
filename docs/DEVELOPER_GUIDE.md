## FastRouter (Route Cache)
- Enable cache via `NIKANZO_FAST_ROUTER=1` (routes cached to `var/cache/routes.php`).
- Warm cache manually: `php nikan route:cache` (scans `src/Application` and `src/Modules`).
- When cache is missing in fast mode, bootstrap builds routes (including module-registered controllers) and persists cache.