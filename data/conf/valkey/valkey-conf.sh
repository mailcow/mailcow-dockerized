#!/bin/sh

cat <<EOF > /valkey.conf
requirepass $VALKEYPASS
user quota_notify on nopass ~QW_* -@all +get +hget +ping
EOF

if [ -n "$VALKEYMASTERPASS" ]; then
  echo "masterauth $VALKEYMASTERPASS" >> /valkey.conf
fi

exec valkey-server /valkey.conf
