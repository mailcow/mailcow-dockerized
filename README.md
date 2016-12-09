# mailcow-dockerized

## Configuration

1. Open mailcow.conf and change stuff, do not touch versions, do not use special chars in passwords for now.

2. ./build-all.sh

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

### MySQL

Connect to MySQL database:
```
./build-mysql.sh --client
```

Init schema (will also be installed when running `./build-mysql.sh` without parameters):
```
./build-mysql.sh --init-schema
```

Reset mailcow admin to `admin:moohoo`:
```
./build-mysql.sh --reset-admin
```

### Redis

Connect to redis database:
```
./build-mysql.sh client
```

### rspamd

Use rspamadm:
```
docker exec -it rspamd-mailcow /bin/bash -c "rspamadm --help"
```

Use rspamc:
```
docker exec -it rspamd-mailcow /bin/bash -c "rspamc --help"
```

Set rspamd controller password:
```
docker exec -it rspamd-mailcow /bin/bash -c "rspamadm pw"
```
Copy given hash to data/conf/rspamd/override.d/worker-controller.inc:
```
...
enable_password = "myhash";
....
```

### Remove persistent data

MySQL:

```
docker stop mysql-mailcow
docker rm mysql-mailcow
rm -rf data/db/mysql/*
./build-mysql.sh
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
