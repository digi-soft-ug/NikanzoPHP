<?php

declare(strict_types=1);

namespace Nikanzo\Tests;

use Nikanzo\Application\HelloController;
use Nikanzo\Core\Container\Container;
use Nikanzo\Core\Kernel;
use Nikanzo\Core\Router;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HelloControllerTest extends TestCase
{
    public function testHelloRouteReturnsJson(): void
    {
        $router = new Router();
        $container = new Container();

        $router->registerController(HelloController::class);
        $container->register(HelloController::class);

        $kernel = new Kernel($router, $container);

        $symfonyRequest = Request::create('/hello', 'GET');
        $response = $kernel->handle($symfonyRequest);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $expected = json_encode(['message' => 'Hello from NikanzoPHP']);
        $actual = (string) $response->getBody();
        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }
}