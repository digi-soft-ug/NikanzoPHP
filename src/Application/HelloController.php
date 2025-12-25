<?php

declare(strict_types=1);

namespace Nikanzo\Application;

use Nikanzo\Core\Attributes\Route;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HelloController
{
    #[Route('/hello', methods: ['GET'])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_encode(['message' => 'Hello from NikanzoPHP'], JSON_THROW_ON_ERROR);

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            $body
        );
    }
}
