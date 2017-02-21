Roundcube Webmail 
=================
[roundcube.net](http://roundcube.net)


INTRODUCTION
------------
Roundcube Webmail is a browser-based multilingual IMAP client with an
application-like user interface. It provides full functionality you expect
from an email client, including MIME support, address book, folder management,
message searching and spell checking. Roundcube Webmail is written in PHP and
requires the MySQL, PostgreSQL or SQLite database. With its plugin API it is
easily extendable and the user interface is fully customizable using skins
which are pure XHTML and CSS 2.

The code is mainly written in PHP and is designed to run on a webserver.
It includes other open-source classes/libraries from [PEAR][pear],
an IMAP library derived from [IlohaMail][iloha] the [TinyMCE][tinymce] rich
text editor, [Googiespell][googiespell] library for spell checking or
the [WASHTML][washtml] sanitizer by Frederic Motte.

The current default skin 'Larry' was kindly created by FLINT / Büro für
Gestaltung, Berne, Switzerland.


INSTALLATION
------------
For detailed instructions on how to install Roundcube webmail on your server,
please refer to the INSTALL document in the same directory as this document.

If you're updating an older version of Roundcube please follow the steps
described in the UPGRADING file.


LICENSE
-------
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License (**with exceptions
for skins & plugins**) as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see [www.gnu.org/licenses/][gpl].

This file forms part of the Roundcube Webmail Software for which the
following exception is added: Plugins and Skins which merely make
function calls to the Roundcube Webmail Software, and for that purpose
include it by reference shall not be considered modifications of
the software.

If you wish to use this file in another project or create a modified
version that will not be part of the Roundcube Webmail Software, you
may remove the exception above and use this source code under the
original version of the license.

For more details about licensing and the exceptions for skins and plugins
see [roundcube.net/license][license]


CONTRIBUTION
------------
Want to help make Roundcube the best webmail solution ever?
Roundcube is open source software. Our developers and contributors all
are volunteers and we're always looking for new additions and resources.
For more information visit [roundcube.net/contribute][contrib]


CONTACT
-------
For bug reports or feature requests please refer to the tracking system
at [Github][githubissues] or subscribe to our mailing list.
See [roundcube.net/support][support] for details.

You're always welcome to send a message to the project admin:
hello(at)roundcube(dot)net


[pear]:         http://pear.php.net
[iloha]:        http://sourceforge.net/projects/ilohamail/
[tinymce]:      http://www.tinymce.com/
[googiespell]:  http://orangoo.com/labs/GoogieSpell/
[washtml]:      http://www.ubixis.com/washtml/
[kmgerich]:     http://kmgerich.com/
[gpl]:          http://www.gnu.org/licenses/
[license]:      http://roundcube.net/license
[contrib]:      http://roundcube.net/contribute
[support]:      http://roundcube.net/support
[githubissues]: https://github.com/roundcube/roundcubemail/issues