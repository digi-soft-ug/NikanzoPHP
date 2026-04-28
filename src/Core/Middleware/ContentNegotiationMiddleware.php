<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Stores the negotiated response format as a request attribute "accept.format".
 *
 * Values: "json" | "html" | "text" | "any"
 *
 * Controllers can read this attribute to decide what to return:
 *   $format = $request->getAttribute('accept.format', 'json');
 */
final class ContentNegotiationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $accept = strtolower($request->getHeaderLine('Accept'));
        $format = $this->negotiate($accept);
        $request = $request->withAttribute('accept.format', $format);

        return $handler->handle($request);
    }

    private function negotiate(string $accept): string
    {
        if ($accept === '' || str_contains($accept, '*/*')) {
            return 'any';
        }

        // Parse q-values and sort by preference
        $types = [];
        foreach (explode(',', $accept) as $part) {
            $part  = trim($part);
            $q     = 1.0;
            if (str_contains($part, ';q=')) {
                [$mime, $qStr] = explode(';q=', $part, 2);
                $q    = (float) $qStr;
                $part = trim($mime);
            }
            $types[$part] = $q;
        }

        arsort($types);

        foreach (array_keys($types) as $mime) {
            if (str_contains($mime, 'json'))                                  return 'json';
            if (str_contains($mime, 'html'))                                  return 'html';
            if (str_contains($mime, 'text/plain'))                            return 'text';
            if (str_contains($mime, 'xml'))                                   return 'xml';
        }

        return 'any';
    }
}
