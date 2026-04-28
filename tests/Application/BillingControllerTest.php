<?php

declare(strict_types=1);

namespace Nikanzo\Tests\Application;

use Nikanzo\Application\BillingController;
use Nikanzo\Services\LemonSqueezyClientInterface;
use Nikanzo\Services\LicenseManagerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unit tests for BillingController.
 *
 * Dependencies are mocked — no database, no HTTP, no Kernel.
 * We invoke controller methods directly with hand-crafted PSR-7 requests.
 */
final class BillingControllerTest extends TestCase
{
    /** @var LemonSqueezyClientInterface|MockObject */
    private $lsClient;
    /** @var LicenseManagerInterface|MockObject */
    private $licenseManager;
    /** @var BillingController */
    private $controller;

    protected function setUp(): void
    {
        $this->lsClient = $this->createMock(LemonSqueezyClientInterface::class);
        $this->licenseManager = $this->createMock(LicenseManagerInterface::class);
        $this->controller = new BillingController($this->lsClient, $this->licenseManager);
    }
    // ...existing code...
    // ── POST /api/billing/checkout ─────────────────────────────────────────────
    public function testCheckoutReturns422WhenVariantIdIsBlank(): void
    {
        $this->licenseManager->method('findUser')->willReturn($this->fakeUser());
        $request = $this->authedRequest(['variant_id' => '   '], 1);
        $response = $this->controller->checkout($request);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testCheckoutReturns401WhenNoAuthClaims(): void
    {
        $request = $this->jsonRequest('POST', '/api/billing/checkout', ['variant_id' => '123']);
        $response = $this->controller->checkout($request);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertResponseJsonKey('error', $response);
    }


    public function testCheckoutReturns502WhenLsClientThrows(): void
    {
        $this->licenseManager->method('findUser')->willReturn($this->fakeUser());
        $this->lsClient->method('createCheckout')->willThrowException(
            new \RuntimeException('Lemon Squeezy API error: HTTP 500')
        );
        $request = $this->authedRequest(['variant_id' => 'v1'], 1);
        $response = $this->controller->checkout($request);
        $this->assertResponseJsonKey('error', $response);
        $this->assertSame(502, $response->getStatusCode());
    }

    // ── POST /api/billing/webhook ──────────────────────────────────────────────

    public function testWebhookReturns401ForInvalidSignature(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(false);
        $request = $this->webhookRequest('{}', 'bad-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCheckoutReturns401WhenClaimsMissingSubject(): void
    {
        $request = $this->jsonRequest('POST', '/api/billing/checkout', ['variant_id' => '123'])
            ->withAttribute('auth.claims', ['scope' => 'read']);
        $response = $this->controller->checkout($request);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testWebhookReturns400ForMalformedJson(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $request = $this->webhookRequest('not-json', 'sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testWebhookActivatedUpgradesUser(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager
            ->expects($this->once())
            ->method('upgradeUser')
            ->with(5, 'sub_abc123', $this->isInstanceOf(\DateTimeImmutable::class));
        $payload = $this->subscriptionPayload('subscription_activated', 5, 'sub_abc123', [
            'renews_at' => '2026-05-27T00:00:00.000000Z',
        ]);
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testWebhookExpiredRevokesUser(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager
            ->expects($this->once())
            ->method('revokeUser')
            ->with(5);
        $payload = $this->subscriptionPayload('subscription_expired', 5, 'sub_abc123');
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testWebhookCancelledWithEndsAtExtendsSubscription(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager
            ->expects($this->once())
            ->method('extendSubscription')
            ->with(5, $this->isInstanceOf(\DateTimeImmutable::class));
        $payload = $this->subscriptionPayload('subscription_cancelled', 5, 'sub_abc123', [
            'ends_at' => '2026-04-30T23:59:59.000000Z',
        ]);
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testWebhookCancelledWithoutEndsAtRevokesImmediately(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager
            ->expects($this->once())
            ->method('revokeUser')
            ->with(5);
        $payload = $this->subscriptionPayload('subscription_cancelled', 5, 'sub_abc123');
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testWebhookPaymentSuccessExtendsSubscription(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager
            ->expects($this->once())
            ->method('extendSubscription')
            ->with(5, $this->isInstanceOf(\DateTimeImmutable::class));
        $payload = $this->subscriptionPayload('subscription_payment_success', 5, 'sub_abc123', [
            'renews_at' => '2026-06-27T00:00:00.000000Z',
        ]);
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testWebhookUnknownEventIsIgnoredAndReturns204(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager->expects($this->never())->method('upgradeUser');
        $this->licenseManager->expects($this->never())->method('revokeUser');
        $this->licenseManager->expects($this->never())->method('extendSubscription');
        $payload = $this->subscriptionPayload('order_created', 5, 'order_xyz');
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testWebhookMissingUserIdIsIgnoredAndReturns204(): void
    {
        $this->lsClient->method('verifyWebhookSignature')->willReturn(true);
        $this->licenseManager->expects($this->never())->method('upgradeUser');
        $payload = [
            'meta' => ['event_name' => 'subscription_activated', 'custom_data' => []],
            'data' => ['id' => 'sub_123', 'attributes' => ['renews_at' => null]],
        ];
        $request = $this->webhookRequest(json_encode($payload), 'valid-sig');
        $response = $this->controller->webhook($request);
        $this->assertSame(204, $response->getStatusCode());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

        
    private function webhookRequest(string $rawBody, string $signature): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream($rawBody);

        return (new ServerRequest('POST', '/api/billing/webhook'))
            ->withHeader('X-Signature', $signature)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);
    }
    
    
    


    public function testCheckoutReturnsCheckoutUrl(): void
    {
        $this->licenseManager->method('findUser')->willReturn($this->fakeUser());
        $this->lsClient
            ->expects($this->once())
            ->method('createCheckout')
            ->with('variant-42', 1, 'user@test.com', $this->anything())
            ->willReturn('https://app.lemonsqueezy.com/checkout/buy/abc');

        $request = $this->authedRequest(['variant_id' => 'variant-42'], 1);
        $response = $this->controller->checkout($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('https://app.lemonsqueezy.com/checkout/buy/abc', $body['url']);
    }


    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(string $method, string $uri, array $body = []): ServerRequestInterface
    {
        return (new ServerRequest($method, $uri))
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function authedRequest(array $body, int $userId): ServerRequestInterface
    {
        return $this->jsonRequest('POST', '/api/billing/checkout', $body)
            ->withAttribute('auth.claims', ['sub' => (string) $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeUser(): array
    {
        return [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'user@test.com',
            'membership_level' => 'free',
            'subscription_id' => null,
            'premium_until' => null,
        ];
    }

    /**
     * Build a minimal Lemon Squeezy webhook payload.
     *
     * @param array<string, mixed> $extraAttrs
     * @return array<string, mixed>
     */
    private function subscriptionPayload(
        string $event,
        int $userId,
        string $subId,
        array $extraAttrs = [],
    ): array {
        return [
            'meta' => [
                'event_name' => $event,
                'custom_data' => ['user_id' => (string) $userId],
            ],
            'data' => [
                'type' => 'subscriptions',
                'id' => $subId,
                'attributes' => array_merge([
                    'status' => 'active',
                    'renews_at' => null,
                    'ends_at' => null,
                ], $extraAttrs),
            ],
        ];
    }

    /**
     * Assert that the response body is JSON containing a key.
     */
    private function assertResponseJsonKey(string $key, \Psr\Http\Message\ResponseInterface $response): void
    {
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey($key, $body);
    }
}
