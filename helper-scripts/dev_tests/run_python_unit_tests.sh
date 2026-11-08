#!/bin/bash
# Runs the Python unit tests of the backup runners (tests/python/backup) in a throwaway container of the locally built
# restic image: the backup runner (data/Dockerfiles/restic) and the dovecot restore runner (data/Dockerfiles/dovecot).
# No network, no database: the tests replace the database and the clock.
# Usage: helper-scripts/dev_tests/run_python_unit_tests.sh [unittest arguments, e.g. test_restic_runner]

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE="mailcow-restic-tests"

if [ $# -eq 0 ]; then
  set -- discover -v
fi

docker build --quiet -t "${IMAGE}" "${REPO_DIR}/data/Dockerfiles/restic" > /dev/null

docker run --rm --network none --security-opt label=disable \
  -v "${REPO_DIR}/data/Dockerfiles/restic:/app/restic:ro" \
  -v "${REPO_DIR}/data/Dockerfiles/dovecot:/app/dovecot:ro" \
  -v "${REPO_DIR}/tests/python/backup:/tests:ro" \
  -w /tests -e PYTHONPATH=/app/restic:/app/dovecot:/tests -e PYTHONDONTWRITEBYTECODE=1 \
  --entrypoint python3 "${IMAGE}" -m unittest "$@"
