<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Source;

/**
 * Represents a module node.
 *
 * If you need to customize the behavior of the generated class, add nodes to
 * the following nodes: display_start, display_end, constructor_start,
 * constructor_end, and class_end.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
final class ModuleNode extends Node
{
    /**
     * @param BodyNode $body
     */
    public function __construct(Node $body, ?AbstractExpression $parent, Node $blocks, Node $macros, Node $traits, $embeddedTemplates, Source $source)
    {
        if (!$body instanceof BodyNode) {
            trigger_deprecation('twig/twig', '3.12', \sprintf('Not passing a "%s" instance as the "body" argument of the "%s" constructor is deprecated.', BodyNode::class, static::class));
        }

        $nodes = [
            'body' => $body,
            'blocks' => $blocks,
            'macros' => $macros,
            'traits' => $traits,
            'display_start' => new Node(),
            'display_end' => new Node(),
            'constructor_start' => new Node(),
            'constructor_end' => new Node(),
            'class_end' => new Node(),
        ];
        if (null !== $parent) {
            $nodes['parent'] = $parent;
        }

        // embedded templates are set as attributes so that they are only visited once by the visitors
        parent::__construct($nodes, [
            'index' => null,
            'embedded_templates' => $embeddedTemplates,
        ], 1);

        // populate the template name of all node children
        $this->setSourceContext($source);
    }

    public function setIndex($index)
    {
        $this->setAttribute('index', $index);
    }

    public function compile(Compiler $compiler): void
    {
        $this->compileTemplate($compiler);

        foreach ($this->getAttribute('embedded_templates') as $template) {
            $compiler->subcompile($template);
        }
    }

    protected function compileTemplate(Compiler $compiler)
    {
        if (!$this->getAttribute('index')) {
            $compiler->write('<?php');
        }

        $this->compileClassHeader($compiler);

        $this->compileConstructor($compiler);

        $this->compileGetParent($compiler);

        $this->compileDisplay($compiler);

        $compiler->subcompile($this->getNode('blocks'));

        $this->compileMacros($compiler);

        $this->compileGetTemplateName($compiler);

        $this->compileIsTraitable($compiler);

        $this->compileDebugInfo($compiler);

        $this->compileGetSourceContext($compiler);

        $this->compileClassFooter($compiler);
    }

    protected function compileGetParent(Compiler $compiler)
    {
        if (!$this->hasNode('parent')) {
            return;
        }
        $parent = $this->getNode('parent');

        $compiler
            ->write("protected function doGetParent(array \$context): bool|string|Template|TemplateWrapper\n", "{\n")
            ->indent()
            ->addDebugInfo($parent)
            ->write('return ')
        ;

        if ($parent instanceof ConstantExpression) {
            $compiler->subcompile($parent);
        } else {
            $compiler
                ->raw('$this->loadTemplate(')
                ->subcompile($parent)
                ->raw(', ')
                ->repr($this->getSourceContext()->getName())
                ->raw(', ')
                ->repr($parent->getTemplateLine())
                ->raw(')')
            ;
        }

        $compiler
            ->raw(";\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }

    protected function compileClassHeader(Compiler $compiler)
    {
        $compiler
            ->write("\n\n")
        ;
        if (!$this->getAttribute('index')) {
            $compiler
                ->write("use Twig\Environment;\n")
                ->write("use Twig\Error\LoaderError;\n")
                ->write("use Twig\Error\RuntimeError;\n")
                ->write("use Twig\Extension\CoreExtension;\n")
                ->write("use Twig\Extension\SandboxExtension;\n")
                ->write("use Twig\Markup;\n")
                ->write("use Twig\Sandbox\SecurityError;\n")
                ->write("use Twig\Sandbox\SecurityNotAllowedTagError;\n")
                ->write("use Twig\Sandbox\SecurityNotAllowedFilterError;\n")
                ->write("use Twig\Sandbox\SecurityNotAllowedFunctionError;\n")
                ->write("use Twig\Source;\n")
                ->write("use Twig\Template;\n")
                ->write("use Twig\TemplateWrapper;\n")
                ->write("\n")
            ;
        }
        $compiler
            // if the template name contains */, add a blank to avoid a PHP parse error
            ->write('/* '.str_replace('*/', '* /', $this->getSourceContext()->getName())." */\n")
            ->write('class '.$compiler->getEnvironment()->getTemplateClass($this->getSourceContext()->getName(), $this->getAttribute('index')))
            ->raw(" extends Template\n")
            ->write("{\n")
            ->indent()
            ->write("private Source \$source;\n")
            ->write("/**\n")
            ->write(" * @var array<string, Template>\n")
            ->write(" */\n")
            ->write("private array \$macros = [];\n\n")
        ;
    }

    protected function compileConstructor(Compiler $compiler)
    {
        $compiler
            ->write("public function __construct(Environment \$env)\n", "{\n")
            ->indent()
            ->subcompile($this->getNode('constructor_start'))
            ->write("parent::__construct(\$env);\n\n")
            ->write("\$this->source = \$this->getSourceContext();\n\n")
        ;

        // parent
        if (!$this->hasNode('parent')) {
            $compiler->write("\$this->parent = false;\n\n");
        }

        $countTraits = \count($this->getNode('traits'));
        if ($countTraits) {
            // traits
            foreach ($this->getNode('traits') as $i => $trait) {
                $node = $trait->getNode('template');

                $compiler
                    ->addDebugInfo($node)
                    ->write(\sprintf('$_trait_%s = $this->loadTemplate(', $i))
                    ->subcompile($node)
                    ->raw(', ')
                    ->repr($node->getTemplateName())
                    ->raw(', ')
                    ->repr($node->getTemplateLine())
                    ->raw(");\n")
                    ->write(\sprintf("if (!\$_trait_%s->unwrap()->isTraitable()) {\n", $i))
                    ->indent()
                    ->write("throw new RuntimeError('Template \"'.")
                    ->subcompile($trait->getNode('template'))
                    ->raw(".'\" cannot be used as a trait.', ")
                    ->repr($node->getTemplateLine())
                    ->raw(", \$this->source);\n")
                    ->outdent()
                    ->write("}\n")
                    ->write(\sprintf("\$_trait_%s_blocks = \$_trait_%s->unwrap()->getBlocks();\n\n", $i, $i))
                ;

                foreach ($trait->getNode('targets') as $key => $value) {
                    $compiler
                        ->write(\sprintf('if (!isset($_trait_%s_blocks[', $i))
                        ->string($key)
                        ->raw("])) {\n")
                        ->indent()
                        ->write("throw new RuntimeError('Block ")
                        ->string($key)
                        ->raw(' is not defined in trait ')
                        ->subcompile($trait->getNode('template'))
                        ->raw(".', ")
                        ->repr($node->getTemplateLine())
                        ->raw(", \$this->source);\n")
                        ->outdent()
                        ->write("}\n\n")

                        ->write(\sprintf('$_trait_%s_blocks[', $i))
                        ->subcompile($value)
                        ->raw(\sprintf('] = $_trait_%s_blocks[', $i))
                        ->string($key)
                        ->raw(\sprintf(']; unset($_trait_%s_blocks[', $i))
                        ->string($key)
                        ->raw("]);\n\n")
                    ;
                }
            }

            if ($countTraits > 1) {
                $compiler
                    ->write("\$this->traits = array_merge(\n")
                    ->indent()
                ;

                for ($i = 0; $i < $countTraits; ++$i) {
                    $compiler
                        ->write(\sprintf('$_trait_%s_blocks'.($i == $countTraits - 1 ? '' : ',')."\n", $i))
                    ;
                }

                $compiler
                    ->outdent()
                    ->write(");\n\n")
                ;
            } else {
                $compiler
                    ->write("\$this->traits = \$_trait_0_blocks;\n\n")
                ;
            }

            $compiler
                ->write("\$this->blocks = array_merge(\n")
                ->indent()
                ->write("\$this->traits,\n")
                ->write("[\n")
            ;
        } else {
            $compiler
                ->write("\$this->blocks = [\n")
            ;
        }

        // blocks
        $compiler
            ->indent()
        ;

        foreach ($this->getNode('blocks') as $name => $node) {
            $compiler
                ->write(\sprintf("'%s' => [\$this, 'block_%s'],\n", $name, $name))
            ;
        }

        if ($countTraits) {
            $compiler
                ->outdent()
                ->write("]\n")
                ->outdent()
                ->write(");\n")
            ;
        } else {
            $compiler
                ->outdent()
                ->write("];\n")
            ;
        }

        $compiler
            ->subcompile($this->getNode('constructor_end'))
            ->outdent()
            ->write("}\n\n")
        ;
    }

    protected function compileDisplay(Compiler $compiler)
    {
        $compiler
            ->write("protected function doDisplay(array \$context, array \$blocks = []): iterable\n", "{\n")
            ->indent()
            ->write("\$macros = \$this->macros;\n")
            ->subcompile($this->getNode('display_start'))
            ->subcompile($this->getNode('body'))
        ;

        if ($this->hasNode('parent')) {
            $parent = $this->getNode('parent');

            $compiler->addDebugInfo($parent);
            if ($parent instanceof ConstantExpression) {
                $compiler
                    ->write('$this->parent = $this->loadTemplate(')
                    ->subcompile($parent)
                    ->raw(', ')
                    ->repr($this->getSourceContext()->getName())
                    ->raw(', ')
                    ->repr($parent->getTemplateLine())
                    ->raw(");\n")
                ;
            }
            $compiler->write('yield from ');

            if ($parent instanceof ConstantExpression) {
                $compiler->raw('$this->parent');
            } else {
                $compiler->raw('$this->getParent($context)');
            }
            $compiler->raw("->unwrap()->yield(\$context, array_merge(\$this->blocks, \$blocks));\n");
        }

        $compiler->subcompile($this->getNode('display_end'));

        if (!$this->hasNode('parent')) {
            $compiler->write("yield from [];\n");
        }

        $compiler
            ->outdent()
            ->write("}\n\n")
        ;
    }

    protected function compileClassFooter(Compiler $compiler)
    {
        $compiler
            ->subcompile($this->getNode('class_end'))
            ->outdent()
            ->write("}\n")
        ;
    }

    protected function compileMacros(Compiler $compiler)
    {
        $compiler->subcompile($this->getNode('macros'));
    }

    protected function compileGetTemplateName(Compiler $compiler)
    {
        $compiler
            ->write("/**\n")
            ->write(" * @codeCoverageIgnore\n")
            ->write(" */\n")
            ->write("public function getTemplateName(): string\n", "{\n")
            ->indent()
            ->write('return ')
            ->repr($this->getSourceContext()->getName())
            ->raw(";\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }

    protected function compileIsTraitable(Compiler $compiler)
    {
        // A template can be used as a trait if:
        //   * it has no parent
        //   * it has no macros
        //   * it has no body
        //
        // Put another way, a template can be used as a trait if it
        // only contains blocks and use statements.
        $traitable = !$this->hasNode('parent') && 0 === \count($this->getNode('macros'));
        if ($traitable) {
            if ($this->getNode('body') instanceof BodyNode) {
                $nodes = $this->getNode('body')->getNode('0');
            } else {
                $nodes = $this->getNode('body');
            }

            if (!\count($nodes)) {
                $nodes = new Node([$nodes]);
            }

            foreach ($nodes as $node) {
                if (!\count($node)) {
                    continue;
                }

                $traitable = false;
                break;
            }
        }

        if ($traitable) {
            return;
        }

        $compiler
            ->write("/**\n")
            ->write(" * @codeCoverageIgnore\n")
            ->write(" */\n")
            ->write("public function isTraitable(): bool\n", "{\n")
            ->indent()
            ->write("return false;\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }

    protected function compileDebugInfo(Compiler $compiler)
    {
        $compiler
            ->write("/**\n")
            ->write(" * @codeCoverageIgnore\n")
            ->write(" */\n")
            ->write("public function getDebugInfo(): array\n", "{\n")
            ->indent()
            ->write(\sprintf("return %s;\n", str_replace("\n", '', var_export(array_reverse($compiler->getDebugInfo(), true), true))))
            ->outdent()
            ->write("}\n\n")
        ;
    }

    protected function compileGetSourceContext(Compiler $compiler)
    {
        $compiler
            ->write("public function getSourceContext(): Source\n", "{\n")
            ->indent()
            ->write('return new Source(')
            ->string($compiler->getEnvironment()->isDebug() ? $this->getSourceContext()->getCode() : '')
            ->raw(', ')
            ->string($this->getSourceContext()->getName())
            ->raw(', ')
            ->string($this->getSourceContext()->getPath())
            ->raw(");\n")
            ->outdent()
            ->write("}\n")
        ;
    }

    protected function compileLoadTemplate(Compiler $compiler, $node, $var)
    {
        if ($node instanceof ConstantExpression) {
            $compiler
                ->write(\sprintf('%s = $this->loadTemplate(', $var))
                ->subcompile($node)
                ->raw(', ')
                ->repr($node->getTemplateName())
                ->raw(', ')
                ->repr($node->getTemplateLine())
                ->raw(");\n")
            ;
        } else {
            throw new \LogicException('Trait templates can only be constant nodes.');
        }
    }
}
