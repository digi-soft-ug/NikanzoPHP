<?php

declare(strict_types=1);

use Nikanzo\Application\HelloController;
use Nikanzo\Core\Container\Container;
use Nikanzo\Core\Kernel;
use Nikanzo\Core\ModuleLoader;
use Nikanzo\Core\Router;

require __DIR__ . '/vendor/autoload.php';

$container = new Container();
$router = new Router();

$container->register(HelloController::class);
$router->registerController(HelloController::class);

$moduleLoader = new ModuleLoader(__DIR__ . '/src/Modules', $container, $router);
$loadedModules = $moduleLoader->load();

$kernel = new Kernel($router, $container);

return [
    'kernel' => $kernel,
    'container' => $container,
    'router' => $router,
    'modules' => $loadedModules,
];
