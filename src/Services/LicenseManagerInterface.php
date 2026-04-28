<?php

declare(strict_types=1);

namespace Nikanzo\Services;

interface LicenseManagerInterface
{
    public function isPremium(array $user): bool;

    /** @return array<string, mixed>|null */
    public function findUser(int $userId): ?array;

    /** @return array<string, mixed>|null */
    public function findUserByEmail(string $email): ?array;

    public function upgradeUser(int $userId, string $subscriptionId, ?\DateTimeImmutable $until = null): void;

    public function revokeUser(int $userId): void;

    public function extendSubscription(int $userId, \DateTimeImmutable $newUntil): void;

    /** @return list<array<string, mixed>> */
    public function activePremiumUsers(): array;
}
