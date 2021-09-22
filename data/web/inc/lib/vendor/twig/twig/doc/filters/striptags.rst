``striptags``
=============

The ``striptags`` filter strips SGML/XML tags and replace adjacent whitespace
by one space:

.. code-block:: twig

    {{ some_html|striptags }}

You can also provide tags which should not be stripped:

.. code-block:: html+twig

    {{ some_html|striptags('<br><p>') }}

In this example, the ``<br/>``, ``<br>``, ``<p>``, and ``</p>`` tags won't be
removed from the string.

.. note::

    Internally, Twig uses the PHP `strip_tags`_ function.

Arguments
---------

* ``allowable_tags``: Tags which should not be stripped

.. _`strip_tags`: https://secure.php.net/strip_tags
