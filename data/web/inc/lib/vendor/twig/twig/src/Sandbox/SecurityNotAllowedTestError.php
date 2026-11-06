<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Sandbox;

/**
 * Exception thrown when a not allowed test is used in a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SecurityNotAllowedTestError extends SecurityError
{
    private string $testName;

    public function __construct(string $message, string $testName)
    {
        parent::__construct($message);
        $this->testName = $testName;
    }

    public function getTestName(): string
    {
        return $this->testName;
    }
}
