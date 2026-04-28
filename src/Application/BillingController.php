<?php

declare(strict_types=1);

namespace Nikanzo\Application;

use Nikanzo\Core\Attributes\Route;
use Nikanzo\Core\Controller\AbstractController;
use Nikanzo\Services\LemonSqueezyClientInterface;
use Nikanzo\Services\LicenseManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles Lemon Squeezy billing: checkout session creation and webhook processing.
 *
 * Routes
 * ──────
 *   POST /api/billing/checkout   Create a hosted checkout URL (requires JWT auth)
 *   POST /api/billing/webhook    Receive and process LS webhook events (no auth,
 *                                verified via HMAC-SHA256 signature)
 *
 * Webhook events handled
 * ──────────────────────
 *   subscription_activated      → upgrade user to premium, set renews_at expiry
 *   subscription_cancelled      → extend access to ends_at (grace period); if
 *                                  no ends_at, revoke immediately
 *   subscription_expired        → revoke premium access
 *   subscription_payment_success → extend subscription to next renews_at
 *
 * Custom data flow
 * ────────────────
 *   createCheckout passes { user_id: "<id>" } in checkout_data.custom.
 *   Lemon Squeezy echoes this back in meta.custom_data of every webhook,
 *   so we can identify the user without storing any LS-side mapping.
 *
 * Env vars required
 * ─────────────────
 *   LEMONSQUEEZY_WEBHOOK_SECRET – signing secret from LS dashboard webhook settings
 */
final class BillingController extends AbstractController
{
    private $lsClient;
    private $licenseManager;
    private $logger;

    public function __construct(
        LemonSqueezyClientInterface $lsClient,
        LicenseManagerInterface $licenseManager,
        LoggerInterface $logger = new NullLogger()
    ) {
        $this->lsClient = $lsClient;
        $this->licenseManager = $licenseManager;
        $this->logger = $logger;
    }

    // ── POST /api/billing/checkout ────────────────────────────────────────────

    /**
     * Create a Lemon Squeezy hosted checkout session.
     *
     * Request body (JSON):
     *   { "variant_id": "12345", "success_url": "https://yourapp.com/dashboard" }
     *
     * success_url is optional; falls back to APP_URL + /dashboard.
     *
     * Response:
     *   200 { "url": "https://app.lemonsqueezy.com/checkout/..." }
     *   401 if no valid JWT
     *   422 if variant_id is missing
     *   502 if the LS API call fails
     */
    #[Route('/api/billing/checkout', methods: ['POST'])]
    public function checkout(ServerRequestInterface $request): ResponseInterface
    {
        $claims = $request->getAttribute('auth.claims');

        if (!is_array($claims) || empty($claims['sub'])) {
            return $this->error('Authentication required', 401);
        }

        $userId = (int) $claims['sub'];
        $user = $this->licenseManager->findUser($userId);

        if ($user === null) {
            return $this->error('User not found', 404);
        }

        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);
        $variantId = trim((string) ($body['variant_id'] ?? ''));

        if ($variantId === '') {
            return $this->error('variant_id is required', 422);
        }

        $appUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
        $successUrl = trim((string) ($body['success_url'] ?? ''));
        if ($successUrl === '') {
            $successUrl = $appUrl !== '' ? $appUrl . '/dashboard' : '/dashboard';
        }

        $userEmail = (string) ($user['email'] ?? '');

        try {
            $url = $this->lsClient->createCheckout(
                $variantId,
                $user['id'],
                $user['email'],
                $successUrl
            );
        } catch (\Throwable $e) {
            $this->logger->error('Checkout creation failed', [
                'user_id' => $userId,
                'exception' => $e->getMessage(),
            ]);

            return $this->error('Could not create checkout session. Please try again.', 502);
        }

        return $this->json(['url' => $url]);
    }

    // ── POST /api/billing/webhook ─────────────────────────────────────────────

    /**
     * Receive and process a Lemon Squeezy webhook.
     *
     * Signature verification uses HMAC-SHA256 over the raw request body with
     * the LEMONSQUEEZY_WEBHOOK_SECRET env var.  Always returns 204 on success
     * so LS does not retry on application errors — log errors instead.
     *
     * LS retries webhooks that receive a non-2xx response, so it is important
     * to return 204 even when the event is unhandled or partially fails.
     */
    #[Route('/api/billing/webhook', methods: ['POST'])]
    public function webhook(ServerRequestInterface $request): ResponseInterface
    {
        $rawBody = (string) $request->getBody();
        $signature = $request->getHeaderLine('X-Signature');
        $secret = (string) (getenv('LEMONSQUEEZY_WEBHOOK_SECRET') ?: '');

        if (!$this->lsClient->verifyWebhookSignature($rawBody, $signature)) {
            $this->logger->warning('Webhook signature verification failed', [
                'signature' => substr($signature, 0, 8) . '…',
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            ]);

            return $this->error('Invalid signature', 401);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('Malformed JSON payload', 400);
        }

        $eventName = (string) ($payload['meta']['event_name'] ?? '');
        $customData = (array) ($payload['meta']['custom_data'] ?? []);
        $attrs = (array) ($payload['data']['attributes'] ?? []);
        $subId = (string) ($payload['data']['id'] ?? '');
        $userId = (int) ($customData['user_id'] ?? 0);

        $this->logger->info('Lemon Squeezy webhook received', [
            'event' => $eventName,
            'user_id' => $userId,
            'subscription_id' => $subId,
        ]);

        try {
            $this->dispatch($eventName, $userId, $subId, $attrs);
        } catch (\Throwable $e) {
            // Log but do NOT re-throw — always return 2xx to prevent LS retries
            $this->logger->error('Webhook handler threw', [
                'event' => $eventName,
                'user_id' => $userId,
                'exception' => $e->getMessage(),
            ]);
        }

        return $this->noContent();
    }

    // ── Event dispatch ────────────────────────────────────────────────────────

    /**
     * Route a verified webhook event to the appropriate handler.
     *
     * @param array<string, mixed> $attrs  Subscription attributes from the payload
     */
    private function dispatch(string $event, int $userId, string $subId, array $attrs): void
    {
        if ($userId === 0) {
            $this->logger->warning('Webhook missing user_id in custom_data', ['event' => $event]);
            return;
        }

        match ($event) {
            'subscription_activated' => $this->onActivated($userId, $subId, $attrs),
            'subscription_cancelled' => $this->onCancelled($userId, $attrs),
            'subscription_expired' => $this->onExpired($userId),
            'subscription_payment_success' => $this->onPaymentSuccess($userId, $attrs),
            default => $this->logger->debug('Unhandled LS event, ignoring', ['event' => $event]),
        };
    }

    // ── Event handlers ────────────────────────────────────────────────────────

    /**
     * Subscription became active (first payment succeeded).
     *
     * Sets premium_until to the next renewal date.
     *
     * @param array<string, mixed> $attrs
     */
    private function onActivated(int $userId, string $subId, array $attrs): void
    {
        $renewsAt = $this->parseDate($attrs['renews_at'] ?? null);

        $this->licenseManager->upgradeUser($userId, $subId, $renewsAt);

        $this->logger->info('User upgraded to premium via LS', [
            'user_id' => $userId,
            'subscription_id' => $subId,
            'renews_at' => $renewsAt?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * User cancelled — keep access until the end of the current billing period.
     *
     * If ends_at is absent, revoke immediately.
     *
     * @param array<string, mixed> $attrs
     */
    private function onCancelled(int $userId, array $attrs): void
    {
        $endsAt = $this->parseDate($attrs['ends_at'] ?? null);

        if ($endsAt !== null) {
            $this->licenseManager->extendSubscription($userId, $endsAt);
            $this->logger->info('Subscription cancelled — access until end of period', [
                'user_id' => $userId,
                'ends_at' => $endsAt->format(\DateTimeInterface::ATOM),
            ]);
        } else {
            $this->licenseManager->revokeUser($userId);
            $this->logger->info('Subscription cancelled — access revoked immediately', [
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Subscription fully expired — remove premium access.
     */
    private function onExpired(int $userId): void
    {
        $this->licenseManager->revokeUser($userId);
        $this->logger->info('Subscription expired — access revoked', ['user_id' => $userId]);
    }

    /**
     * Renewal payment succeeded — push premium_until to the next cycle.
     *
     * @param array<string, mixed> $attrs
     */
    private function onPaymentSuccess(int $userId, array $attrs): void
    {
        $renewsAt = $this->parseDate($attrs['renews_at'] ?? null);

        if ($renewsAt === null) {
            return;
        }

        try {
            $this->licenseManager->extendSubscription($userId, $renewsAt);
        } catch (\RuntimeException $e) {
            // Edge case: payment event arrives before subscription_activated.
            // Log and skip — activation event will set the correct state.
            $this->logger->warning('payment_success before activation, skipping extend', [
                'user_id' => $userId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Parse an ISO-8601 timestamp string from Lemon Squeezy into a DateTimeImmutable.
     *
     * Returns null if the value is missing or unparseable.
     */
    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            $this->logger->warning('Could not parse LS date', ['value' => $value]);
            return null;
        }
    }
}
