.. index:: Filters
.. _settings-managesieve-filters:

*******
Filters
*******

Incoming mail is automatically processed by the server and handled/organized
according to defined criteria. For example you can tell the server to move the message to
specified folder, redirect it to another account, send a reply, discard, delete, etc.

Filtering is based on `Sieve <https://www.rfc-editor.org/info/rfc5228>`_ language, which means that under the hood
all filters are stored as a Sieve script on the server. This interface allows you to
define rules in easy way without the need to know the language.

Each filter definition has a name and set of rules and actions. Usually 
the number of definitions is unlimited and they can be grouped into sets
(scripts) for convenience.


Filter sets
-----------

Filter definitions can be grouped into sets. These can be activated or disactivated.
Depending on server configuration there can be none, one or more active sets
at the same time. They need to have a unique name.

New sets can be created as empty or as a copy of an existing set. It is also possible
to import them from a text file containing Sieve script. Sets in form of a script
can be also downloaded e.g. for backup or migration purposes.


Filter definition
-----------------

Every filter can be active or inactive, which is convenient if you want to
disable some actions temporarily.

Because filters are executed in specified order (from top to bottom as you see them on the list)
you can use drag-and-drop technique to rearange filters on the list.

Every filter definition contains at least one rule and one action. Depending on server
capabilities a rule can be based e.g. on message headers, body, date or size.

A set of actions also depends on server capabilities. Most servers support:

* moving/copying messages to specified folder
* redirecting/copying messages to another account
* discarding messages with specified error message
* replying (vacation)
* deleting (ignoring) messages
* setting flags (e.g. marking as Read)

Note: Some actions stop filtering process, some do not. Use *Stop evaluating rules*
and *Keep message in Inbox* actions to have more control on this.
