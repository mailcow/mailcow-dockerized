#!/bin/bash
# Runs the unit tests of netfilter (tests/netfilter) in a throwaway container of the locally built netfilter image.
# The container has its own network namespace, the nftables tests never touch the firewall of the host.
# Usage: helper-scripts/dev_tests/run_netfilter_unit_tests.sh [unittest arguments, e.g. test_ban_manager]

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE="mailcow-netfilter-tests"

if [ $# -eq 0 ]; then
  set -- discover -v
fi

docker build --quiet -t "${IMAGE}" "${REPO_DIR}/data/Dockerfiles/netfilter" > /dev/null

docker run --rm --network none --cap-add NET_ADMIN \
  -v "${REPO_DIR}/data/Dockerfiles/netfilter:/app:ro" \
  -v "${REPO_DIR}/tests/netfilter:/tests:ro" \
  -w /tests -e PYTHONPATH=/app -e PYTHONDONTWRITEBYTECODE=1 \
  --entrypoint python3 "${IMAGE}" -m unittest "$@"
