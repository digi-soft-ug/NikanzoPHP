<?php

declare(strict_types=1);

namespace Nikanzo\Core\Hooks;

final class HookDispatcher
{
    /** @var array<string, list<HookInterface>> */
    private array $listeners = [];

    public function addListener(string $hook, HookInterface $listener): void
    {
        $this->listeners[$hook] ??= [];
        $this->listeners[$hook][] = $listener;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $hook, array $payload = []): void
    {
        foreach ($this->listeners[$hook] ?? [] as $listener) {
            $listener->handle($payload);
        }
    }
}
