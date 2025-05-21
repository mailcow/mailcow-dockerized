#!/bin/bash

# Run hooks
for file in /hooks/*; do
  if [ -x "${file}" ]; then
    echo "Running hook ${file}"
    "${file}"
  fi
done

python3 -u /bootstrap/main.py
BOOTSTRAP_EXIT_CODE=$?

if [ $BOOTSTRAP_EXIT_CODE -ne 0 ]; then
  echo "Bootstrap failed with exit code $BOOTSTRAP_EXIT_CODE. Not starting Clamd."
  exit $BOOTSTRAP_EXIT_CODE
fi

echo "Bootstrap succeeded. Starting Clamd..."
exec "$@"
