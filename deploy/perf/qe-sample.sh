#!/usr/bin/env bash
# EXAMIS perf: sample the host and each component every 5 s into a CSV, and
# stop k6 if the host runs short of memory. Started by examis-qe-run:
#
#   examis-qe-sample <out.csv> <k6-scope-unit>
#
# CPU and memory per component come from systemd's cgroup accounting, so the
# load generator, the perf copy and the staging services are attributed
# separately. cpu_ms columns are cumulative: use the difference between rows.
set -u

OUT="${1:?usage: examis-qe-sample <out.csv> <k6-scope-unit>}"
K6_UNIT="${2:?usage: examis-qe-sample <out.csv> <k6-scope-unit>}"
ABORT="$(dirname "$OUT")/abort.txt"
INTERVAL=5

UNITS=(
    "$K6_UNIT"
    examis-perf-fpm.service postgresql@16-perf.service examis-perf-redis.service examis-perf-queue.service
    nginx.service
    php8.4-fpm.service postgresql@16-main.service redis-server.service examis-queue.service
)
NAMES=(k6 perf_fpm perf_pg perf_redis perf_queue nginx staging_fpm staging_pg staging_redis staging_queue)

header="time,load1,cpu_user_pct,cpu_system_pct,cpu_iowait_pct,cpu_steal_pct,mem_available_mb,swap_used_mb,perf_fpm_active"
for n in "${NAMES[@]}"; do header+=",${n}_cpu_ms,${n}_mem_mb"; done
echo "$header" > "$OUT"

read_cpu() { awk '/^cpu / {print $2+$3, $4+$7+$8, $6, $9, $2+$3+$4+$5+$6+$7+$8+$9}' /proc/stat; }
read -r pu ps pw pst pt < <(read_cpu)
total_mb=$(awk '/^MemTotal/ {print int($2/1024)}' /proc/meminfo)

while true; do
    sleep "$INTERVAL"
    read -r u s w st t < <(read_cpu)
    dt=$((t - pt)); ((dt > 0)) || dt=1
    pct() { awk -v a="$1" -v b="$dt" 'BEGIN {printf "%.1f", 100 * a / b}'; }
    line="$(date -Iseconds),$(cut -d' ' -f1 /proc/loadavg)"
    line+=",$(pct $((u - pu))),$(pct $((s - ps))),$(pct $((w - pw))),$(pct $((st - pst)))"
    pu=$u ps=$s pw=$w pst=$st pt=$t

    avail=$(awk '/^MemAvailable/ {print int($2/1024)}' /proc/meminfo)
    swap=$(awk '/^SwapTotal/ {t=$2} /^SwapFree/ {f=$2} END {print int((t-f)/1024)}' /proc/meminfo)
    active=$(curl -fsk --max-time 2 --resolve students.perf.localhost:8443:127.0.0.1 \
        https://students.perf.localhost:8443/fpm-status 2>/dev/null | awk -F': *' '/^active processes/ {print $2}')
    line+=",$avail,$swap,${active:-}"

    for unit in "${UNITS[@]}"; do
        props="$(systemctl show -p CPUUsageNSec -p MemoryCurrent "$unit" 2>/dev/null)"
        cpu="$(sed -n 's/^CPUUsageNSec=//p' <<< "$props")"
        mem="$(sed -n 's/^MemoryCurrent=//p' <<< "$props")"
        [[ "$cpu" =~ ^[0-9]+$ ]] && cpu=$((cpu / 1000000)) || cpu=""
        [[ "$mem" =~ ^[0-9]+$ ]] && mem=$((mem / 1048576)) || mem=""
        line+=",$cpu,$mem"
    done
    echo "$line" >> "$OUT"

    # Watchdog: below 10% available memory, stop the generator first.
    if ((avail * 10 < total_mb)) && [[ ! -f "$ABORT" ]]; then
        echo "$(date -Iseconds) available memory ${avail} MB < 10% of ${total_mb} MB: stopping k6" > "$ABORT"
        systemctl kill "$K6_UNIT" 2>/dev/null || true
    fi
done
