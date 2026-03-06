<?php

namespace LdapRecord\Models\Concerns;

use Closure;
use LdapRecord\Events\NullDispatcher;
use LdapRecord\Models\Events;
use LdapRecord\Models\Events\Event;
use LdapRecord\Support\Arr;

/** @mixin \LdapRecord\Models\Model */
trait HasEvents
{
    /**
     * Execute the callback without raising any events.
     */
    protected static function withoutEvents(Closure $callback): mixed
    {
        $container = static::getConnectionContainer();

        $dispatcher = $container->getDispatcher();

        if ($dispatcher) {
            $container->setDispatcher(
                new NullDispatcher($dispatcher)
            );
        }

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                $container->setDispatcher($dispatcher);
            }
        }
    }

    /**
     * Dispatch the given model events.
     */
    protected function dispatch(array|string $events, array $args = []): void
    {
        foreach (Arr::wrap($events) as $name) {
            $this->fireCustomModelEvent($name, $args);
        }
    }

    /**
     * Fire a custom model event.
     */
    protected function fireCustomModelEvent(string $name, array $args = []): void
    {
        $event = implode('\\', [Events::class, ucfirst($name)]);

        $this->fireModelEvent(new $event($this, ...$args));
    }

    /**
     * Fire a model event.
     */
    protected function fireModelEvent(Event $event): void
    {
        static::getConnectionContainer()->getDispatcher()->fire($event);
    }

    /**
     * Listen to a model event.
     */
    protected function listenForModelEvent(string $event, Closure $listener): void
    {
        static::getConnectionContainer()->getDispatcher()->listen($event, $listener);
    }
}
