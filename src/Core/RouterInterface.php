<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function registerController(string $controllerClass): void;

    /**
     * @return array{0: string, 1: string, 2: array<string,string>}|null
     *   [controllerClass, method, routeParams] or null if not matched
     */
    public function match(ServerRequestInterface $request): ?array;
}
