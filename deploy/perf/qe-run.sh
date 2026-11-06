#!/usr/bin/env bash
# EXAMIS perf: run one k6 script against the perf copy, with caps, resource
# sampling per component and the integrity checks afterwards. Run as root:
#
#   examis-qe-run <script.js> <run-id> [NAME=value ...]
#   examis-qe-run submission-flow.js 2026-11-06-submit-05 QE_VUS=5 QE_STUDENT_OFFSET=100
#
# k6 runs as "qe" in the scope qe-k6-<run-id> (1 vCPU, 1.5 GB). Results go to
# /srv/examis-perf/qe/results/<run-id>/: k6.log, summary.json, resources.csv,
# integrity.txt, run.txt. The synthetic staff password is passed to k6 from
# /etc/examis-perf/secrets and is never written to the results.
set -euo pipefail

PERF=/srv/examis-perf
SCRIPT="${1:?usage: examis-qe-run <script.js> <run-id> [NAME=value ...]}"
RUN_ID="${2:?usage: examis-qe-run <script.js> <run-id> [NAME=value ...]}"
shift 2

fail() { printf '[qe-run] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == "0" ]] || fail "run as root"
[[ "$RUN_ID" =~ ^[A-Za-z0-9._-]{6,64}$ ]] || fail "run id: 6-64 characters of A-Z a-z 0-9 . _ -"
[[ -f "$PERF/qe/load/$SCRIPT" ]] || fail "no such script: $SCRIPT"
systemctl is-active --quiet examis-perf.target || fail "the perf session is not running (systemctl start examis-perf.target)"
pgrep -x k6 >/dev/null && fail "another k6 run is active: one heavy tool at a time"
for pair in "$@"; do
    [[ "$pair" =~ ^(QE_[A-Z_]+|STAGE_[A-Z]+)=[A-Za-z0-9._:-]*$ ]] || fail "not allowed: $pair"
    [[ "$pair" == QE_STAFF_PASSWORD=* ]] && fail "the staff password comes from the secrets file"
done

OUT="$PERF/qe/results/$RUN_ID"
[[ -e "$OUT" ]] && fail "results for $RUN_ID already exist"
install -d -m 750 -o qe -g qe "$OUT"
STAFF_PASSWORD="$(grep '^QE_STAFF_PASSWORD=' /etc/examis-perf/secrets | cut -d= -f2-)"

{
    echo "run_id=$RUN_ID"
    echo "script=$SCRIPT"
    echo "settings=$*"
    echo "commit=$(cat "$PERF/app/.perf-commit")"
    echo "app_release=$(readlink -f /srv/examis/current)"
    echo "started=$(date -Iseconds)"
    echo "host=$(hostname) $(nproc) vCPU $(free -m | awk '/^Mem:/ {print $2}') MB"
    echo "k6=$(k6 version | head -n 1)"
    echo "caps=k6 CPUQuota=100% MemoryMax=1536M"
} > "$OUT/run.txt"

UNIT="qe-k6-${RUN_ID//./-}"
/usr/local/sbin/examis-qe-sample "$OUT/resources.csv" "$UNIT.scope" &
SAMPLER=$!
trap 'kill "$SAMPLER" 2>/dev/null || true' EXIT
sleep 6

set +e
systemd-run --quiet --scope --unit="$UNIT" -p CPUQuota=100% -p MemoryMax=1536M -- \
    runuser -u qe -- env -i PATH=/usr/local/bin:/usr/bin HOME="$PERF/qe" \
    QE_ENV=perf QE_RUN_ID="$RUN_ID" \
    STUDENTS_URL=https://students.perf.localhost:8443 STAFF_URL=https://staff.perf.localhost:8443 \
    QE_STAFF_PASSWORD="$STAFF_PASSWORD" "$@" \
    k6 run --no-color --summary-export "$OUT/summary.json" "$PERF/qe/load/$SCRIPT" > "$OUT/k6.log" 2>&1
K6_EXIT=$?
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

{
    echo "finished=$(date -Iseconds)"
    echo "k6_exit=$K6_EXIT"
    if [[ -f "$OUT/abort.txt" ]]; then echo "aborted_by_watchdog=yes"; fi
} >> "$OUT/run.txt"
chown -R qe:qe "$OUT"
cat "$OUT/run.txt"
exit "$K6_EXIT"
