#!/bin/sh

cat <<EOF > /redis.conf
requirepass $REDISPASS
user quota_notify on nopass ~QW_* -@all +get +hget +ping
EOF

exec redis-server /redis.conf
