<?php

namespace LdapRecord\Events;

class NullDispatcher implements DispatcherInterface
{
    /**
     * The underlying dispatcher instance.
     */
    protected DispatcherInterface $dispatcher;

    /**
     * Constructor.
     */
    public function __construct(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Register an event listener with the dispatcher.
     */
    public function listen(string|array $events, mixed $listener): void
    {
        $this->dispatcher->listen($events, $listener);
    }

    /**
     * Determine if a given event has listeners.
     */
    public function hasListeners(string $event): bool
    {
        return $this->dispatcher->hasListeners($event);
    }

    /**
     * Fire an event until the first non-null response is returned.
     */
    public function until(string|object $event, mixed $payload = []): ?array
    {
        return null;
    }

    /**
     * Fire an event and call the listeners.
     */
    public function fire(string|object $event, mixed $payload = [], bool $halt = false): void {}

    /**
     * Fire an event and call the listeners.
     */
    public function dispatch(string|object $event, mixed $payload = [], $halt = false): mixed
    {
        return null;
    }

    /**
     * Get all the listeners for a given event name.
     */
    public function getListeners(string $event): array
    {
        return $this->dispatcher->getListeners($event);
    }

    /**
     * Remove a set of listeners from the dispatcher.
     */
    public function forget(string $event): void
    {
        $this->dispatcher->forget($event);
    }

    /**
     * Remove all the listeners from the dispatcher.
     */
    public function forgetAll(): void
    {
        $this->dispatcher->forgetAll();
    }
}
