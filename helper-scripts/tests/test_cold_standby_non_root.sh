#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COLD_STANDBY="${ROOT}/helper-scripts/_cold-standby.sh"
CREATE_COLD_STANDBY="${ROOT}/create_cold_standby.sh"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

require_pattern() {
  local pattern="$1"
  grep -Eq -- "${pattern}" "${COLD_STANDBY}" \
    || fail "${COLD_STANDBY} is missing ${pattern}"
}

reject_pattern() {
  local pattern="$1"
  if grep -Eq -- "${pattern}" "${COLD_STANDBY}"; then
    fail "${COLD_STANDBY} still contains ${pattern}"
  fi
}

bash -n "${CREATE_COLD_STANDBY}"
bash -n "${COLD_STANDBY}"

grep -Fq 'REMOTE_SSH_USER=root' "${CREATE_COLD_STANDBY}" \
  || fail "${CREATE_COLD_STANDBY} changed the root-compatible default"
require_pattern 'REMOTE_SSH_USER=.*:-root'
require_pattern 'REMOTE_SSH_PORT=.*:-22'
require_pattern 'REMOTE_SSH_STRICT_HOST_KEY_CHECKING=.*:-no'
require_pattern 'sudo -n true'
require_pattern 'sudo -n rsync'
require_pattern '\-\-rsync-path='
require_pattern '\-l "\$\{REMOTE_SSH_USER\}"'
reject_pattern 'root@\$\{REMOTE_SSH_HOST\}'

eval "$(
  sed -n \
    -e '/^function remote_shell()/,/^}/p' \
    -e '/^function remote_privileged_shell()/,/^}/p' \
    -e '/^function remote_rsync_path()/,/^}/p' \
    -e '/^function remote_rsync_shell()/,/^}/p' \
    -e '/^function remote_rsync_target()/,/^}/p' \
    "${COLD_STANDBY}"
)"

SSH_CAPTURE="$(mktemp)"
trap 'rm -f "${SSH_CAPTURE}"' EXIT
SSH_STATUS=0
ssh() {
  printf '%s\0' "$@" > "${SSH_CAPTURE}"
  return "${SSH_STATUS}"
}

export REMOTE_SSH_USER="Directory.User"
export REMOTE_SSH_HOST="standby.example"
export REMOTE_SSH_KEY="/tmp/key with spaces"
export REMOTE_SSH_PORT="2200"
export REMOTE_SSH_STRICT_HOST_KEY_CHECKING="no"

remote_shell "printf '%s\n' ready"
mapfile -d '' -t ssh_args < "${SSH_CAPTURE}"
expected_ssh_args=(
  -o StrictHostKeyChecking=no
  -i "/tmp/key with spaces"
  -p 2200
  -l Directory.User
  --
  standby.example
  "printf '%s\n' ready"
)
[[ "${ssh_args[*]}" == "${expected_ssh_args[*]}" ]] \
  || fail "SSH argument construction failed"

[[ "$(remote_rsync_shell)" == \
  'ssh -o StrictHostKeyChecking=no -i /tmp/key\ with\ spaces -p 2200 -l Directory.User' ]] \
  || fail "rsync SSH command construction failed"
[[ "$(remote_rsync_target "/srv/mailcow data")" == \
  "standby.example:/srv/mailcow data" ]] \
  || fail "rsync target construction failed"
[[ "$(remote_rsync_path)" == "sudo -n rsync" ]] \
  || fail "non-root rsync privilege path failed"

remote_privileged_shell "mkdir -p '/srv/mailcow data'"
mapfile -d '' -t sudo_args < "${SSH_CAPTURE}"
[[ "${sudo_args[-1]}" == sudo\ -n\ bash\ -lc* ]] \
  || fail "non-root privileged command construction failed"

SSH_STATUS=23
if remote_shell "true"; then
  fail "SSH failure status was not propagated"
fi
SSH_STATUS=0

export REMOTE_SSH_USER="root"
[[ "$(remote_rsync_path)" == "rsync" ]] \
  || fail "root compatibility path failed"
remote_privileged_shell "id"
mapfile -d '' -t root_args < "${SSH_CAPTURE}"
[[ "${root_args[-1]}" == "id" ]] \
  || fail "root command compatibility failed"

echo "Cold-standby non-root tests passed."
