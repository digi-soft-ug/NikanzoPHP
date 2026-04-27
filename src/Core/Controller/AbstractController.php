<?php

declare(strict_types=1);

namespace Nikanzo\Core\Controller;

use Nikanzo\Core\Template\TemplateRenderer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Optional base class for controllers.
 *
 * Provides convenience helpers — nothing here is required; controllers can
 * implement their own response building and still work with the framework.
 */
abstract class AbstractController
{
    private static ?Psr17Factory $factory = null;

    private static function factory(): Psr17Factory
    {
        return self::$factory ??= new Psr17Factory();
    }

    /**
     * Return a JSON response.
     *
     * @param array<mixed>|object $data
     */
    protected function json(array|object $data, int $status = 200, int $flags = JSON_THROW_ON_ERROR): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data, $flags)
        );
    }

    /**
     * Return an HTML response rendered from a Twig template.
     *
     * @param array<string, mixed> $context
     */
    protected function render(
        TemplateRenderer $renderer,
        string $template,
        array $context = [],
        int $status = 200,
    ): ResponseInterface {
        return new Response(
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8'],
            $renderer->render($template, $context)
        );
    }

    /**
     * Return a plain-text response.
     */
    protected function text(string $body, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'text/plain; charset=UTF-8'], $body);
    }

    /**
     * Return a redirect response.
     */
    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return new Response($status, ['Location' => $url]);
    }

    /**
     * Return a 204 No Content response.
     */
    protected function noContent(): ResponseInterface
    {
        return new Response(204);
    }

    /**
     * Return a structured error JSON response.
     *
     * @param array<string, mixed> $extra
     */
    protected function error(string $message, int $status = 400, array $extra = []): ResponseInterface
    {
        return $this->json(array_merge(['error' => $message], $extra), $status);
    }

    /**
     * Return a 201 Created response with optional body.
     *
     * @param array<mixed>|object|null $data
     */
    protected function created(array|object|null $data = null, string $location = ''): ResponseInterface
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($location !== '') {
            $headers['Location'] = $location;
        }

        return new Response(
            201,
            $headers,
            $data !== null ? json_encode($data, JSON_THROW_ON_ERROR) : ''
        );
    }
}
