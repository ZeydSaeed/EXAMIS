# Keeps the SSH tunnel to the server's PostgreSQL open for the live copy:
# local 127.0.0.1:15432 -> server 127.0.0.1:5432, with the examis-tunnel key
# (which can forward to that port and do nothing else). Reconnects after a
# drop. Run hidden by the scheduled task "EXAMIS replica tunnel".
param(
    [string]$Key = "$env:USERPROFILE\.ssh\examis_replica_tunnel",
    [string]$Server = 'examis-tunnel@178.105.246.187',
    [int]$LocalPort = 15432
)
$log = Join-Path $PSScriptRoot '..\..\storage\logs\replica-tunnel.log'
while ($true) {
    & C:\Windows\System32\OpenSSH\ssh.exe -N -o IdentitiesOnly=yes -o BatchMode=yes -o ExitOnForwardFailure=yes `
        -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -i $Key -L "127.0.0.1:${LocalPort}:127.0.0.1:5432" $Server 2>&1 |
        ForEach-Object { Add-Content $log "$(Get-Date -Format o) $_" }
    Add-Content $log "$(Get-Date -Format o) tunnel closed (exit $LASTEXITCODE); reconnecting in 15 s"
    Start-Sleep -Seconds 15
}
