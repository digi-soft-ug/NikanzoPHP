<?php

declare(strict_types=1);

namespace Nikanzo\Application;

use Nikanzo\Core\Attributes\PremiumRequired;
use Nikanzo\Core\Attributes\RequiredScope;
use Nikanzo\Core\Attributes\Route;
use Nikanzo\Core\Controller\AbstractController;
use Nikanzo\Core\Database\QueryBuilder;
use Nikanzo\Core\Template\TemplateRenderer;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Demo controller that demonstrates the premium feature gate.
 *
 * Routes:
 *   GET /dashboard          – public summary (no premium required)
 *   GET /dashboard/advanced – full analytics (premium only)
 *   GET /dashboard/api      – JSON advanced stats (premium only, API clients)
 */
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TemplateRenderer $renderer,
    ) {
    }

    // ── Public ────────────────────────────────────────────────────────────────

    /**
     * Basic dashboard — available to all authenticated users.
     */
    #[Route('/dashboard', methods: ['GET'])]
    #[RequiredScope('dashboard:read')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $claims   = $request->getAttribute('auth.claims', []);
        $userId   = (int) ($claims['sub'] ?? 0);
        $isPremium = (bool) ($request->getAttribute('premium.user') !== null);

        $stats = [
            'total_todos' => (new QueryBuilder($this->pdo, 'todos'))->count(),
        ];

        return $this->render($this->renderer, 'dashboard/index.html.twig', [
            'user_id'    => $userId,
            'is_premium' => $isPremium,
            'stats'      => $stats,
        ]);
    }

    // ── Premium ───────────────────────────────────────────────────────────────

    /**
     * Advanced analytics dashboard — premium members only (HTML).
     *
     * The #[PremiumRequired] attribute causes the Kernel to verify the user's
     * membership before this method is ever invoked.  HTML clients are
     * redirected to /upgrade; JSON clients receive a 403.
     */
    #[Route('/dashboard/advanced', methods: ['GET'])]
    #[RequiredScope('dashboard:read')]
    #[PremiumRequired(redirectTo: '/upgrade')]
    public function advanced(ServerRequestInterface $request): ResponseInterface
    {
        $claims = $request->getAttribute('auth.claims', []);
        $user   = $request->getAttribute('premium.user', []);

        // Premium analytics: richer data only shown to premium members
        $analytics = $this->buildAnalytics();

        return $this->render($this->renderer, 'dashboard/advanced.html.twig', [
            'user'      => $user,
            'claims'    => $claims,
            'analytics' => $analytics,
        ]);
    }

    /**
     * JSON version of the advanced stats — for API / SPA clients.
     *
     * Same gate as the HTML endpoint; JSON clients get 403 automatically.
     */
    #[Route('/dashboard/api/advanced', methods: ['GET'])]
    #[RequiredScope('dashboard:read')]
    #[PremiumRequired]
    public function advancedApi(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json($this->buildAnalytics());
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Build the premium analytics payload.
     *
     * In a real application this would run complex aggregations.
     * Here it returns illustrative data for the demo.
     *
     * @return array<string, mixed>
     */
    private function buildAnalytics(): array
    {
        $qb = new QueryBuilder($this->pdo, 'todos');

        return [
            'total_todos'     => $qb->count(),
            'completed_todos' => (new QueryBuilder($this->pdo, 'todos'))->where('completed', 1)->count(),
            'open_todos'      => (new QueryBuilder($this->pdo, 'todos'))->where('completed', 0)->count(),
            'recent_todos'    => (new QueryBuilder($this->pdo, 'todos'))
                ->orderBy('id', 'DESC')
                ->limit(5)
                ->get(),
            'generated_at'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
