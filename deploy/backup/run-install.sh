#!/usr/bin/env bash
# One entry point, run as root on the server from /root/examis-backup-deploy
# after the files are copied there: installs the owner's age public key and
# the backups, takes the first daily and 6-hourly sets, and reports. Safe to
# run again (install-backup.sh is idempotent; each run adds new sets).
set -euo pipefail
D=/root/examis-backup-deploy
cd "$D"
sed -i 's/\r$//' ./*.sh ./*.service ./*.timer recipients.txt examis_backup_pull.pub
grep -qE '^age1[0-9a-z]{58}$' recipients.txt || { echo "recipients.txt holds no age public key" >&2; exit 1; }
install -d -m 700 /etc/examis-backup
install -m 600 recipients.txt /etc/examis-backup/recipients.txt
bash install-backup.sh examis_backup_pull.pub

echo; echo "== first sets"
systemctl start examis-backup@daily.service
systemctl start examis-backup@6h.service
for kind in daily 6h; do echo "$kind: $(systemctl show -p Result --value examis-backup@$kind.service) $(cat /var/backups/examis/last-$kind)"; done
journalctl -u examis-backup@daily.service -n 12 --no-pager -o cat
echo; echo "== timers"; systemctl list-timers 'examis-backup*' --no-legend
echo; echo "== sshd lets examis-pull in?"; sshd -T | grep -iE '^(allowusers|allowgroups|denyusers|denygroups) ' || echo "no AllowUsers/AllowGroups restriction"
