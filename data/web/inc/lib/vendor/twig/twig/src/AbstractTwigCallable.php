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
abstract class AbstractTwigCallable implements TwigCallableInterface
{
    protected $options;

    private $name;
    private $dynamicName;
    private $callable;
    private $arguments;

    public function __construct(string $name, $callable = null, array $options = [])
    {
        $this->name = $this->dynamicName = $name;
        $this->callable = $callable;
        $this->arguments = [];
        $this->options = array_merge([
            'needs_environment' => false,
            'needs_context' => false,
            'needs_charset' => false,
            'needs_is_sandboxed' => false,
            'is_variadic' => false,
            'always_allowed_in_sandbox' => false,
            'deprecation_info' => null,
            'deprecated' => false,
            'deprecating_package' => '',
            'alternative' => null,
        ], $options);

        if ($this->options['deprecation_info'] && !$this->options['deprecation_info'] instanceof DeprecatedCallableInfo) {
            throw new \LogicException(\sprintf('The "deprecation_info" option must be an instance of "%s".', DeprecatedCallableInfo::class));
        }

        if ($this->options['deprecated']) {
            if ($this->options['deprecation_info']) {
                throw new \LogicException('When setting the "deprecation_info" option, you need to remove the obsolete deprecated options.');
            }

            trigger_deprecation('twig/twig', '3.15', 'Using the "deprecated", "deprecating_package", and "alternative" options is deprecated, pass a "deprecation_info" one instead.');

            $this->options['deprecation_info'] = new DeprecatedCallableInfo(
                $this->options['deprecating_package'],
                $this->options['deprecated'],
                null,
                $this->options['alternative'],
            );
        }

        if ($this->options['deprecation_info']) {
            $this->options['deprecation_info']->setName($name);
            $this->options['deprecation_info']->setType($this->getType());
        }
    }

    public function __toString(): string
    {
        return \sprintf('%s(%s)', static::class, $this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDynamicName(): string
    {
        return $this->dynamicName;
    }

    /**
     * @return callable|array{class-string, string}|null
     */
    public function getCallable()
    {
        return $this->callable;
    }

    public function getNodeClass(): string
    {
        return $this->options['node_class'];
    }

    public function needsCharset(): bool
    {
        return $this->options['needs_charset'];
    }

    public function needsEnvironment(): bool
    {
        return $this->options['needs_environment'];
    }

    public function needsContext(): bool
    {
        return $this->options['needs_context'];
    }

    public function needsIsSandboxed(): bool
    {
        return $this->options['needs_is_sandboxed'];
    }

    public function isAlwaysAllowedInSandbox(): bool
    {
        return $this->options['always_allowed_in_sandbox'];
    }

    /**
     * @return static
     */
    public function withDynamicArguments(string $name, string $dynamicName, array $arguments): self
    {
        $new = clone $this;
        $new->name = $name;
        $new->dynamicName = $dynamicName;
        $new->arguments = $arguments;

        return $new;
    }

    /**
     * @deprecated since Twig 3.12, use withDynamicArguments() instead
     */
    public function setArguments(array $arguments): void
    {
        trigger_deprecation('twig/twig', '3.12', 'The "%s::setArguments()" method is deprecated, use "%s::withDynamicArguments()" instead.', static::class, static::class);

        $this->arguments = $arguments;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function isVariadic(): bool
    {
        return $this->options['is_variadic'];
    }

    public function isDeprecated(): bool
    {
        return (bool) $this->options['deprecation_info'];
    }

    public function triggerDeprecation(?string $file = null, ?int $line = null): void
    {
        $this->options['deprecation_info']->triggerDeprecation($file, $line);
    }

    /**
     * @deprecated since Twig 3.15
     */
    public function getDeprecatingPackage(): string
    {
        trigger_deprecation('twig/twig', '3.15', 'The "%s" method is deprecated, use "%s::triggerDeprecation()" instead.', __METHOD__, static::class);

        return $this->options['deprecating_package'];
    }

    /**
     * @deprecated since Twig 3.15
     */
    public function getDeprecatedVersion(): string
    {
        trigger_deprecation('twig/twig', '3.15', 'The "%s" method is deprecated, use "%s::triggerDeprecation()" instead.', __METHOD__, static::class);

        return \is_bool($this->options['deprecated']) ? '' : $this->options['deprecated'];
    }

    /**
     * @deprecated since Twig 3.15
     */
    public function getAlternative(): ?string
    {
        trigger_deprecation('twig/twig', '3.15', 'The "%s" method is deprecated, use "%s::triggerDeprecation()" instead.', __METHOD__, static::class);

        return $this->options['alternative'];
    }

    public function getMinimalNumberOfRequiredArguments(): int
    {
        return ($this->options['needs_charset'] ? 1 : 0) + ($this->options['needs_environment'] ? 1 : 0) + ($this->options['needs_context'] ? 1 : 0) + ($this->options['needs_is_sandboxed'] ? 1 : 0) + \count($this->arguments);
    }
}
