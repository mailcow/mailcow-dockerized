# Change default language

Change `data/conf/sogo/sogo.conf` and replace English by your language.

Create a file `data/web/inc/vars.local.inc.php` and add "DEFAULT_LANG" with either "en", "pt", "de" or "nl":
```
<?php
$DEFAULT_LANG = "de";
```

# SSL (and: How to use Let's Encrypt)

mailcow dockerized comes with a snakeoil CA "mailcow" and a server certificate in `data/assets/ssl`. Please use your own trusted certificates.

mailcow uses 3 domain names that should be covered by your new certificate:

- ${MAILCOW_HOSTNAME}
- autodiscover.*example.org*
- autoconfig.*example.org*

## Obtain multi-SAN certificate by Let's Encrypt

This is just an example of how to obtain certificates with certbot. There are several methods!

1. Get the certbot client:
```
wget https://dl.eff.org/certbot-auto -O /usr/local/sbin/certbot && chmod +x /usr/local/sbin/certbot
```
2. Make sure you set `HTTP_BIND=0.0.0.0` in `mailcow.conf` or setup a reverse proxy to enable connections to port 80. If you changed HTTP_BIND, then restart Nginx: `docker-compose restart nginx-mailcow`.

3. Request the certificate with the webroot method:

```
cd /path/to/git/clone/mailcow-dockerized
source mailcow.conf
certbot certonly \
        --webroot \
        -w ${PWD}/data/web \
        -d ${MAILCOW_HOSTNAME} \
        -d autodiscover.example.org \
        -d autoconfig.example.org \
        --email you@example.org \
        --agree-tos
```

3. Create hard links to the full path of the new certificates. Assuming you are still in the mailcow root folder:
```
mv data/assets/ssl/cert.{pem,pem.backup}
mv data/assets/ssl/key.{pem,pem.backup}
ln $(readlink -f /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/fullchain.pem) data/assets/ssl/cert.pem
ln $(readlink -f /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/privkey.pem) data/assets/ssl/key.pem
```
4. Restart containers which use the certificate:
```
docker-compose restart postfix-mailcow dovecot-mailcow nginx-mailcow
```
When renewing certificates, run the last two steps (link + restart) as post-hook in a script.

# Rspamd UI access
At first you may want to setup Rspamds web interface which provides some useful features and information.

1. Generate a Rspamd controller password hash:
```
docker-compose exec rspamd-mailcow rspamadm pw
```
2. Replace the default hash in `data/conf/rspamd/override.d/worker-controller.inc` by your newly generated:
```
enable_password = "myhash";
```
3. Restart rspamd:

```
docker-compose restart rspamd-mailcow
```

Open https://${MAILCOW_HOSTNAME}/rspamd in a browser and login!
