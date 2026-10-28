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

use Twig\Source;

/**
 * Enforces a security policy at runtime.
 *
 * Compiled templates and Twig internals resolve the checker via
 * SandboxExtension::getChecker().
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class SecurityChecker
{
    private bool $sandboxed = false;

    public function __construct(
        private SecurityPolicyInterface $policy,
        private bool $sandboxedGlobally = false,
        private ?SourcePolicyInterface $sourcePolicy = null,
    ) {
    }

    /**
     * Toggles the sandbox mode for the legacy stateful mechanisms.
     *
     * To be removed in 4.0 together with the $sandboxed state: a sandbox
     * environment is permanently sandboxed.
     */
    public function setSandboxed(bool $sandboxed): void
    {
        $this->sandboxed = $sandboxed;
    }

    public function isSandboxed(?Source $source = null): bool
    {
        return $this->sandboxedGlobally || $this->sandboxed || $this->isSourceSandboxed($source);
    }

    /**
     * To be removed in 4.0: only the deprecated SandboxExtension::isSandboxedGlobally() uses it.
     */
    public function isSandboxedGlobally(): bool
    {
        return $this->sandboxedGlobally;
    }

    /**
     * To be removed in 4.0 together with the deprecated SourcePolicyInterface.
     */
    private function isSourceSandboxed(?Source $source): bool
    {
        if (null === $source || null === $this->sourcePolicy) {
            return false;
        }

        return $this->sourcePolicy->enableSandbox($source);
    }

    /**
     * To be removed in 4.0: a sandbox policy is fixed at construction.
     */
    public function setSecurityPolicy(SecurityPolicyInterface $policy): void
    {
        $this->policy = $policy;
    }

    public function getSecurityPolicy(): SecurityPolicyInterface
    {
        return $this->policy;
    }

    /**
     * $source can only be null when called through the deprecated
     * SandboxExtension::checkSecurity() method; type it Source in 4.0.
     */
    public function checkSecurity(array $tags, array $filters, array $functions, array $tests, ?Source $source): void
    {
        if (!$this->isSandboxed($source)) {
            return;
        }

        try {
            if ((new \ReflectionMethod($this->policy, 'checkSecurity'))->getNumberOfParameters() >= 4) {
                $this->policy->checkSecurity($tags, $filters, $functions, $tests);

                return;
            }

            trigger_deprecation('twig/twig', '3.28', 'The "%s::checkSecurity()" method will take a 4th "array $tests" argument in 4.0; not declaring it is deprecated.', $this->policy::class);

            $this->policy->checkSecurity($tags, $filters, $functions);
        } catch (SecurityError $e) {
            $e->setSourceContext($source);

            throw $e;
        }
    }

    public function checkMethodAllowed(mixed $obj, mixed $method, int $lineno = -1, ?Source $source = null): void
    {
        if ($this->isSandboxed($source)) {
            try {
                $this->policy->checkMethodAllowed($obj, $method);
            } catch (SecurityNotAllowedMethodError $e) {
                $e->setSourceContext($source);
                $e->setTemplateLine($lineno);

                throw $e;
            }
        }
    }

    public function checkPropertyAllowed(mixed $obj, mixed $property, int $lineno = -1, ?Source $source = null): void
    {
        if ($this->isSandboxed($source)) {
            try {
                $this->policy->checkPropertyAllowed($obj, $property);
            } catch (SecurityNotAllowedPropertyError $e) {
                $e->setSourceContext($source);
                $e->setTemplateLine($lineno);

                throw $e;
            }
        }
    }

    /**
     * @throws SecurityNotAllowedMethodError
     */
    public function ensureToStringAllowed(mixed $obj, int $lineno = -1, ?Source $source = null): mixed
    {
        return $this->doEnsureToStringAllowed($obj, $lineno, $source, new \SplObjectStorage());
    }

    /**
     * Materializes a spread operand and runs the policy on every element.
     *
     * @throws SecurityNotAllowedMethodError
     */
    public function ensureSpreadAllowed(iterable $obj, int $lineno = -1, ?Source $source = null): array
    {
        $seen = new \SplObjectStorage();
        if ($obj instanceof \Traversable) {
            $seen[$obj] = true;
            $obj = iterator_to_array($obj);
        }

        $this->ensureToStringAllowedForArray($obj, $lineno, $source, $seen);

        return $obj;
    }

    private function doEnsureToStringAllowed(mixed $obj, int $lineno, ?Source $source, \SplObjectStorage $seen): mixed
    {
        if (\is_array($obj)) {
            $this->ensureToStringAllowedForArray($obj, $lineno, $source, $seen);

            return $obj;
        }

        if (!$this->isSandboxed($source)) {
            return $obj;
        }

        if ($obj instanceof \Stringable) {
            try {
                $this->policy->checkMethodAllowed($obj, '__toString');
            } catch (SecurityNotAllowedMethodError $e) {
                $e->setSourceContext($source);
                $e->setTemplateLine($lineno);

                throw $e;
            }
        }

        // Elements yielded by a Traversable may be string-coerced downstream
        // (e.g. by `join`/`replace`), bypassing the policy. Check them now.
        if ($obj instanceof \Traversable) {
            if (isset($seen[$obj])) {
                return $obj;
            }
            $seen[$obj] = true;

            // IteratorAggregate::getIterator() is idempotent, so we can walk
            // the elements and return the original object: host code typed
            // against a specific class (e.g. FormView) keeps working.
            if ($obj instanceof \IteratorAggregate) {
                foreach ($obj as $v) {
                    $this->doEnsureToStringAllowed($v, $lineno, $source, $seen);
                }

                return $obj;
            }

            // Single-pass Iterator/Generator: materialize to validate.
            $array = iterator_to_array($obj);
            $this->ensureToStringAllowedForArray($array, $lineno, $source, $seen);

            if (!$obj instanceof \Stringable) {
                return $array;
            }
        }

        return $obj;
    }

    private function ensureToStringAllowedForArray(array $obj, int $lineno, ?Source $source, \SplObjectStorage $seen, array &$stack = []): void
    {
        foreach ($obj as $k => $v) {
            if (!$v) {
                continue;
            }

            if (!\is_array($v)) {
                $this->doEnsureToStringAllowed($v, $lineno, $source, $seen);
                continue;
            }

            if ($r = \ReflectionReference::fromArrayElement($obj, $k)) {
                if (isset($stack[$r->getId()])) {
                    continue;
                }

                $stack[$r->getId()] = true;
            }

            $this->ensureToStringAllowedForArray($v, $lineno, $source, $seen, $stack);
        }
    }
}
