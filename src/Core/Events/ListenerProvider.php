<?php

declare(strict_types=1);

namespace Nikanzo\Core\Events;

use Psr\EventDispatcher\ListenerProviderInterface;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /**
     * @param callable $listener
     */
    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $class = $event::class;

        foreach ($this->listeners[$class] ?? [] as $listener) {
            yield $listener;
        }

        // Also yield listeners registered for parent classes / interfaces
        foreach (class_parents($class) ?: [] as $parent) {
            foreach ($this->listeners[$parent] ?? [] as $listener) {
                yield $listener;
            }
        }

        foreach (class_implements($class) ?: [] as $iface) {
            foreach ($this->listeners[$iface] ?? [] as $listener) {
                yield $listener;
            }
        }
    }
}
