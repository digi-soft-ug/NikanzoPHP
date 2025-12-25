<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ') || trim(substr($authorization, 7)) === '') {
            return new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Unauthorized'], JSON_THROW_ON_ERROR)
            );
        }

        return $handler->handle($request);
    }
}
