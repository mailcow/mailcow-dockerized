``timezone_name``
=================

The ``timezone_name`` filter returns the timezone name given a timezone identifier:

.. code-block:: twig

    {# Central European Time (Paris) #}
    {{ 'Europe/Paris'|timezone_name }}

    {# Pacific Time (Los Angeles) #}
    {{ 'America/Los_Angeles'|timezone_name }}

By default, the filter uses the current locale. You can pass it explicitly:

.. code-block:: twig

    {# heure du Pacifique nord-américain (Los Angeles) #}
    {{ 'America/Los_Angeles'|timezone_name('fr') }}

.. note::

    The ``timezone_name`` filter is part of the ``IntlExtension`` which is not
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
