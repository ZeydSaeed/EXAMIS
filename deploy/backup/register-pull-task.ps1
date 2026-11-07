# Registers the daily "EXAMIS backup pull" task for the signed-in user (no
# stored password; it runs while the user is signed in, and catches up after a
# missed run). The server's daily set is ready by 03:20 UTC.
$ErrorActionPreference = 'Stop'
$script = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'pull-latest.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`""
$trigger = New-ScheduledTaskTrigger -Daily -At '09:00'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -MultipleInstances IgnoreNew
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName 'EXAMIS backup pull' -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Select-Object TaskName, State
