#!/usr/bin/env bash
# EXAMIS perf: install OWASP ZAP for scanning the perf copy only. Run as root.
# Java comes from Ubuntu; ZAP is the official release, checked against the
# SHA-256 published with it. Nothing runs at boot: zap-run.sh starts ZAP for
# one scan, as the qe user, under the same caps as k6.
set -euo pipefail

ZAP_VERSION=2.17.0
ZAP_SHA256=efe799aaa3627db683b43f00c9c210aea0b75c00cc8f0a0f0434d12bb3ddde5a
ZAP_DIR=/opt/zap
PERF=/srv/examis-perf

fail() { printf '[install-zap] ERROR: %s\n' "$*" >&2; exit 1; }
[[ "$(id -u)" == "0" ]] || fail "run as root"
id qe >/dev/null 2>&1 || fail "run provision-perf.sh first (no qe user)"

if ! command -v java >/dev/null; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends openjdk-21-jre-headless
fi

if [[ ! -f "$ZAP_DIR/ZAP_$ZAP_VERSION/zap-$ZAP_VERSION.jar" ]]; then
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"' EXIT
    curl -fsSL -o "$tmp/zap.tgz" \
        "https://github.com/zaproxy/zaproxy/releases/download/v$ZAP_VERSION/ZAP_${ZAP_VERSION}_Linux.tar.gz"
    echo "$ZAP_SHA256  $tmp/zap.tgz" | sha256sum -c --quiet || fail "checksum mismatch"
    install -d -m 755 "$ZAP_DIR"
    tar -xzf "$tmp/zap.tgz" -C "$ZAP_DIR"
    chown -R root:root "$ZAP_DIR"
fi

# Java ignores the system resolver's *.localhost answers (IPv6 only here);
# the perf vhost listens on 127.0.0.1.
install -d -m 750 -o qe -g qe "$PERF/qe/zap" "$PERF/qe/zap/home"
install -d -m 700 -o qe -g qe "$PERF/qe/zap/session"
printf '127.0.0.1 students.perf.localhost staff.perf.localhost\n' > "$PERF/qe/zap/hosts"
chmod 644 "$PERF/qe/zap/hosts"

java -version 2>&1 | head -n 1
echo "[install-zap] ZAP $ZAP_VERSION in $ZAP_DIR/ZAP_$ZAP_VERSION"
