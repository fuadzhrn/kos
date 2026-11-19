# Tahap 9 · Pengingat tagihan dan scheduler

Generator membaca tagihan yang dibuat admin. Acuannya tanggal kalender Asia/Jakarta (WIB). H-7 dan H-3 dibuat tepat pada hari tersebut. Pengingat terlambat dibuat pada eksekusi pertama setelah jatuh tempo selama tagihan masih terutang. Jika komputer mati pada H-7, eksekusi berikutnya memeriksa kondisi hari itu. Informasi lunas tersimpan bersama transaksi pembayaran sah. CLI juga melengkapi informasi lunas untuk pembayaran lama.

Halaman penghuni hanya menampilkan notifikasi untuk tagihan miliknya. Pembayaran yang dibatalkan membuat informasi lunas lama berhenti ditampilkan. Pembayaran pengganti mendapat kunci peristiwa sendiri. Pengulangan generator mempertahankan tanda baca dan waktu baca pertama.

## Persiapan database

Untuk database dari Tahap 8, pilih database aplikasi di klien MySQL lalu jalankan `database/migrasi-09-notifikasi.sql` satu kali sebelum mengaktifkan kode baru. Instalasi baru sudah membawa perubahan dalam `database/schema.sql`. Migrasi mengubah indeks unik menjadi kombinasi tagihan, tipe, dan peristiwa. Baris lama serta status bacanya dipertahankan. Database pengembangan lokal sudah dimigrasikan pada 19 November 2026.

## Windows Task Scheduler

1. Pastikan MySQL berjalan dan akun Windows yang menjalankan tugas dapat membaca aplikasi serta konfigurasi database lokal.
2. Buat tugas bernama **Kos Aron Pengingat Tagihan**. Pilih pemicu harian. Contoh jam 09.00 pada komputer berzona waktu Singapura sama dengan 08.00 WIB.
3. Pilih aksi **Start a program**. Untuk instalasi Laragon yang diuji, isi kolom berikut.

| Kolom | Nilai |
|---|---|
| Program/script | `D:\laragon\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe` |
| Add arguments | `"D:\laragon\laragon\www\kos-proposal\kos-aron\database\buat-notifikasi.php"` |
| Start in | `D:\laragon\laragon\www\kos-proposal\kos-aron` |

Aktifkan opsi menjalankan tugas setelah jadwal yang terlewat jika dibutuhkan. Gunakan **Run** untuk uji awal, kemudian periksa **History**, waktu eksekusi terjadwal berikutnya, dan **Last Run Result**. Kode keluar 0 berarti berhasil, termasuk ketika jumlah baru nol. Kode 1 berarti terjadi kegagalan.

## Cron

Contoh untuk mesin dengan cron berzona waktu Asia/Jakarta. Sesuaikan lokasi PHP CLI, aplikasi dan log dengan server. Akun cron perlu akses baca ke konfigurasi dan akses tulis ke direktori log.

```cron
0 8 * * * /usr/bin/php /var/www/kos-aron/database/buat-notifikasi.php >> /var/log/kos-aron/pengingat.log 2>&1
```

Untuk memeriksa langsung dari root repositori, jalankan perintah berikut.

```sh
php kos-aron/database/buat-notifikasi.php
```

Generator mencetak waktu WIB dan jumlah notifikasi baru. Pada verifikasi tahap ini CLI berhasil dijalankan langsung, dan pengulangan pada database uji menghasilkan nol duplikat. Tugas Windows dan cron berkala belum dipasang atau diuji berjalan sesuai jadwal.
