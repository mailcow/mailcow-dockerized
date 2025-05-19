#!/bin/sh

python3 -u /bootstrap/main.py
BOOTSTRAP_EXIT_CODE=$?

if [ $BOOTSTRAP_EXIT_CODE -ne 0 ]; then
  echo "Bootstrap failed with exit code $BOOTSTRAP_EXIT_CODE. Not starting Nginx."
  exit $BOOTSTRAP_EXIT_CODE
fi

echo "Bootstrap succeeded. Starting Nginx..."
nginx -g "daemon off;"
