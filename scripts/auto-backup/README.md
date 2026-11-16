# Git Backup Otomatis - Kos Aron

Commit dan push otomatis perubahan source code ke GitHub setiap 2 jam
menggunakan PowerShell + Windows Task Scheduler. Tidak mengubah kode aplikasi.

## File

| File | Fungsi |
|---|---|
| `git-auto-backup.ps1` | Script backup (cek status, saring file sensitif, commit, push) |
| `register-backup-task.ps1` | Mendaftarkan / menghapus task di Task Scheduler |
| `backup-manual.cmd` | Menjalankan backup manual (bisa di-double-click) |
| `../../logs/git-backup.log` | Log backup (diabaikan Git) |

## Menjalankan backup manual

Double-click `backup-manual.cmd`, atau dari PowerShell di root repository:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\auto-backup\git-auto-backup.ps1
```

Kode keluar: `0` berhasil / tidak ada perubahan, `1` gagal, `2` sebagian file sensitif dilewati.

## Task Scheduler

```powershell
# Daftarkan (setiap hari, tiap 2 jam: 00:00, 02:00, 04:00, ...)
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\auto-backup\register-backup-task.ps1

# Jalankan task sekarang (uji lewat Task Scheduler)
Start-ScheduledTask -TaskName 'KosAron Git Backup'

# Lihat status, hasil terakhir (0 = berhasil), dan jadwal berikutnya
Get-ScheduledTaskInfo -TaskName 'KosAron Git Backup'

# Hapus task
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\auto-backup\register-backup-task.ps1 -Remove
```

Pengaturan task: tidak menjalankan dua instance bersamaan, jadwal yang terlewat
dijalankan saat komputer tersedia kembali, batas waktu 30 menit, berjalan sebagai
user saat ini **hanya ketika user sedang login** (agar login GitHub tersimpan dapat dipakai).

## Memeriksa hasil backup

```powershell
Get-Content logs\git-backup.log -Tail 20        # log terakhir
git log --oneline -5                            # commit terakhir
git status -sb                                  # "## main...origin/main" tanpa [ahead] = sudah ter-push
```

## Keamanan

- File yang **tidak pernah** di-commit oleh script: `.env`, `*.sql`/dump, `*.local.php`,
  kunci (`*.pem`, `*.key`, SSH key), `*.csv`/`*.xlsx` (ekspor data penghuni), `uploads/`,
  folder backup, log, serta file > 10 MB.
- Isi file teks dipindai: token GitHub/API key, private key, password yang tertulis
  di kode, dan nomor mirip NIK. File seperti itu dilewati dan dicatat di log (nilai
  rahasianya tidak ditulis ke log).
- Jika file sensitif sudah di-stage manual, backup dibatalkan.
- Tidak pernah: force push, reset, rebase, stash, pull otomatis, atau menghapus file.
- Jika remote punya commit baru, push dihentikan. Selesaikan manual (`git pull`),
  lalu backup berikutnya akan mem-push commit yang tertunda.
- Login GitHub memakai Git Credential Manager (tersimpan di Windows Credential Manager),
  tidak ada token di script maupun repository.
