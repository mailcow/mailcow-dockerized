<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Loader;

use Twig\Error\LoaderError;
use Twig\Source;

/**
 * Loads templates from other loaders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ChainLoader implements LoaderInterface
{
    /**
     * @var array<string, bool>
     */
    private $hasSourceCache = [];

    /**
     * @param iterable<LoaderInterface> $loaders
     */
    public function __construct(
        private iterable $loaders = [],
    ) {
    }

    public function addLoader(LoaderInterface $loader): void
    {
        $current = $this->loaders;

        $this->loaders = (static function () use ($current, $loader): \Generator {
            yield from $current;
            yield $loader;
        })();

        $this->hasSourceCache = [];
    }

    /**
     * @return LoaderInterface[]
     */
    public function getLoaders(): array
    {
        if (!\is_array($this->loaders)) {
            $this->loaders = iterator_to_array($this->loaders, false);
        }

        return $this->loaders;
    }

    public function getSourceContext(string $name): Source
    {
        $exceptions = [];

        foreach ($this->getLoaders() as $loader) {
            if (!$loader->exists($name)) {
                continue;
            }

            try {
                return $loader->getSourceContext($name);
            } catch (LoaderError $e) {
                $exceptions[] = $e->getMessage();
            }
        }

        throw new LoaderError(\sprintf('Template "%s" is not defined%s.', $name, $exceptions ? ' ('.implode(', ', $exceptions).')' : ''));
    }

    public function exists(string $name): bool
    {
        if (isset($this->hasSourceCache[$name])) {
            return $this->hasSourceCache[$name];
        }

        foreach ($this->getLoaders() as $loader) {
            if ($loader->exists($name)) {
                return $this->hasSourceCache[$name] = true;
            }
        }

        return $this->hasSourceCache[$name] = false;
    }

    public function getCacheKey(string $name): string
    {
        $exceptions = [];

        foreach ($this->getLoaders() as $loader) {
            if (!$loader->exists($name)) {
                continue;
            }

            try {
                return $loader->getCacheKey($name);
            } catch (LoaderError $e) {
                $exceptions[] = $loader::class.': '.$e->getMessage();
            }
        }

        throw new LoaderError(\sprintf('Template "%s" is not defined%s.', $name, $exceptions ? ' ('.implode(', ', $exceptions).')' : ''));
    }

    public function isFresh(string $name, int $time): bool
    {
        $exceptions = [];

        foreach ($this->getLoaders() as $loader) {
            if (!$loader->exists($name)) {
                continue;
            }

            try {
                return $loader->isFresh($name, $time);
            } catch (LoaderError $e) {
                $exceptions[] = $loader::class.': '.$e->getMessage();
            }
        }

        throw new LoaderError(\sprintf('Template "%s" is not defined%s.', $name, $exceptions ? ' ('.implode(', ', $exceptions).')' : ''));
    }
}
