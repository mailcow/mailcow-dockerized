# mailcow-dockerized

mailcow dockerized comes with 11 containers linked in a mailcow network:
Dovecot, Memcached, Redis, MariaDB, PowerDNS Recursor, PHP-FPM, Postfix, Nginx, Rmilter, Rspamd and SOGo.

All configurations were written with security in mind.

### Exposed ports:

| Name              | Service      | Hostname, Alias                | External bindings                            | Internal bindings              |
|:------------------|:-------------|:-------------------------------|:---------------------------------------------|:-------------------------------|
| postfix-mailcow   | Postfix      | ${MAILCOW_HOSTNAME}, postfix   | 25/tcp, 465/tcp, 587/tcp                     | 588/tcp                        |
| dovecot-mailcow   | Dovecot      | ${MAILCOW_HOSTNAME}, dovecot   | 110/tcp, 143/tcp, 993/tcp, 995/tcp, 4190/tcp | 24/tcp, 10001/tcp              |
| nginx-mailcow     | Nginx        | nginx                          | 443/tcp                                      | 80/tcp, 8081/tcp               |
| pdns-mailcow      | PowerDNS     | pdns                           | -                                            | 53/udp                         |
| rspamd-mailcow    | Rspamd       | rspamd                         | -                                            | 11333/tcp, 11334/tcp           |
| mariadb-mailcow   | MariaDB      | mysql                          | -                                            | 3306/tcp                       |
| rmilter-mailcow   | Rmilter      | rmilter                        | -                                            | 9000/tcp                       |
| phpfpm-mailcow    | PHP FPM      | phpfpm                         | -                                            | 9000/tcp                       |
| sogo-mailcow      | SOGo         | sogo                           | -                                            | 9000/tcp                       |
| redis-mailcow     | Redis        | redis                          | -                                            | 6379/tcp                       |
| memcached-mailcow | Memcached    | memcached                      | -                                            | 11211/tcp                      |

All containers share a network "mailcow-network" with the subnet 172.22.1.0/24 - if you want to change it, set it in the composer file.
IPs are dynamic except for PowerDNS resolver which has a static ip address 172.22.1.2.

### **FAQ**

- rspamd learns mail as spam or ham when you move a message in or out of the junk folder to any mailbox besides trash.
- rspamd auto-learns mail when a high or low score is detected (see https://rspamd.com/doc/configuration/statistic.html#autolearning)
- You can upgrade SOGo by running `docker-compose up -d sogo-mailcow nginx-mailcow`.
- Only Postfix and Rspamd use the PowerDNS resolver for DNSSEC. 
- Linking to existing redis and memcached containers will be possible soon

## Installation

1. You need Docker and Docker Compose. Most systems can install Docker by running `wget -qO- https://get.docker.com/ | sh` - see [this link](https://docs.docker.com/compose/install/) for installing Docker Compose.

2. Clone this repository and configure `mailcow.conf`, do not use special chars in passwords in this file (will be fixed soon).

3. `docker-compose up -d` - leave the `-d` out for a wall of logs in case of debugging.

Done.

You can now access https://${MAILCOW_HOSTNAME} with the default credentials `admin` + password `moohoo`. The database will be initialized when you first visit the UI.

## Configuration after installation

### Rspamd UI access
If you want to use Rspamds web UI, you need to set a Rspamd controller password:

```
# Generate hash
docker-compose exec rspamd-mailcow rspamadm pw
```

Replace given hash in data/conf/rspamd/override.d/worker-controller.inc:
```
enable_password = "myhash";
```

Restart rspamd:
```
docker-compose restart rspamd-mailcow
```

Open https://${MAILCOW_HOSTNAME}/rspamd in a browser.

### SSL (and: How to use Let's Encrypt)
mailcow dockerized comes with a snakeoil CA "mailcow" and a server certificate in `data/assets/ssl`. Please use your own trusted certificates.

**Use Let's Encrypt?**

Get the certbot client:
```
wget https://dl.eff.org/certbot-auto -O /usr/local/sbin/certbot && chmod +x /usr/local/sbin/certbot
```

Please disable applications blocking port 80 and run certbot:
```
certbot-auto certonly \
	--standalone \
	--standalone-supported-challenges http-01 \
	-d ${MAILCOW_HOSTNAME} \
	--email you@example.org \
	--agree-tos
```

Create hard links to the full path of the new certificates. Assuming you are still in the mailcow root folder:
```
mv data/assets/ssl/cert.{pem,pem.backup}
mv data/assets/ssl/key.{pem,pem.backup}
ln $(readlink -f /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/fullchain.pem) data/assets/ssl/mail.crt
ln $(readlink -f /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/privkey.pem) data/assets/ssl/mail.key
```

Restart containers which use the certificate:
```
docker-compose restart postfix-mailcow
docker-compose restart dovecot-mailcow
docker-compose restart nginx-mailcow
```

When renewing certificates, run the last two steps (link + restart) as post-hook in certbot.

## More useful commands and examples (todo: move to wiki soon)

### build-*.files

(Re)build a container:
```
./n-build-$name.sh 
```
**:exclamation:** Any previous container with the same name will be stopped and removed.
No persistent data is deleted at any time.
If an image exists, you will be asked wether or not to repull/rebuild it.

Build files are numbered "nnn" for dependencies.

### Logs

You can use `docker-compose logs $service-name` for almost all containers. Only rmilter does not log to stdout. You can check rspamd logs for rmilter responses.

### MariaDB

Connect to MariaDB database:
```
source mailcow.conf
docker-compose exec mariadb-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME}
```

Init schema (will be auto-installed by mailcow UI, but just in case...):
```
source mailcow.conf
docker-compose exec mariadb-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} < data/web/inc/init.sql
```

Reset mailcow admin to `admin:moohoo`:
```
source mailcow.conf
docker-compose exec mariadb-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "DROP TABLE admin; DROP TABLE domain_admins"
# Open mailcow UI to auto-init the db
```

Backup and restore database:
```
source mailcow.conf
# Create
DATE=$(date +"%Y%m%d_%H%M%S")
docker-compose exec mariadb-mailcow mysqldump --default-character-set=utf8mb4 -u${DBUSER} -p${DBPASS} ${DBNAME} > backup_${DBNAME}_${DATE}.sql
# Restore
docker exec -i mariadb-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} < ${1}
```

### Redis

Connect to redis key store:
```
docker-compose exec redis-mailcow redis-cli
```

### Use rspamadm:
```
docker-compose exec rspamd-mailcow rspamadm --help
```

### Use rspamc:
```
docker-compose exec rspamd-mailcow rspamc --help
```
### Use doveadm:
```
docker-compose exec dovecot-mailcow doveadm
```

### Remove persistent data

MariaDB:
```
docker stop mariadb-mailcow
docker rm mariadb-mailcow
rm -rf data/db/mysql/*
./n-build-sql.sh
```

Redis:
```
# If you feel hardcore:
docker stop redis-mailcow
docker rm redus-mailcow
rm -rf data/db/redis/*
./n-build-redis.sh

## It is almost always enough to just flush all keys:
./n-build-redis client
# FLUSHALL [ENTER]
```
