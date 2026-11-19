# Dokumentasi Perancangan Backend — Kos Aron

Folder ini berisi hasil analisis dan rancangan database untuk tahap backend
Sistem Informasi Kos Aron. **Belum ada perubahan kode backend, belum ada
database yang dibuat, dan belum ada data dummy yang dihapus** — seluruh isi
folder ini adalah dokumen rancangan untuk ditinjau terlebih dahulu.

## Daftar dokumen

1. [01-analisis-frontend.md](01-analisis-frontend.md) — Hasil pemeriksaan struktur project yang sudah ada (halaman, data dummy, include, form, navigasi, fungsi PHP).
2. [02-rancangan-database.md](02-rancangan-database.md) — Rancangan 8 tabel `db_kos_aron`: kolom, tipe data, PK/FK, constraint, index, termasuk catatan perubahan saat implementasi (DECIMAL untuk nominal uang).
3. [03-relasi-dan-alur.md](03-relasi-dan-alur.md) — Penjelasan ERD, hubungan antar tabel, dan alur (penghuni baru, tagihan, pembayaran, monitoring kamar, dashboard penghuni).
4. [04-penyesuaian-frontend.md](04-penyesuaian-frontend.md) — Daftar penyesuaian yang mungkin diperlukan pada frontend saat nanti diintegrasikan dengan backend.
5. [05-panduan-import-database.md](05-panduan-import-database.md) — Cara import `database/schema.sql` + `database/seed.sql` lewat phpMyAdmin (XAMPP/Laragon) atau command line.
6. [06-hasil-pengujian.md](06-hasil-pengujian.md) — Hasil pemeriksaan struktur tabel/relasi dan pengujian koneksi PDO yang benar-benar dijalankan.
7. [07-login-dan-keamanan.md](07-login-dan-keamanan.md) — Sistem login, sesi, proteksi peran, logout, akun admin awal, dan hasil 10 skenario pengujian (TUGAS 7).
8. [erd.drawio](erd.drawio) — Diagram ERD, bisa dibuka dan diedit di [draw.io](https://app.diagrams.net/) (File → Open From → Device).

## Database & koneksi (tahap fondasi — belum terhubung ke frontend)

- [`database/schema.sql`](../database/schema.sql) — DDL 8 tabel (InnoDB, utf8mb4).
- [`database/seed.sql`](../database/seed.sql) — data uji **fiktif** (lihat isi file untuk skenario lengkap).
- [`database/uji-koneksi.php`](../database/uji-koneksi.php) — alat uji koneksi PDO (`php database/uji-koneksi.php`).
- [`includes/koneksi.php`](../includes/koneksi.php) — fungsi `koneksi_database()` (PDO, prepared statement, exception mode).
- [`includes/database.local.example.php`](../includes/database.local.example.php) — contoh konfigurasi (salin menjadi `database.local.php`, **tidak ikut Git**).

## Catatan lokasi project

Prompt menyebut lokasi `C:\xampp\htdocs\kos-proposal\kos-aron`, tetapi project
ini berjalan di Laragon pada `d:\laragon\laragon\www\kos-proposal\kos-aron`
(sesuai setup yang sudah berjalan). Dokumen ini ditulis berdasarkan lokasi
Laragon tersebut; tidak ada perubahan konfigurasi yang dilakukan.
