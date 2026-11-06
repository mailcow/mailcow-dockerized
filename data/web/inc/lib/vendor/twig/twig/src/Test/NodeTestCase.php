<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Test;

use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Node;

abstract class NodeTestCase extends TestCase
{
    /**
     * @var Environment
     */
    private $currentEnv;

    /**
     * @return iterable<array{0: Node, 1: string, 2?: Environment|null, 3?: bool}>
     */
    public function getTests()
    {
        return [];
    }

    /**
     * @return iterable<array{0: Node, 1: string, 2?: Environment|null, 3?: bool}>
     */
    public static function provideTests(): iterable
    {
        trigger_deprecation('twig/twig', '3.13', 'Not implementing "%s()" in "%s" is deprecated. This method will be abstract in 4.0.', __METHOD__, static::class);

        return [];
    }

    /**
     * The non-static "getTests" is intentionally not mirrored as an attribute: PHPUnit >= 11 rejects non-static providers, so PHPUnit >= 10 relies on the static "provideTests" instead.
     *
     * @dataProvider getTests
     * @dataProvider provideTests
     *
     * @return void
     */
    #[DataProvider('provideTests')]
    public function testCompile($node, $source, $environment = null, $isPattern = false)
    {
        $this->assertNodeCompilation($source, $node, $environment, $isPattern);
    }

    /**
     * @return void
     */
    public function assertNodeCompilation($source, Node $node, ?Environment $environment = null, $isPattern = false)
    {
        $compiler = $this->getCompiler($environment);
        $compiler->compile($node);

        if ($isPattern) {
            $this->assertStringMatchesFormat($source, trim($compiler->getSource()));
        } else {
            $this->assertEquals($source, trim($compiler->getSource()));
        }
    }

    /**
     * @return Compiler
     */
    protected function getCompiler(?Environment $environment = null)
    {
        return new Compiler($environment ?? $this->getEnvironment());
    }

    /**
     * @return Environment
     *
     * @final since Twig 3.13
     */
    protected function getEnvironment()
    {
        return $this->currentEnv ??= static::createEnvironment();
    }

    protected static function createEnvironment(): Environment
    {
        return new Environment(new ArrayLoader());
    }

    /**
     * @return string
     *
     * @deprecated since Twig 3.13, use createVariableGetter() instead.
     */
    protected function getVariableGetter($name, $line = false)
    {
        trigger_deprecation('twig/twig', '3.13', 'Method "%s()" is deprecated, use "createVariableGetter()" instead.', __METHOD__);

        return self::createVariableGetter($name, $line);
    }

    final protected static function createVariableGetter(string $name, bool $line = false): string
    {
        $line = $line > 0 ? "// line $line\n" : '';

        return \sprintf('%s($context["%s"] ?? null)', $line, $name);
    }

    /**
     * @return string
     *
     * @deprecated since Twig 3.13, use createAttributeGetter() instead.
     */
    protected function getAttributeGetter()
    {
        trigger_deprecation('twig/twig', '3.13', 'Method "%s()" is deprecated, use "createAttributeGetter()" instead.', __METHOD__);

        return self::createAttributeGetter();
    }

    final protected static function createAttributeGetter(): string
    {
        return 'CoreExtension::getAttribute($this->env, $this->source, ';
    }

    /** @beforeClass */
    #[BeforeClass]
    final public static function checkDataProvider(): void
    {
        $r = new \ReflectionMethod(static::class, 'getTests');
        if (self::class !== $r->getDeclaringClass()->getName()) {
            trigger_deprecation('twig/twig', '3.13', 'Implementing "%s::getTests()" in "%s" is deprecated, implement "provideTests()" instead.', self::class, static::class);
        }
    }
}
