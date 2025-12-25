<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

final class FastRouter implements RouterInterface
{
    /**
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private array $routes = [];
    private string $cacheFile;
    private bool $cacheLoaded = false;
    private bool $dirty = false;

    public function __construct(string $cacheFile)
    {
        $this->cacheFile = $cacheFile;
    }

    public function registerController(string $controllerClass): void
    {
        // No-op when using cache; use warm() to build routes from reflection.
        if ($this->cacheLoaded) {
            return;
        }

        $this->addRoutesFromController($controllerClass);
        $this->dirty = true;
    }

    /**
     * Build routes from controllers and write cache.
     *
     * @param string[] $controllers
     */
    public function warm(array $controllers): void
    {
        $this->routes = [];
        foreach ($controllers as $controller) {
            $this->addRoutesFromController($controller);
        }
        $this->dirty = true;
        $this->persistCache();
        $this->cacheLoaded = true;
    }

    /**
     * Load routes from cache file if available.
     */
    public function loadFromCache(): bool
    {
        if (!is_file($this->cacheFile)) {
            return false;
        }

        $routes = require $this->cacheFile;
        if (!is_array($routes)) {
            return false;
        }

        $this->routes = $routes;
        $this->cacheLoaded = true;
        $this->dirty = false;

        return true;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function match(ServerRequestInterface $request): ?array
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalizePath($request->getUri()->getPath());

        return $this->routes[$method][$path] ?? null;
    }

    public function persistCache(): void
    {
        if (!$this->dirty) {
            return;
        }

        $this->writeCache();
        $this->dirty = false;
        $this->cacheLoaded = true;
    }

    private function addRoutesFromController(string $controllerClass): void
    {
        if (!class_exists($controllerClass)) {
            throw new \InvalidArgumentException('Controller class does not exist: ' . $controllerClass);
        }
        $reflection = new ReflectionClass($controllerClass);
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $path = $this->normalizePath($route->getPath());

                foreach ($route->getMethods() as $httpMethod) {
                    $methodKey = strtoupper($httpMethod);
                    $this->routes[$methodKey][$path] = [$controllerClass, $method->getName()];
                }
            }
        }
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');

        return rtrim($normalized, '/') ?: '/';
    }

    private function writeCache(): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $content = '<?php return ' . var_export($this->routes, true) . ';';
        file_put_contents($this->cacheFile, $content);
    }
}
