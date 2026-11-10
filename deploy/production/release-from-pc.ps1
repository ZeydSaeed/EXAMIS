# Installs one EXAMIS release on the production server from this PC: uploads
# the tarball package.ps1 built, runs release.sh as "examis", prints the
# result and tags the commit (release-<UTC time>) on GitHub. The owner allows
# Claude to run exactly this script (user settings, permission rule), so it
# accepts nothing but a release built here.
#
#   powershell -NoProfile -ExecutionPolicy Bypass -File deploy\production\release-from-pc.ps1 <sha>
#
# Releases come from main, which CI checks on every pull request. Only in an
# emergency, -FromBranch releases a pushed commit that is not on main yet;
# merge it to main afterwards. Rollback: point /srv/examis/current at the
# previous release (deploy/production/README.md).
param(
    [Parameter(Mandatory)][ValidatePattern('^[0-9a-f]{7,12}$')][string]$Sha,
    [switch]$FromBranch,
    [string]$ServerIp = '178.105.246.187',
    [string]$RootKey = "$env:USERPROFILE\.ssh\examis_staging"
)
$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$tarball = Join-Path $root "storage\releases\examis-$Sha.tgz"
if (-not (Test-Path $tarball)) { throw "No package for $Sha; build it first with deploy\staging\package.ps1" }

# Only a commit that exists here and is on GitHub: on main, or, with
# -FromBranch, on any pushed branch.
git -C $root fetch -q origin
$full = (git -C $root rev-parse --verify "$Sha^{commit}" 2>$null)
if (-not $full) { throw "$Sha is not a commit in this repository" }
git -C $root merge-base --is-ancestor $full origin/main
$onMain = $LASTEXITCODE -eq 0
if (-not $onMain) {
    if (-not $FromBranch) { throw "$Sha is not on main; merge its pull request first (or, in an emergency only, add -FromBranch)" }
    if (-not (git -C $root branch -r --contains $full 2>$null)) { throw "$Sha is not pushed to GitHub; push it before releasing" }
    "[release-from-pc] WARNING: $Sha is not on main (emergency release); merge it to main afterwards"
}

$ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
$scp = 'C:\Windows\System32\OpenSSH\scp.exe'
$opts = @('-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-i', $RootKey)
$name = "examis-$Sha.tgz"

"[release-from-pc] Uploading $name"
& $scp @opts -q $tarball "root@${ServerIp}:/tmp/$name"
if ($LASTEXITCODE -ne 0) { throw 'upload failed' }

"[release-from-pc] Installing"
# pipefail: the exit code is release.sh's, not that of the filter after it.
& $ssh @opts "root@$ServerIp" "set -o pipefail; chown examis:examis /tmp/$name && cd /tmp && runuser -u examis -- bash /srv/examis/deploy/release.sh /tmp/$name 2>&1 | grep -E '^.release.|Nothing to migrate|Migrat|health/ready|ERROR'; rc=`$?; rm -f /tmp/$name; readlink -f /srv/examis/current; exit `$rc"
if ($LASTEXITCODE -ne 0) { throw 'release.sh failed; the previous release is still in /srv/examis/releases' }

# Each release is a tag on GitHub, so what runs is always known.
$tag = 'release-' + (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmm')
git -C $root tag -a $tag $full -m "Production release of $Sha$(if (-not $onMain) { ' (emergency, not on main)' })"
git -C $root push -q origin $tag
if ($LASTEXITCODE -ne 0) { "[release-from-pc] WARNING: released, but pushing tag $tag failed; push it by hand" }
"[release-from-pc] Done: $Sha ($tag)"
