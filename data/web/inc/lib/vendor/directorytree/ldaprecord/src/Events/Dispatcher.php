<?php

namespace LdapRecord\Events;

use Closure;
use LdapRecord\Support\Arr;
use LdapRecord\Support\Str;

/**
 * Class Dispatcher.
 *
 * Handles event listening and dispatching.
 *
 * This code was taken out of the Laravel Framework core
 * with broadcasting and queuing omitted to remove
 * an extra dependency that would be required.
 *
 * @author Taylor Otwell
 *
 * @see https://github.com/laravel/framework
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * The registered event listeners.
     */
    protected array $listeners = [];

    /**
     * The wildcard listeners.
     */
    protected array $wildcards = [];

    /**
     * The cached wildcard listeners.
     */
    protected array $wildcardsCache = [];

    /**
     * {@inheritdoc}
     */
    public function listen(string|array $events, mixed $listener): void
    {
        foreach ((array) $events as $event) {
            if (str_contains((string) $event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][] = $this->makeListener($listener);
            }
        }
    }

    /**
     * Setup a wildcard listener callback.
     */
    protected function setupWildcardListen(string $event, mixed $listener): void
    {
        $this->wildcards[$event][] = $this->makeListener($listener, true);

        $this->wildcardsCache = [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) || isset($this->wildcards[$event]);
    }

    /**
     * {@inheritdoc}
     */
    public function until(string|object $event, mixed $payload = []): mixed
    {
        return $this->dispatch($event, $payload, true);
    }

    /**
     * {@inheritdoc}
     */
    public function fire(string|object $event, mixed $payload = [], bool $halt = false): void
    {
        $this->dispatch($event, $payload, $halt);
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(string|object $event, mixed $payload = [], $halt = false): mixed
    {
        // When the given "event" is actually an object we will assume it is an event
        // object and use the class as the event name and this event itself as the
        // payload to the handler, which makes object based events quite simple.
        [$event, $payload] = $this->parseEventAndPayload(
            $event,
            $payload
        );

        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            $response = $listener($event, $payload);

            // If a response is returned from the listener and event halting is enabled
            // we will just return this response, and not call the rest of the event
            // listeners. Otherwise we will add the response on the response list.
            if ($halt && ! is_null($response)) {
                return $response;
            }

            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and firing every one in our sequence.
            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    /**
     * Parse the given event and payload and prepare them for dispatching.
     */
    protected function parseEventAndPayload(string|object $event, mixed $payload): array
    {
        if (is_object($event)) {
            [$payload, $event] = [[$event], get_class($event)];
        }

        return [$event, Arr::wrap($payload)];
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(string $event): array
    {
        $listeners = $this->listeners[$event] ?? [];

        $listeners = array_merge(
            $listeners,
            $this->wildcardsCache[$event] ?? $this->getWildcardListeners($event)
        );

        return class_exists($event, false)
            ? $this->addInterfaceListeners($event, $listeners)
            : $listeners;
    }

    /**
     * Get the wildcard listeners for the event.
     */
    protected function getWildcardListeners(string $event): array
    {
        $wildcards = [];

        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $event)) {
                $wildcards = array_merge($wildcards, $listeners);
            }
        }

        return $this->wildcardsCache[$event] = $wildcards;
    }

    /**
     * Add the listeners for the event's interfaces to the given array.
     */
    protected function addInterfaceListeners(string $eventName, array $listeners = []): array
    {
        foreach (class_implements($eventName) as $interface) {
            if (isset($this->listeners[$interface])) {
                foreach ($this->listeners[$interface] as $names) {
                    $listeners = array_merge($listeners, (array) $names);
                }
            }
        }

        return $listeners;
    }

    /**
     * Register an event listener with the dispatcher.
     */
    public function makeListener(Closure|string $listener, bool $wildcard = false): Closure
    {
        if (is_string($listener)) {
            return $this->createClassListener($listener, $wildcard);
        }

        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return $listener($event, $payload);
            }

            return $listener(...array_values($payload));
        };
    }

    /**
     * Create a class based listener.
     */
    protected function createClassListener(string $listener, bool $wildcard = false): Closure
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return call_user_func($this->createClassCallable($listener), $event, $payload);
            }

            return call_user_func_array(
                $this->createClassCallable($listener),
                $payload
            );
        };
    }

    /**
     * Create the class based event callable.
     */
    protected function createClassCallable(string $listener): callable
    {
        [$class, $method] = $this->parseListenerCallback($listener);

        return [new $class, $method];
    }

    /**
     * Parse the class listener into class and method.
     */
    protected function parseListenerCallback(string $listener): array
    {
        return str_contains($listener, '@')
            ? explode('@', $listener, 2)
            : [$listener, 'handle'];
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $event): void
    {
        if (str_contains($event, '*')) {
            unset($this->wildcards[$event]);
        } else {
            unset($this->listeners[$event]);
        }

        foreach ($this->wildcardsCache as $key => $listeners) {
            if (Str::is($event, $key)) {
                unset($this->wildcardsCache[$key]);
            }
        }
    }

    /**
     * Remove all the listeners from the dispatcher.
     */
    public function forgetAll(): void
    {
        $listeners = array_merge(
            $this->listeners, $this->wildcards
        );

        foreach (array_keys($listeners) as $listener) {
            $this->forget($listener);
        }
    }
}
