<?php

namespace LdapRecord\Query;

use Closure;
use DateInterval;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;

class Cache
{
    use InteractsWithTime;

    /**
     * The cache driver.
     */
    protected CacheInterface $store;

    /**
     * Constructor.
     */
    public function __construct(CacheInterface $store)
    {
        $this->store = $store;
    }

    /**
     * Get an item from the cache.
     */
    public function get(string $key): mixed
    {
        return $this->store->get($key);
    }

    /**
     * Store an item in the cache.
     */
    public function put(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        return $this->store->set($key, $value, $this->secondsUntil($ttl));
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     */
    public function remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        if (! is_null($value = $this->get($key))) {
            return $value;
        }

        $this->put($key, $value = $callback(), $ttl);

        return $value;
    }

    /**
     * Delete an item from the cache.
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    /**
     * Get the underlying cache store.
     */
    public function store(): CacheInterface
    {
        return $this->store;
    }
}
