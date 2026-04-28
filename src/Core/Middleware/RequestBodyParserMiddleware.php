<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Parses the request body into structured data and sets it via withParsedBody().
 *
 * Supported Content-Types:
 *   application/json            → decoded JSON array
 *   application/x-www-form-urlencoded → already parsed by PSR-7 server factory
 *   multipart/form-data         → already parsed by PSR-7 server factory
 */
final class RequestBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getParsedBody() !== null) {
            return $handler->handle($request);
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();

            if ($body !== '') {
                try {
                    $parsed = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                    $request = $request->withParsedBody(is_array($parsed) ? $parsed : ['_raw' => $parsed]);
                } catch (\JsonException) {
                    // leave parsedBody as null; controller can inspect raw body
                }
            }
        }

        return $handler->handle($request);
    }
}
