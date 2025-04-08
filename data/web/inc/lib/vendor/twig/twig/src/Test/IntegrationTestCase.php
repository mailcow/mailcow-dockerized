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

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
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
     * @dataProvider getTests
     */
    public function testIntegration($file, $message, $condition, $templates, $exception, $outputs, $deprecation = '')
    {
        $this->doIntegrationTest($file, $message, $condition, $templates, $exception, $outputs, $deprecation);
    }

    /**
     * @dataProvider getLegacyTests
     *
     * @group legacy
     */
    public function testLegacyIntegration($file, $message, $condition, $templates, $exception, $outputs, $deprecation = '')
    {
        $this->doIntegrationTest($file, $message, $condition, $templates, $exception, $outputs, $deprecation);
    }

    /**
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

            $tests[] = [str_replace($fixturesDir.'/', '', $file), $message, $condition, $templates, $exception, $outputs, $deprecation];
        }

        if ($legacyTests && empty($tests)) {
            // add a dummy test to avoid a PHPUnit message
            return [['not', '-', '', [], '', []]];
        }

        return $tests;
    }

    /**
     * @final since Twig 3.13
     */
    public function getLegacyTests()
    {
        return $this->getTests('testLegacyIntegration', true);
    }

    protected function doIntegrationTest($file, $message, $condition, $templates, $exception, $outputs, $deprecation = '')
    {
        if (!$outputs) {
            $this->markTestSkipped('no tests to run');
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
            foreach ($templates as $j => $template) {
                $templates[$j] = $template.str_repeat(' ', $i);
            }
            $loader = new ArrayLoader($templates);
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

            $deprecations = [];
            try {
                $prevHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$deprecations, &$prevHandler) {
                    if (\E_USER_DEPRECATED === $type) {
                        $deprecations[] = $msg;

                        return true;
                    }

                    return $prevHandler ? $prevHandler($type, $msg, $file, $line, $context) : false;
                });

                $template = $twig->load('index.twig');
            } catch (\Exception $e) {
                if (false !== $exception) {
                    $message = $e->getMessage();
                    $this->assertSame(trim($exception), trim(\sprintf('%s: %s', \get_class($e), $message)));
                    $last = substr($message, \strlen($message) - 1);
                    $this->assertTrue('.' === $last || '?' === $last, 'Exception message must end with a dot or a question mark.');

                    return;
                }

                throw new Error(\sprintf('%s: %s', \get_class($e), $e->getMessage()), -1, null, $e);
            } finally {
                restore_error_handler();
            }

            $this->assertSame($deprecation, implode("\n", $deprecations));

            try {
                $output = trim($template->render(eval($match[1].';')), "\n ");
            } catch (\Exception $e) {
                if (false !== $exception) {
                    $this->assertSame(trim($exception), trim(\sprintf('%s: %s', \get_class($e), $e->getMessage())));

                    return;
                }

                $e = new Error(\sprintf('%s: %s', \get_class($e), $e->getMessage()), -1, null, $e);

                $output = trim(\sprintf('%s: %s', \get_class($e), $e->getMessage()));
            }

            if (false !== $exception) {
                [$class] = explode(':', $exception);
                $constraintClass = class_exists('PHPUnit\Framework\Constraint\Exception') ? 'PHPUnit\Framework\Constraint\Exception' : 'PHPUnit_Framework_Constraint_Exception';
                $this->assertThat(null, new $constraintClass($class));
            }

            $expected = trim($match[3], "\n ");

            if ($expected !== $output) {
                printf("Compiled templates that failed on case %d:\n", $i + 1);

                foreach (array_keys($templates) as $name) {
                    echo "Template: $name\n";
                    echo $twig->compile($twig->parse($twig->tokenize($twig->getLoader()->getSourceContext($name))));
                }
            }
            $this->assertEquals($expected, $output, $message.' (in '.$file.')');
        }
    }

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
