<?php

declare(strict_types=1);

namespace Nikanzo\Core\Attributes;

use Attribute;

/**
 * Marks a controller method (or entire class) as premium-gated.
 *
 * When applied, the Kernel will call LicenseManager::isPremium() after routing
 * and before the controller is invoked.  Non-premium callers receive a 403 JSON
 * response for API requests or a redirect to /upgrade for HTML requests.
 *
 * Usage on a method:
 *
 *   #[Route('/dashboard/advanced', methods: ['GET'])]
 *   #[PremiumRequired]
 *   public function advanced(ServerRequestInterface $request): ResponseInterface { ... }
 *
 * Usage on a class (all routes in the controller become premium-gated):
 *
 *   #[PremiumRequired]
 *   final class AnalyticsController extends AbstractController { ... }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class PremiumRequired
{
    /**
     * @param string $redirectTo  URL to redirect HTML clients to when access is denied.
     *                            API clients (Accept: application/json) always receive 403 JSON.
     */
    public function __construct(public readonly string $redirectTo = '/upgrade')
    {
    }
}
