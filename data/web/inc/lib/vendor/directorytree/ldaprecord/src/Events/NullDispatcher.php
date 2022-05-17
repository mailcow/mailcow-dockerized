<?php

namespace LdapRecord\Events;

class NullDispatcher implements DispatcherInterface
{
    /**
     * The underlying dispatcher instance.
     *
     * @var DispatcherInterface
     */
    protected $dispatcher;

    /**
     * Constructor.
     *
     * @param DispatcherInterface $dispatcher
     */
    public function __construct(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param string|array $events
     * @param mixed        $listener
     *
     * @return void
     */
    public function listen($events, $listener)
    {
        $this->dispatcher->listen($events, $listener);
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     *
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * Fire an event until the first non-null response is returned.
     *
     * @param string|object $event
     * @param mixed         $payload
     *
     * @return null
     */
    public function until($event, $payload = [])
    {
        return null;
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param string|object $event
     * @param mixed         $payload
     * @param bool          $halt
     *
     * @return null
     */
    public function fire($event, $payload = [], $halt = false)
    {
        return null;
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param string|object $event
     * @param mixed         $payload
     * @param bool          $halt
     *
     * @return null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        return null;
    }

    /**
     * Get all of the listeners for a given event name.
     *
     * @param string $eventName
     *
     * @return array
     */
    public function getListeners($eventName)
    {
        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     *
     * @return void
     */
    public function forget($event)
    {
        $this->dispatcher->forget($event);
    }
}
