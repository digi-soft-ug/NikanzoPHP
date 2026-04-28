<?php

declare(strict_types=1);

namespace Nikanzo\Core\Http;

use Nikanzo\Core\Attributes\Route;
use ReflectionClass;

/**
 * Extracts #[Route] metadata from a controller class via reflection.
 *
 * Shared between Router and FastRouter to eliminate duplicated reflection code.
 */
final class RouteExtractor
{
    /**
     * @param string $prefix  Optional URI prefix (e.g. "/api/v1")
     * @return array<string, array<string, array{0: string, 1: string, 2: array<string,string>}>>
     *   [ HTTP_METHOD => [ '/path' => [controllerClass, methodName, paramNames] ] ]
     */
    public static function extract(string $controllerClass, string $prefix = ''): array
    {
        if (!class_exists($controllerClass)) {
            throw new \InvalidArgumentException('Controller class does not exist: ' . $controllerClass);
        }

        $prefix  = rtrim($prefix, '/');
        $routes  = [];
        $refClass = new ReflectionClass($controllerClass);

        foreach ($refClass->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route  = $attribute->newInstance();
                $rawPath = $prefix . '/' . ltrim($route->getPath(), '/');
                $rawPath = rtrim($rawPath, '/') ?: '/';

                // Extract named parameters: /users/{id} → regex + param names
                [$pattern, $paramNames] = self::compilePath($rawPath);

                foreach ($route->getMethods() as $httpMethod) {
                    $routes[strtoupper($httpMethod)][$pattern] = [
                        $controllerClass,
                        $method->getName(),
                        $paramNames,
                    ];
                }
            }
        }

        return $routes;
    }

    /**
     * Convert a path template into a regex pattern and a list of param names.
     *
     * /users/{id}          → ['#^/users/(?P<id>[^/]+)$#', ['id']]
     * /posts/{slug:[a-z-]+} → ['#^/posts/(?P<slug>[a-z-]+)$#', ['slug']]
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function compilePath(string $path): array
    {
        $paramNames = [];
        $pattern    = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                $regex        = $m[2] ?? '[^/]+';

                return '(?P<' . $m[1] . '>' . $regex . ')';
            },
            $path
        );

        return ['#^' . $pattern . '$#', $paramNames];
    }

    /**
     * Match a URI path against a compiled route table.
     *
     * @param array<string, array<string, array{0: string, 1: string, 2: array<string, string>}>> $routes
     * @return array{0: string, 1: string, 2: array<string,string>}|null  [class, method, params]
     */
    public static function matchPath(string $httpMethod, string $path, array $routes): ?array
    {
        $methodRoutes = $routes[strtoupper($httpMethod)] ?? [];
        $normalized   = '/' . trim($path, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        foreach ($methodRoutes as $pattern => [$class, $method, $paramNames]) {
            // Static route (no regex)
            if (!str_starts_with($pattern, '#')) {
                if ($pattern === $normalized) {
                    return [$class, $method, []];
                }
                continue;
            }

            if (preg_match($pattern, $normalized, $matches)) {
                $params = [];
                foreach ($paramNames as $name) {
                    $params[$name] = $matches[$name] ?? '';
                }

                return [$class, $method, $params];
            }
        }

        return null;
    }
}
