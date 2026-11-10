#!/usr/bin/env bash
# The only command the examis-pull SSH key may run (authorized_keys
# command=). Streams the newest backup set of one kind, already encrypted, as
# a tar to stdout for the owner's computer. The client may name the kind
# (daily, 6h or manual; daily when it names none); nothing else is accepted.
set -euo pipefail
KIND="${SSH_ORIGINAL_COMMAND:-daily}"
[[ "$KIND" == daily || "$KIND" == 6h || "$KIND" == manual ]] || { echo "kind must be daily, 6h or manual" >&2; exit 1; }
ROOT="/var/backups/examis/$KIND"
LATEST="$(find "$ROOT" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort | tail -n 1)"
[[ -n "$LATEST" ]] || { echo "no $KIND backup yet" >&2; exit 1; }
tar -C "$ROOT" -cf - "$(basename "$LATEST")"
