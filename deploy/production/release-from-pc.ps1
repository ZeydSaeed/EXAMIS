# Installs one EXAMIS release on the production server from this PC: uploads
# the tarball package.ps1 built, runs release.sh as "examis", and prints the
# result. The owner allows Claude to run exactly this script (user settings,
# permission rule), so it accepts nothing but a release built here.
#
#   powershell -NoProfile -ExecutionPolicy Bypass -File deploy\production\release-from-pc.ps1 <sha>
#
# Rollback, if needed: point /srv/examis/current at the previous release
# (deploy/production/README.md).
param(
    [Parameter(Mandatory)][ValidatePattern('^[0-9a-f]{7,12}$')][string]$Sha,
    [string]$ServerIp = '178.105.246.187',
    [string]$RootKey = "$env:USERPROFILE\.ssh\examis_staging"
)
$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$tarball = Join-Path $root "storage\releases\examis-$Sha.tgz"
if (-not (Test-Path $tarball)) { throw "No package for $Sha; build it first with deploy\staging\package.ps1" }

# Only a release of a commit that exists here and has been pushed.
$full = (git -C $root rev-parse --verify "$Sha^{commit}" 2>$null)
if (-not $full) { throw "$Sha is not a commit in this repository" }
if (-not (git -C $root branch -r --contains $full 2>$null)) { throw "$Sha is not pushed to GitHub; push it before releasing" }

$ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
$scp = 'C:\Windows\System32\OpenSSH\scp.exe'
$opts = @('-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-i', $RootKey)
$name = "examis-$Sha.tgz"

"[release-from-pc] Uploading $name"
& $scp @opts -q $tarball "root@${ServerIp}:/tmp/$name"
if ($LASTEXITCODE -ne 0) { throw 'upload failed' }

"[release-from-pc] Installing"
& $ssh @opts "root@$ServerIp" "chown examis:examis /tmp/$name && cd /tmp && runuser -u examis -- bash /srv/examis/deploy/release.sh /tmp/$name 2>&1 | grep -E '^.release.|Nothing to migrate|Migrat|health/ready|ERROR'; rm -f /tmp/$name; readlink -f /srv/examis/current"
if ($LASTEXITCODE -ne 0) { throw 'release.sh failed; the previous release is still in /srv/examis/releases' }
"[release-from-pc] Done: $Sha"
