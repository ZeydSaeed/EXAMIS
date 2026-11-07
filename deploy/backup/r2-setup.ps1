# Sets up the off-server copy on Cloudflare R2 from one bootstrap API token
# the owner created and saved locally (never in the chat):
#
#   %USERPROFILE%\.examis-backup\cloudflare-bootstrap.txt
#     account_id=<account id>
#     token=<token with Account: Workers R2 Storage: Edit, User: API Tokens: Edit>
#
# Creates the bucket, a 30-day Bucket Lock rule, a 45-day expiry, and a token
# limited to that bucket (object read and write); turns it into S3 keys and
# writes the server's rclone.conf over SSH. Prints no secret. Afterwards the
# bootstrap token can be deleted in the dashboard.
param(
    [string]$Bootstrap = "$env:USERPROFILE\.examis-backup\cloudflare-bootstrap.txt",
    [string]$Bucket = 'examis-staging-backups',
    [string]$ServerKey = "$env:USERPROFILE\.ssh\examis_staging",
    [string]$Server = 'root@178.105.246.187'
)
$ErrorActionPreference = 'Stop'
$lines = Get-Content $Bootstrap
$account = (($lines | Where-Object { $_ -match '^account_id=' }) -replace '^account_id=', '').Trim()
$headers = @{ Authorization = 'Bearer ' + (($lines | Where-Object { $_ -match '^token=' }) -replace '^token=', '').Trim() }
$api = 'https://api.cloudflare.com/client/v4'
function Cf($method, $path, $body) {
    $json = if ($body) { $body | ConvertTo-Json -Depth 10 -Compress } else { $null }
    try { Invoke-RestMethod -Method $method -Uri "$api$path" -Headers $headers -ContentType 'application/json' -Body $json }
    catch {
        $detail = $_.ErrorDetails.Message
        if ($detail) { return ($detail | ConvertFrom-Json) }
        throw
    }
}
function Need($response, $what) {
    if (-not $response.success) { throw "$what failed: " + (($response.errors | ForEach-Object { "$($_.code) $($_.message)" }) -join '; ') }
    "ok  $what"
}

Need (Cf GET '/user/tokens/verify') 'bootstrap token is valid'
$created = Cf POST "/accounts/$account/r2/buckets" @{ name = $Bucket }
if (-not $created.success -and ($created.errors.code -notcontains 10004)) { Need $created 'create bucket' } else { "ok  bucket $Bucket" }
Need (Cf PUT "/accounts/$account/r2/buckets/$Bucket/lock" @{ rules = @(@{ id = 'retain-30-days'; enabled = $true; prefix = ''; condition = @{ type = 'Age'; maxAgeSeconds = 2592000 } }) }) 'bucket lock: 30 days'
Need (Cf PUT "/accounts/$account/r2/buckets/$Bucket/lifecycle" @{ rules = @(@{ id = 'expire-45-days'; enabled = $true; conditions = @{ prefix = '' }; deleteObjectsTransition = @{ condition = @{ type = 'Age'; maxAge = 3888000 } } }) }) 'lifecycle: delete after 45 days'

$groups = Cf GET '/user/tokens/permission_groups'
Need $groups 'permission groups' | Out-Null
$write = $groups.result | Where-Object { $_.name -eq 'Workers R2 Storage Bucket Item Write' }
if (-not $write) { throw 'permission group "Workers R2 Storage Bucket Item Write" not found' }
$token = Cf POST '/user/tokens' @{
    name = "$Bucket-server"
    policies = @(@{ effect = 'allow'; resources = @{ "com.cloudflare.edge.r2.bucket.$($account)_default_$Bucket" = '*' }; permission_groups = @(@{ id = $write.id }) })
}
Need $token 'bucket-only token (object read and write)'

# R2's S3 keys: the token id, and the SHA-256 of the token value.
$sha = [Security.Cryptography.SHA256]::Create()
$secret = -join ($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($token.result.value)) | ForEach-Object { $_.ToString('x2') })
$conf = Join-Path $env:TEMP ('rclone-' + [guid]::NewGuid() + '.conf')
@(
    '[examis-offsite]', 'type = s3', 'provider = Cloudflare',
    "access_key_id = $($token.result.id)", "secret_access_key = $secret",
    "endpoint = https://$account.r2.cloudflarestorage.com", 'acl = private', 'no_check_bucket = true'
) | Set-Content -Encoding ascii $conf
$secret = $null; $token = $null
try {
    & C:\Windows\System32\OpenSSH\scp.exe -q -o IdentitiesOnly=yes -o BatchMode=yes -i $ServerKey $conf "${Server}:/root/.config/rclone/rclone.conf.new"
    & C:\Windows\System32\OpenSSH\ssh.exe -o IdentitiesOnly=yes -o BatchMode=yes -i $ServerKey $Server "install -d -m 700 /root/.config/rclone && sed -i 's/\r$//' /root/.config/rclone/rclone.conf.new && chmod 600 /root/.config/rclone/rclone.conf.new && mv /root/.config/rclone/rclone.conf.new /root/.config/rclone/rclone.conf && echo 'ok  rclone.conf on the server (root, 600)'"
} finally { Remove-Item $conf -ErrorAction SilentlyContinue }
