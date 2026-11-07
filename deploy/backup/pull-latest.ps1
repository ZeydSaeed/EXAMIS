# Pulls the newest encrypted daily backup set from staging to this computer,
# with the examis-pull key (which can do nothing else), checks it against its
# SHA256SUMS and keeps the newest $Keep sets. Run daily by the scheduled task
# "EXAMIS backup pull" (register-pull-task.ps1). Appends one line to pull.log.
param(
    [string]$Dest = "$env:USERPROFILE\EXAMIS-backups\daily",
    [int]$Keep = 14,
    [string]$Key = "$env:USERPROFILE\.ssh\examis_backup_pull",
    [string]$Server = 'examis-pull@178.105.246.187'
)
$ErrorActionPreference = 'Stop'
New-Item -ItemType Directory -Force $Dest | Out-Null
$log = Join-Path $Dest 'pull.log'
$tar = Join-Path $Dest ('incoming-' + (Get-Date -Format 'yyyyMMddHHmmss') + '.tar')
try {
    # cmd keeps the tar stream binary; PowerShell redirection would re-encode it.
    cmd /c "`"C:\Windows\System32\OpenSSH\ssh.exe`" -o IdentitiesOnly=yes -o BatchMode=yes -i `"$Key`" $Server > `"$tar`""
    if ($LASTEXITCODE -ne 0 -or (Get-Item $tar).Length -lt 1024) { throw "ssh exit $LASTEXITCODE" }
    $set = (tar -tf $tar | Select-Object -First 1).TrimEnd('/')
    if (Test-Path (Join-Path $Dest $set)) { Remove-Item $tar; Add-Content $log "$(Get-Date -Format o) $set already here"; exit 0 }
    tar -xf $tar -C $Dest
    Remove-Item $tar
    foreach ($line in Get-Content (Join-Path $Dest "$set\SHA256SUMS")) {
        $hash, $name = $line -split '\s+\*?', 2
        if ((Get-FileHash (Join-Path $Dest "$set\$name") -Algorithm SHA256).Hash -ne $hash.ToUpper()) { throw "checksum of $set\$name" }
    }
    Get-ChildItem $Dest -Directory | Sort-Object Name -Descending | Select-Object -Skip $Keep | Remove-Item -Recurse
    Add-Content $log "$(Get-Date -Format o) $set ok"
} catch {
    Add-Content $log "$(Get-Date -Format o) FAILED $_"
    if (Test-Path $tar) { Remove-Item $tar }
    exit 1
}
