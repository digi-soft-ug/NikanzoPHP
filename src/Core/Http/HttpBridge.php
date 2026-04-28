<?php

declare(strict_types=1);

namespace Nikanzo\Core\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Converts a Symfony HttpFoundation Request into a PSR-7 ServerRequestInterface.
 *
 * Extracted from Kernel to keep the kernel focused on pipeline orchestration.
 */
final class HttpBridge
{
    private static ?Psr17Factory $factory = null;

    private static function factory(): Psr17Factory
    {
        return self::$factory ??= new Psr17Factory();
    }

    public static function toPsr7(Request $request): ServerRequestInterface
    {
        $factory = self::factory();
        $uri     = $factory->createUri($request->getUri());

        $psrRequest = $factory
            ->createServerRequest($request->getMethod(), $uri, $request->server->all())
            ->withQueryParams($request->query->all())
            ->withParsedBody($request->request->all() ?: null)
            ->withCookieParams($request->cookies->all());

        foreach ($request->headers->all() as $name => $values) {
            foreach ($values as $value) {
                $psrRequest = $psrRequest->withAddedHeader($name, $value);
            }
        }

        $content = $request->getContent();
        if ($content !== false && $content !== '') {
            $psrRequest = $psrRequest->withBody($factory->createStream($content));
        }

        return $psrRequest;
    }
}
