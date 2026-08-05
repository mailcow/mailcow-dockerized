#!/bin/sh

cat <<EOF > /redis.conf
requirepass $REDISPASS
user quota_notify on nopass ~QW_* -@all +get +hget +ping
save 43200 1000
EOF

if [ -n "$REDISMASTERPASS" ]; then
  echo "masterauth $REDISMASTERPASS" >> /redis.conf
fi

exec redis-server /redis.conf
