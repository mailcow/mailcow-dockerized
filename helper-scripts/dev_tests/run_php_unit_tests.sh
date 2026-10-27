#!/bin/bash
# Runs the PHPUnit tests of data/web/inc/lib/mailcow (tests/php) in throwaway containers.
# Dev only: PHPUnit is installed into tests/php/vendor, never into the production vendor directory.
# Usage: helper-scripts/dev_tests/run_php_unit_tests.sh [phpunit arguments, e.g. --filter JobServiceTest]

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TESTS_DIR="${REPO_DIR}/tests/php"

docker run --rm -u "$(id -u):$(id -g)" -v "${TESTS_DIR}:/app" -w /app composer:2 \
  install --no-interaction --no-progress --quiet

docker run --rm -u "$(id -u):$(id -g)" -v "${REPO_DIR}:/app" -w /app/tests/php php:8.2-cli \
  php vendor/bin/phpunit "$@"
