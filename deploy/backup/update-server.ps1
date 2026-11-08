# Updates the backup kit on the server from this repository (scripts, units,
# path watches) as root, then checks that the delete watch answers a request
# for a set that does not exist, so no real backup is touched.
#
#   powershell -ExecutionPolicy Bypass -File deploy\backup\update-server.ps1
param(
    [string]$ServerIp = '178.105.246.187',
    [string]$RootKey = "$env:USERPROFILE\.ssh\examis_staging",
    [string]$PullKey = "$env:USERPROFILE\.ssh\examis_backup_pull.pub"
)
$ErrorActionPreference = 'Stop'
$ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
$scp = 'C:\Windows\System32\OpenSSH\scp.exe'
$opts = @('-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-i', $RootKey)
$files = 'examis-backup.sh', 'examis-backup-pull.sh', 'install-backup.sh', 'examis-backup@.service',
    'examis-backup-6h.timer', 'examis-backup-daily.timer', 'examis-backup-request.path', 'examis-backup-delete.path' |
    ForEach-Object { Join-Path $PSScriptRoot $_ }

& $ssh @opts "root@$ServerIp" 'rm -rf /root/examis-backup-update && mkdir -p /root/examis-backup-update'
& $scp @opts -q @files $PullKey "root@${ServerIp}:/root/examis-backup-update/"
& $ssh @opts "root@$ServerIp" @'
set -e
cd /root/examis-backup-update
sed -i 's/\r$//' *
bash install-backup.sh examis_backup_pull.pub | tail -2
echo "watches: $(systemctl is-active examis-backup-request.path) $(systemctl is-active examis-backup-delete.path)"
touch /srv/examis/shared/storage/app/backup-status/delete-6h-20000101T000000Z
for i in $(seq 1 20); do [ -e /srv/examis/shared/storage/app/backup-status/delete-6h-20000101T000000Z ] || break; sleep 1; done
journalctl -u examis-backup@delete.service -n 3 --no-pager -o cat
'@
