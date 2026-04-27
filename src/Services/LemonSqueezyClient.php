<?php

declare(strict_types=1);

namespace Nikanzo\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Thin HTTP client for the Lemon Squeezy REST API.
 *
 * Responsibilities:
 *   1. Create a hosted checkout session (returns the redirect URL).
 *   2. Verify incoming webhook signatures (HMAC-SHA256).
 *
 * The $httpAdapter constructor parameter allows injecting a test double so
 * unit tests never make real network calls.  In production, leave it null
 * and the built-in cURL implementation is used.
 *
 * Required env vars:
 *   LEMONSQUEEZY_API_KEY       – your API key (Bearer token)
 *   LEMONSQUEEZY_STORE_ID      – numeric store ID shown in LS dashboard
 *   LEMONSQUEEZY_WEBHOOK_SECRET – signing secret set in LS webhook settings
 */
final class LemonSqueezyClient implements LemonSqueezyClientInterface
{
    private const BASE_URL = 'https://api.lemonsqueezy.com/v1';
    private const TIMEOUT = 10;

    /**
     * @param string        $apiKey      Bearer token (LEMONSQUEEZY_API_KEY)
     * @param string        $storeId     Numeric store ID (LEMONSQUEEZY_STORE_ID)
     * @param \Closure|null $httpAdapter Optional HTTP adapter for testing.
     *                                   Signature: fn(string $path, array $payload): array
     * @param LoggerInterface $logger
     */
    private $apiKey;
    private $storeId;
    private $httpAdapter;
    private $logger;

    public function __construct(
        string $apiKey,
        string $storeId,
        ?\Closure $httpAdapter = null,
        LoggerInterface $logger = null
    ) {
        $this->apiKey = $apiKey;
        $this->storeId = $storeId;
        $this->httpAdapter = $httpAdapter;
        $this->logger = $logger ?: new NullLogger();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a hosted checkout session.
     *
     * The user is passed as custom_data so the webhook can identify which
     * database user to upgrade when the subscription activates.
     *
     * @param string $variantId  Lemon Squeezy variant ID (the SKU for the plan)
     * @param int    $userId     Your database user ID (echoed back in webhook meta)
     * @param string $userEmail  Pre-fill the checkout email field
     * @param string $successUrl Where LS redirects after a successful payment
     *
     * @return string The hosted checkout URL to redirect the buyer to
     *
     * @throws \RuntimeException on API or network error
     */
    public function createCheckout(
        string $variantId,
        int $userId,
        string $userEmail,
        string $successUrl
    ): string {
        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'checkout_data' => [
                        'email' => $userEmail,
                        'custom' => ['user_id' => (string) $userId],
                    ],
                    'product_options' => [
                        'redirect_url' => $successUrl,
                    ],
                ],
                'relationships' => [
                    'store' => ['data' => ['type' => 'stores', 'id' => $this->storeId]],
                    'variant' => ['data' => ['type' => 'variants', 'id' => $variantId]],
                ],
            ],
        ];

        $this->logger->debug('Creating LS checkout', [
            'variant_id' => $variantId,
            'user_id' => $userId,
        ]);

        $response = $this->post('/checkouts', $payload);

        $url = $response['data']['attributes']['url'] ?? null;

        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('Lemon Squeezy did not return a checkout URL.');
        }

        return $url;
    }

    /**
     * Verify the HMAC-SHA256 signature on an incoming webhook.
     *
     * Lemon Squeezy sends the hex digest of the raw request body signed with
     * your webhook signing secret in the X-Signature header.
     *
     * Always returns false (never throws) so callers can safely gate on the
     * return value without needing a try/catch.
     *
     * @param string $rawBody  The raw, unparsed request body string
     * @param string $signature The value of the X-Signature header
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        // Use env for secret to match interface
        $secret = getenv('LEMONSQUEEZY_WEBHOOK_SECRET') ?: '';
        if ($signature === '' || $secret === '' || $rawBody === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, strtolower($signature));
    }

    // ── HTTP layer ────────────────────────────────────────────────────────────

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    private function post(string $path, array $payload): array
    {
        if ($this->httpAdapter !== null) {
            /** @var array<mixed> $result */
            $result = ($this->httpAdapter)($path, $payload);
            return $result;
        }

        return $this->curlPost($path, $payload);
    }

    /**
     * Execute a real cURL POST against the Lemon Squeezy API.
     *
     * @param array<mixed> $payload
     * @return array<mixed>
     *
     * @throws \RuntimeException on cURL error, non-2xx response, or invalid JSON
     */
    private function curlPost(string $path, array $payload): array
    {
        $url = self::BASE_URL . $path;
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init() failed — is the curl extension loaded?');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/vnd.api+json',
                'Accept: application/vnd.api+json',
            ],
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new \RuntimeException('Lemon Squeezy network error: ' . $error);
        }

        /** @var array<mixed> $data */
        $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        if ($status >= 400) {
            $detail = $data['errors'][0]['detail'] ?? "HTTP {$status}";
            $this->logger->error('Lemon Squeezy API error', ['status' => $status, 'detail' => $detail]);
            throw new \RuntimeException('Lemon Squeezy API error: ' . $detail);
        }

        return $data;
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Build a client from environment variables.
     *
     * Reads: LEMONSQUEEZY_API_KEY, LEMONSQUEEZY_STORE_ID
     */
    public static function fromEnv(LoggerInterface $logger = new NullLogger()): self
    {
        $apiKey = (string) (getenv('LEMONSQUEEZY_API_KEY') ?: '');
        $storeId = (string) (getenv('LEMONSQUEEZY_STORE_ID') ?: '');

        if ($apiKey === '' || $storeId === '') {
            throw new \RuntimeException(
                'LEMONSQUEEZY_API_KEY and LEMONSQUEEZY_STORE_ID must be set in .env'
            );
        }

        return new self($apiKey, $storeId, null, $logger);
    }
}
