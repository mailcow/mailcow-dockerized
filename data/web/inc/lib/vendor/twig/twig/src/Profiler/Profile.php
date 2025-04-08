<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Profiler;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Profile implements \IteratorAggregate, \Serializable
{
    public const ROOT = 'ROOT';
    public const BLOCK = 'block';
    public const TEMPLATE = 'template';
    public const MACRO = 'macro';
    private $starts = [];
    private $ends = [];
    private $profiles = [];

    public function __construct(
        private string $template = 'main',
        private string $type = self::ROOT,
        private string $name = 'main',
    ) {
        $this->name = str_starts_with($name, '__internal_') ? 'INTERNAL' : $name;
        $this->enter();
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRoot(): bool
    {
        return self::ROOT === $this->type;
    }

    public function isTemplate(): bool
    {
        return self::TEMPLATE === $this->type;
    }

    public function isBlock(): bool
    {
        return self::BLOCK === $this->type;
    }

    public function isMacro(): bool
    {
        return self::MACRO === $this->type;
    }

    /**
     * @return Profile[]
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    public function addProfile(self $profile): void
    {
        $this->profiles[] = $profile;
    }

    /**
     * Returns the duration in microseconds.
     */
    public function getDuration(): float
    {
        if ($this->isRoot() && $this->profiles) {
            // for the root node with children, duration is the sum of all child durations
            $duration = 0;
            foreach ($this->profiles as $profile) {
                $duration += $profile->getDuration();
            }

            return $duration;
        }

        return isset($this->ends['wt']) && isset($this->starts['wt']) ? $this->ends['wt'] - $this->starts['wt'] : 0;
    }

    /**
     * Returns the memory usage in bytes.
     */
    public function getMemoryUsage(): int
    {
        return isset($this->ends['mu']) && isset($this->starts['mu']) ? $this->ends['mu'] - $this->starts['mu'] : 0;
    }

    /**
     * Returns the peak memory usage in bytes.
     */
    public function getPeakMemoryUsage(): int
    {
        return isset($this->ends['pmu']) && isset($this->starts['pmu']) ? $this->ends['pmu'] - $this->starts['pmu'] : 0;
    }

    /**
     * Starts the profiling.
     */
    public function enter(): void
    {
        $this->starts = [
            'wt' => microtime(true),
            'mu' => memory_get_usage(),
            'pmu' => memory_get_peak_usage(),
        ];
    }

    /**
     * Stops the profiling.
     */
    public function leave(): void
    {
        $this->ends = [
            'wt' => microtime(true),
            'mu' => memory_get_usage(),
            'pmu' => memory_get_peak_usage(),
        ];
    }

    public function reset(): void
    {
        $this->starts = $this->ends = $this->profiles = [];
        $this->enter();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->profiles);
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize($data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->template, $this->name, $this->type, $this->starts, $this->ends, $this->profiles];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [$this->template, $this->name, $this->type, $this->starts, $this->ends, $this->profiles] = $data;
    }
}
