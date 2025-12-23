<?php

declare(strict_types=1);

return [
    // Default driver: sqlite. Override via env NIKANZO_DB_DRIVER (sqlite|mysql|pgsql)
    'driver' => getenv('NIKANZO_DB_DRIVER') ?: 'sqlite',
    'database' => getenv('NIKANZO_DB_DATABASE') ?: __DIR__ . '/../database/database.sqlite',
    'host' => getenv('NIKANZO_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('NIKANZO_DB_PORT') ?: null,
    'username' => getenv('NIKANZO_DB_USERNAME') ?: null,
    'password' => getenv('NIKANZO_DB_PASSWORD') ?: null,
    'charset' => 'utf8mb4',
];