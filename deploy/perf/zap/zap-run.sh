#!/usr/bin/env bash
# EXAMIS perf: one ZAP scan of the perf copy, as qe, under the k6 caps (1 vCPU,
# 1.5 GB). Run as root after zap-prepare.py:
#
#   bash zap-run.sh passive <run-id>
#   bash zap-run.sh active <run-id>
#
# Results go to /srv/examis-perf/qe/results/<run-id>/: zap-report.html,
# zap-report.json, zap.log, resources.csv, integrity.txt, run.txt. Session
# cookies are scrubbed from every result file before this script ends.
set -euo pipefail

PERF=/srv/examis-perf
ZAP_JAR=/opt/zap/ZAP_2.17.0/zap-2.17.0.jar
KIND="${1:?usage: zap-run.sh passive|active <run-id>}"
RUN_ID="${2:?usage: zap-run.sh passive|active <run-id>}"
PLAN="$PERF/qe/zap/session/plan-$KIND.yaml"
REDACT=/root/examis-perf-deploy/zap-redact.txt

fail() { printf '[zap-run] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == "0" ]] || fail "run as root"
[[ "$KIND" == passive || "$KIND" == active ]] || fail "kind: passive or active"
[[ "$RUN_ID" =~ ^[A-Za-z0-9._-]{6,64}$ ]] || fail "run id: 6-64 characters of A-Z a-z 0-9 . _ -"
[[ -f "$PLAN" && -f "$REDACT" ]] || fail "run zap-prepare.py first"
systemctl is-active --quiet examis-perf.target || fail "the perf session is not running"
pgrep -x k6 >/dev/null && fail "a k6 run is active: one heavy tool at a time"
pgrep -f "$ZAP_JAR" >/dev/null && fail "another ZAP scan is active"

OUT="$PERF/qe/results/$RUN_ID"
[[ -e "$OUT" ]] && fail "results for $RUN_ID already exist"
install -d -m 750 -o qe -g qe "$OUT"
RUN_PLAN="$PERF/qe/zap/session/run-$RUN_ID.yaml"
sed "s#__OUT__#$OUT#g" "$PLAN" > "$RUN_PLAN"
chown qe:qe "$RUN_PLAN"
chmod 600 "$RUN_PLAN"

{
    echo "run_id=$RUN_ID"
    echo "tool=ZAP 2.17.0 automation framework, $KIND plan"
    echo "commit=$(cat "$PERF/app/.perf-commit")"
    echo "started=$(date -Iseconds)"
    echo "host=$(hostname) $(nproc) vCPU $(free -m | awk '/^Mem:/ {print $2}') MB"
    echo "java=$(java -version 2>&1 | head -n 1)"
    echo "caps=zap CPUQuota=100% MemoryMax=1536M -Xmx1g"
} > "$OUT/run.txt"

UNIT="qe-zap-${RUN_ID//./-}"
/usr/local/sbin/examis-qe-sample "$OUT/resources.csv" "$UNIT.scope" &
SAMPLER=$!
trap 'kill "$SAMPLER" 2>/dev/null || true; rm -f "$RUN_PLAN"' EXIT
sleep 6

set +e
systemd-run --quiet --scope --unit="$UNIT" -p CPUQuota=100% -p MemoryMax=1536M -- \
    runuser -u qe -- env -i PATH=/usr/local/bin:/usr/bin HOME="$PERF/qe" \
    java -Xmx1g -Djdk.net.hosts.file="$PERF/qe/zap/hosts" -jar "$ZAP_JAR" \
    -cmd -silent -dir "$PERF/qe/zap/home" -autorun "$RUN_PLAN" > "$OUT/zap.log" 2>&1
ZAP_EXIT=$?
set -e
sleep 6
kill "$SAMPLER" 2>/dev/null || true
wait "$SAMPLER" 2>/dev/null || true

{
    echo "# post-run-integrity.sql on examis_perf ($(date -Iseconds)); every count must be 0"
    sudo -u postgres psql -p 5433 -d examis_perf -X < "$PERF/qe/load/sql/post-run-integrity.sql" 2>&1
    echo
    echo "# php artisan examis:verify-integrity"
    (cd "$PERF/app" && runuser -u examisperf -- php artisan examis:verify-integrity 2>&1) || echo "exit=$?"
} > "$OUT/integrity.txt"

# Scrub the session cookies (longest value first) from every result file.
python3 - "$REDACT" "$OUT" <<'EOF'
import pathlib, sys
values = [v for v in pathlib.Path(sys.argv[1]).read_text().splitlines() if v]
for path in pathlib.Path(sys.argv[2]).iterdir():
    text = path.read_text(errors='replace')
    for value in values:
        text = text.replace(value, '[redacted]')
    path.write_text(text)
EOF

{
    echo "finished=$(date -Iseconds)"
    echo "zap_exit=$ZAP_EXIT"
} >> "$OUT/run.txt"
chown -R qe:qe "$OUT"
echo "[zap-run] $RUN_ID: exit $ZAP_EXIT, results in $OUT"
