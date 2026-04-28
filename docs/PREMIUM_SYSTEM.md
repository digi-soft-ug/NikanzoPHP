# Premium Subscription System

This document describes the architecture, configuration, and day-to-day usage of NikanzoPHP's premium membership system.

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Database Schema](#2-database-schema)
3. [Running the Migration](#3-running-the-migration)
4. [LicenseManager Service](#4-licensemanager-service)
5. [Protecting Routes](#5-protecting-routes)
   - [Option A — #[PremiumRequired] attribute](#option-a--premiumrequired-attribute)
   - [Option B — PremiumAccessMiddleware](#option-b--premiumaccessmiddleware)
6. [Wiring Everything in bootstrap.php](#6-wiring-everything-in-bootstrapphp)
7. [Manually Upgrading a User](#7-manually-upgrading-a-user)
8. [Checking Premium Status in Twig Templates](#8-checking-premium-status-in-twig-templates)
9. [Stripe Integration Sketch](#9-stripe-integration-sketch)
10. [Revoking / Expiring Subscriptions](#10-revoking--expiring-subscriptions)
11. [Testing](#11-testing)
12. [Environment Variables Reference](#12-environment-variables-reference)

---

## 1. Architecture Overview

```
Request
  │
  ▼
JwtAuthMiddleware           → sets auth.claims (contains sub = user ID)
  │
  ▼
PremiumAccessMiddleware     → path-prefix guard (optional, for bulk path protection)
  │                           reads auth.claims, calls LicenseManager::isPremium()
  ▼
Kernel core handler
  │  router match()
  │  checkScopes()          → #[RequiredScope] enforcement
  │  checkPremium()         → #[PremiumRequired] enforcement  ← NEW
  ▼
Controller method

LicenseManager
  └── QueryBuilder → PDO → users table
```

Two complementary gatekeeping mechanisms exist:

| Mechanism | Where | Best for |
|---|---|---|
| `#[PremiumRequired]` attribute | On a controller method or class | Individual route precision |
| `PremiumAccessMiddleware` | Registered on the Kernel | Bulk path-prefix protection |

Both read the same `auth.claims` attribute and call the same `LicenseManager::isPremium()` — there is no duplication of business logic.

---

## 2. Database Schema

Three columns are added to the `users` table:

| Column | Type | Default | Description |
|---|---|---|---|
| `membership_level` | `'free'` \| `'premium'` | `'free'` | Current tier |
| `subscription_id` | `varchar` / `text`, nullable | `NULL` | External payment provider reference (e.g. Stripe `sub_xxx`) |
| `premium_until` | datetime / text, nullable | `NULL` | Expiry datetime; `NULL` = never expires while level is `premium` |

**Expiry logic:** A user is premium when `membership_level = 'premium'` AND (`premium_until IS NULL` OR `premium_until > NOW()`).

---

## 3. Running the Migration

```bash
php nikan db:migrate
```

This runs `database/migrations/20260427000000_add_premium_to_users_table.php`, which adds the three columns to all supported drivers (SQLite, MySQL, PostgreSQL) using driver-aware SQL.

To roll back (SQLite re-creates the table without the columns):

```bash
# There is no `db:rollback` command yet — run down() manually or via a test.
```

---

## 4. LicenseManager Service

**Namespace:** `Nikanzo\Services\LicenseManager`  
**File:** [src/Services/LicenseManager.php](../src/Services/LicenseManager.php)

### Methods

```php
// Check whether a user row (array from QueryBuilder) is premium right now
$manager->isPremium(array $user): bool

// Fetch a user by primary key
$manager->findUser(int $userId): ?array

// Fetch a user by email
$manager->findUserByEmail(string $email): ?array

// Upgrade a user (sets membership_level = 'premium', saves subscription ID)
$manager->upgradeUser(int $userId, string $subscriptionId, ?\DateTimeImmutable $until = null): void

// Revoke premium (sets membership_level = 'free', clears premium_until)
$manager->revokeUser(int $userId): void

// Extend an active subscription's expiry date
$manager->extendSubscription(int $userId, \DateTimeImmutable $newUntil): void

// List all currently active premium users
$manager->activePremiumUsers(): list<array>
```

### Example: instantiate manually

```php
use Nikanzo\Services\LicenseManager;
use Nikanzo\Core\Database\ConnectionFactory;

$pdo     = ConnectionFactory::make(require __DIR__ . '/config/database.php');
$manager = new LicenseManager($pdo, $logger);
```

### Example: inject via DI container

```php
// bootstrap.php
$container->register(LicenseManager::class);

// In a controller constructor:
public function __construct(private readonly LicenseManager $license) {}
```

---

## 5. Protecting Routes

### Option A — `#[PremiumRequired]` attribute

Apply on a single method — the most precise approach:

```php
use Nikanzo\Core\Attributes\PremiumRequired;
use Nikanzo\Core\Attributes\Route;

final class AnalyticsController extends AbstractController
{
    #[Route('/analytics/deep', methods: ['GET'])]
    #[PremiumRequired(redirectTo: '/upgrade')]   // HTML → 302; JSON → 403
    public function deep(ServerRequestInterface $request): ResponseInterface
    {
        // Only premium users reach here
        return $this->json(['data' => '...']);
    }
}
```

Apply on an entire class — all routes in the controller are gated:

```php
#[PremiumRequired]
final class ReportsController extends AbstractController
{
    #[Route('/reports/monthly', methods: ['GET'])]
    public function monthly(ServerRequestInterface $request): ResponseInterface { ... }

    #[Route('/reports/annual', methods: ['GET'])]
    public function annual(ServerRequestInterface $request): ResponseInterface { ... }
}
```

**How the Kernel enforces it:**  
After routing and scope checks, `Kernel::checkPremium()` reads the `#[PremiumRequired]` attribute via reflection, looks up the user with `LicenseManager::findUser()`, and calls `LicenseManager::isPremium()`. No change to the controller code is needed.

**Response behaviour:**

| Client `Accept` | Not premium response |
|---|---|
| `application/json` | `403 {"error":"premium_required","message":"...","upgrade_url":"/upgrade"}` |
| `text/html` (browser) | `302 Location: /upgrade?reason=<url-encoded message>` |

---

### Option B — `PremiumAccessMiddleware`

Useful when you want to protect an entire path prefix without adding an attribute to each controller:

```php
use Nikanzo\Core\Middleware\PremiumAccessMiddleware;

$kernel->addMiddleware(new PremiumAccessMiddleware(
    licenseManager:    $licenseManager,
    protectedPrefixes: ['/premium/', '/dashboard/advanced'],
    upgradeUrl:        '/upgrade',
    logger:            $logger,
));
```

The middleware must run **after** `JwtAuthMiddleware` (which sets `auth.claims`) and optionally after `ContentNegotiationMiddleware` (which sets `accept.format`).

Requests to any other path pass through untouched.

---

## 6. Wiring Everything in bootstrap.php

```php
use Nikanzo\Core\Database\ConnectionFactory;
use Nikanzo\Core\Logging\LoggerFactory;
use Nikanzo\Core\Middleware\ContentNegotiationMiddleware;
use Nikanzo\Core\Middleware\JwtAuthMiddleware;
use Nikanzo\Core\Middleware\PremiumAccessMiddleware;
use Nikanzo\Core\Middleware\RequestBodyParserMiddleware;
use Nikanzo\Core\Middleware\SecurityHeadersMiddleware;
use Nikanzo\Services\LicenseManager;
use Nikanzo\Application\DashboardController;
use Nikanzo\Application\UpgradeController;

require __DIR__ . '/vendor/autoload.php';

$logger  = LoggerFactory::create();
$pdo     = ConnectionFactory::make(require __DIR__ . '/config/database.php');
$license = new LicenseManager($pdo, $logger);

// ── Kernel wired with LicenseManager ─────────────────────────────────────────
$kernel = new Kernel($router, $container, licenseManager: $license);

// ── Middleware order (outermost → innermost) ──────────────────────────────────
$kernel->addMiddleware(new SecurityHeadersMiddleware());
$kernel->addMiddleware(new ErrorHandlerMiddleware(debug: (bool) getenv('APP_DEBUG'), logger: $logger));
$kernel->addMiddleware(new RequestBodyParserMiddleware());
$kernel->addMiddleware(new ContentNegotiationMiddleware());
$kernel->addMiddleware(new JwtAuthMiddleware());

// Option B (path-prefix guard) — remove if using #[PremiumRequired] only
$kernel->addMiddleware(new PremiumAccessMiddleware(
    licenseManager:    $license,
    protectedPrefixes: ['/dashboard/advanced', '/premium/'],
));

// ── Register controllers ──────────────────────────────────────────────────────
$container->register(DashboardController::class);
$router->registerController(DashboardController::class);

$container->register(UpgradeController::class);
$router->registerController(UpgradeController::class);
```

---

## 7. Manually Upgrading a User

### Via PHP (script or tinker)

```php
<?php
// scripts/upgrade_user.php
require __DIR__ . '/vendor/autoload.php';

$pdo     = \Nikanzo\Core\Database\ConnectionFactory::make(require __DIR__ . '/config/database.php');
$manager = new \Nikanzo\Services\LicenseManager($pdo);

// Upgrade user ID 1 with an indefinite subscription
$manager->upgradeUser(
    userId:         1,
    subscriptionId: 'sub_manual_upgrade',
    until:          null,  // null = never expires
);
echo "User 1 is now premium.\n";

// Verify
$user = $manager->findUser(1);
var_dump($manager->isPremium($user)); // true
```

Run: `php scripts/upgrade_user.php`

### Via SQL (database client)

```sql
-- MySQL / PostgreSQL
UPDATE users
SET
    membership_level = 'premium',
    subscription_id  = 'sub_manual',
    premium_until    = NULL          -- NULL = indefinite; or a specific datetime
WHERE id = 1;

-- SQLite
UPDATE users
SET
    membership_level = 'premium',
    subscription_id  = 'sub_manual',
    premium_until    = NULL
WHERE id = 1;
```

### Via the CLI (future command)

A `user:upgrade` CLI command can be added to wrap `LicenseManager::upgradeUser()`:

```bash
php nikan user:upgrade 1 --subscription=sub_manual
```

---

## 8. Checking Premium Status in Twig Templates

The controller passes membership data to the template. The base layout (`templates/layout/base.html.twig`) already checks `is_premium` to show the PRO badge and navigation links.

### Show/hide content based on membership

```twig
{% if is_premium %}
    <a href="/dashboard/advanced" class="btn btn-premium">Advanced Analytics</a>
{% else %}
    <div class="alert alert-warning">
        <strong>PRO feature.</strong>
        <a href="/upgrade">Upgrade to unlock this →</a>
    </div>
{% endif %}
```

### Show PRO badge next to the user's name

```twig
{# Anywhere in your templates — works out of the box in layout/base.html.twig #}
{{ user.name }}
{% if is_premium %}
    <span class="badge-pro">PRO</span>
{% endif %}
```

### Pass the flag from a controller

```php
return $this->render($this->renderer, 'my-page.html.twig', [
    'is_premium' => $licenseManager->isPremium($user),
    'user'       => $user,
    'user_name'  => $user['name'],
]);
```

### Available template variables (set by DashboardController)

| Variable | Type | Description |
|---|---|---|
| `is_premium` | `bool` | Whether the current user is premium |
| `user` | `array` | Full user row from the database |
| `user_name` | `string` | User's display name |
| `analytics` | `array` | Advanced stats (premium endpoint only) |

---

## 9. Stripe Integration Sketch

The upgrade page renders Stripe Checkout buttons. The server-side flow:

```
Browser                  NikanzoPHP backend              Stripe
  │                             │                           │
  │  POST /api/billing/checkout │                           │
  │ ─────────────────────────── │                           │
  │                             │  Create CheckoutSession   │
  │                             │ ──────────────────────── ▶│
  │                             │ ◀─────────────── session  │
  │  ◀── { url: session.url } ──│                           │
  │  redirect to session.url    │                           │
  │ ────────────────────────────────────────────────────── ▶│
  │ (user pays)                 │                           │
  │                             │◀── webhook: checkout.completed
  │                             │    verifyWebhookSignature()
  │                             │    LicenseManager::upgradeUser()
```

Required env vars (set in `.env`):

```dotenv
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_MONTHLY=price_...
STRIPE_PRICE_ANNUAL=price_...
```

Stub controller to create a Stripe Checkout Session:

```php
// src/Application/BillingController.php
#[Route('/api/billing/checkout', methods: ['POST'])]
#[RequiredScope('billing:write')]
public function checkout(ServerRequestInterface $request): ResponseInterface
{
    $body    = $request->getParsedBody();
    $priceId = $body['price_id'] ?? '';

    // Use stripe-php: composer require stripe/stripe-php
    \Stripe\Stripe::setApiKey((string) getenv('STRIPE_SECRET_KEY'));

    $session = \Stripe\Checkout\Session::create([
        'mode'                => 'subscription',
        'payment_method_types' => ['card'],
        'line_items'          => [['price' => $priceId, 'quantity' => 1]],
        'success_url'         => 'https://example.com/billing/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'          => 'https://example.com/upgrade',
    ]);

    return $this->json(['url' => $session->url]);
}

// Webhook handler
#[Route('/api/billing/webhook', methods: ['POST'])]
public function webhook(ServerRequestInterface $request): ResponseInterface
{
    $payload   = (string) $request->getBody();
    $sigHeader = $request->getHeaderLine('Stripe-Signature');

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            (string) getenv('STRIPE_WEBHOOK_SECRET')
        );
    } catch (\Throwable) {
        return $this->error('invalid_signature', 400);
    }

    if ($event->type === 'checkout.session.completed') {
        $session        = $event->data->object;
        $subscriptionId = $session->subscription;
        $userId         = (int) $session->client_reference_id; // pass user ID in metadata

        $this->licenseManager->upgradeUser($userId, $subscriptionId);
    }

    return $this->noContent();
}
```

---

## 10. Revoking / Expiring Subscriptions

### Manual revocation

```php
$manager->revokeUser(42); // sets membership_level = 'free', clears premium_until
```

### Automatic expiry

`LicenseManager::isPremium()` checks `premium_until` on every call — no background job is needed. Once `premium_until` passes, the user is automatically treated as free on the next request.

### Stripe webhook: `customer.subscription.deleted`

```php
if ($event->type === 'customer.subscription.deleted') {
    $subscriptionId = $event->data->object->id;
    // Find the user by subscription_id and revoke
    $user = (new QueryBuilder($pdo, 'users'))
        ->where('subscription_id', $subscriptionId)
        ->first();
    if ($user !== null) {
        $manager->revokeUser((int) $user['id']);
    }
}
```

---

## 11. Testing

```php
use Nikanzo\Core\Testing\TestClient;
use Nikanzo\Services\LicenseManager;
use PHPUnit\Framework\TestCase;

final class PremiumGateTest extends TestCase
{
    // ── LicenseManager unit tests ─────────────────────────────────────────────

    public function testFreeUserIsNotPremium(): void
    {
        $manager = new LicenseManager($this->pdo());
        $user    = ['membership_level' => 'free', 'premium_until' => null];
        $this->assertFalse($manager->isPremium($user));
    }

    public function testPremiumUserWithNoExpiryIsPremium(): void
    {
        $manager = new LicenseManager($this->pdo());
        $user    = ['membership_level' => 'premium', 'premium_until' => null];
        $this->assertTrue($manager->isPremium($user));
    }

    public function testExpiredSubscriptionIsNotPremium(): void
    {
        $manager = new LicenseManager($this->pdo());
        $user    = [
            'membership_level' => 'premium',
            'premium_until'    => '2000-01-01 00:00:00',  // past date
        ];
        $this->assertFalse($manager->isPremium($user));
    }

    public function testFutureExpiryIsPremium(): void
    {
        $manager = new LicenseManager($this->pdo());
        $user    = [
            'membership_level' => 'premium',
            'premium_until'    => date('Y-m-d H:i:s', strtotime('+1 year')),
        ];
        $this->assertTrue($manager->isPremium($user));
    }

    // ── HTTP-level gate tests ─────────────────────────────────────────────────

    public function testFreeUserGetsRedirectedFromPremiumRoute(): void
    {
        // Build a kernel with the premium gate and a free-tier JWT
        $client   = new TestClient($this->kernel());
        $response = $client->get('/dashboard/advanced', [
            'Authorization' => 'Bearer ' . $this->jwtForFreeUser(),
            'Accept'        => 'text/html',
        ]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/upgrade', $response->getHeaderLine('Location'));
    }

    public function testPremiumUserCanAccessAdvancedDashboard(): void
    {
        $client   = new TestClient($this->kernel());
        $response = $client->get('/dashboard/advanced', [
            'Authorization' => 'Bearer ' . $this->jwtForPremiumUser(),
        ]);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testJsonClientGetsForbiddenInsteadOfRedirect(): void
    {
        $client   = new TestClient($this->kernel());
        $response = $client->get('/dashboard/advanced', [
            'Authorization' => 'Bearer ' . $this->jwtForFreeUser(),
            'Accept'        => 'application/json',
        ]);
        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('premium_required', $body['error']);
    }

    // ── Helpers (fill in with your test bootstrap) ────────────────────────────
    private function pdo(): \PDO { /* ... */ }
    private function kernel(): \Nikanzo\Core\Kernel { /* ... */ }
    private function jwtForFreeUser(): string { /* ... */ }
    private function jwtForPremiumUser(): string { /* ... */ }
}
```

---

## 12. Environment Variables Reference

| Variable | Required | Description |
|---|---|---|
| `STRIPE_PUBLISHABLE_KEY` | For payment UI | Stripe publishable key (`pk_test_...` or `pk_live_...`) |
| `STRIPE_SECRET_KEY` | For Checkout Session creation | Stripe secret key — **never expose to the browser** |
| `STRIPE_WEBHOOK_SECRET` | For webhook validation | Starts with `whsec_` |
| `STRIPE_PRICE_MONTHLY` | For checkout | Stripe Price ID for the monthly plan |
| `STRIPE_PRICE_ANNUAL` | For checkout | Stripe Price ID for the annual plan |

All Stripe keys must be set in `.env` only — they are never committed to source control.

For local development use Stripe's test-mode keys and the Stripe CLI to forward webhooks:

```bash
stripe listen --forward-to localhost:8000/api/billing/webhook
```
