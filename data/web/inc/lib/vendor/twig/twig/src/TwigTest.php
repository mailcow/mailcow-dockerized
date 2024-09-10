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

use Twig\Node\Expression\TestExpression;

/**
 * Represents a template test.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see https://twig.symfony.com/doc/templates.html#test-operator
 */
final class TwigTest extends AbstractTwigCallable
{
    /**
     * @param callable|array{class-string, string}|null $callable A callable implementing the test. If null, you need to overwrite the "node_class" option to customize compilation.
     */
    public function __construct(string $name, $callable = null, array $options = [])
    {
        parent::__construct($name, $callable, $options);

        $this->options = array_merge([
            'node_class' => TestExpression::class,
            'one_mandatory_argument' => false,
        ], $this->options);
    }

    public function getType(): string
    {
        return 'test';
    }

    public function needsCharset(): bool
    {
        return false;
    }

    public function needsEnvironment(): bool
    {
        return false;
    }

    public function needsContext(): bool
    {
        return false;
    }

    public function hasOneMandatoryArgument(): bool
    {
        return (bool) $this->options['one_mandatory_argument'];
    }

    public function getMinimalNumberOfRequiredArguments(): int
    {
        return parent::getMinimalNumberOfRequiredArguments() + 1;
    }
}
