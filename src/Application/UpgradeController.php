<?php

declare(strict_types=1);

namespace Nikanzo\Application;

use Nikanzo\Core\Attributes\Route;
use Nikanzo\Core\Controller\AbstractController;
use Nikanzo\Core\Template\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles the public upgrade / pricing page.
 *
 * This controller has NO premium gate — it must be accessible to free-tier
 * users who were redirected here by PremiumAccessMiddleware or #[PremiumRequired].
 */
final class UpgradeController extends AbstractController
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    /**
     * Show the upgrade / pricing page.
     *
     * Query params populated automatically by PremiumAccessMiddleware:
     *   ?reason=<url-encoded message>
     */
    #[Route('/upgrade', methods: ['GET'])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reason = isset($params['reason']) ? (string) $params['reason'] : null;

        return $this->render($this->renderer, 'upgrade.html.twig', [
            'reason' => $reason,
            'plans'  => $this->plans(),
        ]);
    }

    /**
     * Pricing plan definitions.
     *
     * Variant IDs come from .env — set LEMONSQUEEZY_VARIANT_MONTHLY and
     * LEMONSQUEEZY_VARIANT_ANNUAL to the numeric IDs from your LS dashboard.
     *
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'name'       => 'Monthly',
                'price'      => '9.99',
                'currency'   => 'USD',
                'interval'   => 'month',
                'variant_id' => (string) (getenv('LEMONSQUEEZY_VARIANT_MONTHLY') ?: ''),
                'features'   => [
                    'All premium analytics',
                    'Advanced dashboard',
                    'Priority support',
                    'API rate limit: 1 000 req/min',
                ],
            ],
            [
                'name'       => 'Annual',
                'price'      => '99.00',
                'currency'   => 'USD',
                'interval'   => 'year',
                'variant_id' => (string) (getenv('LEMONSQUEEZY_VARIANT_ANNUAL') ?: ''),
                'features'   => [
                    'Everything in Monthly',
                    '2 months free',
                    'Dedicated onboarding',
                    'SLA guarantee',
                ],
            ],
        ];
    }
}
