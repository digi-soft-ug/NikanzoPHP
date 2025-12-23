<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

final class Router
{
    /**
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private array $routes = [];

    public function registerController(string $controllerClass): void
    {
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

    /**
     * @return array{0: string, 1: string}|null
     */
    public function match(ServerRequestInterface $request): ?array
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalizePath($request->getUri()->getPath());

        return $this->routes[$method][$path] ?? null;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');

        return rtrim($normalized, '/') ?: '/';
    }
}
