<?php

declare(strict_types=1);

namespace Nikanzo\Core\Hooks;

interface HookInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload = []): void;
}
