# mailcow: dockerized - 🐮 + 🐋 = 💕

![mailcow](https://www.debinux.de/256.png)

**Official website: <https://mailcow.email>**

- [Introduction](#introduction)
- [Info and documentation](#info-and-documentation)
- [Before You Begin (Prerequisites)](#before-you-begin-prerequisites)


## Want to support mailcow?

Please [consider a support contract (around 30 € per month) with Servercow](https://www.servercow.de/mailcow#support) to support further development. _We_ support _you_ while _you_ support _us_. :)

Or just spread the word: moo.

## Get support

### Commercial support

For commercial support contact [info@servercow.de](mailto:info@servercow.de).

### Community support

- IRC @ [Freenode, #mailcow](irc://irc.freenode.org:6667/mailcow)
- Forum @ [forum.mailcow.email](forum.mailcow.email)
- GitHub @ [mailcow/mailcow-dockerized](https://github.com/mailcow/mailcow)

# Introduction

* Multi-SAN self-signed SSL certificate for all installed and supporting services
    * Let's Encrypt optional
* Webserver installation
    * Apache or Nginx (+PHP5-FPM)
* SQL database backend, remote database support
    * MySQL or MariaDB
* **mailcow web UI**
    * Add domains, mailboxes, aliases, set limits, enforce TLS outgoing and incoming, monitor mail statistics, change mail server settings, create/delete DKIM records and more...
* Postscreen activated and configured
* STARTTLS and SMTPS support
* The default restrictions used are a good compromise between blocking spam and avoiding false-positives
* Incoming and outgoing spam and virus protection with FuGlu as pre-queue content filter; [Heinlein Support](https://www.heinlein-support.de/) spamassassin rules included; Advanced ClamAV malware filters
* Sieve/ManageSieve (default filter: move spam to "Junk" folder, move tagged mail to folder "tag")
* Public folder support via control center
* Per-user ACL
* Shared Namespace
* Quotas
* Auto-configuration for ActiveSync + Thunderbird (and its derivates)

Comes with...
* Roundcube
    * ManageSieve support (w/ vacation)
    * Attachment reminder (multiple locales)
    * Zip-download marked messages
or
* SOGo
    * Full groupware with ActiveSync and Card-/CalDAV support

# Info and documentation

Please see [the official documentation](https://mailcow.github.io/mailcow-dockerized-docs/) for instructions.

# Before You Begin: Prerequisites
- **Please remove any web and mail services** running on your server. I recommend using a clean Debian minimal installation.
Remember to purge Debian's default MTA Exim4:
```
apt-get purge exim4*
``` 

- If there is a firewall, unblock the following ports for incoming connections:

| Service               | Protocol | Port   |
| -------------------   |:--------:|:-------|
| Postfix Submission    | TCP      | 587    |
| Postfix SMTPS         | TCP      | 465    |
| Postfix SMTP          | TCP      | 25     |
| Dovecot IMAP          | TCP      | 143    |
| Dovecot IMAPS         | TCP      | 993    |
| Dovecot POP3          | TCP      | 110    |
| Dovecot POP3S         | TCP      | 995    |
| Dovecot ManageSieve   | TCP      | 4190   |
| HTTP(S)               | TCP      | 80/443 |

- Setup DNS records:

Obviously you will need an A and/or AAAA record `sys_hostname.sys_domain` pointing to your IP address and a valid MX record.
*Let's Encrypt does not assign certificates when it cannot determine a valid* **IPv4** *address.*

| Name                       | Type   | Value                        | Priority   |
| ---------------------------|:------:|:----------------------------:|:-----------|
| `sys_hostname.sys_domain`  | A/AAAA | IPv4/6                       | any        |
| `sys_domain`               | MX     | `sys_hostname.sys_domain`    | 25         |

**Optional:** Auto-configuration services for Thunderbird (and derivates) + ActiveSync.
You do not need to setup `autodiscover` when not using SOGo with ActiveSync.

| Name                       | Type   | Value                        | Priority   |
| ---------------------------|:------:|:----------------------------:|:-----------|
| autoconfig.`sys_domain`    | A/AAAA | IPv4/6                       | any        |
| autodiscover.`sys_domain`  | A/AAAA | IPv4/6                       | any        |

**Hint:** ActiveSync auto-discovery is setup to configure desktop clients with IMAP!

Further DNS records for SPF and DKIM are recommended. These entries will raise trust in your mailserver, reduce abuse of your domain name and increase authenticity.

Find more details about mailcow DNS entries and SPF/DKIM related configuration in our wiki article on [DNS Records](https://github.com/andryyy/mailcow/wiki/DNS-records).

- Next it is important that you **do not use Google DNS** or another public DNS which is known to be blocked by DNS-based Blackhole List (DNSBL) providers.

**Important**: mailcow makes use of various open-source software. Please assure you agree with their license before using mailcow.
Any part of mailcow itself is released under **GNU General Public License, Version 3**.
