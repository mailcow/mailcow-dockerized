``country_timezones``
=====================

The ``country_timezones`` function returns the names of the timezones associated
with a given country code:

.. code-block:: twig

    {# Europe/Paris #}
    {{ country_timezones('FR')|join(', ') }}

.. note::

    The ``country_timezones`` function is part of the ``IntlExtension`` which is not
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
