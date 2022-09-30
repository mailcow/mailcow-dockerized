#!/bin/bash

if [[ "${SKIP_CLAMD}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo "SKIP_CLAMD=y, skipping ClamAV..."
  exit 0
fi

# run clamd healthcheck
/usr/local/bin/clamdcheck.sh
