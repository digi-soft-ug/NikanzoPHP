# Billing Guide — Lemon Squeezy Integration

This document explains how NikanzoPHP's billing layer works, how to configure it, and how to test it end-to-end.

---

## Architecture

```
Browser / API client
  │
  │  POST /api/billing/checkout   ←── JWT-authenticated, returns a redirect URL
  │  POST /api/billing/webhook    ←── HMAC-verified, no auth, processes events
  ▼
BillingController
  ├── LemonSqueezyClient    (HTTP calls + HMAC verification)
  └── LicenseManager        (database writes: upgrade / extend / revoke)
```

### Files

| File | Role |
|---|---|
| `src/Services/LemonSqueezyClient.php` | Lemon Squeezy API wrapper + HMAC verification |
| `src/Application/BillingController.php` | Route handlers for checkout + webhook |
| `src/Services/LicenseManager.php` | User membership database operations |
| `tests/Services/LemonSqueezyClientTest.php` | Unit tests (no network) |
| `tests/Application/BillingControllerTest.php` | Controller unit tests (no DB, no network) |

---

## Setup

### 1. Lemon Squeezy Dashboard

1. **Create a store** at <https://app.lemonsqueezy.com>
2. **Create a product** — set it as a subscription (Monthly / Annual variants)
3. **Note the IDs** you need:
   - **Store ID** — Settings → Stores → numeric ID in the URL
   - **Variant IDs** — Products → your product → Variants tab → numeric ID in URL for each plan
   - **API Key** — Settings → API → "New API key"
   - **Webhook Secret** — Settings → Webhooks → create a webhook pointing to `https://yourapp.com/api/billing/webhook`, enable these events:
     - `subscription_activated`
     - `subscription_cancelled`
     - `subscription_expired`
     - `subscription_payment_success`
     - Copy the **Signing Secret** shown after saving

### 2. Environment Variables

Copy `.env.example` to `.env` and fill in the real values:

```dotenv
APP_URL=https://yourapp.com

LEMONSQUEEZY_API_KEY=eyJ0eXAiOiJKV1Qi...   # never commit this
LEMONSQUEEZY_STORE_ID=123456
LEMONSQUEEZY_WEBHOOK_SECRET=wh_xxxxxxxx
LEMONSQUEEZY_VARIANT_MONTHLY=111111
LEMONSQUEEZY_VARIANT_ANNUAL=222222
```

### 3. Wire into Bootstrap

```php
// bootstrap.php  (or wherever you build the kernel)

use Nikanzo\Application\BillingController;
use Nikanzo\Services\LemonSqueezyClient;
use Nikanzo\Services\LicenseManager;

$lsClient = LemonSqueezyClient::fromEnv($logger);
$license  = new LicenseManager($pdo, $logger);

$container->register(BillingController::class, fn () =>
    new BillingController($lsClient, $license, $logger)
);

$router->registerController(BillingController::class);
```

### 4. Run the Migration

Apply the premium columns migration if you haven't already:

```bash
php nikan db:migrate
```

---

## API Reference

### POST `/api/billing/checkout`

**Requires:** `Authorization: Bearer <JWT>` header

**Request body:**
```json
{
  "variant_id": "111111",
  "success_url": "https://yourapp.com/dashboard"
}
```

`success_url` is optional — falls back to `APP_URL/dashboard`.

**Success response (200):**
```json
{
  "url": "https://app.lemonsqueezy.com/checkout/buy/abc123"
}
```

**Error responses:**
| Status | Meaning |
|---|---|
| 401 | No valid JWT or `sub` claim missing |
| 404 | User not found in database |
| 422 | `variant_id` missing or blank |
| 502 | Lemon Squeezy API call failed |

**Frontend usage** (already wired in `templates/upgrade.html.twig`):
```js
const res  = await fetch('/api/billing/checkout', {
    method:  'POST',
    headers: {
        'Content-Type':  'application/json',
        'Authorization': 'Bearer ' + jwt,
    },
    body: JSON.stringify({ variant_id: '111111' }),
});
const { url } = await res.json();
window.location.href = url;   // redirect to LS hosted checkout
```

---

### POST `/api/billing/webhook`

**No authentication.** Verified via HMAC-SHA256 signature.

Lemon Squeezy sends this header:
```
X-Signature: <hex-encoded HMAC-SHA256 of raw body using LEMONSQUEEZY_WEBHOOK_SECRET>
```

**Events handled:**

| Event | Action |
|---|---|
| `subscription_activated` | `upgradeUser($userId, $subId, $renewsAt)` |
| `subscription_cancelled` | `extendSubscription($userId, $endsAt)` or `revokeUser()` if no `ends_at` |
| `subscription_expired` | `revokeUser($userId)` |
| `subscription_payment_success` | `extendSubscription($userId, $renewsAt)` |

All other events are logged and ignored. The endpoint always returns `204 No Content` on success (even for unhandled events) to prevent Lemon Squeezy from retrying.

**Custom data flow:**
When creating a checkout, `user_id` is passed in `checkout_data.custom`. Lemon Squeezy echoes it back in every webhook under `meta.custom_data.user_id`, allowing the webhook handler to identify the user without any extra mapping table.

---

## How to Test

### Unit Tests (no network, no database)

```bash
# Run all tests
php vendor/bin/phpunit

# Run only billing tests
php vendor/bin/phpunit tests/Services/LemonSqueezyClientTest.php
php vendor/bin/phpunit tests/Application/BillingControllerTest.php

# With coverage
php vendor/bin/phpunit --coverage-text
```

The 19 test cases cover:
- Signature verification (valid, invalid, empty sig, empty secret, empty payload, case-insensitive)
- `createCheckout` URL extraction, payload structure, error cases
- `BillingController::checkout` — all auth/validation/upstream-error paths
- `BillingController::webhook` — all event handlers, missing user_id, unknown events

### Manual Testing with Lemon Squeezy Test Mode

1. Enable **test mode** in your LS dashboard (all purchases are free)
2. Start your app: `php -S localhost:8000 public/index.php`
3. Expose it publicly via **ngrok** or **Cloudflare Tunnel**:
   ```bash
   ngrok http 8000
   # → https://abc123.ngrok.io
   ```
4. Set `APP_URL=https://abc123.ngrok.io` in `.env`
5. Create a webhook in LS dashboard pointing to `https://abc123.ngrok.io/api/billing/webhook`
6. Open `https://abc123.ngrok.io/upgrade`, click "Get Monthly PRO"
7. Complete the (test) checkout — use card number `4242 4242 4242 4242`
8. Check your app logs (`var/log/app.log`) for webhook events
9. Verify the user row in the database has `membership_level = 'premium'`

### Replaying Webhooks

In the LS dashboard → Settings → Webhooks → your webhook → "Recent deliveries", you can replay any past webhook. Useful for testing specific events without making a purchase.

### Simulating Webhooks Locally (curl)

```bash
SECRET="your_webhook_signing_secret_here"
PAYLOAD='{"meta":{"event_name":"subscription_activated","custom_data":{"user_id":"1"}},"data":{"id":"sub_test123","attributes":{"status":"active","renews_at":"2026-05-27T00:00:00.000000Z","ends_at":null}}}'
SIG=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST http://localhost:8000/api/billing/webhook \
     -H "Content-Type: application/json" \
     -H "X-Signature: $SIG" \
     -d "$PAYLOAD"
# → HTTP 204 (no content)
```

Change `user_id` to match a real user in your database. You can also test `subscription_cancelled`, `subscription_expired`, and `subscription_payment_success` by swapping `event_name`.

---

## Custom Data in Webhooks

The `user_id` passed at checkout creation is echoed back in every webhook under `meta.custom_data`. If you need to pass additional data (e.g., plan tier, team ID), extend the `checkout_data.custom` object in `LemonSqueezyClient::createCheckout()` and read it back in `BillingController::dispatch()`.

---

## Security Checklist

- [ ] `LEMONSQUEEZY_API_KEY` is in `.env`, not committed to git
- [ ] `LEMONSQUEEZY_WEBHOOK_SECRET` is in `.env`, not committed to git
- [ ] `.env` is in `.gitignore`
- [ ] Webhook endpoint returns 204 (not 200/400/500) on all non-signature-failure paths
- [ ] `hash_equals()` used (not `===`) for constant-time signature comparison
- [ ] Raw body used for HMAC verification (not re-serialised parsed JSON)
