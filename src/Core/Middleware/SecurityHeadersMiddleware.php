<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds security-related HTTP response headers.
 *
 * Default policy is strict. Override individual directives via constructor.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $overrides  Key/value pairs to override or extend the default headers.
     * @param bool                  $hsts       Whether to add Strict-Transport-Security (disable for local HTTP dev).
     */
    public function __construct(array $overrides = [], bool $hsts = true)
    {
        $defaults = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'X-XSS-Protection'        => '1; mode=block',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
        ];

        if ($hsts) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }

        $this->headers = array_merge($defaults, $overrides);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
