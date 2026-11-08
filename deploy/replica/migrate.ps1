# After a release whose migrations change tables, bring the local copy's
# schema up to date so replication of those tables continues (it waits, and
# retries by itself, until then; no change is lost). Runs this repository's
# migrations against examis_replica, which holds the server's migration list.
#
#   powershell -ExecutionPolicy Bypass -File deploy\replica\migrate.ps1
param([string]$LocalDb = 'examis_replica')
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..\..'))
$env:DB_CONNECTION = 'pgsql'
$env:DB_DATABASE = $LocalDb
php artisan migrate --force
