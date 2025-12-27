<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/vendor/autoload.php';

function benchmark(string $name, callable $fn, int $requests = 10000): array
{
    $startMem = memory_get_usage(true);
    $start = microtime(true);
    $fn($requests);
    $duration = microtime(true) - $start;
    $endMem = memory_get_usage(true);

    return [
        'name' => $name,
        'requests' => $requests,
        'duration_ms' => $duration * 1000,
        'per_request_ms' => ($duration * 1000) / $requests,
        'memory_mb' => ($endMem - $startMem) / (1024 * 1024),
    ];
}

$results = [];

// NikanzoPHP benchmark
$results[] = benchmark('NikanzoPHP', function (int $requests): void {
    $bootstrap = require __DIR__ . '/bootstrap.php';
    $kernel = $bootstrap['kernel'] ?? null;
    if ($kernel === null) {
        throw new RuntimeException('Kernel not initialized');
    }

    for ($i = 0; $i < $requests; $i++) {
        $symfonyRequest = Request::create('/hello', 'GET');
        $response = $kernel->handle($symfonyRequest);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Unexpected status: ' . $response->getStatusCode());
        }
    }
});

// Laravel benchmark (optional): set LARAVEL_BASE env to the project root containing bootstrap/app.php
$laravelBase = getenv('LARAVEL_BASE');
if ($laravelBase && is_file($laravelBase . '/bootstrap/app.php')) {
    $results[] = benchmark('Laravel', function (int $requests) use ($laravelBase): void {
        $app = require $laravelBase . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        for ($i = 0; $i < $requests; $i++) {
            $symfonyRequest = Request::create('/hello', 'GET');
            $response = $kernel->handle($symfonyRequest);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('Unexpected status: ' . $response->getStatusCode());
            }
            $kernel->terminate($symfonyRequest, $response);
        }
    });
} else {
    $results[] = [
        'name' => 'Laravel (skipped)',
        'requests' => 0,
        'duration_ms' => 0,
        'per_request_ms' => 0,
        'memory_mb' => 0,
    ];
}

// Symfony benchmark (optional): set SYMFONY_KERNEL to a callable factory returning HttpKernelInterface
$symfonyFactory = getenv('SYMFONY_FACTORY');
if ($symfonyFactory && is_file($symfonyFactory)) {
    $factory = require $symfonyFactory; // should return callable(): HttpKernelInterface
    if (is_callable($factory)) {
        $results[] = benchmark('Symfony', function (int $requests) use ($factory): void {
            $kernel = $factory();
            for ($i = 0; $i < $requests; $i++) {
                $symfonyRequest = Request::create('/hello', 'GET');
                $response = $kernel->handle($symfonyRequest);
                if ($response->getStatusCode() !== 200) {
                    throw new RuntimeException('Unexpected status: ' . $response->getStatusCode());
                }
            }
        });
    }
} else {
    $results[] = [
        'name' => 'Symfony (skipped)',
        'requests' => 0,
        'duration_ms' => 0,
        'per_request_ms' => 0,
        'memory_mb' => 0,
    ];
}

// Output results as table
printf("%-20s %10s %15s %18s %12s\n", 'Framework', 'Req', 'Total ms', 'Per req ms', 'Mem MB');
foreach ($results as $r) {
    printf(
        "%-20s %10d %15.2f %18.4f %12.2f\n",
        $r['name'],
        $r['requests'],
        $r['duration_ms'],
        $r['per_request_ms'],
        $r['memory_mb']
    );
}