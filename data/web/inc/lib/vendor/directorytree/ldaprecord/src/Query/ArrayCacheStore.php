<?php

namespace LdapRecord\Query;

use Psr\SimpleCache\CacheInterface;

class ArrayCacheStore implements CacheInterface
{
    use InteractsWithTime;

    /**
     * An array of stored values.
     */
    protected array $storage = [];

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null): mixed
    {
        if (! isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];

        $expiresAt = $item['expiresAt'] ?? 0;

        if ($expiresAt !== 0 && $this->currentTime() > $expiresAt) {
            $this->delete($key);

            return $default;
        }

        return $item['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null): bool
    {
        $this->storage[$key] = [
            'value' => $value,
            'expiresAt' => $this->calculateExpiration($ttl),
        ];

        return true;
    }

    /**
     * Get the expiration time of the key.
     */
    protected function calculateExpiration($seconds = null): int
    {
        return $this->toTimestamp($seconds);
    }

    /**
     * Get the UNIX timestamp for the given number of seconds.
     */
    protected function toTimestamp($seconds = null): int
    {
        return $seconds > 0 ? $this->availableAt($seconds) : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key): bool
    {
        unset($this->storage[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->storage = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key): bool
    {
        return isset($this->storage[$key]);
    }
}
