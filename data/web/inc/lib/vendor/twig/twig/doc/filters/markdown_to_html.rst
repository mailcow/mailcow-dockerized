``markdown_to_html``
====================

The ``markdown_to_html`` filter converts a block of Markdown to HTML:

.. code-block:: twig

    {% apply markdown_to_html %}
    Title
    ======

    Hello!
    {% endapply %}

Note that you can indent the Markdown content as leading whitespaces will be
removed consistently before conversion:

.. code-block:: twig

    {% apply markdown_to_html %}
        Title
        ======

        Hello!
    {% endapply %}

You can also use the filter on an included file or a variable:

.. code-block:: twig

    {{ include('some_template.markdown.twig')|markdown_to_html }}
    
    {{ changelog|markdown_to_html }}

.. note::

    The ``markdown_to_html`` filter is part of the ``MarkdownExtension`` which
    is not installed by default. Install it first:

    .. code-block:: bash

        $ composer require twig/markdown-extra

    Then, on Symfony projects, install the ``twig/extra-bundle``:

    .. code-block:: bash

        $ composer require twig/extra-bundle

    Otherwise, add the extension explicitly on the Twig environment::

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
       
    Afterwards you need to install a markdown library of your choice. Some of them are
    mentioned in the ``require-dev`` section of the ``twig/markdown-extra`` package.
