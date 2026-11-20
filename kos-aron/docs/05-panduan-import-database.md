# 5. Panduan Import Database (XAMPP / Laragon + phpMyAdmin)

File yang dibutuhkan ada di folder `database/`:
- `database/schema.sql` memuat sembilan tabel dasar. Setelah memilih database, impor `database/migrasi-registrasi-pemesanan.sql` untuk menambahkan dua tabel pemesanan. Pada database yang sudah berjalan, gunakan hanya migrasi yang diperlukan setelah backup.
- `database/seed.sql` — data uji fiktif (lihat `database/seed.sql` untuk rincian skenario).

> **Peringatan sebelum menjalankan ulang**: `schema.sql` melakukan
> `DROP TABLE IF EXISTS` dan `seed.sql` melakukan `TRUNCATE TABLE`. Keduanya
> **aman untuk database baru/kosong**, tetapi akan **menghapus seluruh data**
> jika dijalankan pada `db_kos_aron` yang sudah berisi data sungguhan.
> Jika `db_kos_aron` sudah dipakai dan berisi data yang ingin dipertahankan,
> **backup dulu** sebelum menjalankan ulang file ini:
> `mysqldump -u root db_kos_aron > backup-sebelum-import-YYYYMMDD.sql`
> (lihat §5.4).

## 5.1 Lewat phpMyAdmin (XAMPP)

1. Jalankan Apache dan MySQL dari XAMPP Control Panel.
2. Buka `http://localhost/phpmyadmin`.
3. Klik tab **Import** (tidak perlu membuat database kosong dulu —
   `schema.sql` sudah memuat `CREATE DATABASE IF NOT EXISTS db_kos_aron`).
4. Pilih file `database/schema.sql` → klik **Go** / **Kirim**.
   Pastikan muncul pesan sukses, lalu tabel `db_kos_aron` akan terlihat di
   panel kiri dengan sembilan tabel dasar.
5. Pastikan database aktif adalah `db_kos_aron` (klik nama database di
   panel kiri dahulu), lalu ulangi langkah Import dengan file
   `database/migrasi-registrasi-pemesanan.sql`. Periksa bahwa ada sebelas tabel. Khusus database pengujian kosong, data fiktif dapat diisi dengan `database/seed.sql`.
6. Klik tab **Structure** pada tiap tabel untuk memeriksa kolom, lalu tab
   **Relation view** (di bagian bawah Structure) untuk memeriksa foreign key.

## 5.2 Lewat phpMyAdmin (Laragon)

Sama seperti §5.1 — Laragon menyertakan phpMyAdmin bawaan, dapat dibuka lewat
menu Laragon → **Database** → **phpMyAdmin**, atau `http://localhost/phpmyadmin`
selagi Apache & MySQL Laragon berjalan.

## 5.3 Lewat command line (alternatif, lebih cepat)

```bash
# Dari folder kos-aron/
mysql -u root -p < database/schema.sql
mysql -u root -p db_kos_aron < database/migrasi-registrasi-pemesanan.sql
# Opsional, hanya pada database uji kosong
mysql -u root -p db_kos_aron < database/seed.sql
```

(Hilangkan `-p` jika root tidak memakai password, seperti bawaan XAMPP/Laragon.)

## 5.4 Backup sebelum menjalankan ulang (jika database sudah berisi data)

```bash
mysqldump -u root db_kos_aron > backup-db_kos_aron-2026-11-19.sql
```

Simpan hasil `mysqldump` di luar folder project (atau pastikan tidak ikut
Git — pola `*.sql` di `.gitignore` memang sengaja mengabaikan dump data
sungguhan, berkas skema, migrasi struktur dan seed fiktif dikecualikan).

## 5.5 Menyiapkan konfigurasi koneksi (sekali saja per environment)

```bash
cp includes/database.local.example.php includes/database.local.php
```

Lalu sesuaikan isinya (host/port/nama database/user/password) jika berbeda
dari default XAMPP/Laragon. File `database.local.php` **tidak ikut Git**.

## 5.6 Memeriksa hasil import

Jalankan alat uji koneksi (lihat juga `06-hasil-pengujian.md`):

```bash
php database/uji-koneksi.php
```

Harus menampilkan `[SELESAI] Semua pengujian koneksi berhasil.` beserta
jumlah baris di setiap tabel.
