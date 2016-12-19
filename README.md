# mailcow: dockerized - 🐮 + 🐋 = 💕

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JWBSYHF4SMC68)

## About mailcow: dockerized
mailcow dockerized comes with **11 containers** linked in a mailcow network:
- Dovecot
- Memcached
- Redis
- MySQL
- PowerDNS Recursor
- PHP-FPM
- Postfix
- Nginx
- Rmilter
- Rspamd
- SOGo

**4** volumes to keep dynamic data - take care of them!
- vmail-vol-1
- dkim-vol-1
- redis-vol-1
- mysql-vol-1

All configurations were written with security in mind.

### Container and volume overview

| Type      | Object name       | Network names                | External binding                             | Internal binding     | Volumes                                                                                                                                                                                       |
|-----------|-------------------|------------------------------|----------------------------------------------|----------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Container | postfix-mailcow   | ${MAILCOW_HOSTNAME}, postfix | 25/tcp, 465/tcp, 587/tcp                     | 588/tcp              | ./data/conf/postfix => /opt/postfix/conf, ./data/assets/ssl => /etc/ssl/mail/:ro                                                                                                              |
| Container | dovecot-mailcow   | ${MAILCOW_HOSTNAME}, dovecot | 110/tcp, 143/tcp, 993/tcp, 995/tcp, 4190/tcp | 24/tcp, 10001/tcp    | vmail-vol-1 => /var/vmail, ./data/conf/dovecot => /etc/dovecot, ./data/assets/ssl => /etc/ssl/mail/:ro                                                                                        |
| Container | nginx-mailcow     | nginx                        | 443/tcp                                      | 80/tcp, 8081/tcp     | Mounts from sogo-mailcow, ./data/web => /web:ro, ./data/conf/rspamd/dynmaps => /dynmaps:ro, ./data/assets/ssl/ => /etc/ssl/mail/:ro, ./data/conf/nginx/ => /etc/nginx/conf.d/:ro              |
| Container | pdns-mailcow      | pdns                         | -                                            | 53/udp               | ./data/conf/pdns/ => /etc/powerdns/                                                                                                                                                           |
| Container | rspamd-mailcow    | rspamd                       | -                                            | 11333/tcp, 11334/tcp | dkim-vol-1 => /data/dkim, ./data/conf/rspamd/override.d/ => /etc/rspamd/override.d:ro, ./data/conf/rspamd/local.d/ => /etc/rspamd/local.d:ro, ./data/conf/rspamd/lua/ => /etc/rspamd/lua/:ro  |
| Container | mysql-mailcow     | mysql                        | -                                            | 3306/tcp             | mysql-vol-1 => /var/lib/mysql/, ./data/conf/mysql/ => /etc/mysql/conf.d/:ro                                                                                                                   |
| Container | rmilter-mailcow   | rmilter                      | -                                            | 9000/tcp             | ./data/conf/rmilter/ => /etc/rmilter.conf.d/:ro                                                                                                                                               |
| Container | phpfpm-mailcow    | phpfpm                       | -                                            | 9000/tcp             | dkim-vol-1 => /data/dkim, ./data/web => /web:ro, ./data/conf/rspamd/dynmaps => /dynmaps:ro                                                                                                    |
| Container | sogo-mailcow      | sogo                         | -                                            | 20000/tcp            | ./data/conf/sogo/ => /etc/sogo/,exposes /usr/lib/GNUstep/SOGo/WebServerResources/                                                                                                             |
| Container | redis-mailcow     | redis                        | -                                            | 6379/tcp             | redis-vol-1 => /data/                                                                                                                                                                         |
| Container | memcached-mailcow | memcached                    | -                                            | 11211/tcp            | -                                                                                                                                                                                             |
| Volume    | vmail-vol-1       | -                            | -                                            | -                    | Mounts to dovecot                                                                                                                                                                             |
| Volume    | dkim-vol-1        | -                            | -                                            | -                    | Mounts to rspamd + phpfpm                                                                                                                                                                     |
| Volume    | redis-vol-1       | -                            | -                                            | -                    | Mounts to redis                                                                                                                                                                               |
| Volume    | mysql-vol-1       | -                            | -                                            | -                    | Mounts to mysql                                                                                                                                                                               |

### Network

All containers share a network "mailcow-network" with the subnet 172.22.1.0/24 - if you want to change it, set it in the composer file.
IPs are dynamic except for PowerDNS resolver which has a static ip address 172.22.1.254.

**FAQ**

- rspamd learns mail as spam or ham when you move a message in or out of the junk folder to any mailbox besides trash.
- rspamd auto-learns mail when a high or low score is detected (see https://rspamd.com/doc/configuration/statistic.html#autolearning)
- You can upgrade containers by running `docker-compose pull && docker-compose up -d`.

## Installation

1. You need Docker and Docker Compose. Most systems can install Docker by running `wget -qO- https://get.docker.com/ | sh` - see [this link](https://docs.docker.com/compose/install/) for installing Docker Compose.

2. Clone this repository and configure `mailcow.conf`, do not use special chars in passwords in this file (will be fixed soon).

3. `docker-compose up -d` - leave the `-d` out for a wall of logs in case of debugging.

Done.

You can now access https://${MAILCOW_HOSTNAME} with the default credentials `admin` + password `moohoo`. The database will be initialized when you first visit the UI.

## Configuration after installation
### Rspamd UI access
At first you may want to setup Rspamds web interface which provides some useful features and information.

1. Set a Rspamd controller password:
```
# Generate hash
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

### SSL (and: How to use Let's Encrypt)
mailcow dockerized comes with a snakeoil CA "mailcow" and a server certificate in `data/assets/ssl`. Please use your own trusted certificates.

mailcow uses 3 domain names that should be covered by your new certificate:
- ${MAILCOW_HOSTNAME}
- autodiscover.example.org (used for ActiveSync)
- autoconfig.example.org (used by Thunderbird and derivates)

**Obtain multi-SAN certificate by Let's Encrypt**
This is just an example of how to obtain certificates with certbot. There are several methods!

1. Get the certbot client:
```
wget https://dl.eff.org/certbot-auto -O /usr/local/sbin/certbot && chmod +x /usr/local/sbin/certbot
```
2. Disable applications blocking port 80 and run certbot:
```
cd /path/to/git/clone/mailcow-dockerized
source mailcow.conf
certbot certonly \
	--standalone \
	--standalone-supported-challenges http-01 \
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
docker-compose restart postfix-mailcow
docker-compose restart dovecot-mailcow
docker-compose restart nginx-mailcow
```
When renewing certificates, run the last two steps (link + restart) as post-hook in a script.

## Other useful commands and examples

All commands need to be run from within `/path/to/git/clone/mailcow-dockerized`.

### Get bash-completion for docker-compose
For the tab-tab... :-)
```
curl -L https://raw.githubusercontent.com/docker/compose/$(docker-compose version --short)/contrib/completion/bash/docker-compose -o /etc/bash_completion.d/docker-compose
```
### Logs
You can use `docker-compose logs $service-name` for almost all containers. Only rmilter does not log to stdout. You can check rspamd logs for rmilter responses.

Run `docker-compose logs` for all logs at once.

### MySQL
Connect to the MySQL database:
```
source mailcow.conf
docker-compose exec mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME}
```
Reset mailcow admin to `admin:moohoo`:
```
# 1. Drop admin table
source mailcow.conf
docker-compose exec mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP TABLE admin;"
# 2. Open mailcow UI to auto-init the db
```
Backup the database:
```
source mailcow.conf
DATE=$(date +"%Y%m%d_%H%M%S")
docker-compose exec mysql-mailcow mysqldump --default-character-set=utf8mb4 -u${DBUSER} -p${DBPASS} ${DBNAME} > backup_${DBNAME}_${DATE}.sql
```

### Redis
Connect to redis key store:
```
docker-compose exec redis-mailcow redis-cli
```

### Rspamd:
Rspamd CLI tools:
```
docker-compose exec rspamd-mailcow rspamc --help
docker-compose exec rspamd-mailcow rspamadm --help
```
### Dovecot:
Run doveadm:
```
docker-compose exec dovecot-mailcow doveadm
```
Backup maildir (simple tar file):
```
docker run --rm -it -v $(docker inspect --format '{{ range .Mounts }}{{ if eq .Destination "/var/vmail" }}{{ .Name }}{{ end }}{{ end }}' $(docker-compose ps -q dovecot-mailcow)):/vmail -v ${PWD}:/backup debian:jessie tar cvfz /backup/backup_vmail.tar.gz /vmail
```
### Remove persistent data
Remove volume `mysql-vol-1` to remove all MySQL data.
Remove volume `redis-vol-1` to remove all Redis data.
Remove volume `vmail-vol-1` to remove all contents of `/var/vmail` mounted to `dovecot-mailcow`.
Remove volume `dkim-vol-1` to remove all DKIM keys.

### Scale it
You can scale some services for mailcow, which is much more useful on a multi-host system. Please do not scale MySQL or Redis containers.
This is insufficiently tested!
```
docker-compose scale rspamd-mailcow=2
docker-compose scale rmilter-mailcow=3
# ...
```
