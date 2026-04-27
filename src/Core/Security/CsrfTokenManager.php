<?php

declare(strict_types=1);

namespace Nikanzo\Core\Security;

/**
 * Generates and validates CSRF tokens stored in the PHP session.
 *
 * Call session_start() before using this class.
 */
final class CsrfTokenManager
{
    private const SESSION_KEY = '_nikanzo_csrf';

    public function __construct(private readonly int $tokenLength = 32)
    {
    }

    /** Return (and lazily create) the session-bound CSRF token. */
    public function getToken(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes($this->tokenLength));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Constant-time comparison — safe against timing attacks. */
    public function isValid(string $submitted): bool
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? '';

        return is_string($stored) && hash_equals($stored, $submitted);
    }

    /** Rotate the token (call after a successful state-changing request). */
    public function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes($this->tokenLength));
    }
}
