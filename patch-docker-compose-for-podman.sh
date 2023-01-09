#!/usr/bin/env bash
#
# This script patches the docker-compose.yml for usage with podman.
# This is necessary because not all options (e.g. DNS) can be overwritten by docker-compose, see
#   https://github.com/docker/compose/issues/3729

set -e

PATCH_FILE="patch-docker-compose-for-podman.patch"
TIMESTAMP="$(date +'%Y%m%d%H%M')"

# Create a backup (in case custom changes are made)
cp docker-compose.yml docker-compose.yml.${TIMESTAMP}.bak

# Detect whether the patch has been applied by trying to reverse the patch in a dry-run scenario
if ! patch -R -s -f --dry-run docker-compose.yml < ${PATCH_FILE} > /dev/null 2>&1; then
    patch docker-compose.yml < ${PATCH_FILE}
else
    echo "Patch file already applied or custom changes prevent applying the patch"
fi
