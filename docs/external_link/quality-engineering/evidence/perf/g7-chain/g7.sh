#!/bin/bash
# G7 heavy runs on the perf copy, one at a time (owner approved 2026-11-07).
# Each run starts from a rebuilt synthetic database and carries its own
# QE_HEAVY_APPROVAL equal to its run id.
set -u
D=/root/examis-perf-deploy
ST=$D/g7.status
PKG=/tmp/examis-b9a9096.tgz
note() { echo "$(date -u +%FT%TZ) $*" >> "$ST"; }
rebuild() { bash $D/perf/release-perf.sh $PKG b9a9096 3000 > "$D/release-$1.log" 2>&1; note "rebuild for $1 exit=$?"; }
run() { id=$1; shift; examis-qe-run "$@" > "$D/run-$id.out" 2>&1; note "$id exit=$?"; }
: > "$ST"
note "start"
rebuild stress
run 2026-11-07-stress-200 stress.js 2026-11-07-stress-200 QE_MAX_VUS=200 QE_HEAVY_APPROVAL=2026-11-07-stress-200 QE_STUDENT_OFFSET=300
rebuild spike
run 2026-11-07-spike-200 spike.js 2026-11-07-spike-200 QE_MAX_VUS=200 QE_HEAVY_APPROVAL=2026-11-07-spike-200 QE_STUDENT_OFFSET=600
rebuild soak
run 2026-11-07-soak-2h soak.js 2026-11-07-soak-2h QE_SOAK_VUS=50 QE_MAX_VUS=55 QE_SOAK_DURATION=2h QE_HEAVY_APPROVAL=2026-11-07-soak-2h QE_STUDENT_OFFSET=900 QE_SUBMIT_OFFSET=1000
systemctl stop examis-perf.target
note "done; perf stopped"
