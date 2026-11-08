# Restores one backup set from this computer into the LIVE site database.
# The owner runs it (database\backup has a shortcut):
#
#   powershell -ExecutionPolicy Bypass -File deploy\backup\restore-production.ps1 [-WithFiles] [-CheckOnly]
#
# 1. Pick a set from database\backup (newest first) and check its SHA256SUMS.
# 2. Decrypt the database with the age key, here only (the server cannot).
# 3. Rehearsal: restore it into a scratch copy of the CURRENT schema on this
#    computer and compare every row count with the set's row-counts.txt. A
#    set that does not fit stops here. -CheckOnly stops after this step.
# 4. Typed confirmation.
# 5. Safety copy: a manual backup of the site as it is now, pulled here.
# 6. On the server (restore-on-server.sh, as examis): maintenance mode, all
#    rows replaced in one transaction, site back. -WithFiles also writes the
#    applicant files back (daily and manual sets only).
# 7. Row counts on the server compared with the set.
# The live copy on this computer (examis_replica) follows by itself.
param(
    [string]$SetDir,
    [switch]$WithFiles,
    [switch]$CheckOnly,
    [string]$KeyFile = "$env:USERPROFILE\.examis-backup\examis-backup.agekey",
    [string]$PgBin = 'D:\PostgreSQL\18\bin',
    [string]$Server = 'examis@178.105.246.187',
    [string]$Key = "$env:USERPROFILE\.ssh\examis_staging"
)
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = (Resolve-Path (Join-Path $here '..\..')).Path
$backups = Join-Path $root 'database\backup'
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'User') + ';' + [Environment]::GetEnvironmentVariable('Path', 'Machine')
$ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
$scp = 'C:\Windows\System32\OpenSSH\scp.exe'
# examis has root's authorized key (provision.sh); there is no default key here.
$opts = @('-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-i', $Key)
$run = Get-Date -Format 'yyyyMMddHHmmss'
$work = Join-Path $env:TEMP "examis-restore-$run"
$logFile = Join-Path $backups 'restore.log'
function Say($text, $color = 'Cyan') { Write-Host "`n[restore] $text" -ForegroundColor $color; Add-Content $logFile "$(Get-Date -Format o) $text" }
function Fail($text) { Say "STOPPED: $text" 'Red'; throw $text }

# Local PostgreSQL from the project's .env, never printed.
$envFile = Get-Content (Join-Path $root '.env')
function EnvValue($key) { (($envFile | Where-Object { $_ -match "^$key=" }) -replace "^$key=", '').Trim('"') }
$env:PGHOST = '127.0.0.1'; $env:PGPORT = EnvValue 'DB_PORT'
$env:PGUSER = EnvValue 'DB_USERNAME'; $env:PGPASSWORD = EnvValue 'DB_PASSWORD'
$psql = Join-Path $PgBin 'psql.exe'

# 1. The set.
if (-not $SetDir) {
    $sets = foreach ($kind in 'manual', 'daily', '6h') {
        Get-ChildItem (Join-Path $backups $kind) -Directory -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -match '^\d{8}T\d{6}Z$' } | ForEach-Object { [pscustomobject]@{ Kind = $kind; Name = $_.Name; Path = $_.FullName } }
    }
    $sets = @($sets | Sort-Object Name -Descending)
    if ($sets.Count -eq 0) { Fail "no backup sets in $backups" }
    Write-Host "`nBackup sets on this computer (times are UTC; Baghdad = UTC + 3):"
    for ($i = 0; $i -lt $sets.Count; $i++) { Write-Host ('  {0,2}) {1}  {2}' -f ($i + 1), $sets[$i].Name, $sets[$i].Kind) }
    $pick = Read-Host "`nNumber of the set to restore"
    if ($pick -notmatch '^\d+$' -or [int]$pick -lt 1 -or [int]$pick -gt $sets.Count) { Fail 'no set chosen' }
    $SetDir = $sets[[int]$pick - 1].Path
}
$stamp = Split-Path $SetDir -Leaf
Say "Set $SetDir"
foreach ($line in Get-Content (Join-Path $SetDir 'SHA256SUMS')) {
    $hash, $name = $line -split '\s+\*?', 2
    if ((Get-FileHash (Join-Path $SetDir $name) -Algorithm SHA256).Hash -ne $hash.ToUpper()) { Fail "checksum of $name" }
}
if ($WithFiles -and -not (Test-Path (Join-Path $SetDir 'files.tar.age'))) { Fail 'this set has no applicant files (only daily and manual sets do)' }
$expected = [ordered]@{}
foreach ($line in Get-Content (Join-Path $SetDir 'row-counts.txt')) { $table, $count = $line -split ' '; if ($table -ne 'migrations') { $expected[$table] = [long]$count } }

New-Item -ItemType Directory $work | Out-Null
$scratch = "examis_restorecheck_$run"
try {
    # 2. Decrypt.
    $dump = Join-Path $work 'database.dump'
    age --decrypt --identity $KeyFile --output $dump (Join-Path $SetDir 'database.dump.age')
    if ($LASTEXITCODE -ne 0) { Fail 'could not decrypt the database (age key?)' }

    # 3. Rehearsal on a scratch copy of the current schema (from the live copy).
    Say 'Rehearsal on this computer'
    & $psql -d postgres -Atqc "create database $scratch"
    & (Join-Path $PgBin 'pg_dump.exe') -d examis_replica --schema-only --no-owner --no-privileges --no-subscriptions --no-publications -f (Join-Path $work 'schema.sql')
    if ($LASTEXITCODE -ne 0) { Fail 'could not read the current schema from examis_replica' }
    & $psql -d $scratch -v ON_ERROR_STOP=1 -q -f (Join-Path $work 'schema.sql') | Out-Null
    # Parents first (pg_restore follows the list's order), then sequences; as
    # restore-on-server.sh does.
    $full = & (Join-Path $PgBin 'pg_restore.exe') -l $dump
    $order = & $psql -d $scratch -Atc "with recursive fk as ( select ch.relname::text as child, pa.relname::text as parent from pg_constraint k join pg_class ch on ch.oid = k.conrelid join pg_class pa on pa.oid = k.confrelid where k.contype = 'f' and k.conrelid <> k.confrelid and ch.relnamespace = 'public'::regnamespace ), depth(t, d) as ( select relname::text, 0 from pg_class where relkind = 'r' and relnamespace = 'public'::regnamespace union all select fk.child, depth.d + 1 from fk join depth on fk.parent = depth.t where depth.d < 50 ) select t from depth group by t order by max(d), t"
    $list = @(foreach ($table in $order) {
            if ($table -ne 'migrations') { $full | Where-Object { $_ -match " TABLE DATA public $table " } }
        }) + @($full | Where-Object { $_ -match ' SEQUENCE SET public ' -and $_ -notmatch ' public migrations_id_seq ' })
    Set-Content -Path (Join-Path $work 'restore.list') -Value $list -Encoding ASCII
    $tables = & $psql -d $scratch -Atc "select string_agg(format('public.%I', tablename), ', ') from pg_tables where schemaname = 'public' and tablename <> 'migrations'"
    Set-Content -Path (Join-Path $work 'truncate.sql') -Encoding ASCII -Value "TRUNCATE $tables RESTART IDENTITY CASCADE;"
    & (Join-Path $PgBin 'pg_restore.exe') --data-only --no-owner --no-privileges -L (Join-Path $work 'restore.list') -f (Join-Path $work 'data.sql') $dump
    # -1 wraps both files in one transaction, as on the server.
    & $psql -d $scratch -v ON_ERROR_STOP=1 -q -1 -f (Join-Path $work 'truncate.sql') -f (Join-Path $work 'data.sql') | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail 'this backup does not fit the current schema; nothing was changed' }
    $bad = @()
    foreach ($table in $expected.Keys) {
        $actual = [long](& $psql -d $scratch -Atc "select count(*) from public.""$table""")
        if ($actual -ne $expected[$table]) { $bad += "$table expected $($expected[$table]) got $actual" }
    }
    if ($bad) { Fail ('rehearsal row counts: ' + ($bad -join '; ')) }
    Say ("Rehearsal OK: users=$($expected['users']) applications=$($expected['applications']) documents=$($expected['application_documents'])") 'Green'
    if ($CheckOnly) { Say 'CheckOnly: the site was not touched.' 'Green'; return }

    # 4. Confirmation.
    Write-Host "`nThis REPLACES every row of the live site database with the backup of $stamp (UTC)." -ForegroundColor Yellow
    Write-Host 'Everything added or changed on the site after that time is lost (a safety copy is taken first).' -ForegroundColor Yellow
    if ($WithFiles) { Write-Host 'Applicant files are written back too.' -ForegroundColor Yellow }
    if ((Read-Host 'Type RESTORE to continue') -cne 'RESTORE') { Fail 'not confirmed; nothing was changed' }

    # 5. Safety copy of the site as it is now.
    Say 'Safety backup of the current site'
    $before = & $ssh @opts $Server 'cat /srv/examis/shared/storage/app/backup-status/status.json' | Out-String
    & $ssh @opts $Server 'd=/srv/examis/shared/storage/app/backup-status; [ -e $d/request ] || date -u +%FT%TZ > $d/request'
    $done = $false
    foreach ($i in 1..100) {
        Start-Sleep -Seconds 3
        $now = & $ssh @opts $Server 'd=/srv/examis/shared/storage/app/backup-status; [ -e $d/request ] && echo waiting || cat $d/status.json' | Out-String
        if ($now -notmatch '^waiting' -and $now -ne $before) { $done = $true; break }
    }
    if (-not $done -or $now -notmatch '"kind":"manual","stamp":"\d{8}T\d{6}Z","result":"ok"') { Fail 'the safety backup did not finish; nothing was changed' }
    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $here 'pull-latest.ps1') -Kind manual
    Say 'Safety backup taken and pulled to database\backup\manual' 'Green'

    # 6. Restore on the server.
    $remote = "/tmp/examis-restore-$run"
    & $ssh @opts $Server "mkdir -m 700 $remote"
    $upload = @($dump, (Join-Path $here 'restore-on-server.sh'))
    if ($WithFiles) {
        age --decrypt --identity $KeyFile --output (Join-Path $work 'files.tar') (Join-Path $SetDir 'files.tar.age')
        $upload += (Join-Path $work 'files.tar')
    }
    & $scp -q @opts @upload "${Server}:$remote/"
    if ($LASTEXITCODE -ne 0) { Fail 'upload failed; nothing was changed' }
    Say 'Restoring on the server'
    $output = & $ssh @opts $Server "sed -i 's/\r$//' $remote/restore-on-server.sh && mv $remote/restore-on-server.sh /tmp/examis-restore-$run.sh && bash /tmp/examis-restore-$run.sh $remote; code=`$?; rm -f /tmp/examis-restore-$run.sh; exit `$code"
    $code = $LASTEXITCODE
    $output | ForEach-Object { Write-Host $_ }
    if ($code -ne 0) { Fail 'the server refused the restore; nothing was changed in the database' }

    # 7. Compare.
    $bad = @()
    $counts = @{}
    foreach ($line in $output) { if ($line -match '^([a-z_]+) (\d+)$') { $counts[$Matches[1]] = [long]$Matches[2] } }
    foreach ($table in $expected.Keys) {
        if ($table -in 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'staff_sessions') { continue }
        if ($counts[$table] -ne $expected[$table]) { $bad += "$table expected $($expected[$table]) got $($counts[$table])" }
    }
    if ($bad) { Say ('Restored, but some counts differ (activity since?): ' + ($bad -join '; ')) 'Yellow' }
    else { Say "Done: the site now holds the backup of $stamp (UTC)." 'Green' }
}
finally {
    & $psql -d postgres -Atqc "drop database if exists $scratch with (force)" 2>$null
    if (Test-Path $work) { Remove-Item -Recurse -Force $work }
}
