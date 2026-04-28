# Security Policy

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Email: **security@digi-soft-ug.com** (or open a private GitHub security advisory).

We aim to acknowledge reports within 48 hours and release a patch within 14 days for confirmed issues.

---

## Built-in Security Features

### 1. Security Headers (`SecurityHeadersMiddleware`)

Add first in your middleware stack:

```php
$kernel->addMiddleware(new SecurityHeadersMiddleware());
```

Default headers:

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |

Disable HSTS for local HTTP dev: `new SecurityHeadersMiddleware(hsts: false)`.

---

### 2. JWT Authentication (`JwtAuthMiddleware`)

- Uses `firebase/php-jwt` — no hand-rolled crypto.
- Algorithm: `HS256` by default (configurable). RS256 supported by passing an `openssl` key.
- Token extracted from `Authorization: Bearer <token>`.
- Validates signature, expiry (`exp`), and `nbf` claims automatically.
- Set secret via `NIKANZO_JWT_SECRET` env var (min 32 chars recommended).
- Returns distinct `reason` values: `missing_token`, `token_expired`, `invalid_signature`, `token_not_yet_valid`, `invalid_token`.

```bash
NIKANZO_JWT_SECRET=$(openssl rand -hex 32)
```

---

### 3. Scope-Based Authorization (`#[RequiredScope]`)

```php
#[Route('/admin/stats', methods: ['GET'])]
#[RequiredScope('admin', 'stats:read')]
public function stats(ServerRequestInterface $request): ResponseInterface { ... }
```

Returns `403 Forbidden` with `required_scopes` list if the JWT's `scopes` claim does not contain all required scopes.

---

### 4. CSRF Protection (`CsrfMiddleware` + `CsrfTokenManager`)

Use for stateful HTML form endpoints. Skip for pure-API (JWT-authenticated) endpoints.

```php
$kernel->addMiddleware(new CsrfMiddleware(new CsrfTokenManager()));
```

- Token stored in PHP session under `_nikanzo_csrf`.
- Validated from `_csrf_token` body field **or** `X-CSRF-Token` header.
- `hash_equals()` comparison — timing-attack safe.
- `CsrfTokenManager::rotate()` invalidates the old token after sensitive operations.
- Safe methods (`GET`, `HEAD`, `OPTIONS`) are automatically skipped.

---

### 5. SQL Injection Prevention (`QueryBuilder`)

All user-supplied values go through PDO prepared statements. The `QueryBuilder` never interpolates values into SQL strings. The only exception is `raw()` which accepts a binding array — use it only with trusted SQL.

```php
// Safe — value bound via prepared statement
(new QueryBuilder($pdo, 'users'))->where('email', $userInput)->first();

// Safe raw with binding
$qb->raw('SELECT * FROM users WHERE email = :e', [':e' => $userInput]);

// NEVER do this
$pdo->query("SELECT * FROM users WHERE email = '$userInput'"); // ← SQL injection risk
```

---

### 6. XSS Prevention

- **Twig** auto-escapes all variables by default. Never mark untrusted data as `|raw`.
- **JSON responses** via `json_encode` are inherently safe as JSON does not execute.
- **HTML responses** built manually: always use `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

---

### 7. Rate Limiting (`RateLimitMiddleware`)

```php
$kernel->addMiddleware(new RateLimitMiddleware(limit: 60, intervalSeconds: 60));
```

- Sliding window algorithm per `IP + path`.
- Returns `429 Too Many Requests` with `Retry-After` header.
- Adds `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.
- **Note:** In-process only. For multi-process servers, replace the bucket storage with APCu or Redis.

---

### 8. Error Handling (`ErrorHandlerMiddleware`)

```php
$kernel->addMiddleware(new ErrorHandlerMiddleware(
    debug:  (bool) getenv('APP_DEBUG'),
    logger: $logger,
));
```

- In **debug mode** (`APP_DEBUG=true`): exception message and stack trace included in response (dev only).
- In **production** (`APP_DEBUG=false`): only `{"error":"internal_error"}` returned — no internals leaked.
- All exceptions are logged via PSR-3 logger regardless of mode.

---

## Secrets Management

- **Never commit `.env`** — it is in `.gitignore`.
- Copy `.env.example` → `.env` and fill in real values.
- Use a secrets manager (Vault, AWS Secrets Manager, GitHub Secrets) in CI/CD.
- Rotate `NIKANZO_JWT_SECRET` periodically. All previously issued tokens will become invalid.

---

## Recommended Production Checklist

- [ ] `APP_DEBUG=false`
- [ ] `NIKANZO_JWT_SECRET` set to a random 32+ character string
- [ ] HTTPS enforced at the reverse proxy (nginx/Caddy); `SecurityHeadersMiddleware(hsts: true)`
- [ ] `NIKANZO_FAST_ROUTER=1` with warmed cache
- [ ] `roave/security-advisories` in `require-dev` (blocks composer install if a vulnerable dep is detected)
- [ ] Run `composer audit` in CI
- [ ] `APP_ENV=production` (disables dev tools)
- [ ] Log rotation configured (`NIKANZO_LOG_MAX_FILES`)
- [ ] Rate limiting enabled
- [ ] Database credentials use a least-privilege DB user
- [ ] File uploads validated (type + size) before processing
