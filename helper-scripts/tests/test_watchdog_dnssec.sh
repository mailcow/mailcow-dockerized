#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WATCHDOG="${ROOT}/data/Dockerfiles/watchdog/watchdog.sh"
COMPOSE="${ROOT}/docker-compose.yml"
STRICT_CONFIG="${ROOT}/helper-scripts/tests/fixtures/unbound-strict.conf"
PERMISSIVE_CONFIG="${ROOT}/helper-scripts/tests/fixtures/unbound-permissive.conf"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

require_pattern() {
  local pattern="$1"
  local file="$2"
  grep -Eq -- "${pattern}" "${file}" || fail "${file} is missing ${pattern}"
}

reject_pattern() {
  local pattern="$1"
  local file="$2"
  if grep -Eq -- "${pattern}" "${file}"; then
    fail "${file} still contains ${pattern}"
  fi
}

bash -n "${WATCHDOG}"

require_pattern '^get_container_ipv6\(\)' "${WATCHDOG}"
require_pattern '^dnssec_validation_mode\(\)' "${WATCHDOG}"
require_pattern '^dnssec_validation_healthy\(\)' "${WATCHDOG}"
require_pattern '^unbound_check_round\(\)' "${WATCHDOG}"
require_pattern 'com\. SOA \+dnssec' "${WATCHDOG}"
require_pattern 'dnssec-failed\.org A \+dnssec' "${WATCHDOG}"
reject_pattern 'restart_container unbound' "${WATCHDOG}"

require_pattern 'image: ghcr\.io/mailcow/watchdog:2\.11a' "${COMPOSE}"
require_pattern 'ENABLE_IPV6=' "${COMPOSE}"
require_pattern 'unbound\.conf:/etc/mailcow/unbound\.conf:ro,z' "${COMPOSE}"
reject_pattern 'UNBOUND_DNSSEC_MODE=' "${COMPOSE}"

unbound_checks_body="$(sed -n '/^unbound_checks()/,/^}/p' "${WATCHDOG}")"
# The search string is intentionally literal source text.
# shellcheck disable=SC2016
[[ "$(grep -Fc 'err_count=$(( ${err_count} + 1 ))' <<< "${unbound_checks_body}")" -eq 1 ]] \
  || fail "one Unbound polling round can add more than one health error"

eval "$(
  sed -n \
    -e '/^get_container_ipv6()/,/^}/p' \
    -e '/^dnssec_validation_mode()/,/^}/p' \
    -e '/^dnssec_validation_healthy()/,/^}/p' \
    -e '/^run_dns_check()/,/^}/p' \
    -e '/^unbound_check_round()/,/^}/p' \
    "${WATCHDOG}"
)"

export COMPOSE_PROJECT_NAME="mailcowdockerized"
curl() {
  case "$*" in
    */containers/json)
      printf '%s\n' \
        '[{"Id":"unbound-id","Config":{"Labels":{"com.docker.compose.service":"unbound-mailcow","com.docker.compose.project":"mailcowdockerized"}}}]'
      ;;
    */containers/unbound-id/json)
      printf '%s\n' \
        '{"NetworkSettings":{"Networks":{"mailcowdockerized_mailcow-network":{"GlobalIPv6Address":"fd00::53"}}}}'
      ;;
    *)
      return 1
      ;;
  esac
}

[[ "$(get_container_ipv6 unbound-mailcow)" == "fd00::53" ]] \
  || fail "Docker API IPv6 address selection failed"
[[ "$(dnssec_validation_mode "${STRICT_CONFIG}")" == "strict" ]] \
  || fail "strict Unbound configuration was misclassified"
[[ "$(dnssec_validation_mode "${PERMISSIVE_CONFIG}")" == "permissive" ]] \
  || fail "permissive Unbound configuration was misclassified"

DIG_CAPTURE="$(mktemp)"
trap 'rm -f "${DIG_CAPTURE}"' EXIT
MOCK_BOGUS_STATUS="SERVFAIL"
MOCK_BOGUS_AD=""
MOCK_SIGNED_AD="yes"
dig() {
  printf '%s\n' "$*" >> "${DIG_CAPTURE}"
  case "$*" in
    *"com. SOA"*)
      printf '%s\n' ';; ->>HEADER<<- opcode: QUERY, status: NOERROR, id: 1'
      if [[ -n "${MOCK_SIGNED_AD}" ]]; then
        printf '%s\n' ';; flags: qr rd ra ad; QUERY: 1, ANSWER: 1'
      else
        printf '%s\n' ';; flags: qr rd ra; QUERY: 1, ANSWER: 1'
      fi
      ;;
    *dnssec-failed.org*)
      printf '%s\n' ";; ->>HEADER<<- opcode: QUERY, status: ${MOCK_BOGUS_STATUS}, id: 2"
      [[ -z "${MOCK_BOGUS_AD}" ]] \
        || printf '%s\n' ';; flags: qr rd ra ad; QUERY: 1, ANSWER: 1'
      ;;
    *)
      return 1
      ;;
  esac
}

dnssec_validation_healthy 6 "fd00::53" IPv6 strict \
  || fail "valid strict DNSSEC behavior was rejected"
grep -Fq -- '-6 com. SOA +dnssec +timeout=2 +tries=1 @fd00::53' "${DIG_CAPTURE}" \
  || fail "signed lookup did not use the IPv6 Unbound transport"
grep -Fq -- '-6 dnssec-failed.org A +dnssec +timeout=2 +tries=1 @fd00::53' "${DIG_CAPTURE}" \
  || fail "bogus lookup did not use the IPv6 Unbound transport"

MOCK_BOGUS_STATUS="NOERROR"
if dnssec_validation_healthy 4 "192.0.2.53" IPv4 strict; then
  fail "strict mode accepted a bogus DNSSEC chain"
fi

dnssec_validation_healthy 4 "192.0.2.53" IPv4 permissive \
  || fail "valid permissive DNSSEC behavior was rejected"

MOCK_BOGUS_AD="yes"
if dnssec_validation_healthy 4 "192.0.2.53" IPv4 permissive; then
  fail "permissive mode accepted a bogus chain as authenticated"
fi

get_container_ip() {
  printf '%s\n' "192.0.2.53"
}
# Invoked by the function extracted above.
# shellcheck disable=SC2329
get_container_ipv6() {
  printf '%s\n' "fd00::53"
}
ipv6_enabled() {
  return 0
}
dnssec_validation_mode() {
  printf '%s\n' "strict"
}
# Invoked by the function extracted above.
# shellcheck disable=SC2329
run_dns_check() {
  return 1
}
# Invoked by the function extracted above.
# shellcheck disable=SC2329
notify_error() {
  :
}

MOCK_SIGNED_AD=""
if unbound_check_round; then
  fail "a failed polling round was reported as healthy"
fi

MOCK_SIGNED_AD="yes"
MOCK_BOGUS_STATUS="SERVFAIL"
MOCK_BOGUS_AD=""
# Invoked by the function extracted above.
# shellcheck disable=SC2329
run_dns_check() {
  return 0
}
# Invoked by the function extracted above.
# shellcheck disable=SC2329
get_container_ipv6() {
  return 1
}
IPV6_NOTICE=0
# Invoked by the function extracted above.
# shellcheck disable=SC2329
notify_error() {
  IPV6_NOTICE=1
}

unbound_check_round \
  || fail "IPv6 discovery trouble was misclassified as an Unbound process failure"
[[ "${IPV6_NOTICE}" -eq 1 ]] \
  || fail "IPv6 discovery trouble did not trigger a configuration notification"

echo "Watchdog IPv6 and DNSSEC tests passed."
