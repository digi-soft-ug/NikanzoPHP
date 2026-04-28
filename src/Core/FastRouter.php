<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Http\RouteExtractor;
use Psr\Http\Message\ServerRequestInterface;

final class FastRouter implements RouterInterface
{
    /**
     * @var array<string, array<string, array{0: string, 1: string, 2: array<string,string>}>>
     */
    private array $routes = [];
    private bool $cacheLoaded = false;
    private bool $dirty = false;
    private string $prefix;

    public function __construct(private readonly string $cacheFile, string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function registerController(string $controllerClass): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        foreach (RouteExtractor::extract($controllerClass, $this->prefix) as $method => $methodRoutes) {
            foreach ($methodRoutes as $pattern => $handler) {
                $this->routes[$method][$pattern] = $handler;
            }
        }

        $this->dirty = true;
    }

    /**
     * Build routes from multiple controllers and write cache.
     *
     * @param string[] $controllers
     */
    public function warm(array $controllers): void
    {
        $this->routes = [];
        foreach ($controllers as $controller) {
            foreach (RouteExtractor::extract($controller, $this->prefix) as $method => $methodRoutes) {
                foreach ($methodRoutes as $pattern => $handler) {
                    $this->routes[$method][$pattern] = $handler;
                }
            }
        }
        $this->dirty = true;
        $this->persistCache();
        $this->cacheLoaded = true;
    }

    public function loadFromCache(): bool
    {
        if (!is_file($this->cacheFile)) {
            return false;
        }

        $routes = require $this->cacheFile;
        if (!is_array($routes)) {
            return false;
        }

        $this->routes      = $routes;
        $this->cacheLoaded = true;
        $this->dirty       = false;

        return true;
    }

    /**
     * @return array{0: string, 1: string, 2: array<string,string>}|null
     */
    public function match(ServerRequestInterface $request): ?array
    {
        return RouteExtractor::matchPath(
            $request->getMethod(),
            $request->getUri()->getPath(),
            $this->routes
        );
    }

    public function persistCache(): void
    {
        if (!$this->dirty) {
            return;
        }

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->cacheFile,
            '<?php return ' . var_export($this->routes, true) . ';',
            LOCK_EX
        );

        $this->dirty       = false;
        $this->cacheLoaded = true;
    }
}
