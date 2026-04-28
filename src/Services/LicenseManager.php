<?php

declare(strict_types=1);

namespace Nikanzo\Services;

use Nikanzo\Core\Database\QueryBuilder;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manages user membership / license status.
 *
 * All database access goes through QueryBuilder so values are always
 * bound via prepared statements.
 *
 * Membership levels:
 *   'free'    – default; no premium features accessible
 *   'premium' – full access while premium_until is null or in the future
 *
 * Integration with payment providers (e.g. Stripe) is intentionally left to
 * the calling layer (webhook handler, etc.). This service only handles the
 * database side of membership management.
 */
final class LicenseManager implements LicenseManagerInterface
{
    private const TABLE = 'users';

    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Determine whether a user array (as returned by QueryBuilder) has an
     * active premium subscription.
     *
     * A user is premium when ALL of the following are true:
     *   1. membership_level === 'premium'
     *   2. premium_until is null  (never expires) OR premium_until > now
     *
     * @param array<string, mixed> $user  A row from the users table.
     */
    public function isPremium(array $user): bool
    {
        if (($user['membership_level'] ?? 'free') !== 'premium') {
            return false;
        }

        $until = $user['premium_until'] ?? null;

        if ($until === null || $until === '') {
            return true; // no expiry set → indefinitely premium
        }

        try {
            $expiry = new \DateTimeImmutable((string) $until);

            return $expiry > new \DateTimeImmutable();
        } catch (\Throwable) {
            // Unparseable date → treat as expired to fail safe
            return false;
        }
    }

    /**
     * Fetch a user row by their primary key.
     *
     * @return array<string, mixed>|null
     */
    public function findUser(int $userId): ?array
    {
        return (new QueryBuilder($this->pdo, self::TABLE))->find($userId);
    }

    /**
     * Fetch a user row by their email address.
     *
     * @return array<string, mixed>|null
     */
    public function findUserByEmail(string $email): ?array
    {
        return (new QueryBuilder($this->pdo, self::TABLE))
            ->where('email', $email)
            ->first();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Upgrade a user to the premium tier.
     *
     * @param int                    $userId         Primary key of the user.
     * @param string                 $subscriptionId External payment provider subscription ID
     *                                               (e.g. Stripe sub_xxxxxxxx).
     * @param \DateTimeImmutable|null $until         Expiry date/time; pass null for indefinite access.
     *
     * @throws \RuntimeException if the user is not found.
     */
    public function upgradeUser(
        int $userId,
        string $subscriptionId,
        ?\DateTimeImmutable $until = null,
    ): void {
        $this->assertUserExists($userId);

        (new QueryBuilder($this->pdo, self::TABLE))
            ->where('id', $userId)
            ->update([
                'membership_level' => 'premium',
                'subscription_id'  => $subscriptionId,
                'premium_until'    => $until?->format('Y-m-d H:i:s'),
            ]);

        $this->logger->info('User upgraded to premium', [
            'user_id'         => $userId,
            'subscription_id' => $subscriptionId,
            'premium_until'   => $until?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Revoke premium access from a user (e.g. after subscription cancellation).
     *
     * The subscription_id is preserved for audit purposes; only membership_level
     * and premium_until are cleared.
     *
     * @throws \RuntimeException if the user is not found.
     */
    public function revokeUser(int $userId): void
    {
        $this->assertUserExists($userId);

        (new QueryBuilder($this->pdo, self::TABLE))
            ->where('id', $userId)
            ->update([
                'membership_level' => 'free',
                'premium_until'    => null,
            ]);

        $this->logger->info('Premium access revoked', ['user_id' => $userId]);
    }

    /**
     * Extend an existing premium subscription's expiry date.
     *
     * @throws \RuntimeException if the user is not found or is not currently premium.
     */
    public function extendSubscription(int $userId, \DateTimeImmutable $newUntil): void
    {
        $user = $this->findUser($userId);
        if ($user === null) {
            throw new \RuntimeException(sprintf('User %d not found', $userId));
        }

        if (($user['membership_level'] ?? 'free') !== 'premium') {
            throw new \RuntimeException(sprintf('User %d is not a premium member', $userId));
        }

        (new QueryBuilder($this->pdo, self::TABLE))
            ->where('id', $userId)
            ->update(['premium_until' => $newUntil->format('Y-m-d H:i:s')]);

        $this->logger->info('Subscription extended', [
            'user_id'    => $userId,
            'new_expiry' => $newUntil->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * List all users with an active premium subscription.
     *
     * @return list<array<string, mixed>>
     */
    public function activePremiumUsers(): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // QueryBuilder does not yet support OR in where; use raw() for the complex condition
        return (new QueryBuilder($this->pdo, self::TABLE))->raw(
            "SELECT * FROM users
              WHERE membership_level = 'premium'
                AND (premium_until IS NULL OR premium_until > :now)
              ORDER BY id",
            [':now' => $now]
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function assertUserExists(int $userId): void
    {
        if ($this->findUser($userId) === null) {
            throw new \RuntimeException(sprintf('User %d not found', $userId));
        }
    }
}
