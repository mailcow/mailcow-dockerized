<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Attribute;

/**
 * Marks nodes that are ready to accept a TwigCallable instead of its name.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class FirstClassTwigCallableReady
{
}
