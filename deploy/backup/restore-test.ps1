# EXAMIS restore drill on the owner's computer. Restores one downloaded backup
# set into a NEW local database (examis_restore_<stamp>) and a scratch folder,
# then proves it: checksums, row counts, document hashes and the app's own
# examis:verify-integrity. Never touches staging, sis or an existing database.
#
#   powershell -File restore-test.ps1 -SetDir <folder> [-KeyFile <age key>]
#
# Prints counts and timings only; the decrypted .env never reaches the disk.
param(
    [Parameter(Mandatory = $true)][string]$SetDir,
    [string]$KeyFile = "$env:USERPROFILE\.examis-backup\examis-backup.agekey",
    [string]$PgBin = 'D:\PostgreSQL\18\bin',
    [string]$AppDir
)
$ErrorActionPreference = 'Stop'
# Windows PowerShell 5.1 has no $PSScriptRoot in parameter defaults.
if (-not $AppDir) { $AppDir = (Resolve-Path (Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\..')).Path }
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'User') + ';' + [Environment]::GetEnvironmentVariable('Path', 'Machine')
$clock = [Diagnostics.Stopwatch]::StartNew()
$timings = [ordered]@{}
function Step($name) { $timings[$name] = [math]::Round($clock.Elapsed.TotalSeconds, 2) }
function Fail($message) { throw "restore drill FAILED: $message" }

$stamp = Split-Path $SetDir -Leaf
$run = Get-Date -Format 'yyyyMMddHHmmss'
$database = 'examis_restore_' + ($stamp -replace '[^0-9A-Za-z]', '').ToLower() + "_$run"
$work = Join-Path $env:TEMP "examis-restore-$stamp-$run"
if (Test-Path $work) { Fail "$work already exists" }
New-Item -ItemType Directory $work | Out-Null

# Local PostgreSQL credentials from the project's .env, never printed.
$envFile = Get-Content "$AppDir\.env"
function EnvValue($key) { (($envFile | Where-Object { $_ -match "^$key=" }) -replace "^$key=", '').Trim('"') }
$env:PGHOST = EnvValue 'DB_HOST'; $env:PGPORT = EnvValue 'DB_PORT'
$env:PGUSER = EnvValue 'DB_USERNAME'; $env:PGPASSWORD = EnvValue 'DB_PASSWORD'
$psql = Join-Path $PgBin 'psql.exe'

# 1. Checksums of what arrived.
foreach ($line in Get-Content "$SetDir\SHA256SUMS") {
    $hash, $name = $line -split '\s+\*?', 2
    if ((Get-FileHash "$SetDir\$name" -Algorithm SHA256).Hash -ne $hash.ToUpper()) { Fail "checksum of $name" }
}
Step 'checksums'

# 2. Database into a new local database.
$exists = & $psql -d postgres -Atc "select 1 from pg_database where datname = '$database'"
if ($exists) { Fail "database $database already exists" }
& $psql -d postgres -qc "create database $database" | Out-Null
$dump = Join-Path $work 'database.dump'
age --decrypt --identity $KeyFile --output $dump "$SetDir\database.dump.age"
Step 'database decrypted'
& (Join-Path $PgBin 'pg_restore.exe') --no-owner --no-privileges --exit-on-error --dbname=$database $dump
if ($LASTEXITCODE -ne 0) { Fail 'pg_restore' }
Remove-Item $dump
Step 'database restored'

$expected = @{}
foreach ($line in Get-Content "$SetDir\row-counts.txt") { $table, $count = $line -split ' '; $expected[$table] = [long]$count }
$mismatch = @()
foreach ($table in $expected.Keys) {
    $actual = [long](& $psql -d $database -Atc "select count(*) from public.""$table""")
    if ($actual -ne $expected[$table]) { $mismatch += "$table expected $($expected[$table]) got $actual" }
}
if ($mismatch) { Fail ("row counts: " + ($mismatch -join '; ')) }
Step 'row counts'

# 3. Applicant files, every hash.
$storage = Join-Path $work 'storage'
New-Item -ItemType Directory $storage | Out-Null
age --decrypt --identity $KeyFile --output (Join-Path $work 'files.tar') "$SetDir\files.tar.age"
tar -xf (Join-Path $work 'files.tar') -C $storage
Remove-Item (Join-Path $work 'files.tar')
$sums = age --decrypt --identity $KeyFile "$SetDir\files.sha256.age"
$badFiles = 0
foreach ($line in $sums) {
    $hash, $name = $line -split '\s+', 2
    $path = Join-Path (Join-Path $storage 'app') ($name -replace '^\./', '')
    if (-not (Test-Path -LiteralPath $path) -or (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $hash.ToUpper()) { $badFiles++ }
}
if ($badFiles) { Fail "$badFiles applicant files differ" }
Step 'files restored and hashed'

# 4. .env and .db-owner: decrypt to memory, check the archive lists both.
$secretNames = age --decrypt --identity $KeyFile "$SetDir\secrets.tar.age" | Out-String
if ($secretNames -notmatch '\.env' -or $secretNames -notmatch '\.db-owner') { Fail 'secrets archive' }
Step 'secrets decrypt'

# 5. The application's own consistency check against the restored copy.
foreach ($dir in 'framework\cache\data', 'framework\views', 'framework\sessions', 'logs') { New-Item -ItemType Directory -Force (Join-Path $storage $dir) | Out-Null }
$env:DB_CONNECTION = 'pgsql'; $env:DB_DATABASE = $database; $env:LARAVEL_STORAGE_PATH = $storage
$env:CACHE_STORE = 'array'; $env:QUEUE_CONNECTION = 'sync'; $env:SESSION_DRIVER = 'array'
Push-Location $AppDir
try { $integrity = php artisan examis:verify-integrity --hashes --json 2>&1 | Out-String; $integrityExit = $LASTEXITCODE } finally { Pop-Location }
Remove-Item Env:DB_DATABASE, Env:LARAVEL_STORAGE_PATH, Env:DB_CONNECTION, Env:CACHE_STORE, Env:QUEUE_CONNECTION, Env:SESSION_DRIVER, Env:PGPASSWORD
if ($integrityExit -ne 0) { Fail "examis:verify-integrity exit $integrityExit" }
Step 'integrity check'

[ordered]@{
    set = $stamp; database = $database; tables = $expected.Count
    rows = ($expected.Values | Measure-Object -Sum).Sum
    files = @($sums).Count; integrity = ($integrity | ConvertFrom-Json -ErrorAction SilentlyContinue)
    seconds = $timings; total_seconds = [math]::Round($clock.Elapsed.TotalSeconds, 2)
} | ConvertTo-Json -Depth 6
