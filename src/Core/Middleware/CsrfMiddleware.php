<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nikanzo\Core\Security\CsrfTokenManager;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Validates CSRF tokens for state-changing HTTP methods.
 *
 * Safe methods (GET, HEAD, OPTIONS) are skipped.
 * The token is read from:
 *   1. Request body field  "_csrf_token"
 *   2. Header              "X-CSRF-Token"
 *
 * Inject the CsrfTokenManager as a service so controllers can call
 * $manager->getToken() and embed it in forms / JSON responses.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const BODY_FIELD   = '_csrf_token';
    private const HEADER_NAME  = 'X-CSRF-Token';

    public function __construct(private readonly CsrfTokenManager $manager)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $token = $this->extractToken($request);

        if ($token === null || !$this->manager->isValid($token)) {
            return new Response(
                403,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'csrf_token_invalid'], JSON_THROW_ON_ERROR)
            );
        }

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        // 1. Header (preferred for SPA / API clients)
        $header = $request->getHeaderLine(self::HEADER_NAME);
        if ($header !== '') {
            return $header;
        }

        // 2. Body field (HTML forms)
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[self::BODY_FIELD]) && is_string($body[self::BODY_FIELD])) {
            return $body[self::BODY_FIELD];
        }

        return null;
    }
}
