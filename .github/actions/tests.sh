#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="plugins/generic/OJS-MailGuard"
SENDRIA_DB="${RUNNER_TEMP}/mails.sqlite"

fail() {
  echo "::error::[MailGuard Phase 0 runtime] $*" >&2
  exit 1
}

sendria_count() {
  python3 - "$SENDRIA_DB" <<'PY'
import sqlite3
import sys

path = sys.argv[1]
with sqlite3.connect(path) as conn:
    print(conn.execute('SELECT COUNT(*) FROM message').fetchone()[0])
PY
}

echo "Installing MailGuard Phase 0 plugin migration into disposable OJS test instance"
php lib/pkp/tools/installPluginVersion.php "${PLUGIN_DIR}/version.xml"

if ! grep -Eq '^\[mailguard\][[:space:]]*$' config.inc.php; then
  cat >> config.inc.php <<'EOF'

[mailguard]
phase0_capture = On
phase0_intercept = On
EOF
else
  fail "Disposable OJS config unexpectedly already contains a [mailguard] section"
fi

before_count="$(sendria_count)"
echo "Sendria messages before MailGuard probes: ${before_count}"

php "${PLUGIN_DIR}/tests/phase0/runtime-probe.php"
php "${PLUGIN_DIR}/tests/phase0/fail-open-probe.php"
php "${PLUGIN_DIR}/tests/phase0/disabled-transport-probe.php"

# Sendria persists received SMTP messages asynchronously. Poll the local DB
# rather than sleeping a fixed amount, then require exactly three native sends:
# one scoped bypass, one fail-open delivery, and one send after plugin disable.
expected_count=$((before_count + 3))
after_count="$(sendria_count)"
for _ in $(seq 1 20); do
  if [ "$after_count" -ge "$expected_count" ]; then
    break
  fi
  sleep 0.25
  after_count="$(sendria_count)"
done

echo "Sendria messages after MailGuard probes: ${after_count}"

if [ "$after_count" -ne "$expected_count" ]; then
  fail "Expected exactly 3 native SMTP deliveries (bypass + fail-open + disabled), got delta $((after_count - before_count)). Intercepted issue mail may have escaped or a safe native path may be broken."
fi

echo "[PASS] Two intercepted new-issue attempts produced zero SMTP deliveries"
echo "[PASS] Scoped bypass produced one native SMTP delivery"
echo "[PASS] Capture failure failed open to one native SMTP delivery"
echo "[PASS] Disabled MailGuard produced one native SMTP delivery"
echo "MAILGUARD_PHASE0_OJS_RUNTIME_PASS"
