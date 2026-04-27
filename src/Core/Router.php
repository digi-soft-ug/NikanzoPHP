<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Http\RouteExtractor;
use Psr\Http\Message\ServerRequestInterface;

final class Router implements RouterInterface
{
    /**
     * [ HTTP_METHOD => [ pattern => [class, method, paramNames] ] ]
     *
     * @var array<string, array<string, array{0: string, 1: string, 2: array<string,string>}>>
     */
    private array $routes = [];

    private string $prefix;

    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function registerController(string $controllerClass): void
    {
        foreach (RouteExtractor::extract($controllerClass, $this->prefix) as $method => $methodRoutes) {
            foreach ($methodRoutes as $pattern => $handler) {
                $this->routes[$method][$pattern] = $handler;
            }
        }
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
}
