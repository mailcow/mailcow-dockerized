<?php

namespace LdapRecord\Events;

interface DispatcherInterface
{
    /**
     * Register an event listener with the dispatcher.
     */
    public function listen(string|array $events, mixed $listener): void;

    /**
     * Determine if a given event has listeners.
     */
    public function hasListeners(string $event): bool;

    /**
     * Fire an event until the first non-null response is returned.
     */
    public function until(string|object $event, mixed $payload = []): mixed;

    /**
     * Fire an event and call the listeners.
     */
    public function fire(string|object $event, mixed $payload = [], bool $halt = false): void;

    /**
     * Fire an event and call the listeners.
     */
    public function dispatch(string|object $event, mixed $payload = [], $halt = false): mixed;

    /**
     * Get all the listeners for a given event name.
     */
    public function getListeners(string $event): array;

    /**
     * Remove a set of listeners from the dispatcher.
     */
    public function forget(string $event): void;

    /**
     * Remove all the listeners from the dispatcher.
     */
    public function forgetAll(): void;
}
