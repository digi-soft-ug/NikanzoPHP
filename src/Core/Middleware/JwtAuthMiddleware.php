<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private ?string $secret = null)
    {
        $this->secret = $this->secret ?? getenv('NIKANZO_JWT_SECRET') ?: '';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return $this->unauthorized('missing_token');
        }

        $claims = $this->decodeJwt($token);
        if ($claims === null) {
            return $this->unauthorized('invalid_token');
        }

        $request = $request->withAttribute('auth.claims', $claims);

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$h, $p, $s] = $parts;
        $header = $this->jsonDecode($this->b64($h));
        $payload = $this->jsonDecode($this->b64($p));
        if ($header === null || $payload === null) {
            return null;
        }

        if (($payload['exp'] ?? 0) && time() >= (int) $payload['exp']) {
            return null;
        }

        $expected = $this->sign($h . '.' . $p);
        $sig = $this->b64($s, true);
        if ($expected === null || $sig === null || !hash_equals($expected, $sig)) {
            return null;
        }

        return $payload;
    }

    private function b64(string $data, bool $raw = false): ?string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        return $raw ? $decoded : $decoded;
    }

    private function sign(string $data): ?string
    {
        if ($this->secret === '') {
            return null;
        }
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    private function jsonDecode(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function unauthorized(string $reason): ResponseInterface
    {
        return new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'unauthorized', 'reason' => $reason], JSON_THROW_ON_ERROR)
        );
    }
}
