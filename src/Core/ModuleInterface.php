<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Container\Container;
use Nikanzo\Core\Router;

interface ModuleInterface
{
    public function register(Container $container, Router $router): void;
}
