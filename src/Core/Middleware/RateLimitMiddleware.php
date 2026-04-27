<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sliding-window rate limiter (in-process storage).
 *
 * For multi-process / distributed deployments replace $this->buckets with an
 * APCu or Redis backend. Static state was deliberately removed to prevent test
 * cross-contamination — each Kernel instantiation gets a clean instance.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var array<string, list<int>> */
    private array $buckets = [];

    public function __construct(
        private readonly int $limit           = 60,
        private readonly int $intervalSeconds = 60,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key         = $this->key($request);
        $now         = time();
        $windowStart = $now - $this->intervalSeconds;

        $this->buckets[$key] = array_values(
            array_filter($this->buckets[$key] ?? [], static fn (int $ts) => $ts >= $windowStart)
        );

        if (count($this->buckets[$key]) >= $this->limit) {
            return new Response(
                429,
                [
                    'Content-Type'  => 'application/json',
                    'Retry-After'   => (string) $this->intervalSeconds,
                    'X-RateLimit-Limit' => (string) $this->limit,
                ],
                json_encode(['error' => 'too_many_requests'], JSON_THROW_ON_ERROR)
            );
        }

        $this->buckets[$key][] = $now;

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->limit - count($this->buckets[$key])));
    }

    private function key(ServerRequestInterface $request): string
    {
        $ip   = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $path = $request->getUri()->getPath();

        return $ip . '|' . $path;
    }
}
