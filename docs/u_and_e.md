## Anonymize headers

Save as `data/conf/postfix/mailcow_anonymize_headers.pcre`:

```
/^\s*Received:[^\)]+\)\s+\(Authenticated sender:(.+)/
	REPLACE Received: from localhost (localhost [127.0.0.1]) (Authenticated sender:$1
/^\s*User-Agent/        IGNORE
/^\s*X-Enigmail/        IGNORE
/^\s*X-Mailer/          IGNORE
/^\s*X-Originating-IP/  IGNORE
/^\s*X-Forward/         IGNORE
```

Add this to `data/conf/postfix/main.cf`:
```
smtp_header_checks = pcre:/opt/postfix/conf/mailcow_anonymize_headers.pcre
```

## Backup and restore maildir (simple tar file)

### Backup

This line backups the vmail directory to a file backup_vmail.tar.gz in the mailcow root directory:
```
cd /path/to/mailcow-dockerized
source mailcow.conf
DATE=$(date +"%Y%m%d_%H%M%S")
docker run --rm -it -v $(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/vmail" }}{{ .Name }}{{ end }}{{ end }}' $(docker-compose ps -q dovecot-mailcow)):/vmail -v ${PWD}:/backup debian:jessie tar cvfz /backup/backup_vmail.tar.gz /vmail
```

You can change the path by adjusting ${PWD} (which equals to the current directory) to any path you have write-access to.
Set the filename `backup_vmail.tar.gz` to any custom name, but leave the path as it is. Example: `[...] tar cvfz /backup/my_own_filename_.tar.gz`

### Restore
```
cd /path/to/mailcow-dockerized
source mailcow.conf
DATE=$(date +"%Y%m%d_%H%M%S")
docker run --rm -it -v $(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/vmail" }}{{ .Name }}{{ end }}{{ end }}' $(docker-compose ps -q dovecot-mailcow)):/vmail -v ${PWD}:/backup debian:jessie tar xvfz /backup/backup_vmail.tar.gz
```

## Docker Compose Bash completion

For the tab-tab... :-)

```
curl -L https://raw.githubusercontent.com/docker/compose/$(docker-compose version --short)/contrib/completion/bash/docker-compose -o /etc/bash_completion.d/docker-compose
```
## Black and Whitelist

Edit a domain as (domain) administrator to add an item to the filter table.

Beware that a mailbox user can login to mailcow and override a domain policy filter item. 

## Change default language

Change `data/conf/sogo/sogo.conf` and replace "English" by your preferred language.

Create a file `data/web/inc/vars.local.inc.php` and add "DEFAULT_LANG" with either "en", "pt", "de" or "nl":
```
<?php
$DEFAULT_LANG = "de";
```
## Change UI theme

mailcow uses [Bootstrap](http://getbootstrap.com/), a HTML, CSS, and JS framework.

Open or create the file `data/web/inc/vars.local.inc.php` and change `DEFAULT_THEME` to either cerulean, cosmo, custom, cyborg, darkly, flatly, journal, paper, readable, sandstone, simplex, slate, spacelab, superhero, united or yeti (see https://bootswatch.com/):
```
<?php
$DEFAULT_THEME = "paper";
```
## Customize Dockerfiles

Make your changes in `data/Dockerfiles/$service` and build the image locally:

```
docker build data/Dockerfiles/service -t andryyy/mailcow-dockerized:$service
```

Now auto-recreate modified containers:

```
docker-compose up -d
```

## Disable sender addresses verification

This option is not best-practice and should only be implemented when there is no other option available to archive whatever you are trying to do.

Simply create a file `data/conf/postfix/check_sender_access` and enter the following content:
```
user-to-allow-everything@example.com OK
```

Open `data/conf/postfix/main.cf` and find `smtpd_sender_restrictions`. Prepend `check_sasl_access hash:/opt/postfix/conf/check_sender_access` like this:
```
smtpd_sender_restrictions = check_sasl_access hash:/opt/postfix/conf/check_sender_access reject_authenticated_sender [...]
```

Run postmap on check_sasl_access:

```
docker-compose exec postfix-mailcow postmap /opt/postfix/conf/check_sasl_access
``` 

Restart the Postfix container.

## Install Roundcube

Download Roundcube 1.3.x (beta at the time of Feb 2017) to the web htdocs directory and extract it (here `rc/`):
```
cd data/web/rc
wget -O - https://github.com/roundcube/roundcubemail/releases/download/1.3-beta/roundcubemail-1.3-beta-complete.tar.gz | tar xfvz -
# Change folder name
mv roundcubemail-1.3* rc
# Change permissions
chown -R root: rc/
```

Create a file `data/web/rc/config/config.inc.php` with the following content.

**Change the `des_key` parameter to a random value.** It is used to temporarily store your IMAP password.

```
<?php
error_reporting(0);
if (!file_exists('/tmp/mime.types')) {
file_put_contents("/tmp/mime.types", fopen("http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types", 'r'));
}
$config = array();
$config['db_dsnw'] = 'mysql://' . getenv('DBUSER') . ':' . getenv('DBPASS') . '@mysql/' . getenv('DBNAME');
$config['default_host'] = 'tls://dovecot';
$config['default_port'] = '143';
$config['smtp_server'] = 'tls://postfix';
$config['smtp_port'] = 587;
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['support_url'] = '';
$config['product_name'] = 'Roundcube Webmail';
$config['des_key'] = 'rcmail-!24ByteDESkey*Str';
$config['log_dir'] = '/dev/null';
$config['temp_dir'] = '/tmp';
$config['plugins'] = array(
    'archive',
);
$config['skin'] = 'larry';
$config['mime_types'] = '/tmp/mime.types';
$config['imap_conn_options'] = array(
'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
);
$config['enable_installer'] = false;
$config['smtp_conn_options'] = array(
'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
);
```

Point your browser to `https://myserver/rc/installer` and follow the instructions.
Initialize the database and leave the installer.

**Delete the directory `data/web/rc/installer` after a successful installation!**

### Enable change password function in Roundcube

Open `data/web/rc/config/config.inc.php` and enable the password plugin:

```
...
$config['plugins'] = array(
    'archive',
    'password',
);
...
```

Open `data/web/rc/plugins/password/password.php`, search for `case 'ssha':` and add above:

```
        case 'ssha256':
            $salt = rcube_utils::random_bytes(8);
            $crypted = base64_encode( hash('sha256', $password . $salt, TRUE ) . $salt );
            $prefix  = '{SSHA256}';
            break;
```

Open `data/web/rc/plugins/password/config.inc.php` and change the following parameters (or add them at the bottom of that file):

```
$config['password_driver'] = 'sql';
$config['password_algorithm'] = 'ssha256';
$config['password_algorithm_prefix'] = '{SSHA256}';
$config['password_query'] = "UPDATE mailbox SET password = %P WHERE username = %u";
```

## MySQL

### Connect
```
source mailcow.conf
docker-compose exec mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME}
```

### Backup
```
cd /path/to/mailcow-dockerized
source mailcow.conf
DATE=$(date +"%Y%m%d_%H%M%S")
docker-compose exec mysql-mailcow mysqldump --default-character-set=utf8mb4 -u${DBUSER} -p${DBPASS} ${DBNAME} > backup_${DBNAME}_${DATE}.sql
```

### Restore
```
cd /path/to/mailcow-dockerized
source mailcow.conf
docker-compose exec mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} < backup_file.sql
```

## Debugging

You can use `docker-compose logs $service-name` for all containers.

Run `docker-compose logs` for all logs at once.

Follow the log output by running docker-compose with `logs -f`.

## Redirect port 80 to 443

Since February the 28th 2017 mailcow does come with port 80 and 443 enabled.

Open `mailcow.conf` and set `HTTP_BIND=0.0.0.0`.

Open `data/conf/nginx/site.conf` and add a new "catch-all" site at the top of that file:

```
server {
	listen 80 default_server;
	server_name _;
	return 301 https://$host$request_uri;
}
```

Restart the stack, changed containers will be updated:

`docker-compose up -d`

## Redis

### Client

```
docker-compose exec redis-mailcow redis-cli
```

## Remove persistent data

- Remove volume `mysql-vol-1` to remove all MySQL data.
- Remove volume `redis-vol-1` to remove all Redis data.
- Remove volume `vmail-vol-1` to remove all contents of `/var/vmail` mounted to `dovecot-mailcow`.
- Remove volume `dkim-vol-1` to remove all DKIM keys.
- Remove volume `rspamd-vol-1` to remove all Rspamd data.

Running `docker-compose down -v` will **destroy all mailcow: dockerized volumes** and delete any related containers.

## Reset admin password
Reset mailcow admin to `admin:moohoo`:

1\. Drop admin table
```
source mailcow.conf
docker-compose exec mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP TABLE admin;"
```

2\. Open mailcow UI to auto-init the db

## Rspamd

### Learn spam and ham

Rspamd learns mail as spam or ham when you move a message in or out of the junk folder to any mailbox besides trash.
This is archived by using the Dovecot plugin "antispam" and a simple parser script.

Rspamd also auto-learns mail when a high or low score is detected (see https://rspamd.com/doc/configuration/statistic.html#autolearning)

The bayes statistics are written to Redis as keys `BAYES_HAM` and `BAYES_SPAM`.

You can also use Rspamd's web ui to learn ham and/or spam.

### CLI tools

```
docker-compose exec rspamd-mailcow rspamc --help
docker-compose exec rspamd-mailcow rspamadm --help
```

See [Rspamd documentation](https://rspamd.com/doc/index.html)

## Adjust service configurations

The most important configuration files are mounted from the host into the related containers:

```
data/conf
├── bind9
│   └── named.conf
├── dovecot
│   ├── dovecot.conf
│   ├── dovecot-master.passwd
│   ├── sieve_after
│   └── sql
│       ├── dovecot-dict-sql.conf
│       └── dovecot-mysql.conf
├── mysql
│   └── my.cnf
├── nginx
│   ├── dynmaps.conf
│   ├── site.conf
│   └── templates
│       ├── listen_plain.template
│       ├── listen_ssl.template
│       └── server_name.template
├── pdns
│   ├── pdns_custom.lua
│   └── recursor.conf
├── postfix
│   ├── main.cf
│   ├── master.cf
│   ├── postscreen_access.cidr
│   ├── smtp_dsn_filter
│   └── sql
│       ├── mysql_relay_recipient_maps.cf
│       ├── mysql_tls_enforce_in_policy.cf
│       ├── mysql_tls_enforce_out_policy.cf
│       ├── mysql_virtual_alias_domain_catchall_maps.cf
│       ├── mysql_virtual_alias_domain_maps.cf
│       ├── mysql_virtual_alias_maps.cf
│       ├── mysql_virtual_domains_maps.cf
│       ├── mysql_virtual_mailbox_maps.cf
│       ├── mysql_virtual_relay_domain_maps.cf
│       ├── mysql_virtual_sender_acl.cf
│       └── mysql_virtual_spamalias_maps.cf
├── rmilter
│   └── rmilter.conf
├── rspamd
│   ├── dynmaps
│   │   ├── authoritative.php
│   │   ├── settings.php
│   │   ├── tags.php
│   │   └── vars.inc.php -> ../../../web/inc/vars.inc.php
│   ├── local.d
│   │   ├── dkim.conf
│   │   ├── metrics.conf
│   │   ├── options.inc
│   │   ├── redis.conf
│   │   ├── rspamd.conf.local
│   │   └── statistic.conf
│   ├── lua
│   │   └── rspamd.local.lua
│   └── override.d
│       ├── logging.inc
│       ├── worker-controller.inc
│       └── worker-normal.inc
└── sogo
    ├── sieve.creds
    └── sogo.conf

```

Just change the according configuration file on the host and restart the related service:
```
docker-compose restart service-mailcow
```

## Tagging

Mailbox users can tag their mail address like in `me+facebook@example.org` and choose between to setups to handle this tag:

1\. Move this message to a subfolder "facebook" (will be created lower case if not existing)

2\. Prepend the tag to the subject: "[facebook] Subject"

## Two-factor authentication

So far two methods for TFA are implemented. Both work with the fantastic [Yubikey](https://www.yubico.com).

While Yubi OTP needs an active internet connection and an API ID and key, U2F will work with any FIDO U2F USB key out of the box, but can only be used when mailcow is accessed over HTTPS.

Both methods support multiple YubiKeys.

As administrator you are able to temporary disable a domain administrators TFA login until they successfully logged in.

The key used to login will be displayed in green, while other keys remain grey.

### Yubi OTP

The Yubi API ID and Key will be checked against the Yubico Cloud API. When setting up TFA you will be asked for your personal API account for this key.
The API ID, API key and the first 12 characters (your YubiKeys ID in modhex) are stored in the MySQL table as secret.

### U2F

Only Google Chrome (+derivates) and Opera support U2F authentication to this day natively.
For Firefox you will need to install the "U2F Support Add-on" as provided on [mozilla.org](https://addons.mozilla.org/en-US/firefox/addon/u2f-support-add-on/).

U2F works without an internet connection.

## Why Bind?

For DNS blacklist lookups and DNSSEC.

Most systems use either a public or a local caching DNS resolver.
That's a very bad idea when it comes to filter spam using DNS-based blackhole lists (DNSBL) or similar technics.
Most if not all providers apply a rate limit based on the DNS resolver that is used to query their service.
Using a public resolver like Googles 4x8, OpenDNS or any other shared DNS resolver like your ISPs will hit that limit very soon.
