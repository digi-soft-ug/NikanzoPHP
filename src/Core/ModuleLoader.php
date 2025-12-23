<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Container\Container;

final class ModuleLoader
{
    private string $modulesPath;
    private Container $container;
    private Router $router;

    public function __construct(string $modulesPath, Container $container, Router $router)
    {
        $this->modulesPath = $modulesPath;
        $this->container = $container;
        $this->router = $router;
    }

    /**
     * @return string[] Loaded module class names
     */
    public function load(): array
    {
        if (!is_dir($this->modulesPath)) {
            return [];
        }

        $loaded = [];
        $directories = glob(rtrim($this->modulesPath, '\\/') . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $class = 'Nikanzo\\Modules\\' . $moduleName . '\\Module';
            $moduleFile = $dir . '/Module.php';

            if (!class_exists($class) && is_file($moduleFile)) {
                require_once $moduleFile;
            }

            if (!class_exists($class)) {
                continue;
            }

            $this->container->register($class);
            $module = $this->container->get($class);

            if ($module instanceof ModuleInterface) {
                $module->register($this->container, $this->router);
                $loaded[] = $class;
            }
        }

        return $loaded;
    }
}
