<?php

declare(strict_types=1);

namespace Nikanzo\Services;

use Psr\Log\LoggerInterface;

/**
 * Interface for LemonSqueezyClient to allow mocking in tests.
 */
interface LemonSqueezyClientInterface
{
    /**
     * Create a hosted checkout session.
     *
     * @param string $variantId
     * @param int    $userId
     * @param string $userEmail
     * @param string $successUrl
     * @return string
     */
    public function createCheckout(string $variantId, int $userId, string $userEmail, string $successUrl): string;

    /**
     * Verify incoming webhook signatures.
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature, ?string $secret = null): bool;

    // Add other public methods as needed
}
