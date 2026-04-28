<?php

declare(strict_types=1);

namespace Nikanzo\Core\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Validates a Bearer JWT using firebase/php-jwt.
 *
 * On success the decoded claims array is stored as request attribute "auth.claims".
 * Env var: NIKANZO_JWT_SECRET
 */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    private string $secret;
    private string $algorithm;

    public function __construct(?string $secret = null, string $algorithm = 'HS256')
    {
        $this->secret    = $secret ?? (string) (getenv('NIKANZO_JWT_SECRET') ?: '');
        $this->algorithm = $algorithm;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return $this->unauthorized('missing_token');
        }

        if ($this->secret === '') {
            return $this->unauthorized('jwt_secret_not_configured');
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            /** @var array<string, mixed> $claims */
            $claims  = (array) $decoded;
        } catch (ExpiredException) {
            return $this->unauthorized('token_expired');
        } catch (SignatureInvalidException) {
            return $this->unauthorized('invalid_signature');
        } catch (BeforeValidException) {
            return $this->unauthorized('token_not_yet_valid');
        } catch (\Throwable) {
            return $this->unauthorized('invalid_token');
        }

        $request = $request->withAttribute('auth.claims', $claims);

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    private function unauthorized(string $reason): ResponseInterface
    {
        return new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'unauthorized', 'reason' => $reason], JSON_THROW_ON_ERROR)
        );
    }
}
