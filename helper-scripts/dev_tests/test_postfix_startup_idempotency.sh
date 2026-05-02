#!/usr/bin/env bash
# Test that the postfix.sh main.cf reset and extra.cf myhostname handling
# are idempotent across multiple simulated container restarts.
#
# Exercises the exact logic from data/Dockerfiles/postfix/postfix.sh without
# requiring Docker.  Run from anywhere:
#
#   bash helper-scripts/dev_tests/test_postfix_startup_idempotency.sh

set -uo pipefail

PASS=0
FAIL=0

pass() { echo "  PASS: $*"; PASS=$((PASS+1)); }
fail() { echo "  FAIL: $*"; FAIL=$((FAIL+1)); }

# ── helpers ──────────────────────────────────────────────────────────────────

# Extracted and parameterised from postfix.sh lines 479-491.
# CONF_DIR = directory containing main.cf and extra.cf
# HOSTNAME = value for myhostname
run_startup() {
  local conf="$1" hostname="$2"

  grep -qF '# Overrides #' "${conf}/main.cf" \
    || printf '\n# DO NOT EDIT ANYTHING BELOW #\n# Overrides #\n' >> "${conf}/main.cf"
  sed -i '/# Overrides #/q' "${conf}/main.cf"
  echo >> "${conf}/main.cf"
  echo -e "\n# User Overrides" >> "${conf}/main.cf"
  touch "${conf}/extra.cf"
  {
    echo "myhostname = ${hostname}"
    grep -v '^[[:space:]]*myhostname[[:space:]]*=' "${conf}/extra.cf" || true
  } > "${conf}/extra.cf.tmp"
  mv "${conf}/extra.cf.tmp" "${conf}/extra.cf"
  cat "${conf}/extra.cf" >> "${conf}/main.cf"
}

count_occurrences() {
  grep -cF "$1" "$2" 2>/dev/null || true
}

# ── test 1: main.cf stays stable across many restarts ────────────────────────
echo "Test 1: main.cf does not accumulate across restarts"
T=$(mktemp -d)
cat > "${T}/main.cf" <<'EOF'
biff = no
smtpd_tls_security_level = may
# DO NOT EDIT ANYTHING BELOW #
# Overrides #
EOF
touch "${T}/extra.cf"

for i in $(seq 1 10); do run_startup "$T" "mail.example.com"; done

n=$(count_occurrences '# Overrides #' "${T}/main.cf")
[[ "$n" -eq 1 ]] \
  && pass "sentinel appears exactly once after 10 restarts" \
  || fail "sentinel appears $n times after 10 restarts (expected 1)"

n=$(count_occurrences '# User Overrides' "${T}/main.cf")
[[ "$n" -eq 1 ]] \
  && pass "User Overrides section appears exactly once after 10 restarts" \
  || fail "User Overrides appears $n times after 10 restarts (expected 1)"

n=$(count_occurrences 'myhostname = mail.example.com' "${T}/main.cf")
[[ "$n" -eq 1 ]] \
  && pass "myhostname appears exactly once in main.cf" \
  || fail "myhostname appears $n times in main.cf (expected 1)"

rm -rf "$T"

# ── test 2: sentinel missing from main.cf — reset should self-heal ───────────
echo "Test 2: missing sentinel is added and reset works"
T=$(mktemp -d)
cat > "${T}/main.cf" <<'EOF'
biff = no
smtpd_tls_security_level = may
EOF
touch "${T}/extra.cf"

for i in $(seq 1 5); do run_startup "$T" "mail.example.com"; done

n=$(count_occurrences '# Overrides #' "${T}/main.cf")
[[ "$n" -eq 1 ]] \
  && pass "sentinel added and stable after 5 restarts on sentinel-free main.cf" \
  || fail "sentinel appears $n times (expected 1)"

rm -rf "$T"

# ── test 3: extra.cf user content preserved, myhostname not duplicated ───────
echo "Test 3: extra.cf user content preserved; myhostname not duplicated"
T=$(mktemp -d)
cat > "${T}/main.cf" <<'EOF'
biff = no
# DO NOT EDIT ANYTHING BELOW #
# Overrides #
EOF
cat > "${T}/extra.cf" <<'EOF'
smtpd_use_tls = yes
smtpd_tls_auth_only = yes
EOF

for i in $(seq 1 5); do run_startup "$T" "mail.example.com"; done

n=$(count_occurrences 'myhostname' "${T}/extra.cf")
[[ "$n" -eq 1 ]] \
  && pass "myhostname appears exactly once in extra.cf after 5 restarts" \
  || fail "myhostname appears $n times in extra.cf (expected 1)"

grep -qF 'smtpd_use_tls = yes' "${T}/extra.cf" \
  && pass "user extra.cf content preserved" \
  || fail "user extra.cf content lost"

grep -qF 'smtpd_tls_auth_only = yes' "${T}/extra.cf" \
  && pass "all user extra.cf lines preserved" \
  || fail "some user extra.cf lines lost"

rm -rf "$T"

# ── test 4: hostname change is reflected correctly ────────────────────────────
echo "Test 4: myhostname update when MAILCOW_HOSTNAME changes"
T=$(mktemp -d)
cat > "${T}/main.cf" <<'EOF'
biff = no
# DO NOT EDIT ANYTHING BELOW #
# Overrides #
EOF
touch "${T}/extra.cf"

run_startup "$T" "mail-old.example.com"
run_startup "$T" "mail-new.example.com"

grep -qF 'myhostname = mail-new.example.com' "${T}/extra.cf" \
  && pass "new hostname present in extra.cf" \
  || fail "new hostname missing from extra.cf"

grep -qF 'myhostname = mail-old.example.com' "${T}/extra.cf" \
  && fail "old hostname still present in extra.cf" \
  || pass "old hostname removed from extra.cf"

rm -rf "$T"

# ── summary ──────────────────────────────────────────────────────────────────
echo ""
echo "Results: ${PASS} passed, ${FAIL} failed"
[[ "$FAIL" -eq 0 ]] && exit 0 || exit 1
