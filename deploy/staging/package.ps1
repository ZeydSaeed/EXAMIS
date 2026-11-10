# EXAMIS staging: build a release tarball on this PC. Uploads nothing.
#
#   powershell -ExecutionPolicy Bypass -File deploy\staging\package.ps1
#
# The tarball holds the committed code (git archive HEAD) plus the built
# assets (public/build), which are plain JS and CSS and run on ARM64 and x86
# alike. Composer packages are installed on the server by release.sh.
$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $root

if (git status --porcelain) {
    throw 'Uncommitted changes: commit or stash them so the release matches a commit.'
}

$sha = (git rev-parse --short HEAD).Trim()
$out = Join-Path $root "storage\releases"
$work = Join-Path $env:TEMP "examis-release-$sha"
$tarball = Join-Path $out "examis-$sha.tgz"

Write-Host "[package] Building assets for $sha"
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }

Write-Host '[package] Exporting the committed tree'
if (Test-Path $work) { Remove-Item -Recurse -Force $work }
New-Item -ItemType Directory -Force -Path $work, $out | Out-Null
git archive --format=tar --output "$work.tar" HEAD
if ($LASTEXITCODE -ne 0) { throw 'git archive failed' }
tar -xf "$work.tar" -C $work
Remove-Item "$work.tar"

Copy-Item -Recurse (Join-Path $root 'public\build') (Join-Path $work 'public\build')

Write-Host '[package] Compressing'
tar -czf $tarball -C $work .
Remove-Item -Recurse -Force $work

$size = [math]::Round((Get-Item $tarball).Length / 1MB, 1)
Write-Host "[package] Ready: $tarball ($size MB)"
Write-Host '[package] Upload and install only after approval, for example:'
Write-Host "  scp `"$tarball`" examis@<SERVER_IP>:/tmp/"
Write-Host "  ssh examis@<SERVER_IP> bash /srv/examis/deploy/release.sh /tmp/examis-$sha.tgz"
