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
interface TwigCallableInterface extends \Stringable
{
    public function getName(): string;

    public function getType(): string;

    public function getDynamicName(): string;

    /**
     * @return callable|array{class-string, string}|null
     */
    public function getCallable();

    public function getNodeClass(): string;

    public function needsCharset(): bool;

    public function needsEnvironment(): bool;

    public function needsContext(): bool;

    public function withDynamicArguments(string $name, string $dynamicName, array $arguments): self;

    public function getArguments(): array;

    public function isVariadic(): bool;

    public function isDeprecated(): bool;

    public function getDeprecatingPackage(): string;

    public function getDeprecatedVersion(): string;

    public function getAlternative(): ?string;

    public function getMinimalNumberOfRequiredArguments(): int;
}
