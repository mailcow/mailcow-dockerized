Twig Internals
==============

Twig is very extensible and you can hack it. Keep in mind that you
should probably try to create an extension before hacking the core, as most
features and enhancements can be handled with extensions. This chapter is also
useful for people who want to understand how Twig works under the hood.

How does Twig work?
-------------------

The rendering of a Twig template can be summarized into four key steps:

* **Load** the template: If the template is already compiled, load it and go
  to the *evaluation* step, otherwise:

  * First, the **lexer** tokenizes the template source code into small pieces
    for easier processing;

  * Then, the **parser** converts the token stream into a meaningful tree
    of nodes (the Abstract Syntax Tree);

  * Finally, the *compiler* transforms the AST into PHP code.

* **Evaluate** the template: It means calling the ``display()``
  method of the compiled template and passing it the context.

The Lexer
---------

The lexer tokenizes a template source code into a token stream (each token is
an instance of ``\Twig\Token``, and the stream is an instance of
``\Twig\TokenStream``). The default lexer recognizes 13 different token types:

* ``\Twig\Token::BLOCK_START_TYPE``, ``\Twig\Token::BLOCK_END_TYPE``: Delimiters for blocks (``{% %}``)
* ``\Twig\Token::VAR_START_TYPE``, ``\Twig\Token::VAR_END_TYPE``: Delimiters for variables (``{{ }}``)
* ``\Twig\Token::TEXT_TYPE``: A text outside an expression;
* ``\Twig\Token::NAME_TYPE``: A name in an expression;
* ``\Twig\Token::NUMBER_TYPE``: A number in an expression;
* ``\Twig\Token::STRING_TYPE``: A string in an expression;
* ``\Twig\Token::OPERATOR_TYPE``: An operator;
* ``\Twig\Token::PUNCTUATION_TYPE``: A punctuation sign;
* ``\Twig\Token::INTERPOLATION_START_TYPE``, ``\Twig\Token::INTERPOLATION_END_TYPE``: Delimiters for string interpolation;
* ``\Twig\Token::EOF_TYPE``: Ends of template.

You can manually convert a source code into a token stream by calling the
``tokenize()`` method of an environment::

    $stream = $twig->tokenize(new \Twig\Source($source, $identifier));

As the stream has a ``__toString()`` method, you can have a textual
representation of it by echoing the object::

    echo $stream."\n";

Here is the output for the ``Hello {{ name }}`` template:

.. code-block:: text

    TEXT_TYPE(Hello )
    VAR_START_TYPE()
    NAME_TYPE(name)
    VAR_END_TYPE()
    EOF_TYPE()

.. note::

    The default lexer (``\Twig\Lexer``) can be changed by calling
    the ``setLexer()`` method::

        $twig->setLexer($lexer);

The Parser
----------

The parser converts the token stream into an AST (Abstract Syntax Tree), or a
node tree (an instance of ``\Twig\Node\ModuleNode``). The core extension defines
the basic nodes like: ``for``, ``if``, ... and the expression nodes.

You can manually convert a token stream into a node tree by calling the
``parse()`` method of an environment::

    $nodes = $twig->parse($stream);

Echoing the node object gives you a nice representation of the tree::

    echo $nodes."\n";

Here is the output for the ``Hello {{ name }}`` template:

.. code-block:: text

    \Twig\Node\ModuleNode(
      \Twig\Node\TextNode(Hello )
      \Twig\Node\PrintNode(
        \Twig\Node\Expression\NameExpression(name)
      )
    )

.. note::

    The default parser (``\Twig\TokenParser\AbstractTokenParser``) can be changed by calling the
    ``setParser()`` method::

        $twig->setParser($parser);

The Compiler
------------

The last step is done by the compiler. It takes a node tree as an input and
generates PHP code usable for runtime execution of the template.

You can manually compile a node tree to PHP code with the ``compile()`` method
of an environment::

    $php = $twig->compile($nodes);

The generated template for a ``Hello {{ name }}`` template reads as follows
(the actual output can differ depending on the version of Twig you are
using)::

    /* Hello {{ name }} */
    class __TwigTemplate_1121b6f109fe93ebe8c6e22e3712bceb extends Template
    {
        protected function doDisplay(array $context, array $blocks = [])
        {
            // line 1
            echo "Hello ";
            echo twig_escape_filter($this->env, (isset($context["name"]) ? $context["name"] : null), "html", null, true);
        }

        // some more code
    }

.. note::

    The default compiler (``\Twig\Compiler``) can be changed by calling the
    ``setCompiler()`` method::

        $twig->setCompiler($compiler);
