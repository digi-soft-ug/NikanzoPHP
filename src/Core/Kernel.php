<?php

declare(strict_types=1);

namespace Nikanzo\Core;

use Nikanzo\Core\Attributes\PremiumRequired;
use Nikanzo\Core\Attributes\RequiredScope;
use Nikanzo\Core\Container\Container;
use Nikanzo\Core\Http\HttpBridge;
use Nikanzo\Services\LicenseManager;
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

    public function __construct(
        private readonly RouterInterface $router,
        private readonly Container $container,
        private readonly ?LicenseManager $licenseManager = null,
    ) {
    }

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(Request $request): ResponseInterface
    {
        $psrRequest = HttpBridge::toPsr7($request);

        return $this->buildPipeline()->handle($psrRequest);
    }

    // ── Pipeline builder ──────────────────────────────────────────────────────

    private function buildPipeline(): RequestHandlerInterface
    {
        $core = $this->coreHandler();

        return array_reduce(
            array_reverse($this->middleware),
            static fn (RequestHandlerInterface $next, MiddlewareInterface $mw) => new class ($mw, $next) implements RequestHandlerInterface {
                public function __construct(
                    private readonly MiddlewareInterface $mw,
                    private readonly RequestHandlerInterface $next,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->mw->process($request, $this->next);
                }
            },
            $core
        );
    }

    // ── Core handler (router → scope check → controller) ─────────────────────

    private function coreHandler(): RequestHandlerInterface
    {
        return new class ($this->router, $this->container, $this) implements RequestHandlerInterface {
            public function __construct(
                private readonly RouterInterface $router,
                private readonly Container $container,
                private readonly Kernel $kernel,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $match = $this->router->match($request);

                if ($match === null) {
                    return new Response(
                        404,
                        ['Content-Type' => 'application/json'],
                        json_encode(['error' => 'not_found'], JSON_THROW_ON_ERROR)
                    );
                }

                [$controllerClass, $method, $routeParams] = $match;

                $scopeError = $this->kernel->checkScopes($controllerClass, $method, $request);
                if ($scopeError !== null) {
                    return $scopeError;
                }

                $premiumError = $this->kernel->checkPremium($controllerClass, $method, $request);
                if ($premiumError !== null) {
                    return $premiumError;
                }

                // Attach route params as request attributes for controllers
                foreach ($routeParams as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                $controller = $this->container->get($controllerClass);
                if (!is_object($controller)) {
                    throw new \RuntimeException('Controller must be an object');
                }

                $result = $this->container->call(
                    $controller,
                    $method,
                    array_merge(['request' => $request], $routeParams)
                );

                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                $body = is_scalar($result) || $result === null
                    ? (string) ($result ?? '')
                    : json_encode($result, JSON_THROW_ON_ERROR);

                return new Response(200, ['Content-Type' => 'application/json'], $body);
            }
        };
    }

    // ── Scope / authorization check ───────────────────────────────────────────

    public function checkScopes(string $controllerClass, string $method, ServerRequestInterface $request): ?ResponseInterface
    {
        $ref        = new \ReflectionMethod($controllerClass, $method);
        $attributes = $ref->getAttributes(RequiredScope::class);

        if ($attributes === []) {
            return null;
        }

        $required = [];
        foreach ($attributes as $attr) {
            $required = array_merge($required, $attr->newInstance()->scopes);
        }
        $required = array_values(array_unique($required));

        $claims = $request->getAttribute('auth.claims');
        $scopes = [];

        if (is_array($claims) && isset($claims['scopes'])) {
            $raw = $claims['scopes'];
            $scopes = is_string($raw)
                ? (preg_split('/\s+/', trim($raw)) ?: [])
                : (is_array($raw) ? $raw : []);
        }

        $missing = array_values(array_diff($required, $scopes));

        if ($missing !== []) {
            return new Response(
                403,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'forbidden', 'required_scopes' => $missing], JSON_THROW_ON_ERROR)
            );
        }

        return null;
    }

    // ── Premium / license check ───────────────────────────────────────────────

    /**
     * Enforce the #[PremiumRequired] attribute on a controller method (or its class).
     *
     * Returns a denial response when:
     *   - The attribute is present AND
     *   - Either LicenseManager is not configured, the user is not authenticated,
     *     or their subscription is not active.
     *
     * JSON clients receive 403; HTML clients receive a 302 redirect.
     */
    public function checkPremium(string $controllerClass, string $method, ServerRequestInterface $request): ?ResponseInterface
    {
        // Check method-level attribute first, then fall back to class-level
        $methodRef     = new \ReflectionMethod($controllerClass, $method);
        $classRef      = new \ReflectionClass($controllerClass);
        $methodAttrs   = $methodRef->getAttributes(PremiumRequired::class);
        $classAttrs    = $classRef->getAttributes(PremiumRequired::class);
        $allAttrs      = array_merge($methodAttrs, $classAttrs);

        if ($allAttrs === []) {
            return null;
        }

        /** @var PremiumRequired $attr */
        $attr        = $allAttrs[0]->newInstance();
        $redirectTo  = $attr->redirectTo;

        if ($this->licenseManager === null) {
            // LicenseManager not wired — fail closed (deny access)
            return $this->premiumDenied($request, $redirectTo, 'Premium features are not configured.');
        }

        $claims = $request->getAttribute('auth.claims');

        if (!is_array($claims) || !isset($claims['sub'])) {
            return $this->premiumDenied($request, $redirectTo, 'Authentication required to access this feature.');
        }

        $userId = (int) $claims['sub'];
        $user   = $this->licenseManager->findUser($userId);

        if ($user === null || !$this->licenseManager->isPremium($user)) {
            return $this->premiumDenied($request, $redirectTo, 'This feature is restricted to premium members.');
        }

        return null;
    }

    private function premiumDenied(
        ServerRequestInterface $request,
        string $redirectTo,
        string $message,
    ): ResponseInterface {
        $accept      = strtolower($request->getHeaderLine('Accept'));
        $format      = (string) ($request->getAttribute('accept.format') ?? 'any');
        $isJsonClient = $format === 'json'
            || str_contains($accept, 'application/json')
            || str_contains($accept, 'application/vnd.');

        if ($isJsonClient) {
            return new Response(
                403,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error'       => 'premium_required',
                    'message'     => $message,
                    'upgrade_url' => $redirectTo,
                ], JSON_THROW_ON_ERROR)
            );
        }

        return new Response(302, ['Location' => $redirectTo . '?reason=' . urlencode($message)]);
    }
}
