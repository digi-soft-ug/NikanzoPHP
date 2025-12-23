<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

$bootstrap = require dirname(__DIR__) . '/bootstrap.php';

$kernel = $bootstrap['kernel'] ?? null;
if ($kernel === null) {
    http_response_code(500);
    echo 'Kernel not initialized';
    return;
}

$response = $kernel->handle(Request::createFromGlobals());

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}

echo (string) $response->getBody();