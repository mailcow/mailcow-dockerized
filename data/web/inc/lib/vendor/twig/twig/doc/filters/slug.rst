``slug``
========

The ``slug`` filter transforms a given string into another string that
only includes safe ASCII characters. 

Here is an example:

.. code-block:: twig

    {{ 'Wôrķšƥáçè ~~sèťtïñğš~~'|slug }}
    Workspace-settings

The default separator between words is a dash (``-``), but you can 
define a selector of your choice by passing it as an argument:

.. code-block:: twig

    {{ 'Wôrķšƥáçè ~~sèťtïñğš~~'|slug('/') }}
    Workspace/settings

The slugger automatically detects the language of the original
string, but you can also specify it explicitly using the second
argument:

.. code-block:: twig

    {{ '...'|slug('-', 'ko') }}

The ``slug`` filter uses the method by the same name in Symfony's 
`AsciiSlugger <https://symfony.com/doc/current/components/string.html#slugger>`_. 

.. note::

    The ``slug`` filter is part of the ``StringExtension`` which is not
    installed by default. Install it first:

    .. code-block:: bash

        $ composer require twig/string-extra

    Then, on Symfony projects, install the ``twig/extra-bundle``:

    .. code-block:: bash

        $ composer require twig/extra-bundle

    Otherwise, add the extension explicitly on the Twig environment::

        use Twig\Extra\String\StringExtension;

        $twig = new \Twig\Environment(...);
        $twig->addExtension(new StringExtension());

Arguments
---------

* ``separator``: The separator that is used to join words (defaults to ``-``)
* ``locale``: The locale of the original string (if none is specified, it will be automatically detected)
