``format_datetime``
===================

The ``format_datetime`` filter formats a date time:

.. code-block:: twig

    {# Aug 7, 2019, 11:39:12 PM #}
    {{ '2019-08-07 23:39:12'|format_datetime() }}

You can tweak the output for the date part and the time part:

.. code-block:: twig

    {# 23:39 #}
    {{ '2019-08-07 23:39:12'|format_datetime('none', 'short', locale='fr') }}

    {# 07/08/2019 #}
    {{ '2019-08-07 23:39:12'|format_datetime('short', 'none', locale='fr') }}

    {# mercredi 7 août 2019 23:39:12 UTC #}
    {{ '2019-08-07 23:39:12'|format_datetime('full', 'full', locale='fr') }}

Supported values are: ``none``, ``short``, ``medium``, ``long``, and ``full``.

For greater flexiblity, you can even define your own pattern (see the `ICU user
guide
<https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax>`_
for supported patterns).

.. code-block:: twig

    {# 11 oclock PM, GMT #}
    {{ '2019-08-07 23:39:12'|format_datetime(pattern="hh 'oclock' a, zzzz") }}

By default, the filter uses the current locale. You can pass it explicitly:

.. code-block:: twig

    {# 7 août 2019 23:39:12 #}
    {{ '2019-08-07 23:39:12'|format_datetime(locale='fr') }}

.. note::

    The ``format_datetime`` filter is part of the ``IntlExtension`` which is not
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
* ``timeFormat``: The time format
* ``pattern``: A date time pattern
* ``timezone``: The date timezone
* ``calendar``: The calendar (Gregorian by default)
