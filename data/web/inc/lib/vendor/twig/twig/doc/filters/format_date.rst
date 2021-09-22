``format_date``
===============

The ``format_date`` filter formats a date. It behaves in the exact same way as
the :doc:`format_datetime<format_datetime>` filter, but without the time.

.. note::

    The ``format_date`` filter is part of the ``IntlExtension`` which is not
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
* ``dateFormat``: The date format
* ``pattern``: A date time pattern
* ``timezone``: The date timezone
* ``calendar``: The calendar (Gregorian by default)
