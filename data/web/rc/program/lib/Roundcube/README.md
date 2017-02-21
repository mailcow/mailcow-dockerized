Roundcube Framework
===================

INTRODUCTION
------------
The Roundcube Framework is the basic library used for the Roundcube Webmail
application. It is an extract of classes providing the core functionality for
an email system. They can be used individually or as package for the following
tasks:

- IMAP mailbox access with optional caching
- MIME message handling
- Email message creation and sending through SMTP
- General caching utilities using the local database
- Database abstraction using PDO
- VCard parsing and writing


REQUIREMENTS
------------
PHP Version 5.4 or greater including:
   - PCRE, DOM, JSON, Session, Sockets, OpenSSL, Mbstring (required)
   - PHP PDO with driver for either MySQL, PostgreSQL, SQL Server, Oracle or SQLite (required)
   - Libiconv, Zip, Fileinfo, Intl, Exif (recommended)
   - LDAP for LDAP addressbook support (optional)


INSTALLATION
------------
Copy all files of this directory to your project or install it in the default
include_path directory of your webserver. Some classes of the framework require
one or multiple of the following [PEAR][pear] libraries:

- Mail_Mime 1.8.1 or newer
- Net_SMTP 1.7.1 or newer
- Net_Socket 1.0.12 or newer
- Net_IDNA2 0.1.1 or newer
- Auth_SASL 1.0.6 or newer


USAGE
-----
The Roundcube Framework provides a bootstrapping file which registers an
autoloader and sets up the environment necessary for the Roundcube classes.
In order to make use of the framework, simply include the bootstrap.php file
from this directory in your application and start using the classes by simply
instantiating them.

If you wanna use more complex functionality like IMAP access with database
caching or plugins, the rcube singleton helps you loading the necessary files:

```php
<?php

define('RCUBE_CONFIG_DIR',  '<path-to-config-directory>');
define('RCUBE_PLUGINS_DIR', '<path-to-roundcube-plugins-directory');

require_once '<path-to-roundcube-framework/bootstrap.php';

$rcube = rcube::get_instance(rcube::INIT_WITH_DB | rcube::INIT_WITH_PLUGINS);
$imap = $rcube->get_storage();

// do cool stuff here...

?>
```

LICENSE
-------
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License (**with exceptions
for plugins**) as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see [www.gnu.org/licenses/][gpl].

This file forms part of the Roundcube Webmail Framework for which the
following exception is added: Plugins which merely make function calls to the
Roundcube Webmail Framework, and for that purpose include it by reference
shall not be considered modifications of the software.

If you wish to use this file in another project or create a modified
version that will not be part of the Roundcube Webmail Framework, you
may remove the exception above and use this source code under the
original version of the license.

For more details about licensing and the exceptions for skins and plugins
see [roundcube.net/license][license]


CONTACT
-------
For bug reports or feature requests please refer to the tracking system
at [Github][githubissues] or subscribe to our mailing list.
See [roundcube.net/support][support] for details.

You're always welcome to send a message to the project admins:
hello(at)roundcube(dot)net


[pear]:         http://pear.php.net
[gpl]:          http://www.gnu.org/licenses/
[license]:      http://roundcube.net/license
[support]:      http://roundcube.net/support
[githubissues]: https://github.com/roundcube/roundcubemail/issues
