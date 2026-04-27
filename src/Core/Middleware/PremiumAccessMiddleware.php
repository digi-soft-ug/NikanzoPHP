<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Nikanzo\Services\LicenseManager;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Path-prefix gate for premium features.
 *
 * Any request whose URI path starts with one of the configured prefixes is
 * checked against the user's membership level.  Unauthenticated requests
 * (no auth.claims attribute) are always rejected.
 *
 * Response strategy:
 *   – JSON clients (Accept: application/json or accept.format === 'json')
 *     → 403 {"error":"premium_required","message":"...","upgrade_url":"/upgrade"}
 *   – HTML clients
 *     → 302 redirect to the configurable $upgradeUrl
 *
 * This middleware must run AFTER JwtAuthMiddleware (which sets auth.claims)
 * and, optionally, after ContentNegotiationMiddleware (which sets accept.format).
 *
 * Usage:
 *
 *   $kernel->addMiddleware(new PremiumAccessMiddleware(
 *       licenseManager:   $licenseManager,
 *       protectedPrefixes: ['/dashboard/advanced', '/premium/'],
 *       upgradeUrl:        '/upgrade',
 *   ));
 */
final class PremiumAccessMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $protectedPrefixes;

    /**
     * @param list<string> $protectedPrefixes  URI path prefixes that require a premium subscription.
     * @param string       $upgradeUrl         Where to redirect HTML clients who are not premium.
     */
    public function __construct(
        private readonly LicenseManager $licenseManager,
        array $protectedPrefixes = ['/dashboard/advanced', '/premium/'],
        private readonly string $upgradeUrl = '/upgrade',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->protectedPrefixes = $protectedPrefixes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!$this->isProtected($path)) {
            return $handler->handle($request);
        }

        $claims = $request->getAttribute('auth.claims');

        if (!is_array($claims)) {
            return $this->deny($request, 'Authentication required to access this feature.');
        }

        $userId = isset($claims['sub']) ? (int) $claims['sub'] : 0;
        if ($userId <= 0) {
            return $this->deny($request, 'Invalid authentication token (missing sub claim).');
        }

        $user = $this->licenseManager->findUser($userId);

        if ($user === null) {
            $this->logger->warning('PremiumAccessMiddleware: user not found', [
                'user_id' => $userId,
                'path'    => $path,
            ]);

            return $this->deny($request, 'User account not found.');
        }

        if (!$this->licenseManager->isPremium($user)) {
            $this->logger->info('Premium access denied — free tier', [
                'user_id' => $userId,
                'path'    => $path,
            ]);

            return $this->deny($request, 'This feature is restricted to premium members.');
        }

        // Attach the resolved user row so controllers don't have to re-query
        $request = $request->withAttribute('premium.user', $user);

        return $handler->handle($request);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function isProtected(string $path): bool
    {
        $normalized = '/' . ltrim($path, '/');

        foreach ($this->protectedPrefixes as $prefix) {
            $normalizedPrefix = '/' . ltrim($prefix, '/');
            if (str_starts_with($normalized, $normalizedPrefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build an appropriate denial response depending on the client's Accept preference.
     */
    private function deny(ServerRequestInterface $request, string $message): ResponseInterface
    {
        $format = $request->getAttribute('accept.format', 'any');
        $accept = strtolower($request->getHeaderLine('Accept'));

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
                    'upgrade_url' => $this->upgradeUrl,
                ], JSON_THROW_ON_ERROR)
            );
        }

        // HTML clients: redirect with an informative query parameter
        $location = $this->upgradeUrl . '?reason=' . urlencode($message);

        return new Response(302, ['Location' => $location]);
    }
}
