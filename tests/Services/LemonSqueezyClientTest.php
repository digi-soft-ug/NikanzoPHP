<?php

declare(strict_types=1);

namespace Nikanzo\Tests\Services;

use Nikanzo\Services\LemonSqueezyClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LemonSqueezyClient.
 *
 * The HTTP adapter is injected via the constructor so no real network calls
 * are made.  Signature verification is pure PHP and needs no mocking.
 */
final class LemonSqueezyClientTest extends TestCase
{
    // ── verifyWebhookSignature ─────────────────────────────────────────────────

    public function testVerifyWebhookSignatureReturnsTrueForValidHmac(): void
    {
        $secret  = 'super-secret-123';
        $payload = '{"meta":{"event_name":"subscription_activated"}}';
        $sig     = hash_hmac('sha256', $payload, $secret);

        $client = $this->makeClient();

        $this->assertTrue($client->verifyWebhookSignature($payload, $sig, $secret));
    }

    public function testVerifyWebhookSignatureReturnsFalseForWrongSignature(): void
    {
        $client = $this->makeClient();

        $this->assertFalse(
            $client->verifyWebhookSignature('payload', 'bad-signature', 'secret')
        );
    }

    public function testVerifyWebhookSignatureReturnsFalseForEmptySignature(): void
    {
        $client = $this->makeClient();

        $this->assertFalse($client->verifyWebhookSignature('payload', '', 'secret'));
    }

    public function testVerifyWebhookSignatureReturnsFalseForEmptySecret(): void
    {
        $client = $this->makeClient();

        $this->assertFalse($client->verifyWebhookSignature('payload', 'sig', ''));
    }

    public function testVerifyWebhookSignatureReturnsFalseForEmptyPayload(): void
    {
        $client = $this->makeClient();

        $this->assertFalse($client->verifyWebhookSignature('', 'sig', 'secret'));
    }

    public function testVerifyWebhookSignatureIsCaseInsensitiveOnSignature(): void
    {
        $secret  = 'my-secret';
        $payload = 'test-body';
        $sig     = strtoupper(hash_hmac('sha256', $payload, $secret));

        $client = $this->makeClient();

        // LS sends lowercase hex; ensure we handle any casing
        $this->assertTrue($client->verifyWebhookSignature($payload, $sig, $secret));
    }

    // ── createCheckout ─────────────────────────────────────────────────────────

    public function testCreateCheckoutReturnsUrlFromApiResponse(): void
    {
        $expectedUrl = 'https://app.lemonsqueezy.com/checkout/buy/abc123';

        $adapter = static function (string $path, array $payload) use ($expectedUrl): array {
            return [
                'data' => [
                    'attributes' => ['url' => $expectedUrl],
                ],
            ];
        };

        $client = $this->makeClient($adapter);
        $url    = $client->createCheckout('variant-1', 42, 'user@example.com', 'https://myapp.com/dashboard');

        $this->assertSame($expectedUrl, $url);
    }

    public function testCreateCheckoutPassesCorrectPathAndPayload(): void
    {
        $capturedPath    = null;
        $capturedPayload = null;

        $adapter = static function (string $path, array $payload) use (&$capturedPath, &$capturedPayload): array {
            $capturedPath    = $path;
            $capturedPayload = $payload;

            return ['data' => ['attributes' => ['url' => 'https://example.com/checkout']]];
        };

        $client = $this->makeClient($adapter);
        $client->createCheckout('variant-99', 7, 'test@test.com', 'https://myapp.com/ok');

        $this->assertSame('/checkouts', $capturedPath);

        // verify the JSON:API structure
        $this->assertSame('checkouts', $capturedPayload['data']['type']);
        $this->assertSame('stores',    $capturedPayload['data']['relationships']['store']['data']['type']);
        $this->assertSame('test-store', $capturedPayload['data']['relationships']['store']['data']['id']);
        $this->assertSame('variants',  $capturedPayload['data']['relationships']['variant']['data']['type']);
        $this->assertSame('variant-99', $capturedPayload['data']['relationships']['variant']['data']['id']);

        // user_id forwarded as custom data
        $this->assertSame(
            '7',
            $capturedPayload['data']['attributes']['checkout_data']['custom']['user_id']
        );

        // email pre-filled
        $this->assertSame(
            'test@test.com',
            $capturedPayload['data']['attributes']['checkout_data']['email']
        );
    }

    public function testCreateCheckoutThrowsWhenUrlMissingFromResponse(): void
    {
        $adapter = static fn (): array => ['data' => ['attributes' => []]];

        $client = $this->makeClient($adapter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/checkout URL/i');

        $client->createCheckout('v1', 1, 'a@b.com', 'https://ok.com');
    }

    public function testCreateCheckoutThrowsWhenAdapterThrows(): void
    {
        $adapter = static function (): never {
            throw new \RuntimeException('Lemon Squeezy API error: HTTP 422');
        };

        $client = $this->makeClient($adapter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/API error/i');

        $client->createCheckout('v1', 1, 'a@b.com', 'https://ok.com');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeClient(?\Closure $adapter = null): LemonSqueezyClient
    {
        return new LemonSqueezyClient(
            apiKey:      'test-api-key',
            storeId:     'test-store',
            httpAdapter: $adapter,
        );
    }
}
