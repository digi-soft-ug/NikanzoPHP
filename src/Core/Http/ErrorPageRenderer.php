<?php

declare(strict_types=1);

namespace Nikanzo\Core\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the HTML body for a 404/500 response, with the app free to supply
 * its own on-brand template via the constructor callback. Falls back to a
 * neutral built-in page whenever no callback is configured, the callback
 * returns nothing, or the callback itself throws — an error page must never
 * be the thing that crashes the request.
 */
final class ErrorPageRenderer
{
    /**
     * @param (\Closure(int, ServerRequestInterface, ?\Throwable): (string|null))|null $templateRenderer
     */
    public function __construct(private readonly ?\Closure $templateRenderer = null)
    {
    }

    /**
     * Same "does this client want JSON" heuristic used by Kernel::premiumDenied() —
     * checks the `accept.format` attribute set by ContentNegotiationMiddleware first,
     * then falls back to a raw Accept header substring match.
     */
    public static function isJsonClient(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));
        $format = (string) ($request->getAttribute('accept.format') ?? 'any');

        return $format === 'json'
            || str_contains($accept, 'application/json')
            || str_contains($accept, 'application/vnd.');
    }

    public function renderHtml(int $status, ServerRequestInterface $request, ?\Throwable $exception = null): string
    {
        if ($this->templateRenderer !== null) {
            try {
                $html = ($this->templateRenderer)($status, $request, $exception);
                if (is_string($html) && $html !== '') {
                    return $html;
                }
            } catch (\Throwable) {
                // Fall through to the built-in page below.
            }
        }

        return self::defaultHtml($status);
    }

    private static function defaultHtml(int $status): string
    {
        $title = match ($status) {
            404 => 'Page not found',
            500 => 'Something went wrong',
            default => 'Error ' . $status,
        };
        $message = match ($status) {
            404 => 'The page you were looking for doesn\'t exist or may have moved.',
            500 => 'An unexpected error occurred. Please try again in a moment.',
            default => 'An unexpected error occurred.',
        };

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$status} — {$title}</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                background: #12141a; color: #eef0f4;
            }
            .card { text-align: center; padding: 2.5rem; max-width: 30rem; }
            .code { font-size: 4rem; font-weight: 700; margin: 0; letter-spacing: -0.03em; opacity: 0.9; }
            h1 { font-size: 1.35rem; margin: 0.5rem 0 0.75rem; }
            p { margin: 0 0 1.5rem; opacity: 0.7; line-height: 1.5; }
            a {
                display: inline-block; padding: 0.6rem 1.4rem; border-radius: 0.5rem;
                background: #eef0f4; color: #12141a; text-decoration: none; font-weight: 600;
            }
        </style>
        </head>
        <body>
            <div class="card">
                <p class="code">{$status}</p>
                <h1>{$title}</h1>
                <p>{$message}</p>
                <a href="/">Go home</a>
            </div>
        </body>
        </html>
        HTML;
    }
}
