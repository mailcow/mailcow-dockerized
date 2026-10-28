<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class CheckSecurityNode extends Node
{
    private $usedFilters;
    private $usedTags;
    private $usedFunctions;
    private $usedTests;

    /**
     * @param array<string, int> $usedFilters
     * @param array<string, int> $usedTags
     * @param array<string, int> $usedFunctions
     * @param array<string, int> $usedTests
     */
    public function __construct(array $usedFilters, array $usedTags, array $usedFunctions, array $usedTests = [])
    {
        if (\func_num_args() < 4) {
            trigger_deprecation('twig/twig', '3.28', 'Not passing the "$usedTests" argument to "%s::__construct()" is deprecated; it will be required in 4.0.', static::class);
        }

        $this->usedFilters = $usedFilters;
        $this->usedTags = $usedTags;
        $this->usedFunctions = $usedFunctions;
        $this->usedTests = $usedTests;

        parent::__construct();
    }

    public function compile(Compiler $compiler): void
    {
        if (isset($this->usedTags['use'])) {
            $compiler
                ->write("\n")
                ->write("protected function checkTraitsAllowed(): void\n")
                ->write("{\n")
                ->indent()
                ->write("if (!\$this->sandbox->isSandboxed(\$this->source)) {\n")
                ->indent()
                ->write("return;\n")
                ->outdent()
                ->write("}\n\n")
                ->write("try {\n")
                ->indent()
                ->write("\$this->sandbox->checkSecurity(['use'], [], [], [], \$this->source);\n")
                ->outdent()
                ->write("} catch (SecurityNotAllowedTagError \$e) {\n")
                ->indent()
                ->write('$e->setTemplateLine(')->repr($this->usedTags['use'])->raw(");\n\n")
                ->write("throw \$e;\n")
                ->outdent()
                ->write("}\n")
                ->outdent()
                ->write("}\n")
            ;
        }

        $compiler
            ->write("\n")
            ->write("public function ensureSecurityChecked(): void\n")
            ->write("{\n")
            ->indent()
            ->write("if (\$this->sandbox->isSandboxed(\$this->source)) {\n")
            ->indent()
            ->write("\$this->checkSecurity();\n")
            ->outdent()
            ->write("}\n")
            ->outdent()
            ->write("}\n")
            ->write("\n")
            ->write("public function checkSecurity()\n")
            ->write("{\n")
            ->indent()
            ->write('static $tags = ')->repr(array_filter($this->usedTags))->raw(";\n")
            ->write('static $filters = ')->repr(array_filter($this->usedFilters))->raw(";\n")
            ->write('static $functions = ')->repr(array_filter($this->usedFunctions))->raw(";\n")
            ->write('static $tests = ')->repr(array_filter($this->usedTests))->raw(";\n\n")
            ->write("try {\n")
            ->indent()
            ->write("\$this->sandbox->checkSecurity(\n")
            ->indent()
            ->write('')->repr(array_keys($this->usedTags))->raw(",\n")
            ->write('')->repr(array_keys($this->usedFilters))->raw(",\n")
            ->write('')->repr(array_keys($this->usedFunctions))->raw(",\n")
            ->write('')->repr(array_keys($this->usedTests))->raw(",\n")
            ->write("\$this->source\n")
            ->outdent()
            ->write(");\n")
            ->outdent()
            ->write("} catch (SecurityError \$e) {\n")
            ->indent()
            ->write("if (\$e instanceof SecurityNotAllowedTagError && isset(\$tags[\$e->getTagName()])) {\n")
            ->indent()
            ->write("\$e->setTemplateLine(\$tags[\$e->getTagName()]);\n")
            ->outdent()
            ->write("} elseif (\$e instanceof SecurityNotAllowedFilterError && isset(\$filters[\$e->getFilterName()])) {\n")
            ->indent()
            ->write("\$e->setTemplateLine(\$filters[\$e->getFilterName()]);\n")
            ->outdent()
            ->write("} elseif (\$e instanceof SecurityNotAllowedFunctionError && isset(\$functions[\$e->getFunctionName()])) {\n")
            ->indent()
            ->write("\$e->setTemplateLine(\$functions[\$e->getFunctionName()]);\n")
            ->outdent()
            ->write("} elseif (\$e instanceof SecurityNotAllowedTestError && isset(\$tests[\$e->getTestName()])) {\n")
            ->indent()
            ->write("\$e->setTemplateLine(\$tests[\$e->getTestName()]);\n")
            ->outdent()
            ->write("}\n\n")
            ->write("throw \$e;\n")
            ->outdent()
            ->write("}\n\n")
            ->outdent()
            ->write("}\n")
        ;
    }
}
