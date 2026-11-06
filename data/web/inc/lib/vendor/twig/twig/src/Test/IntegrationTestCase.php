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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\TokenParser\TokenParserInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Integration test helper.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Karma Dordrak <drak@zikula.org>
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * @deprecated since Twig 3.13, use getFixturesDirectory() instead.
     *
     * @return string
     */
    protected function getFixturesDir()
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    protected static function getFixturesDirectory(): string
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * @return RuntimeLoaderInterface[]
     */
    protected function getRuntimeLoaders()
    {
        return [];
    }

    /**
     * @return ExtensionInterface[]
     */
    protected function getExtensions()
    {
        return [];
    }

    /**
     * @return TwigFilter[]
     */
    protected function getTwigFilters()
    {
        return [];
    }

    /**
     * @return TwigFunction[]
     */
    protected function getTwigFunctions()
    {
        return [];
    }

    /**
     * @return TwigTest[]
     */
    protected function getTwigTests()
    {
        return [];
    }

    /**
     * @return array<callable(string): (TwigFilter|false)>
     */
    protected function getUndefinedFilterCallbacks(): array
    {
        return [];
    }

    /**
     * @return array<callable(string): (TwigFunction|false)>
     */
    protected function getUndefinedFunctionCallbacks(): array
    {
        return [];
    }

    /**
     * @return array<callable(string): (TwigTest|false)>
     */
    protected function getUndefinedTestCallbacks(): array
    {
        return [];
    }

    /**
     * @return array<callable(string): (TokenParserInterface|false)>
     */
    protected function getUndefinedTokenParserCallbacks(): array
    {
        return [];
    }

    /**
     * The annotation feeds PHPUnit < 10; the attribute feeds PHPUnit >= 10 and must point to a static provider, as PHPUnit >= 11 rejects non-static ones.
     *
     * @dataProvider getTests
     *
     * @return void
     */
    #[DataProvider('provideTests')]
    public function testIntegration($file, $message, $condition, $templates, $exception, $outputs, $deprecation = '')
    {
        $this->doIntegrationTest($file, $message, $condition, $templates, $exception, $outputs, $deprecation);
    }

    /**
     * @dataProvider getLegacyTests
     *
     * @group legacy
     *
     * @return void
     */
    #[DataProvider('provideLegacyTests'), Group('legacy')]
    public function testLegacyIntegration($file, $message, $condition, $templates, $exception, $outputs, $deprecation = '')
    {
        $this->doIntegrationTest($file, $message, $condition, $templates, $exception, $outputs, $deprecation);
    }

    final public static function provideTests(): iterable
    {
        return self::assembleTests(false, static::getFixturesDirectory());
    }

    final public static function provideLegacyTests(): iterable
    {
        return self::assembleTests(true, static::getFixturesDirectory());
    }

    /**
     * @return iterable
     *
     * @final since Twig 3.13
     */
    public function getTests($name, $legacyTests = false)
    {
        try {
            $fixturesDir = static::getFixturesDirectory();
        } catch (\BadMethodCallException) {
            trigger_deprecation('twig/twig', '3.13', 'Not overriding "%s::getFixturesDirectory()" in "%s" is deprecated. This method will be abstract in 4.0.', self::class, static::class);
            $fixturesDir = $this->getFixturesDir();
        }

        return self::assembleTests($legacyTests, $fixturesDir);
    }

    private static function assembleTests(bool $legacyTests, string $fixturesDir): array
    {
        $fixturesDir = realpath($fixturesDir);
        $tests = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixturesDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if (!preg_match('/\.test$/', $file)) {
                continue;
            }

            if ($legacyTests xor str_contains($file->getRealpath(), '.legacy.test')) {
                continue;
            }

            $test = file_get_contents($file->getRealpath());

            if (preg_match('/--TEST--\s*(.*?)\s*(?:--CONDITION--\s*(.*))?\s*(?:--DEPRECATION--\s*(.*?))?\s*((?:--TEMPLATE(?:\(.*?\))?--(?:.*?))+)\s*(?:--DATA--\s*(.*))?\s*--EXCEPTION--\s*(.*)/sx', $test, $match)) {
                $message = $match[1];
                $condition = $match[2];
                $deprecation = $match[3];
                $templates = self::parseTemplates($match[4]);
                $exception = $match[6];
                $outputs = [[null, $match[5], null, '']];
            } elseif (preg_match('/--TEST--\s*(.*?)\s*(?:--CONDITION--\s*(.*))?\s*(?:--DEPRECATION--\s*(.*?))?\s*((?:--TEMPLATE(?:\(.*?\))?--(?:.*?))+)--DATA--.*?--EXPECT--.*/s', $test, $match)) {
                $message = $match[1];
                $condition = $match[2];
                $deprecation = $match[3];
                $templates = self::parseTemplates($match[4]);
                $exception = false;
                preg_match_all('/--DATA--(.*?)(?:--CONFIG--(.*?))?--EXPECT--(.*?)(?=\-\-DATA\-\-|$)/s', $test, $outputs, \PREG_SET_ORDER);
            } else {
                throw new \InvalidArgumentException(\sprintf('Test "%s" is not valid.', str_replace($fixturesDir.'/', '', $file)));
            }

            $tests[str_replace($fixturesDir.'/', '', $file)] = [str_replace($fixturesDir.'/', '', $file), $message, $condition, $templates, $exception, $outputs, $deprecation];
        }

        if ($legacyTests && !$tests) {
            // add a dummy test to avoid a PHPUnit message
            return [['not', '-', '', [], '', []]];
        }

        return $tests;
    }

    /**
     * @final since Twig 3.13
     *
     * @return iterable
     */
    public function getLegacyTests()
    {
        return $this->getTests('testLegacyIntegration', true);
    }

    /**
     * @return void
     */
    protected function doIntegrationTest($file, $message, $condition, $templateSources, $exception, $outputs, $deprecation = '')
    {
        if (!$outputs) {
            // dummy test added by assembleTests() when there is no (legacy) test to run
            $this->expectNotToPerformAssertions();

            return;
        }

        if ($condition) {
            $ret = '';
            eval('$ret = '.$condition.';');
            if (!$ret) {
                $this->markTestSkipped($condition);
            }
        }

        foreach ($outputs as $i => $match) {
            $config = array_merge([
                'cache' => false,
                'strict_variables' => true,
            ], $match[2] ? eval($match[2].';') : []);
            // make sure that template are always compiled even if they are the same (useful when testing with more than one data/expect sections)
            foreach ($templateSources as $name => $template) {
                $templateSources[$name] = $template.str_repeat(' ', $i);
            }
            $loader = new ArrayLoader($templateSources);
            $twig = new Environment($loader, $config);
            $twig->addGlobal('global', 'global');
            foreach ($this->getRuntimeLoaders() as $runtimeLoader) {
                $twig->addRuntimeLoader($runtimeLoader);
            }

            foreach ($this->getExtensions() as $extension) {
                $twig->addExtension($extension);
            }

            foreach ($this->getTwigFilters() as $filter) {
                $twig->addFilter($filter);
            }

            foreach ($this->getTwigTests() as $test) {
                $twig->addTest($test);
            }

            foreach ($this->getTwigFunctions() as $function) {
                $twig->addFunction($function);
            }

            foreach ($this->getUndefinedFilterCallbacks() as $callback) {
                $twig->registerUndefinedFilterCallback($callback);
            }

            foreach ($this->getUndefinedFunctionCallbacks() as $callback) {
                $twig->registerUndefinedFunctionCallback($callback);
            }

            foreach ($this->getUndefinedTestCallbacks() as $callback) {
                $twig->registerUndefinedTestCallback($callback);
            }

            foreach ($this->getUndefinedTokenParserCallbacks() as $callback) {
                $twig->registerUndefinedTokenParserCallback($callback);
            }

            $deprecations = [];
            $templates = [];
            try {
                $prevHandler = set_error_handler(static function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$prevHandler) {
                    if (\E_USER_DEPRECATED === $type) {
                        $deprecations[] = $msg;

                        return true;
                    }

                    return $prevHandler ? $prevHandler($type, $msg, $file, $line, $context) : false;
                });

                foreach (array_keys($templateSources) as $templateName) {
                    $templates[$templateName] = $twig->load($templateName);
                }
            } catch (\Exception $e) {
                if (false !== $exception) {
                    $message = $e->getMessage();
                    $this->assertSame(trim($exception), trim(\sprintf('%s: %s', $e::class, $message)));
                    $last = substr($message, \strlen($message) - 1);
                    $this->assertTrue('.' === $last || '?' === $last, 'Exception message must end with a dot or a question mark.');

                    return;
                }

                throw new Error(\sprintf('%s: %s', $e::class, $e->getMessage()), -1, null, $e);
            } finally {
                restore_error_handler();
            }

            $template = $templates['index.twig'];
            $captureRenderDeprecations = '' !== $deprecation;

            try {
                if ($captureRenderDeprecations) {
                    $prevHandler = set_error_handler(static function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$prevHandler) {
                        if (\E_USER_DEPRECATED === $type) {
                            $deprecations[] = $msg;

                            return true;
                        }

                        return $prevHandler ? $prevHandler($type, $msg, $file, $line, $context) : false;
                    });
                }

                $output = trim($template->render(eval($match[1].';')), "\n ");
            } catch (\Exception $e) {
                if (false !== $exception) {
                    $this->assertStringMatchesFormat(trim($exception), trim(\sprintf('%s: %s', $e::class, $e->getMessage())));

                    return;
                }

                $e = new Error(\sprintf('%s: %s', $e::class, $e->getMessage()), -1, null, $e);

                $output = trim(\sprintf('%s: %s', $e::class, $e->getMessage()));
            } finally {
                if ($captureRenderDeprecations) {
                    restore_error_handler();
                }
            }

            if (false !== $exception) {
                [$class] = explode(':', $exception);
                $constraintClass = class_exists('PHPUnit\Framework\Constraint\Exception') ? 'PHPUnit\Framework\Constraint\Exception' : 'PHPUnit_Framework_Constraint_Exception';
                $this->assertThat(null, new $constraintClass($class));
            }

            $expected = trim($match[3], "\n ");

            if ($expected !== $output) {
                printf("Compiled templates that failed on case %d:\n", $i + 1);

                foreach (array_keys($templateSources) as $name) {
                    echo "Template: $name\n";
                    echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSourceContext($name))));
                }
            }
            $this->assertEquals($expected, $output, $message.' (in '.$file.')');

            $this->assertSame($deprecation, implode("\n", $deprecations));
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function parseTemplates($test)
    {
        $templates = [];
        preg_match_all('/--TEMPLATE(?:\((.*?)\))?--(.*?)(?=\-\-TEMPLATE|$)/s', $test, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $templates[$match[1] ?: 'index.twig'] = $match[2];
        }

        return $templates;
    }
}
