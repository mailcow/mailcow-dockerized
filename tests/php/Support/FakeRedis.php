<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

/**
 * The few Redis commands of the archive state, in memory (the test image has no redis extension).
 */
class FakeRedis
{
    public array $lists = [];
    public array $strings = [];

    public function set(string $key, string $value): bool
    {
        $this->strings[$key] = $value;
        return true;
    }

    public function get(string $key): string|false
    {
        return $this->strings[$key] ?? false;
    }

    public function incr(string $key): int
    {
        $this->strings[$key] = (string)((int)($this->strings[$key] ?? 0) + 1);
        return (int)$this->strings[$key];
    }

    public function lPush(string $key, string $value): int
    {
        $this->lists[$key] ??= [];
        array_unshift($this->lists[$key], $value);
        return count($this->lists[$key]);
    }

    public function lTrim(string $key, int $start, int $stop): bool
    {
        $this->lists[$key] = array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
        return true;
    }

    /** Like Redis, a negative stop counts from the end (-1 is the last element) */
    public function lRange(string $key, int $start, int $stop): array
    {
        $list = $this->lists[$key] ?? [];
        if ($stop < 0) {
            $stop += count($list);
        }
        return array_slice($list, $start, max(0, $stop - $start + 1));
    }
}
