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
     *
     * @param  Closure  $callback
     * @return mixed
     */
    protected static function withoutEvents(Closure $callback)
    {
        $container = static::getConnectionContainer();

        $dispatcher = $container->getEventDispatcher();

        if ($dispatcher) {
            $container->setEventDispatcher(
                new NullDispatcher($dispatcher)
            );
        }

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                $container->setEventDispatcher($dispatcher);
            }
        }
    }

    /**
     * Dispatch the given model events.
     *
     * @param  string|array  $events
     * @param  array  $args
     * @return void
     */
    protected function dispatch($events, array $args = [])
    {
        foreach (Arr::wrap($events) as $name) {
            $this->fireCustomModelEvent($name, $args);
        }
    }

    /**
     * Fire a custom model event.
     *
     * @param  string  $name
     * @param  array  $args
     * @return mixed
     */
    protected function fireCustomModelEvent($name, array $args = [])
    {
        $event = implode('\\', [Events::class, ucfirst($name)]);

        return $this->fireModelEvent(new $event($this, ...$args));
    }

    /**
     * Fire a model event.
     *
     * @param  Event  $event
     * @return mixed
     */
    protected function fireModelEvent(Event $event)
    {
        return static::getConnectionContainer()->getEventDispatcher()->fire($event);
    }

    /**
     * Listen to a model event.
     *
     * @param  string  $event
     * @param  Closure  $listener
     * @return mixed
     */
    protected function listenForModelEvent($event, Closure $listener)
    {
        return static::getConnectionContainer()->getEventDispatcher()->listen($event, $listener);
    }
}
