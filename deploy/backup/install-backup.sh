#!/usr/bin/env bash
# Installs the EXAMIS staging backups. Run as root from this directory:
#
#   bash install-backup.sh <pull-key.pub>
#
# Installs age and rclone (Ubuntu packages), the backup and pull scripts, the
# units, and the examis-pull account whose only SSH command streams the
# newest encrypted daily set. The timers start only once
# /etc/examis-backup/recipients.txt holds the owner's age public key, so no
# backup is ever taken without encryption. Safe to run again.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
PULL_KEY="${1:?usage: install-backup.sh <pull-key.pub>}"
log() { printf '\n[install-backup] %s\n' "$*"; }
fail() { printf '[install-backup] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == 0 ]] || fail "run as root"
grep -qE '^ssh-ed25519 [A-Za-z0-9+/=]+( .*)?$' "$PULL_KEY" || fail "$PULL_KEY is not one ssh-ed25519 public key"

log "Packages"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq age rclone > /dev/null
age --version; rclone version | head -1

log "Pull account (no password, no shell access beyond one command)"
getent group examis-pull > /dev/null || groupadd --system examis-pull
id examis-pull > /dev/null 2>&1 || useradd --system --gid examis-pull --home-dir /var/lib/examis-pull \
    --create-home --shell /bin/bash examis-pull
passwd -l examis-pull > /dev/null
install -d -m 700 -o examis-pull -g examis-pull /var/lib/examis-pull/.ssh
printf 'restrict,command="/usr/local/sbin/examis-backup-pull.sh" %s\n' "$(cat "$PULL_KEY")" \
    > /var/lib/examis-pull/.ssh/authorized_keys
chown examis-pull:examis-pull /var/lib/examis-pull/.ssh/authorized_keys
chmod 600 /var/lib/examis-pull/.ssh/authorized_keys

log "Directories"
install -d -m 700 /etc/examis-backup /root/.config/rclone
install -d -m 750 -g examis-pull /var/backups/examis /var/backups/examis/daily /var/backups/examis/6h /var/backups/examis/manual
# Shared with the application: it reads status.json and creates "request";
# sticky, so it cannot replace what the backup writes.
install -d -m 1770 -o root -g examis /srv/examis/shared/storage/app/backup-status
install -d -m 700 /var/backups/examis/weekly

log "Scripts and units"
install -m 750 "$HERE/examis-backup.sh" /usr/local/sbin/examis-backup.sh
install -m 755 "$HERE/examis-backup-pull.sh" /usr/local/sbin/examis-backup-pull.sh
install -m 644 "$HERE/examis-backup@.service" "$HERE/examis-backup-6h.timer" "$HERE/examis-backup-daily.timer"     "$HERE/examis-backup-request.path" /etc/systemd/system/
systemctl daemon-reload

if grep -qE '^age1[0-9a-z]{58}$' /etc/examis-backup/recipients.txt 2>/dev/null; then
    systemctl enable --now examis-backup-6h.timer examis-backup-daily.timer examis-backup-request.path
    log "Timers and the manual-request watch enabled"
else
    log "No age public key in /etc/examis-backup/recipients.txt yet: timers NOT enabled"
fi
