<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nikanzo\Application\HelloController;
use Nikanzo\Core\Container\Container;
use Nikanzo\Core\FastRouter;
use Nikanzo\Core\Kernel;
use Nikanzo\Core\Logging\LoggerFactory;
use Nikanzo\Core\ModuleLoader;
use Nikanzo\Core\Router;
use Psr\Log\LoggerInterface;

require __DIR__ . '/vendor/autoload.php';

// ── Load .env if present ──────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/.env') && class_exists(Dotenv::class)) {
    Dotenv::createImmutable(__DIR__)->safeLoad();
}

// ── Logger ────────────────────────────────────────────────────────────────────
$logger = LoggerFactory::create();

// ── Container ────────────────────────────────────────────────────────────────
$container = new Container();

// Bind PSR-3 LoggerInterface so controllers/services can inject it
$container->register(LoggerInterface::class);

// ── Router ────────────────────────────────────────────────────────────────────
$useFast = getenv('NIKANZO_FAST_ROUTER') === '1';

if ($useFast) {
    $cacheFile = __DIR__ . '/var/cache/routes.php';
    $router = new FastRouter($cacheFile);
    $loaded = $router->loadFromCache();
} else {
    $router = new Router();
    $loaded = false;
}

// ── Register application controllers dynamically ─────────────────────────────
$controllerDir = __DIR__ . '/src/Application';
$controllerNamespace = 'Nikanzo\\Application';
$controllerFiles = glob($controllerDir . '/*Controller.php');
foreach ($controllerFiles as $file) {
    $className = $controllerNamespace . '\\' . basename($file, '.php');
    if (class_exists($className)) {
        $container->register($className);
        $router->registerController($className);
    }
}

// ── Load modules ─────────────────────────────────────────────────────────────
$moduleLoader = new ModuleLoader(__DIR__ . '/src/Modules', $container, $router);
$loadedModules = $moduleLoader->load();

if ($useFast && !$loaded) {
    $router->persistCache();
}

// ── Kernel ───────────────────────────────────────────────────────────────────
$kernel = new Kernel($router, $container);

return [
    'kernel' => $kernel,
    'container' => $container,
    'router' => $router,
    'logger' => $logger,
    'modules' => $loadedModules,
];
