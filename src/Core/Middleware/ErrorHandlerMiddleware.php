<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private bool $debug = false)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $payload = $this->debug ? [
                'error' => 'internal_error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ] : ['error' => 'internal_error'];

            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode($payload, JSON_THROW_ON_ERROR)
            );
        }
    }
}
