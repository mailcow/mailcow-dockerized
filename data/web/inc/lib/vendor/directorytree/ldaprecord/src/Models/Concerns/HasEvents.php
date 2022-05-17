<?php

namespace LdapRecord\Models\Concerns;

use Closure;
use LdapRecord\Events\NullDispatcher;
use LdapRecord\Models\Events\Event;

trait HasEvents
{
    /**
     * Execute the callback without raising any events.
     *
     * @param Closure $callback
     *
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
     * Fires the specified model event.
     *
     * @param Event $event
     *
     * @return mixed
     */
    protected function fireModelEvent(Event $event)
    {
        return static::getConnectionContainer()->getEventDispatcher()->fire($event);
    }

    /**
     * Listens to a model event.
     *
     * @param string  $event
     * @param Closure $listener
     *
     * @return mixed
     */
    protected function listenForModelEvent($event, Closure $listener)
    {
        return static::getConnectionContainer()->getEventDispatcher()->listen($event, $listener);
    }
}
