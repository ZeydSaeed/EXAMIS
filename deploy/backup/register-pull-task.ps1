# Registers the backup pull tasks for the signed-in user (no stored password;
# they run while the user is signed in, and catch up after a missed run). Sets
# land in database\backup\<kind> of this repository (ignored by git).
#   "EXAMIS backup pull 6h":    35 minutes after each 6-hourly set (server
#                               00/06/12/18 UTC), also any new manual set.
#   "EXAMIS backup pull daily": after the daily set (server 03:15 UTC).
# Times are UTC converted to this computer's time zone.
$ErrorActionPreference = 'Stop'
$script = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'pull-latest.ps1'
$run = { param($kind) New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`" -Kind $kind" }
$at = { param($utcHour, $minute) [DateTime]::SpecifyKind([DateTime]::UtcNow.Date.AddHours($utcHour).AddMinutes($minute), 'Utc').ToLocalTime() }
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -MultipleInstances IgnoreNew
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited

$sixHourly = 0, 6, 12, 18 | ForEach-Object { New-ScheduledTaskTrigger -Daily -At (& $at $_ 35) }
Register-ScheduledTask -TaskName 'EXAMIS backup pull 6h' -Action (& $run '6h'), (& $run 'manual') -Trigger $sixHourly -Settings $settings -Principal $principal -Force | Select-Object TaskName, State
Register-ScheduledTask -TaskName 'EXAMIS backup pull daily' -Action (& $run 'daily') -Trigger (New-ScheduledTaskTrigger -Daily -At (& $at 3 50)) -Settings $settings -Principal $principal -Force | Select-Object TaskName, State

# The single daily task of the first version, if it was ever registered.
Unregister-ScheduledTask -TaskName 'EXAMIS backup pull' -Confirm:$false -ErrorAction SilentlyContinue
