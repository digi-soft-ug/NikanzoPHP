<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(private readonly bool $debug = false, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'method'    => $request->getMethod(),
                'uri'       => (string) $request->getUri(),
            ]);

            $payload = $this->debug ? [
                'error'   => 'internal_error',
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ] : ['error' => 'internal_error'];

            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode($payload, JSON_THROW_ON_ERROR)
            );
        }
    }
}
