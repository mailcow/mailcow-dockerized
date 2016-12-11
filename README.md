# mailcow-dockerized

mailcow dockerized comes with 11 containers linked in a mailcow network:
Dovecot, Memcached, Redis, MariaDB, PowerDNS Recursor, PHP-FPM, Postfix, Nginx, Rmilter, Rspamd and SOGo.

The DNS resolver is DNSSEC enabled and forwards local hostnames to Docker.

## Installation

You need Docker. Most systems can install Docker by running the following command:

```
wget -qO- https://get.docker.com/ | sh
```

1. Open mailcow.conf and change stuff, do not use special chars in passwords. This will be fixed soon.

2. Run ./build-all.sh

Done.

You can now access https://${MAILCOW_HOSTNAME} with the default credentials `admin` + password `moohoo`.

## Configuration after installation

### Rspamd UI access
If you want to use Rspamds web UI, you need to set a Rspamd controller password:

```
# Generate hash
docker exec -it rspamd-mailcow rspamadm pw
```

Replace given hash in data/conf/rspamd/override.d/worker-controller.inc:
```
enable_password = "myhash";
```

Restart rspamd:
```
docker restart rspamd-mailcow
```

Open https://${MAILCOW_HOSTNAME}/rspamd in a browser.

### SSL (or: How to use Let's Encrypt)
mailcow dockerized comes with a self-signed certificate.

First you should renew the DH parameters. Assuming you are in the mailcow root folder:
```
openssl dhparam -out ./data/assets/ssl/dhparams.pem 2048
```

Get the certbot client:
```
wget https://dl.eff.org/certbot-auto && chmod +x certbot-auto
```

Please disable applications blocking port 80 and run certbot:
```
./certbot-auto certonly \
	--standalone \
	--standalone-supported-challenges http-01 \
	-d ${MAILCOW_HOSTNAME} \
	--email you@example.org \
	--agree-tos
```

Link certificates to assets directory. Assuming you are still in the mailcow root folder:
```
mv data/assets/ssl/mail.{crt,crt_old}
mv data/assets/ssl/mail.{key,key_old}
ln -s /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/fullchain.pem data/assets/ssl/mail.crt
ln -s /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/privkey.pem data/assets/ssl/mail.key
```

Restart containers which use the certificate:
```
docker restart postfix-mailcow
docker restart dovecot-mailcow
docker restart nginx-mailcow
```

When renewing certificates, run the last two steps as post-hook in certbot.

## Special usage
### build-*.files

(Re)build a container:
```
./build-$name.sh 
```

**:exclamation:** Any previous container with the same name will be stopped and removed.
No persistent data is deleted at any time.
If an image exists, you will be asked wether or not to repull/rebuild it.

### Logs

You can use docker logs $name for almost all containers. Only rmilter does not log to stdout. You can check rspamd logs for rmilter reponses.

When a process dies, the container dies, too. Except for Postfix' container.

### MariaDB

Connect to MariaDB database:
```
./build-sql.sh --client
```

Init schema (will also be installed when running `./build-sql.sh` without parameters):
```
./build-sql.sh --init-schema
```

Reset mailcow admin to `admin:moohoo`:
```
./build-sql.sh --reset-admin
```

Dump database to file backup_${DBNAME}_${DATE}.sql:
```
./build-sql.sh --dump
```

Restore database from a file:
```
./build-sql.sh --restore filename

### Redis

Connect to redis database:
```
./build-sql.sh --client
```

### Rspamd examples

Use rspamadm:
```
docker exec -it rspamd-mailcow rspamadm --help
```

Use rspamc:
```
docker exec -it rspamd-mailcow rspamc --help
```

### Remove persistent data

MariaDB:

```
docker stop mariadb-mailcow
docker rm mariadb-mailcow
rm -rf data/db/mysql/*
./build-sql.sh
```

Redis:

```
# If you feel hardcore:
docker stop redis-mailcow
docker rm redus-mailcow
rm -rf data/db/redis/*
./build-redis.sh

## It is almost always enough to just flush all keys:
./build-redis client
# FLUSHALL [ENTER]
```
