# mailcow: dockerized - 🐮 + 🐋 = 💕

[![Servercow](https://www.servercow.de/img/cow_globe_200.svg)](https://www.servercow.de)

If you want to support mailcow, consider hosting mailcow on a Servercow virtual machine @ Servercow!

## Screenshots

You can find screenshots [on Imgur](http://imgur.com/a/oewYt).

## Overview

mailcow dockerized comes with **12 containers** linked in **one bridged network**.
Each container represents a single application.

- Dovecot
- ClamAV
- Memcached
- Redis
- MySQL
- Bind9 (Resolver) (formerly PDNS Recursor)
- PHP-FPM
- Postfix
- Nginx
- Rmilter
- Rspamd
- SOGo

**7 volumes** to keep dynamic data - take care of them!

- vmail-vol-1
- dkim-vol-1
- redis-vol-1
- mysql-vol-1
- rspamd-vol-1
- postfix-vol-1
- crypt-vol-1

The integrated **mailcow UI** allows administrative work on your mail server instance as well as separated domain administrator and mailbox user access:

- DKIM key management
- Black- and whitelists per domain and per user
- Spam score managment per-user (reject spam, mark spam, greylist)
- Allow mailbox users to create temporary spam aliases
- Prepend mail tags to subject or move mail to subfolder (per-user)
- Allow mailbox users to toggle incoming and outgoing TLS enforcement
- Allow users to reset SOGo ActiveSync device caches
- imapsync to migrate or pull remote mailboxes regularly
- TFA: Yubi OTP and U2F USB (Google Chrome and derivates only)
- Add domains, mailboxes, aliases, domain aliases and SOGo resources
- Add whitelisted hosts to forward mail to mailcow

*[Looking for a farm to host your cow?](https://www.servercow.de)* 
