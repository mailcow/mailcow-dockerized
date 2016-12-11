# mailcow-dockerized

## Installation

1. Open mailcow.conf and change stuff, do not use special chars in passwords. This will be fixed soon.

2. Run ./build-all.sh

3. Set a rspamd controller password (see section "rspamd")

Done.

The default username for mailcow is `admin` with password `moohoo`.

## Usage
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

### Redis

Connect to redis database:
```
./build-sql.sh --client
```

### rspamd

Use rspamadm:
```
docker exec -it rspamd-mailcow rspamadm --help
```

Use rspamc:
```
docker exec -it rspamd-mailcow rspamc --help
```

Set rspamd controller password:
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
