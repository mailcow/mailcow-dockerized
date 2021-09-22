``locale_name``
===============

The ``locale_name`` filter returns the locale name given its two-letter
code:

.. code-block:: twig

    {# German #}
    {{ 'de'|locale_name }}

By default, the filter uses the current locale. You can pass it explicitly:

.. code-block:: twig

    {# allemand #}
    {{ 'de'|locale_name('fr') }}

    {# français (Canada) #}
    {{ 'fr_CA'|locale_name('fr_FR') }}

.. note::

    The ``locale_name`` filter is part of the ``IntlExtension`` which is not
    installed by default. Install it first:

    .. code-block:: bash

        $ composer require twig/intl-extra

    Then, on Symfony projects, install the ``twig/extra-bundle``:

    .. code-block:: bash

        $ composer require twig/extra-bundle

    Otherwise, add the extension explicitly on the Twig environment::

        use Twig\Extra\Intl\IntlExtension;

        $twig = new \Twig\Environment(...);
        $twig->addExtension(new IntlExtension());

Arguments
---------

* ``locale``: The locale
