# One-way live copy of the server's examis_production into the database
# examis_replica of this PC's PostgreSQL 18. The owner runs it once:
#
#   powershell -ExecutionPolicy Bypass -File deploy\replica\setup.ps1
#
# 1. Server (as root, server-setup.sh): logical replication on (PostgreSQL
#    restarts once, a few seconds), read-only role examis_replica, the
#    publication, and the examis-tunnel account.
# 2. This PC: the scheduled task "EXAMIS replica tunnel" (tunnel.ps1, at
#    sign-in, reconnects by itself), the database examis_replica with the
#    server's schema, and the subscription. From then on every insert, update
#    and delete on the server reaches this PC within seconds; when the PC was
#    off it catches up.
#
# The copy is for reading. Change data on the site (staff "Students
# database" screen); a change made here is not sent back and may stop the
# copy. After a release with migrations run deploy\replica\migrate.ps1.
# -Rebuild drops and rebuilds the local copy (e.g. after a long time off).
param(
    [switch]$Rebuild,
    [string]$ServerIp = '178.105.246.187',
    [string]$RootKey = "$env:USERPROFILE\.ssh\examis_staging",
    [string]$TunnelKey = "$env:USERPROFILE\.ssh\examis_replica_tunnel",
    [string]$PgBin = 'D:\PostgreSQL\18\bin',
    [string]$LocalDb = 'examis_replica'
)
$ErrorActionPreference = 'Stop'
$here = $PSScriptRoot
$root = Resolve-Path (Join-Path $here '..\..')
$ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
$scp = 'C:\Windows\System32\OpenSSH\scp.exe'
function Step($text) { Write-Host "`n[replica] $text" -ForegroundColor Cyan }

# Local superuser from the project's .env (never printed).
$envFile = Get-Content (Join-Path $root '.env')
$localUser = ($envFile | Where-Object { $_ -match '^DB_USERNAME=' }) -replace '^DB_USERNAME=', ''
$env:PGPASSWORD = ($envFile | Where-Object { $_ -match '^DB_PASSWORD=' }) -replace '^DB_PASSWORD=', ''
$local = @('-h', '127.0.0.1', '-p', '5432', '-U', $localUser)
& "$PgBin\psql.exe" @local -d postgres -Atqc 'select 1' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Cannot reach the local PostgreSQL with the .env credentials.' }

Step 'Tunnel key'
if (-not (Test-Path $TunnelKey)) {
    & "$env:WINDIR\System32\OpenSSH\ssh-keygen.exe" -q -t ed25519 -N '""' -C 'examis-replica-tunnel' -f $TunnelKey
}

Step 'Server (as root): settings, role, publication, tunnel account'
& $scp -q -o IdentitiesOnly=yes -i $RootKey (Join-Path $here 'server-setup.sh') "$TunnelKey.pub" "root@${ServerIp}:/root/"
& $ssh -o IdentitiesOnly=yes -o BatchMode=yes -i $RootKey "root@$ServerIp" "sed -i 's/\r$//' /root/server-setup.sh /root/examis_replica_tunnel.pub && bash /root/server-setup.sh /root/examis_replica_tunnel.pub"
if ($LASTEXITCODE -ne 0) { throw 'server-setup.sh failed' }
$replicaPassword = (& $ssh -o IdentitiesOnly=yes -o BatchMode=yes -i $RootKey "root@$ServerIp" 'cat /root/examis-replica.env') -replace '^REPLICA_PASSWORD=', ''

Step 'Scheduled task "EXAMIS replica tunnel"'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$(Join-Path $here 'tunnel.ps1')`""
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName 'EXAMIS replica tunnel' -Action $action -Trigger (New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME") `
    -Settings $settings -Principal $principal -Force | Out-Null
Start-ScheduledTask -TaskName 'EXAMIS replica tunnel'
$up = $false
foreach ($i in 1..30) {
    if ((Test-NetConnection 127.0.0.1 -Port 15432 -WarningAction SilentlyContinue).TcpTestSucceeded) { $up = $true; break }
    Start-Sleep -Seconds 2
}
if (-not $up) { throw 'The tunnel did not open; see storage\logs\replica-tunnel.log' }

$remote = @('-h', '127.0.0.1', '-p', '15432', '-U', 'examis_replica', '-d', 'examis_production')

Step "Local database $LocalDb"
$exists = & "$PgBin\psql.exe" @local -d postgres -Atc "select 1 from pg_database where datname = '$LocalDb'"
if ($exists -eq '1') {
    if (-not $Rebuild) { throw "$LocalDb already exists; run again with -Rebuild to replace it." }
    & "$PgBin\psql.exe" @local -d $LocalDb -Atqc 'ALTER SUBSCRIPTION examis_sub DISABLE' 2>$null
    & "$PgBin\psql.exe" @local -d $LocalDb -Atqc 'ALTER SUBSCRIPTION examis_sub SET (slot_name = NONE)' 2>$null
    & "$PgBin\psql.exe" @local -d $LocalDb -Atqc 'DROP SUBSCRIPTION IF EXISTS examis_sub' 2>$null
    & "$PgBin\psql.exe" @local -d postgres -Atqc "DROP DATABASE $LocalDb WITH (FORCE)"
    # The server keeps the old slot until it is dropped there.
    $env:PGPASSWORD_LOCAL = $env:PGPASSWORD; $env:PGPASSWORD = $replicaPassword
    & "$PgBin\psql.exe" @remote -Atqc "select pg_drop_replication_slot('examis_sub') from pg_replication_slots where slot_name = 'examis_sub'" | Out-Null
    $env:PGPASSWORD = $env:PGPASSWORD_LOCAL; Remove-Item Env:PGPASSWORD_LOCAL
}
& "$PgBin\psql.exe" @local -d postgres -v ON_ERROR_STOP=1 -Atqc "CREATE DATABASE $LocalDb"
& "$PgBin\psql.exe" @local -d $LocalDb -Atqc "COMMENT ON DATABASE $LocalDb IS 'Read-only live copy of the server examis_production (deploy/replica). Do not edit.'"

Step 'Schema and migration list from the server'
$schema = Join-Path $env:TEMP 'examis-replica-schema.sql'
$migrations = Join-Path $env:TEMP 'examis-replica-migrations.sql'
$localPassword = $env:PGPASSWORD
$env:PGPASSWORD = $replicaPassword
& "$PgBin\pg_dump.exe" @remote --schema-only --no-owner --no-privileges --no-publications --no-subscriptions -f $schema
& "$PgBin\pg_dump.exe" @remote --data-only --table=migrations -f $migrations
$env:PGPASSWORD = $localPassword
& "$PgBin\psql.exe" @local -d $LocalDb -v ON_ERROR_STOP=1 -q -f $schema | Out-Null
& "$PgBin\psql.exe" @local -d $LocalDb -v ON_ERROR_STOP=1 -q -f $migrations | Out-Null
Remove-Item $schema, $migrations

Step 'Subscription (first copy of all rows, then live changes)'
$sql = Join-Path $env:TEMP 'examis-replica-sub.sql'
$conninfo = "host=127.0.0.1 port=15432 dbname=examis_production user=examis_replica password=$replicaPassword"
Set-Content -Path $sql -Encoding ASCII -Value "CREATE SUBSCRIPTION examis_sub CONNECTION '$conninfo' PUBLICATION examis_pub;"
try { & "$PgBin\psql.exe" @local -d $LocalDb -v ON_ERROR_STOP=1 -q -f $sql } finally { Remove-Item $sql }
if ($LASTEXITCODE -ne 0) { throw 'CREATE SUBSCRIPTION failed' }

Step 'Waiting for the first copy'
foreach ($i in 1..60) {
    $pending = & "$PgBin\psql.exe" @local -d $LocalDb -Atc "select count(*) from pg_subscription_rel where srsubstate <> 'r'"
    if ($pending -eq '0') { break }
    Start-Sleep -Seconds 3
}
& "$PgBin\psql.exe" @local -d $LocalDb -Atc "select 'tables still copying: ' || count(*) from pg_subscription_rel where srsubstate <> 'r'"
& "$PgBin\psql.exe" @local -d $LocalDb -Atc "select 'users=' || (select count(*) from users) || ' applications=' || (select count(*) from applications) || ' audit_events=' || (select count(*) from audit_events)"
Step "Done. pgAdmin: server 127.0.0.1:5432 (this PC), database $LocalDb."
