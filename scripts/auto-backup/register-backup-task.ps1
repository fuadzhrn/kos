<#
.SYNOPSIS
    Mendaftarkan (atau menghapus) task Windows Task Scheduler untuk Git Backup Kos Aron.

.DESCRIPTION
    Task berjalan setiap hari, mulai pukul 00:00, berulang setiap 2 jam.
    - Tidak menjalankan beberapa instance bersamaan (MultipleInstances = IgnoreNew).
    - Jadwal yang terlewat (komputer mati/tidur) dijalankan saat komputer tersedia (StartWhenAvailable).
    - Berjalan sebagai user saat ini, hanya ketika user sedang login, agar Git Credential
      Manager dapat memakai login GitHub yang sudah tersimpan (tanpa menyimpan password di task).
    Tidak memerlukan hak Administrator.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\auto-backup\register-backup-task.ps1
.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\auto-backup\register-backup-task.ps1 -Remove
#>
[CmdletBinding()]
param(
    [string]$TaskName = 'KosAron Git Backup',
    [int]$IntervalHours = 2,
    [switch]$Remove
)

$ErrorActionPreference = 'Stop'

if ($Remove) {
    if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
        Write-Host "Task '$TaskName' dihapus."
    } else {
        Write-Host "Task '$TaskName' tidak ditemukan."
    }
    return
}

$script = Join-Path $PSScriptRoot 'git-auto-backup.ps1'
if (-not (Test-Path $script)) { throw "Script tidak ditemukan: $script" }
$repo = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))

$action = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`"" `
    -WorkingDirectory $repo

# Harian pukul 00:00, diulang setiap $IntervalHours jam selama 1 hari -> 00:00, 02:00, 04:00, ...
$trigger = New-ScheduledTaskTrigger -Daily -At '00:00'
$ulang   = New-ScheduledTaskTrigger -Once -At '00:00' `
    -RepetitionInterval (New-TimeSpan -Hours $IntervalHours) `
    -RepetitionDuration (New-TimeSpan -Days 1)
$trigger.Repetition = $ulang.Repetition

$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

$principal = New-ScheduledTaskPrincipal `
    -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) `
    -LogonType Interactive `
    -RunLevel Limited

Register-ScheduledTask -TaskName $TaskName `
    -Description "Commit & push otomatis project Kos Aron setiap $IntervalHours jam. Log: $repo\logs\git-backup.log" `
    -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null

$info = Get-ScheduledTaskInfo -TaskName $TaskName
Write-Host "Task '$TaskName' terdaftar."
Write-Host "Jadwal berikutnya : $($info.NextRunTime)"
Write-Host "Script            : $script"
Write-Host "Log               : $repo\logs\git-backup.log"
