``data_uri``
============

The ``data_uri`` filter generates a URL using the data scheme as defined in
`RFC 2397`_:

.. code-block:: html+twig

    {{ image_data|data_uri }}

    {{ source('path_to_image')|data_uri }}

    {# force the mime type, disable the guessing of the mime type #}
    {{ image_data|data_uri(mime="image/svg") }}

    {# also works with plain text #}
    {{ '<b>foobar</b>'|data_uri(mime="text/html") }}

    {# add some extra parameters #}
    {{ '<b>foobar</b>'|data_uri(mime="text/html", parameters={charset: "ascii"}) }}

.. note::

    The ``data_uri`` filter is part of the ``HtmlExtension`` which is not
    installed by default. Install it first:

    .. code-block:: bash

        $ composer require twig/html-extra

    Then, on Symfony projects, install the ``twig/extra-bundle``:

    .. code-block:: bash

        $ composer require twig/extra-bundle

    Otherwise, add the extension explicitly on the Twig environment::

        use Twig\Extra\Html\HtmlExtension;

        $twig = new \Twig\Environment(...);
        $twig->addExtension(new HtmlExtension());

.. note::

    The filter does not perform any length validation on purpose (limit depends
    on the usage context), validation should be done before calling this filter.

Arguments
---------

* ``mime``: The mime type
* ``parameters``: An array of parameters

.. _RFC 2397: https://tools.ietf.org/html/rfc2397
