``inky_to_html``
================

The ``inky_to_html`` filter processes an `inky email template
<https://github.com/zurb/inky>`_:

.. code-block:: html+twig

    {% apply inky_to_html %}
        <row>
            <columns large="6"></columns>
            <columns large="6"></columns>
        </row>
    {% endapply %}

You can also use the filter on an included file:

.. code-block:: twig

    {{ include('some_template.inky.twig')|inky_to_html }}

.. note::

    The ``inky_to_html`` filter is part of the ``InkyExtension`` which is not
    installed by default. Install it first:

    .. code-block:: bash

        $ composer require twig/inky-extra

    Then, on Symfony projects, install the ``twig/extra-bundle``:

    .. code-block:: bash

        $ composer require twig/extra-bundle

    Otherwise, add the extension explicitly on the Twig environment::

        use Twig\Extra\Inky\InkyExtension;

        $twig = new \Twig\Environment(...);
        $twig->addExtension(new InkyExtension());
