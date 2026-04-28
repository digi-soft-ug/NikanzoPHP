<?php

declare(strict_types=1);

namespace Nikanzo\Core\Testing;

use Nikanzo\Core\Kernel;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lightweight HTTP test client that drives the Kernel directly.
 *
 * Usage in PHPUnit:
 *
 *   $client   = new TestClient($kernel);
 *   $response = $client->get('/hello');
 *   $this->assertSame(200, $response->getStatusCode());
 *   $this->assertJson((string) $response->getBody());
 */
final class TestClient
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    /**
     * @param array<string, string>  $headers
     * @param array<string, mixed>   $query
     */
    public function get(string $uri, array $headers = [], array $query = []): ResponseInterface
    {
        return $this->request('GET', $uri, $headers, [], $query);
    }

    /**
     * @param array<string, string>  $headers
     * @param array<string, mixed>|string $body
     * @param array<string, mixed>   $query
     */
    public function post(string $uri, array|string $body = [], array $headers = [], array $query = []): ResponseInterface
    {
        return $this->request('POST', $uri, $headers, $body, $query);
    }

    /**
     * @param array<string, string>  $headers
     * @param array<string, mixed>|string $body
     */
    public function put(string $uri, array|string $body = [], array $headers = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function patch(string $uri, array|string $body = [], array $headers = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function delete(string $uri, array $headers = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $headers);
    }

    /**
     * @param array<string, string>       $headers
     * @param array<string, mixed>|string $body
     * @param array<string, mixed>        $query
     */
    public function request(
        string $method,
        string $uri,
        array $headers = [],
        array|string $body = [],
        array $query = [],
    ): ResponseInterface {
        $isJson   = is_string($body);
        $postData = $isJson ? [] : $body;
        $content  = $isJson ? $body : (
            $body !== [] ? json_encode($body, JSON_THROW_ON_ERROR) : ''
        );

        if ($body !== [] && !$isJson) {
            $headers['Content-Type'] ??= 'application/json';
        }

        $request = Request::create(
            uri:        $uri,
            method:     $method,
            parameters: array_merge($query, $postData),
            cookies:    [],
            files:      [],
            server:     $this->buildServer($headers),
            content:    $content,
        );

        return $this->kernel->handle($request);
    }

    /**
     * Decode the JSON body of a response.
     *
     * @return array<mixed>
     */
    public function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function buildServer(array $headers): array
    {
        $server = ['REMOTE_ADDR' => '127.0.0.1'];
        foreach ($headers as $name => $value) {
            $key          = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$key] = $value;

            if (strtolower($name) === 'content-type') {
                $server['CONTENT_TYPE'] = $value;
            }
        }

        return $server;
    }
}
