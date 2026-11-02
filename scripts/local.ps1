# Runs both portals on this computer against the test database only.
# Nothing here reaches the EXAMIS development database or the network: the
# server binds to 127.0.0.1, and `fresh` wipes examis_testing.
#
#   .\scripts\local.ps1 fresh                 Rebuild examis_testing with demo data
#   .\scripts\local.ps1 serve                 Serve http://students.examis.test:8000
#                                             and http://staff.examis.test:8000
#   .\scripts\local.ps1 artisan <command...>  Run artisan on examis_testing
#
# Add to C:\Windows\System32\drivers\etc\hosts first (as administrator):
#   127.0.0.1 students.examis.test
#   127.0.0.1 staff.examis.test

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidateSet('fresh', 'serve', 'artisan')]
    [string] $Action,

    [Parameter(Position = 1, ValueFromRemainingArguments = $true)]
    [string[]] $Arguments = @()
)

$ErrorActionPreference = 'Stop'

$port = 8000

$env:DB_CONNECTION = 'pgsql'
$env:DB_DATABASE = 'examis_testing'
$env:SESSION_DRIVER = 'database'
$env:APP_URL = "http://students.examis.test:$port"

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

switch ($Action) {
    'fresh' {
        php artisan migrate:fresh --seed --force
    }
    'serve' {
        Write-Host "Students: http://students.examis.test:$port"
        Write-Host "Staff:    http://staff.examis.test:$port"
        Set-Location (Join-Path $root 'public')
        php -S "127.0.0.1:$port" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
    }
    'artisan' {
        php artisan @Arguments
    }
}
