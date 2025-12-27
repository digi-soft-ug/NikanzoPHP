<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private static array $bucket = [];

    public function __construct(private int $limit = 60, private int $intervalSeconds = 60)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->key($request);
        $now = time();
        $windowStart = $now - $this->intervalSeconds;

        if (!isset(self::$bucket[$key])) {
            self::$bucket[$key] = [];
        }

        // Drop old timestamps
        self::$bucket[$key] = array_values(array_filter(self::$bucket[$key], fn (int $ts) => $ts >= $windowStart));

        if (count(self::$bucket[$key]) >= $this->limit) {
            return new Response(
                429,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'too_many_requests'], JSON_THROW_ON_ERROR)
            );
        }

        self::$bucket[$key][] = $now;

        return $handler->handle($request);
    }

    private function key(ServerRequestInterface $request): string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $path = $request->getUri()->getPath();

        return $ip . '|' . $path;
    }
}
