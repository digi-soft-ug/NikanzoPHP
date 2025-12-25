<?php

declare(strict_types=1);

use Nikanzo\Application\HelloController;
use Nikanzo\Core\Container\Container;
use Nikanzo\Core\FastRouter;
use Nikanzo\Core\Kernel;
use Nikanzo\Core\ModuleLoader;
use Nikanzo\Core\Router;

require __DIR__ . '/vendor/autoload.php';

$container = new Container();
$useFast = getenv('NIKANZO_FAST_ROUTER') === '1';

if ($useFast) {
    $cacheFile = __DIR__ . '/var/cache/routes.php';
    $router = new FastRouter($cacheFile);
    $loaded = $router->loadFromCache();
} else {
    $router = new Router();
    $loaded = false;
}

$container->register(HelloController::class);
$router->registerController(HelloController::class);

$moduleLoader = new ModuleLoader(__DIR__ . '/src/Modules', $container, $router);
$loadedModules = $moduleLoader->load();

if ($useFast && !$loaded) {
    $router->persistCache();
}

$kernel = new Kernel($router, $container);

return [
    'kernel' => $kernel,
    'container' => $container,
    'router' => $router,
    'modules' => $loadedModules,
];
