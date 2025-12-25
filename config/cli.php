<?php

declare(strict_types=1);

return [
    'name' => getenv('NIKANZO_CLI_NAME') ?: 'NikanzoPHP',
    'version' => getenv('NIKANZO_CLI_VERSION') ?: '0.2.0',
];