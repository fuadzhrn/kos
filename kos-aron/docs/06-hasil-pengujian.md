# 6. Hasil Pemeriksaan Tabel/Relasi dan Pengujian Koneksi

Seluruh pengujian di bawah ini **benar-benar dijalankan** terhadap MySQL
8.4.3 (bawaan Laragon, `D:/laragon/laragon/bin/mysql/mysql-8.4.3-winx64`)
pada database `db_kos_aron`, bukan simulasi.

## 6.1 Struktur tabel

```
SHOW TABLES;
→ kamar, notifikasi, pembayaran, pengaturan, penghuni, penyewaan, tagihan, users
```

Seluruh 8 tabel dibuat, mesin `InnoDB`, collation `utf8mb4_unicode_ci` —
sesuai ketentuan tugas (diperiksa lewat `information_schema.TABLES`).

## 6.2 Foreign key yang terpasang (`information_schema.KEY_COLUMN_USAGE`)

| Tabel | Kolom | Mengacu ke |
|---|---|---|
| `notifikasi` | `penghuni_id` | `penghuni.id` |
| `notifikasi` | `tagihan_id` | `tagihan.id` |
| `pembayaran` | `dicatat_oleh` | `users.id` |
| `pembayaran` | `tagihan_id` | `tagihan.id` |
| `pengaturan` | `updated_oleh` | `users.id` |
| `penghuni` | `user_id` | `users.id` |
| `penyewaan` | `dibuat_oleh` | `users.id` |
| `penyewaan` | `kamar_id` | `kamar.id` |
| `penyewaan` | `penghuni_id` | `penghuni.id` |
| `tagihan` | `dibuat_oleh` | `users.id` |
| `tagihan` | `penyewaan_id` | `penyewaan.id` |

11 foreign key — sesuai yang direncanakan di `docs/02-rancangan-database.md` §2.1–2.9.

## 6.3 Unique constraint & index (`information_schema.STATISTICS`)

Terverifikasi ada: `uq_users_username`, `uq_kamar_nomor`, `uq_penghuni_user_id`,
`uq_kamar_aktif` & `uq_penghuni_aktif` (generated column, lihat §6.4),
`uq_tagihan_penyewaan_periode`, `uq_pembayaran_tagihan`,
`uq_notifikasi_tagihan_tipe`, plus seluruh index pendukung query
(`idx_kamar_status`, `idx_penyewaan_penghuni`, `idx_penyewaan_kamar`,
`idx_tagihan_periode`, `idx_tagihan_jatuh_tempo`, `idx_pembayaran_tanggal`,
`idx_notifikasi_penghuni_dibaca`, `idx_users_role`, `idx_penghuni_nama`).

## 6.4 Pengujian aturan bisnis (percobaan melanggar aturan — HARUS ditolak)

| # | Percobaan | Hasil |
|---|---|---|
| 1 | INSERT penyewaan aktif KE-2 untuk kamar 04 (sudah dihuni Ahmad) | **Ditolak**: `Duplicate entry '4' for key 'penyewaan.uq_kamar_aktif'` |
| 2 | INSERT tagihan periode `2026-10` KE-2 untuk penyewaan Ahmad (sudah ada) | **Ditolak**: `Duplicate entry '2-2026-10' for key 'tagihan.uq_tagihan_penyewaan_periode'` |
| 3 | INSERT pembayaran KE-2 untuk tagihan #2 (sudah lunas) | **Ditolak**: `Duplicate entry '2' for key 'pembayaran.uq_pembayaran_tagihan'` |
| 4 | INSERT kamar dengan `harga = -100` | **Ditolak**: `Check constraint 'chk_kamar_harga' is violated` |
| 5 | INSERT tagihan dengan `periode = '2026-13'` (bulan tidak valid) | **Ditolak**: `Check constraint 'chk_tagihan_periode_format' is violated` |
| 6 | DELETE penghuni yang masih punya riwayat tagihan/notifikasi | **Ditolak**: `Cannot delete ... foreign key constraint fails ... fk_notifikasi_penghuni ... ON DELETE RESTRICT` |

Keenamnya ditolak oleh database itu sendiri (bukan hanya validasi aplikasi),
sesuai tujuan rancangan di `docs/02-rancangan-database.md` §2.0 poin 4 dan 6.

## 6.5 Pemeriksaan konsistensi data uji (`database/seed.sql`)

```sql
-- Kamar terisi harus punya tepat satu penyewaan aktif, dan sebaliknya
SELECT k.nomor, k.status, s.id AS penyewaan_aktif_id, p.nama
FROM kamar k
LEFT JOIN penyewaan s ON s.kamar_id = k.id AND s.status = 'aktif'
LEFT JOIN penghuni p ON p.id = s.penghuni_id;
```
Hasil: kamar 01/02/03 = kosong (tanpa penyewaan aktif), kamar 04/05/06 =
terisi dengan masing-masing **tepat satu** penyewaan aktif (Ahmad Fauzi,
Lina Marlina, Dedi Kurniawan). **Sesuai aturan #8.**

```sql
-- Status tagihan dihitung dari ada/tidaknya pembayaran + tanggal hari ini
SELECT t.id, t.periode, t.jatuh_tempo,
  CASE WHEN pb.id IS NOT NULL THEN 'lunas'
       WHEN CURDATE() > t.jatuh_tempo THEN 'terlambat'
       ELSE 'belum_bayar' END AS status
FROM tagihan t LEFT JOIN pembayaran pb ON pb.tagihan_id = t.id;
```
Hasil (per tanggal pengujian, 2026-11-19): 3 `lunas` (tagihan #1, #2, #6),
2 `terlambat` (tagihan #3, #4), 2 `belum_bayar` (tagihan #5, #7) — kombinasi
yang sengaja dibuat untuk menguji ketiga status sekaligus.

```sql
-- Tidak boleh ada tagihan lunas dengan nominal pembayaran ≠ nominal tagihan
SELECT t.id FROM tagihan t JOIN pembayaran pb ON pb.tagihan_id = t.id
WHERE pb.nominal_dibayar <> t.jumlah;
-- Hasil: 0 baris. ✔
```

```sql
-- Tidak boleh ada kamar 'terisi' tanpa penyewaan aktif, atau 'kosong' dengan penyewaan aktif
SELECT k.nomor, k.status FROM kamar k
WHERE (k.status='terisi' AND NOT EXISTS (SELECT 1 FROM penyewaan s WHERE s.kamar_id=k.id AND s.status='aktif'))
   OR (k.status='kosong' AND EXISTS (SELECT 1 FROM penyewaan s WHERE s.kamar_id=k.id AND s.status='aktif'));
-- Hasil: 0 baris. ✔
```

## 6.6 Pengujian koneksi PDO

Perintah: `php database/uji-koneksi.php`

```
[OK] Koneksi PDO ke db_kos_aron berhasil.
    Driver: mysql
    Emulated prepares: nonaktif
    Error mode: EXCEPTION

[OK] Prepared statement berjalan. Kamar berstatus 'terisi': 3

Jumlah baris per tabel:
    users        4
    kamar        6
    penghuni     4
    penyewaan    4
    tagihan      7
    pembayaran   3
    notifikasi   7
    pengaturan   7

[SELESAI] Semua pengujian koneksi berhasil.
```

Terkonfirmasi: **emulated prepares nonaktif**, **exception mode aktif**,
prepared statement (`:status`) berfungsi.

### Pengujian jalur gagal (kredensial salah) — pesan ke pengguna harus aman

Koneksi dicoba dengan password yang disengaja salah:
```
Pesan ke pengguna: Tidak dapat terhubung ke database saat ini. Silakan coba lagi nanti.
Tipe exception: RuntimeException
```
Detail asli (`SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' ...`)
**hanya** tercatat di `error_log()` server (`php_errors.log`), **tidak**
pernah dikembalikan ke pemanggil/pengunjung — sesuai ketentuan "jangan
tampilkan detail error database kepada pengunjung".

## 6.7 Catatan lingkungan pengujian

- Pengujian dijalankan dengan menyalakan MySQL Laragon secara manual lewat
  `mysqld.exe` (memakai `my.ini` bawaan Laragon) karena belum berjalan di
  sesi ini. **Jika Anda menutup dan membuka ulang Laragon, kelola
  start/stop MySQL lewat Laragon seperti biasa** — tidak ada konfigurasi
  Laragon yang diubah oleh proses ini.
- Validasi XML `docs/erd.drawio` dan hasil pengujian di atas dijalankan pada
  2026-11-19.
