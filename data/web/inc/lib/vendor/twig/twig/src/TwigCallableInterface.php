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
 *
 * @method bool needsIsSandboxed()         Whether the callable needs the current sandbox state passed as an argument. Not implementing this method is deprecated since Twig 3.25, it will be required in 4.0.
 * @method bool isAlwaysAllowedInSandbox() Whether the callable is always allowed in sandbox mode, even when not explicitly allow-listed. Not implementing this method is deprecated since Twig 3.28, it will be required in 4.0.
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
