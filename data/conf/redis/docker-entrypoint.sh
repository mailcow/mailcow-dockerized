#!/bin/sh

cat <<EOF > /redis.conf
requirepass $REDISPASS
EOF
exec redis-server /redis.conf
