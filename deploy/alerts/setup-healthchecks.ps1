# Sets up the EXAMIS alerts on Healthchecks.io and points the server at them.
#
#   powershell -ExecutionPolicy Bypass -File deploy\alerts\setup-healthchecks.ps1 -StoreKey   # once: asks for the API key (hidden)
#   powershell -ExecutionPolicy Bypass -File deploy\alerts\setup-healthchecks.ps1             # create/update checks, configure the server
#
# The project's read-write API key stays in Windows Credential Manager
# (EXAMIS/Healthchecks/ApiKey) and never reaches the server; the server gets
# only the ping URLs. Every check notifies all of the project's integrations
# (e-mail and Telegram). Safe to run again: checks are matched by name.
param(
    [switch]$StoreKey,
    [string]$ServerIp = '178.105.246.187',
    [string]$RootKey = "$env:USERPROFILE\.ssh\examis_staging"
)
$ErrorActionPreference = 'Stop'
. "$env:USERPROFILE\examis-cloudflare\CfCredential.ps1"   # ExamisCred: Credential Manager read/write
$target = 'EXAMIS/Healthchecks/ApiKey'

if ($StoreKey) {
    $secure = Read-Host 'Healthchecks.io API key, read-write (input hidden)' -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try {
        $key = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr).Trim()
        if ($key.Length -lt 20) { throw 'The key looks too short; nothing was stored.' }
        [ExamisCred]::Write($target, 'healthchecks.io', $key)
    } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr); $key = $null }
    "Stored as '$target'. Run the script again without -StoreKey."
    return
}

$stored = [ExamisCred]::Read($target)
if (-not $stored) { throw "No API key stored; run with -StoreKey first." }
$headers = @{ 'X-Api-Key' = $stored[1] }
$api = 'https://healthchecks.io/api/v3'

$channels = @((Invoke-RestMethod -Uri "$api/channels/" -Headers $headers).channels)
"ok  integrations: " + (($channels | ForEach-Object { $_.kind }) -join ', ')
if (-not ($channels | Where-Object { $_.kind -eq 'telegram' })) { "WARN no Telegram integration yet: alerts go by e-mail only" }

# name => settings. Times are UTC, like the server and the scheduler.
$checks = [ordered]@{
    HC_HEARTBEAT = @{ name = 'EXAMIS heartbeat'; timeout = 300; grace = 600
        desc = 'Every 5 min from the server: site answers, services run, disk < 85%, replica WAL < 2 GB, no failed units. Silence = server down.' }
    HC_BACKUP_6H = @{ name = 'EXAMIS backup 6h'; schedule = '0 0,6,12,18 * * *'; tz = 'UTC'; grace = 3600
        desc = 'Encrypted database backup every 6 hours (examis-backup@6h).' }
    HC_BACKUP_DAILY = @{ name = 'EXAMIS backup daily'; schedule = '15 3 * * *'; tz = 'UTC'; grace = 7200
        desc = 'Full encrypted backup and off-site copy to R2 every day (examis-backup@daily).' }
    HEALTHCHECK_STAFF_ACCESS_SYNC_URL = @{ name = 'EXAMIS staff access sync'; schedule = '0 * * * *'; tz = 'UTC'; grace = 1800
        desc = 'Hourly sync of active staff e-mails to Cloudflare Access (examis:sync-staff-access).' }
    HEALTHCHECK_VERIFY_INTEGRITY_URL = @{ name = 'EXAMIS integrity check'; schedule = '30 2 * * *'; tz = 'UTC'; grace = 3600
        desc = 'Nightly data integrity verification (examis:verify-integrity).' }
}

$urls = [ordered]@{}
foreach ($envKey in $checks.Keys) {
    $body = $checks[$envKey] + @{ channels = '*'; unique = @('name'); tags = 'examis' }
    $check = Invoke-RestMethod -Method Post -Uri "$api/checks/" -Headers $headers -ContentType 'application/json' -Body ($body | ConvertTo-Json -Compress)
    if ($check.ping_url -notmatch '^https://hc-ping\.com/[0-9a-f-]{36}$') { throw "unexpected ping URL for $($body.name)" }
    $urls[$envKey] = $check.ping_url
    "ok  check '$($body.name)' ($($check.status))"
}

# The server: ping URLs for the heartbeat and backups (root only), and the
# application's two in its .env. Sent over SSH stdin, LF only.
$ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
$scp = 'C:\Windows\System32\OpenSSH\scp.exe'
$opts = @('-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-i', $RootKey)
$OutputEncoding = New-Object System.Text.UTF8Encoding $false

# Uploaded with LF line endings, whatever the checkout uses.
$staging = Join-Path $env:TEMP ('examis-alerts-' + [guid]::NewGuid())
New-Item -ItemType Directory -Path $staging | Out-Null
$files = 'install-alerts.sh', 'set-app-env.sh', 'examis-heartbeat.sh', 'examis-heartbeat.service', 'examis-heartbeat.timer' | ForEach-Object {
    $text = [IO.File]::ReadAllText((Join-Path $PSScriptRoot $_)) -replace "`r`n", "`n"
    $out = Join-Path $staging $_
    [IO.File]::WriteAllText($out, $text, (New-Object System.Text.UTF8Encoding $false))
    $out
}
& $ssh @opts "root@$ServerIp" 'rm -rf /root/examis-alerts-update && mkdir -p /root/examis-alerts-update'
& $scp @opts -q @files "root@${ServerIp}:/root/examis-alerts-update/"
$uploaded = $LASTEXITCODE
Remove-Item -Recurse -Force $staging
if ($uploaded -ne 0) { throw 'upload failed' }

$serverLines = ('HC_HEARTBEAT', 'HC_BACKUP_6H', 'HC_BACKUP_DAILY' | ForEach-Object { "$_=$($urls[$_])" }) -join "`n"
$serverLines | & $ssh @opts "root@$ServerIp" 'bash /root/examis-alerts-update/install-alerts.sh'
if ($LASTEXITCODE -ne 0) { throw 'install-alerts.sh failed' }

$appLines = ('HEALTHCHECK_STAFF_ACCESS_SYNC_URL', 'HEALTHCHECK_VERIFY_INTEGRITY_URL' | ForEach-Object { "$_=$($urls[$_])" }) -join "`n"
$appLines | & $ssh @opts "root@$ServerIp" 'bash /root/examis-alerts-update/set-app-env.sh'
if ($LASTEXITCODE -ne 0) { throw 'updating the application .env failed' }
'Done.'
