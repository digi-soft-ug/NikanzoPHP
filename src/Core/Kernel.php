<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Attributes\RequiredScope;
use Nikanzo\Core\Container\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

final class Kernel
{
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];
    private RouterInterface $router;
    private Container $container;

    public function __construct(RouterInterface $router, Container $container)
    {
        $this->router = $router;
        $this->container = $container;
    }

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(Request $request): ResponseInterface
    {
        $psrRequest = $this->convertRequest($request);

        $coreHandler = new class ($this->router, $this->container, fn (string $c, string $m, ServerRequestInterface $r) => $this->checkScopes($c, $m, $r)) implements RequestHandlerInterface {
            private RouterInterface $router;
            private Container $container;
            /** @var callable */
            private $scopeChecker;

            public function __construct(RouterInterface $router, Container $container, callable $scopeChecker)
            {
                $this->router = $router;
                $this->container = $container;
                $this->scopeChecker = $scopeChecker;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $match = $this->router->match($request);
                if ($match === null) {
                    return new Response(
                        404,
                        ['Content-Type' => 'application/json'],
                        json_encode(['error' => 'Not found'], JSON_THROW_ON_ERROR)
                    );
                }

                [$controllerClass, $method] = $match;

                $scopeResult = ($this->scopeChecker)($controllerClass, $method, $request);
                if ($scopeResult instanceof ResponseInterface) {
                    return $scopeResult;
                }

                $controller = $this->container->get($controllerClass);
                if (!is_object($controller)) {
                    throw new \RuntimeException('Controller must be an object');
                }

                $result = $this->container->call($controller, $method, ['request' => $request]);

                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                $body = is_scalar($result) || $result === null
                    ? (string) ($result ?? '')
                    : json_encode($result, JSON_THROW_ON_ERROR);

                return new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    $body
                );
            }
        };

        $handler = array_reduce(
            array_reverse($this->middleware),
            fn (RequestHandlerInterface $next, MiddlewareInterface $middleware) => new class ($middleware, $next) implements RequestHandlerInterface {
                private MiddlewareInterface $middleware;
                private RequestHandlerInterface $next;

                public function __construct(MiddlewareInterface $middleware, RequestHandlerInterface $next)
                {
                    $this->middleware = $middleware;
                    $this->next = $next;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            },
            $coreHandler
        );

        return $handler->handle($psrRequest);
    }

    private function convertRequest(Request $request): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $uri = $psr17Factory->createUri($request->getUri());
        $serverParams = $request->server->all();

        $psrRequest = $psr17Factory->createServerRequest(
            $request->getMethod(),
            $uri,
            $serverParams
        )
            ->withQueryParams($request->query->all())
            ->withParsedBody($request->request->all() ?: null)
            ->withCookieParams($request->cookies->all());

        foreach ($request->headers->all() as $name => $values) {
            foreach ($values as $value) {
                $psrRequest = $psrRequest->withAddedHeader($name, $value);
            }
        }

        $content = $request->getContent();
        if ($content !== false) {
            $psrRequest = $psrRequest->withBody($psr17Factory->createStream($content));
        }

        return $psrRequest;
    }

    private function checkScopes(string $controllerClass, string $method, ServerRequestInterface $request): ?ResponseInterface
    {
        $ref = new \ReflectionMethod($controllerClass, $method);
        $attributes = $ref->getAttributes(RequiredScope::class);
        if ($attributes === []) {
            return null;
        }

        $required = [];
        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();
            $required = array_merge($required, $instance->scopes);
        }
        $required = array_values(array_unique($required));

        $claims = $request->getAttribute('auth.claims');
        $scopes = [];
        if (is_array($claims) && isset($claims['scopes'])) {
            $claimScopes = $claims['scopes'];
            if (is_string($claimScopes)) {
                $scopes = preg_split('/\s+/', trim($claimScopes)) ?: [];
            } elseif (is_array($claimScopes)) {
                $scopes = $claimScopes;
            }
        }

        $missing = array_diff($required, $scopes);
        if ($missing !== []) {
            return new Response(
                403,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error' => 'forbidden',
                    'required_scopes' => array_values($missing),
                ], JSON_THROW_ON_ERROR)
            );
        }

        return null;
    }
}
