@echo off
rem Menjalankan Git Backup Kos Aron secara manual (bisa di-double-click).
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0git-auto-backup.ps1"
echo.
echo Kode keluar: %ERRORLEVEL%  (0 = berhasil, 1 = gagal, 2 = sebagian file sensitif dilewati)
echo Log lengkap: %~dp0..\..\logs\git-backup.log
pause
