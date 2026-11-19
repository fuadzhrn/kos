# 2. Rancangan Database `db_kos_aron`

> Dokumen rancangan untuk ditinjau. **Belum dijalankan** — belum ada database,
> tabel, atau migrasi yang dibuat di server mana pun.

## 2.0 Keputusan desain penting

1. **Penyewaan dipisah dari penghuni.** Pada data dummy saat ini, `kamar_id`,
   `tanggal_masuk`, `tanggal_keluar`, dan `status` menempel langsung pada satu
   baris penghuni. Ini tidak bisa menyimpan **riwayat** penyewaan (penghuni
   pindah kamar, atau keluar lalu masuk lagi) tanpa menimpa data lama. Rancangan
   ini memisahkan menjadi tabel `penyewaan` — satu penghuni bisa punya banyak
   baris penyewaan dari waktu ke waktu, dan `penghuni` murni data biodata.
2. **Status tagihan tetap dihitung, tidak disimpan** — konsisten dengan pola
   `status_tagihan()` yang sudah berjalan di frontend: status `lunas` /
   `terlambat` / `belum_bayar` ditentukan dari ada/tidaknya baris di
   `pembayaran` dan perbandingan tanggal, bukan kolom status di `tagihan`.
   Ini mencegah status basi (tidak sinkron) jika baris pembayaran diubah/hapus.
3. **Harga sewa dicatat ulang di `penyewaan` (snapshot)**, bukan hanya dirujuk
   dari `kamar.harga`. Jika harga kamar naik di kemudian hari, tagihan-tagihan
   penyewaan lama tetap konsisten dengan harga yang disepakati saat itu.
4. **"Satu kamar/penghuni maksimal satu penyewaan aktif"** diimplementasikan
   dengan *generated column* + `UNIQUE INDEX` (lihat 2.5), bukan hanya
   diandalkan ke validasi aplikasi, supaya aturan ini tetap terjaga walau ada
   bug di kode aplikasi atau akses langsung ke database.
5. **Informasi kos yang perlu bisa diubah admin** (nama kos, deskripsi,
   alamat, WhatsApp, link Maps, foto utama) dipindah ke tabel `pengaturan`
   (key-value). Konstanta teknis yang tidak perlu diubah admin (timezone,
   path folder foto, nama file placeholder) **tetap** di `includes/config.php`
   sebagai konstanta kode.
6. **Tidak ada penghapusan permanen** untuk data yang punya riwayat keuangan:
   `penghuni`, `penyewaan`, `tagihan`, `pembayaran` tidak pernah di-`DELETE`
   lewat aplikasi (hanya berubah status). Semua foreign key ke tabel-tabel
   ini memakai `ON DELETE RESTRICT`.

## 2.1 Daftar Tabel

1. `users` — akun login (admin & penghuni)
2. `kamar` — data kamar
3. `penghuni` — biodata penghuni
4. `penyewaan` — riwayat penempatan penghuni di suatu kamar
5. `tagihan` — tagihan bulanan per penyewaan
6. `pembayaran` — pencatatan pembayaran oleh admin
7. `notifikasi` — notifikasi untuk penghuni (status baca + isi)
8. `pengaturan` — pengaturan situs yang dapat diubah admin (key-value)

---

## 2.2 `users`

Akun login untuk **admin** dan **penghuni**. Satu baris `users` dengan
`role = 'penghuni'` terhubung ke satu baris `penghuni` lewat `penghuni.user_id`.
Admin tidak memiliki baris `penghuni` — nama tampilannya disimpan di `users.nama`.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `username` | VARCHAR(50) | NOT NULL | UNIQUE. Dipakai login. |
| `password_hash` | VARCHAR(255) | NOT NULL | Hasil `password_hash()` (bcrypt/argon2). **Tidak pernah disimpan plain text.** |
| `role` | ENUM('admin','penghuni') | NOT NULL | |
| `nama` | VARCHAR(100) | NOT NULL | Nama tampilan (untuk admin: satu-satunya sumber nama; untuk penghuni: salinan `penghuni.nama` agar query login cepat tanpa join) |
| `status` | ENUM('aktif','nonaktif') | NOT NULL DEFAULT 'aktif' | Menonaktifkan login tanpa menghapus akun |
| `last_login_at` | DATETIME | NULL | |
| `created_at` | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

- **PK**: `id`
- **Unique**: `username`
- **Index**: `idx_users_role (role)`

---

## 2.3 `kamar`

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `nomor` | VARCHAR(10) | NOT NULL | UNIQUE, contoh "01" |
| `harga` | INT UNSIGNED | NOT NULL | Harga sewa bulanan saat ini (Rupiah, tanpa desimal) |
| `deskripsi` | TEXT | NULL | |
| `fasilitas` | JSON | NULL | Array teks, contoh `["Kasur","Lemari"]` (mengikuti struktur dummy saat ini) |
| `foto_utama` | VARCHAR(255) | NULL | Nama file di `assets/images/kamar/` |
| `galeri` | JSON | NULL | Array nama file foto tambahan |
| `status` | ENUM('kosong','terisi') | NOT NULL DEFAULT 'kosong' | **Nilai cache/denormalisasi** — sumber kebenaran sebenarnya adalah ADA/TIDAKNYA baris `penyewaan` aktif untuk kamar ini. Kolom ini diperbarui otomatis oleh lapisan aplikasi setiap kali penyewaan dibuat/diakhiri, supaya daftar & filter kamar tetap cepat tanpa subquery. |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | |

- **PK**: `id`
- **Unique**: `nomor`
- **Index**: `idx_kamar_status (status)`

---

## 2.4 `penghuni`

Biodata penghuni saja — **tidak** menyimpan kamar atau tanggal sewa (lihat
`penyewaan`). Baris ini tidak pernah dihapus, bahkan setelah penghuni keluar.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `user_id` | BIGINT UNSIGNED | NULL | FK → `users.id`, UNIQUE. Null jika akun login belum dibuat admin. |
| `nama` | VARCHAR(100) | NOT NULL | |
| `no_telepon` | VARCHAR(20) | NOT NULL | |
| `email` | VARCHAR(100) | NULL | Opsional, terpisah dari `users.username` (username bisa berupa username biasa, bukan email) |
| `keterangan` | VARCHAR(255) | NULL | Catatan bebas admin (opsional) |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | |

- **PK**: `id`
- **FK**: `user_id` → `users.id` ON DELETE SET NULL ON UPDATE CASCADE
- **Unique**: `user_id`
- **Index**: `idx_penghuni_nama (nama)`

> Tidak ada kolom status "aktif/tidak_aktif" di tabel ini — status keaktifan
> penghuni ditentukan dari **ada/tidaknya penyewaan aktif miliknya**
> (lihat `penyewaan.status`), supaya tidak ada dua sumber kebenaran yang bisa
> tidak sinkron.

---

## 2.5 `penyewaan`

Tabel inti riwayat penempatan kamar — **tabel baru**, memecah data yang di
dummy saat ini bercampur di `data/penghuni.php`.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `penghuni_id` | BIGINT UNSIGNED | NOT NULL | FK → `penghuni.id` |
| `kamar_id` | BIGINT UNSIGNED | NOT NULL | FK → `kamar.id` |
| `harga_sewa_disepakati` | INT UNSIGNED | NOT NULL | Snapshot harga saat penyewaan dimulai (lihat keputusan desain #3) |
| `hari_jatuh_tempo` | TINYINT UNSIGNED | NOT NULL | 1–31. Hari jatuh tempo tagihan bulanan (lihat 2.8 aturan tanggal jatuh tempo) |
| `tanggal_masuk` | DATE | NOT NULL | |
| `tanggal_keluar` | DATE | NULL | Null = masih berjalan |
| `status` | ENUM('aktif','selesai') | NOT NULL DEFAULT 'aktif' | |
| `keterangan` | VARCHAR(255) | NULL | |
| `dibuat_oleh` | BIGINT UNSIGNED | NULL | FK → `users.id` (admin yang mendaftarkan, audit) |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | |
| `kamar_aktif_unik` *(generated)* | BIGINT UNSIGNED | — | `GENERATED ALWAYS AS (IF(status='aktif', kamar_id, NULL)) VIRTUAL` |
| `penghuni_aktif_unik` *(generated)* | BIGINT UNSIGNED | — | `GENERATED ALWAYS AS (IF(status='aktif', penghuni_id, NULL)) VIRTUAL` |

- **PK**: `id`
- **FK**: `penghuni_id` → `penghuni.id` ON DELETE RESTRICT; `kamar_id` → `kamar.id` ON DELETE RESTRICT; `dibuat_oleh` → `users.id` ON DELETE SET NULL
- **UNIQUE** `uq_kamar_aktif (kamar_aktif_unik)` → menegakkan **"satu kamar maksimal satu penyewaan aktif"** (MySQL mengizinkan banyak `NULL` pada unique index, jadi baris `status='selesai'` tidak saling bentrok)
- **UNIQUE** `uq_penghuni_aktif (penghuni_aktif_unik)` → menegakkan **"satu penghuni maksimal satu penyewaan aktif"**
- **Index**: `idx_penyewaan_penghuni (penghuni_id, status)`, `idx_penyewaan_kamar (kamar_id, status)`

> Jika MySQL yang dipakai tidak mendukung generated column (versi sangat
> lama), alternatifnya adalah menegakkan aturan ini lewat transaksi + row
> lock di lapisan aplikasi (`SELECT ... FOR UPDATE` sebelum insert). Rancangan
> ini **merekomendasikan generated column** karena lebih aman terhadap akses
> data langsung di luar aplikasi.

---

## 2.6 `tagihan`

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `penyewaan_id` | BIGINT UNSIGNED | NOT NULL | FK → `penyewaan.id` (bukan langsung ke `penghuni_id`, supaya tagihan tetap terikat ke periode sewa yang benar jika penghuni pernah sewa dua kali) |
| `periode` | CHAR(7) | NOT NULL | Format `YYYY-MM` |
| `jumlah` | INT UNSIGNED | NOT NULL | Nominal tagihan (Rupiah) |
| `jatuh_tempo` | DATE | NOT NULL | Dihitung dari `penyewaan.hari_jatuh_tempo` + `periode` (lihat 2.8) |
| `keterangan` | VARCHAR(255) | NULL | |
| `dibuat_oleh` | BIGINT UNSIGNED | NULL | FK → `users.id` (admin pembuat, audit; juga menandai apakah dibuat lewat "tagihan massal") |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | |

- **PK**: `id`
- **FK**: `penyewaan_id` → `penyewaan.id` ON DELETE RESTRICT; `dibuat_oleh` → `users.id` ON DELETE SET NULL
- **UNIQUE** `uq_tagihan_penyewaan_periode (penyewaan_id, periode)` → menegakkan **"tagihan untuk penyewaan dan periode yang sama tidak boleh ganda"**
- **Index**: `idx_tagihan_periode (periode)`, `idx_tagihan_jatuh_tempo (jatuh_tempo)`

Status tagihan (`lunas` / `terlambat` / `belum_bayar`) **tidak disimpan** —
dihitung lewat query/VIEW, persis seperti `status_tagihan()` saat ini:

```sql
-- Contoh VIEW opsional (bukan DDL yang wajib dijalankan, hanya ilustrasi logika)
CREATE VIEW v_tagihan_status AS
SELECT t.*,
       CASE
           WHEN pb.id IS NOT NULL THEN 'lunas'
           WHEN CURDATE() > t.jatuh_tempo THEN 'terlambat'
           ELSE 'belum_bayar'
       END AS status
FROM tagihan t
LEFT JOIN pembayaran pb ON pb.tagihan_id = t.id;
```

---

## 2.7 `pembayaran`

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `tagihan_id` | BIGINT UNSIGNED | NOT NULL | FK → `tagihan.id`, **UNIQUE** |
| `tanggal_bayar` | DATE | NOT NULL | |
| `nominal_dibayar` | INT UNSIGNED | NOT NULL | Harus sama persis dengan `tagihan.jumlah` (ditegakkan di lapisan aplikasi/trigger, lihat catatan) |
| `metode` | ENUM('tunai','transfer') | NOT NULL | |
| `keterangan` | VARCHAR(255) | NULL | |
| `dicatat_oleh` | BIGINT UNSIGNED | NOT NULL | FK → `users.id` — **wajib diisi**, karena aturan "pembayaran hanya dicatat admin" |
| `created_at` / `updated_at` | TIMESTAMP | NOT NULL | |

- **PK**: `id`
- **FK**: `tagihan_id` → `tagihan.id` ON DELETE RESTRICT; `dicatat_oleh` → `users.id` ON DELETE RESTRICT
- **UNIQUE** `uq_pembayaran_tagihan (tagihan_id)` → menegakkan **"satu tagihan dibayar penuh satu kali"** (tidak ada cicilan — baris kedua untuk `tagihan_id` yang sama akan ditolak database)
- **Index**: `idx_pembayaran_tanggal (tanggal_bayar)`

> Validasi "`nominal_dibayar` = `tagihan.jumlah`" tidak bisa ditulis sebagai
> `CHECK` constraint lintas tabel di MySQL. Direkomendasikan: validasi di
> lapisan aplikasi (seperti sekarang) **dan** trigger `BEFORE INSERT` sebagai
> jaring pengaman tambahan.

---

## 2.8 `notifikasi`

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | NOT NULL | PK, AUTO_INCREMENT |
| `penghuni_id` | BIGINT UNSIGNED | NOT NULL | FK → `penghuni.id` (didenormalisasi dari `tagihan→penyewaan→penghuni` supaya query "notifikasi milik penghuni X" tidak perlu join berlapis) |
| `tagihan_id` | BIGINT UNSIGNED | NULL | FK → `tagihan.id`. Null diperbolehkan untuk jenis notifikasi non-tagihan di masa depan. |
| `tipe` | ENUM('pengingat','terlambat','lunas') | NOT NULL | Sama seperti `buat_notifikasi_tagihan()` saat ini |
| `judul` | VARCHAR(100) | NOT NULL | |
| `pesan` | TEXT | NOT NULL | |
| `tanggal` | DATE | NOT NULL | |
| `dibaca` | TINYINT(1) | NOT NULL DEFAULT 0 | |
| `dibaca_pada` | DATETIME | NULL | |
| `created_at` | TIMESTAMP | NOT NULL | |

- **PK**: `id`
- **FK**: `penghuni_id` → `penghuni.id` ON DELETE RESTRICT; `tagihan_id` → `tagihan.id` ON DELETE RESTRICT
- **UNIQUE** `uq_notifikasi_tagihan_tipe (tagihan_id, tipe)` → sama persis dengan `kunci_notifikasi($tagihanId, $tipe)` yang sudah ada, mencegah notifikasi dobel untuk tagihan+tipe yang sama
- **Index**: `idx_notifikasi_penghuni_dibaca (penghuni_id, dibaca)`

Dua cara implementasi yang sama-sama valid dengan struktur tabel ini (pilih
salah satu saat implementasi, tidak mengubah skema):
- **Opsi A (dekat dengan logika sekarang):** baris notifikasi tetap
  dihasilkan secara dinamis saat halaman dimuat (seperti `buat_notifikasi_tagihan()`),
  tabel ini hanya dipakai untuk menyimpan/membaca status `dibaca`.
- **Opsi B (lebih "benar" secara database):** proses terjadwal (cron) menulis
  baris baru begitu status tagihan berubah (jatuh tempo mendekat / terlambat / lunas),
  dan halaman hanya membaca dari tabel ini.

---

## 2.9 `pengaturan`

Pengganti konstanta yang perlu bisa diubah admin di `admin/pengaturan.php`
("Informasi Kos"). Pola *key-value* dipilih karena jumlah settingnya kecil
dan jenisnya heterogen (teks, URL, nama file) — tabel kolom-per-setting akan
berubah struktur setiap kali ada setting baru.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `kunci` | VARCHAR(50) | NOT NULL | **PK**. Contoh: `nama_kos`, `deskripsi_kos`, `alamat`, `whatsapp`, `maps_link`, `maps_embed`, `foto_utama` |
| `nilai` | TEXT | NULL | |
| `updated_at` | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |
| `updated_oleh` | BIGINT UNSIGNED | NULL | FK → `users.id` |

- **PK**: `kunci`
- **FK**: `updated_oleh` → `users.id` ON DELETE SET NULL

> Konstanta teknis yang **tidak** perlu diedit admin (timezone, path folder
> `assets/images/...`, nama file placeholder) tetap di `includes/config.php`
> sebagai konstanta PHP, tidak dipindah ke tabel ini.

---

## 2.10 Aturan tanggal jatuh tempo (asumsi, masih bisa disesuaikan)

> **ASUMSI** — bisa diubah pemilik/pembimbing sebelum implementasi.

- Admin menetapkan **hari** jatuh tempo (1–31) per penyewaan, disimpan di
  `penyewaan.hari_jatuh_tempo`.
- Saat tagihan dibuat untuk suatu `periode` (`YYYY-MM`), tanggal jatuh tempo
  dihitung sebagai: `hari_jatuh_tempo` pada bulan `periode`, **atau hari
  terakhir bulan itu jika `hari_jatuh_tempo` (29/30/31) melebihi jumlah hari
  bulan tersebut** (contoh: jatuh tempo tanggal 31, periode Februari → jatuh
  tempo jadi 28/29 Februari).
- Perhitungan ini murni logika aplikasi saat tagihan dibuat (bukan trigger
  database), setara `LEAST(hari_jatuh_tempo, LAST_DAY(tanggal_periode))` di SQL
  atau `min($hari, (int) date('t', strtotime($periode.'-01')))` di PHP.

## 2.11 Perubahan pada tahap implementasi (disetujui user)

Saat implementasi (`database/schema.sql`), ada **satu** penyesuaian terhadap
rancangan di atas, diminta langsung oleh instruksi tahap implementasi
("gunakan DECIMAL untuk nominal uang"):

- `kamar.harga`, `penyewaan.harga_sewa_disepakati`, `tagihan.jumlah`, dan
  `pembayaran.nominal_dibayar` diubah dari **INT UNSIGNED** menjadi
  **DECIMAL(12,2)**. Alasan: DECIMAL adalah tipe standar untuk nilai uang
  (menghindari masalah pembulatan biner seperti pada FLOAT/DOUBLE, dan lebih
  eksplisit secara semantik dibanding INT untuk kolom nominal).
- Karena dukungan `UNSIGNED` pada `DECIMAL` sudah *deprecated* di MySQL 8,
  validasi "tidak boleh negatif" dipindah ke `CHECK` constraint
  (`chk_kamar_harga`, `chk_penyewaan_harga`, `chk_tagihan_jumlah`,
  `chk_pembayaran_nominal`) — hasil akhirnya setara, hanya cara
  penegakannya berbeda.
- Dua `CHECK` tambahan (tidak mengubah struktur, hanya memperketat validasi)
  ikut ditambahkan saat implementasi: `chk_penyewaan_tanggal` (tanggal
  keluar tidak boleh sebelum tanggal masuk) dan `chk_tagihan_periode_format`
  (format `periode` harus `YYYY-MM`).

**Tidak ada** tabel, kolom lain, primary key, foreign key, atau relasi yang
berubah dari ERD yang sudah disetujui — lihat `docs/erd.drawio` (tidak
diubah) dan hasil pemeriksaan struktur di `docs/06-hasil-pengujian.md`.

## 2.12 Perubahan pada tahap backend Data Kamar (disetujui user)

Saat mengimplementasikan CRUD kamar, ada **satu** perubahan struktural pada
tabel `kamar`, diminta langsung oleh instruksi tahap ini:

- Kolom `status ENUM('kosong','terisi')` **dihapus**. Instruksi tahap ini
  menegaskan "Status ketersediaan kamar tidak boleh bergantung pada input
  manual yang bertentangan dengan penyewaan" — menyimpannya sebagai kolom
  (meski diberi komentar "cache") tetap membuka risiko nilainya tidak
  sinkron dengan tabel `penyewaan` yang sebenarnya. Nilai ini sekarang
  **selalu dihitung ulang** setiap kali dibaca: `terisi` jika ada baris
  `penyewaan` berstatus `aktif` untuk kamar itu, `kosong` jika tidak ada
  (lihat `data/kamar.php`, fungsi `get_semua_kamar()` dkk.) — tidak ada
  lagi satu pun jalur di aplikasi yang bisa menulis nilai ini secara
  manual.
- Kolom `kondisi ENUM('aktif','perawatan') NOT NULL DEFAULT 'aktif'`
  **ditambahkan**. Ini konsep yang BERBEDA dari ketersediaan: status
  operasional yang memang diatur manual oleh admin ("kamar dalam
  perawatan tidak dapat disewakan" — TUGAS 2 tahap ini), independen dari
  ada/tidaknya penyewaan aktif. Index `idx_kamar_status` diganti
  `idx_kamar_kondisi`.

**Tidak ada** tabel lain, primary key, foreign key, atau relasi yang
berubah — lihat `docs/erd.drawio` (kotak `kamar` diperbarui mengikuti
perubahan ini) dan `database/schema.sql` untuk DDL final.

## 2.13 DDL final

DDL lengkap yang sudah **benar-benar dijalankan dan diverifikasi** (termasuk
perubahan §2.11) ada di [`database/schema.sql`](../database/schema.sql) —
tidak diduplikasi di sini supaya tidak ada dua versi yang bisa tidak sinkron.
Cuplikan singkat (lihat file aslinya untuk versi lengkap):

```sql
CREATE DATABASE IF NOT EXISTS db_kos_aron CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_kos_aron;

CREATE TABLE kamar (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomor      VARCHAR(10)     NOT NULL,
    harga      DECIMAL(12, 2)  NOT NULL,  -- lihat §2.11: DECIMAL, bukan INT
    kondisi    ENUM('aktif', 'perawatan') NOT NULL DEFAULT 'aktif', -- lihat §2.12
    -- (tidak ada kolom status kosong/terisi -- lihat §2.12, selalu dihitung)
    -- ...kolom & constraint selengkapnya ada di database/schema.sql
    PRIMARY KEY (id),
    UNIQUE KEY uq_kamar_nomor (nomor),
    CONSTRAINT chk_kamar_harga CHECK (harga >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ...dan seterusnya untuk users, penghuni, penyewaan, tagihan,
-- pembayaran, notifikasi, pengaturan — lihat database/schema.sql
-- untuk DDL lengkap dan sudah terverifikasi (database/uji-koneksi.php,
-- lihat docs/06-hasil-pengujian.md).
```
