``format_currency``
===================

The ``format_currency`` filter formats a number as a currency:

.. code-block:: twig

    {# €1,000,000.00 #}
    {{ '1000000'|format_currency('EUR') }}

You can pass attributes to tweak the output:

.. code-block:: twig

    {# €12.34 #}
    {{ '12.345'|format_currency('EUR', {rounding_mode: 'floor'}) }}

    {# €1,000,000.0000 #}
    {{ '1000000'|format_currency('EUR', {fraction_digit: 4}) }}

The list of supported options:

* ``grouping_used``;
* ``decimal_always_shown``;
* ``max_integer_digit``;
* ``min_integer_digit``;
* ``integer_digit``;
* ``max_fraction_digit``;
* ``min_fraction_digit``;
* ``fraction_digit``;
* ``multiplier``;
* ``grouping_size``;
* ``rounding_mode``;
* ``rounding_increment``;
* ``format_width``;
* ``padding_position``;
* ``secondary_grouping_size``;
* ``significant_digits_used``;
* ``min_significant_digits_used``;
* ``max_significant_digits_used``;
* ``lenient_parse``.

By default, the filter uses the current locale. You can pass it explicitly:

.. code-block:: twig

    {# 1.000.000,00 € #}
    {{ '1000000'|format_currency('EUR', locale='de') }}

.. note::

    The ``format_currency`` filter is part of the ``IntlExtension`` which is not
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

* ``currency``: The currency
* ``attrs``: A map of attributes
* ``locale``: The locale
