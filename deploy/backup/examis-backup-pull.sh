#!/usr/bin/env bash
# The only command the examis-pull SSH key may run (authorized_keys
# command=). Streams the newest daily backup set, already encrypted, as a tar
# to stdout for the owner's computer. No shell, no arguments, read-only.
set -euo pipefail
ROOT=/var/backups/examis/daily
LATEST="$(find "$ROOT" -mindepth 1 -maxdepth 1 -type d | sort | tail -n 1)"
[[ -n "$LATEST" ]] || { echo "no daily backup yet" >&2; exit 1; }
tar -C "$ROOT" -cf - "$(basename "$LATEST")"
