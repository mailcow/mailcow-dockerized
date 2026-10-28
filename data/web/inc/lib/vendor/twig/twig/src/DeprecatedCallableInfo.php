<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class DeprecatedCallableInfo
{
    private string $type;
    private string $name;

    public function __construct(
        private string $package,
        private string $version,
        private ?string $altName = null,
        private ?string $altPackage = null,
        private ?string $altVersion = null,
    ) {
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function triggerDeprecation(?string $file = null, ?int $line = null): void
    {
        $message = \sprintf('Twig %s "%s" is deprecated', ucfirst($this->type), $this->name);

        if ($this->altName) {
            $message .= \sprintf('; use "%s"', $this->altName);
            if ($this->altPackage) {
                $message .= \sprintf(' from the "%s" package', $this->altPackage);
            }
            if ($this->altVersion) {
                $message .= \sprintf(' (available since version %s)', $this->altVersion);
            }
            $message .= ' instead';
        }

        if ($file) {
            $message .= \sprintf(' in %s', $file);
            if ($line) {
                $message .= \sprintf(' at line %d', $line);
            }
        }

        $message .= '.';

        trigger_deprecation($this->package, $this->version, $message);
    }
}
