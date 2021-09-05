``html_to_markdown``
====================

The ``html_to_markdown`` filter converts a block of HTML to Markdown:

.. code-block:: html+twig

    {% apply html_to_markdown %}
        <html>
            <h1>Hello!</h1>
        </html>
    {% endapply %}

You can also use the filter on an entire template which you ``include``:

.. code-block:: twig

    {{ include('some_template.html.twig')|html_to_markdown }}

.. note::

    The ``html_to_markdown`` filter is part of the ``MarkdownExtension`` which
    is not installed by default. Install it first:

    .. code-block:: bash

        $ composer require twig/markdown-extra

    On Symfony projects, you can automatically enable it by installing the
    ``twig/extra-bundle``:

    .. code-block:: bash

        $ composer require twig/extra-bundle

    Or add the extension explicitly on the Twig environment::

        use Twig\Extra\Markdown\MarkdownExtension;

        $twig = new \Twig\Environment(...);
        $twig->addExtension(new MarkdownExtension());

    If you are not using Symfony, you must also register the extension runtime::

        use Twig\Extra\Markdown\DefaultMarkdown;
        use Twig\Extra\Markdown\MarkdownRuntime;
        use Twig\RuntimeLoader\RuntimeLoaderInterface;

        $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
            public function load($class) {
                if (MarkdownRuntime::class === $class) {
                    return new MarkdownRuntime(new DefaultMarkdown());
                }
            }
        });

``html_to_markdown`` is just a frontend; the actual conversion is done by one of
the following compatible libraries, from which you can choose:

* `erusev/parsedown`_
* `league/html-to-markdown`_
* `michelf/php-markdown`_

Depending on the library, you can also add some options by passing them as an argument
to the filter. Example for ``league/html-to-markdown``:

.. code-block:: html+twig

    {% apply html_to_markdown({hard_break: false}) %}
        <html>
            <h1>Hello!</h1>
        </html>
    {% endapply %}
    
.. _erusev/parsedown: https://github.com/erusev/parsedown
.. _league/html-to-markdown: https://github.com/thephpleague/html-to-markdown
.. _michelf/php-markdown: https://github.com/michelf/php-markdown
