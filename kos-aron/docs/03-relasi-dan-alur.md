# 3. ERD, Relasi, dan Alur Data

Diagram visual: [erd.drawio](erd.drawio) (buka/edit di https://app.diagrams.net/).

## 3.1 Ringkasan Entitas

| Tabel | PK | FK |
|---|---|---|
| `users` | `id` | — |
| `kamar` | `id` | — |
| `penghuni` | `id` | `user_id` → `users.id` |
| `penyewaan` | `id` | `penghuni_id` → `penghuni.id`; `kamar_id` → `kamar.id`; `dibuat_oleh` → `users.id` |
| `tagihan` | `id` | `penyewaan_id` → `penyewaan.id`; `dibuat_oleh` → `users.id` |
| `pembayaran` | `id` | `tagihan_id` → `tagihan.id`; `dicatat_oleh` → `users.id` |
| `notifikasi` | `id` | `penghuni_id` → `penghuni.id`; `tagihan_id` → `tagihan.id` |
| `pengaturan` | `kunci` | `updated_oleh` → `users.id` |

## 3.2 Hubungan Antar Tabel

- **`users` 1 — 0/1 `penghuni`**: satu akun penghuni terhubung ke satu baris
  biodata penghuni (`penghuni.user_id` UNIQUE). Akun admin tidak punya baris
  `penghuni`.
- **`penghuni` 1 — N `penyewaan`**: satu penghuni bisa memiliki banyak riwayat
  penyewaan (pindah kamar, keluar-masuk lagi), tapi **maksimal satu yang
  `status='aktif'`** pada satu waktu (ditegakkan `UNIQUE` di generated column).
- **`kamar` 1 — N `penyewaan`**: satu kamar bisa dihuni banyak penghuni secara
  berurutan dari waktu ke waktu, tapi **maksimal satu penyewaan `aktif`** per
  kamar pada satu waktu.
- **`penyewaan` 1 — N `tagihan`**: satu penyewaan punya banyak tagihan bulanan
  (satu per periode), **tidak boleh dua tagihan periode yang sama untuk
  penyewaan yang sama**.
- **`tagihan` 1 — 0/1 `pembayaran`**: satu tagihan paling banyak punya satu
  pembayaran (tidak ada cicilan). Tagihan tanpa baris `pembayaran` berarti
  belum lunas.
- **`tagihan` 1 — N `notifikasi`**: satu tagihan bisa menghasilkan beberapa
  notifikasi sepanjang waktu (`pengingat` lalu `lunas`, atau `terlambat` lalu
  `lunas`), tapi tidak dobel untuk kombinasi tagihan+tipe yang sama.
- **`penghuni` 1 — N `notifikasi`**: didenormalisasi agar query "semua
  notifikasi milik penghuni X" tidak perlu join lewat `tagihan → penyewaan`.
- **`users` 1 — N `penyewaan` / `tagihan` / `pembayaran` / `pengaturan`**:
  relasi audit ("siapa yang membuat/mencatat/mengubah"), lewat
  `dibuat_oleh` / `dicatat_oleh` / `updated_oleh`.

## 3.3 Alur Penghuni Baru

1. Admin membuka **Data Penghuni → Tambah Penghuni** (`admin/penghuni-form.php`).
2. Admin mengisi biodata (nama, telepon, email opsional) → `INSERT penghuni`.
3. Admin membuatkan akun login (`username` + password awal yang di-hash) → `INSERT users` dengan `role='penghuni'`, lalu `UPDATE penghuni SET user_id = ...`.
4. Admin memilih kamar kosong + tanggal masuk + hari jatuh tempo → `INSERT penyewaan` dengan `status='aktif'`.
   - Jika kamar yang dipilih sudah punya penyewaan aktif, `INSERT` ini **ditolak database** (melanggar `uq_kamar_aktif`) — aplikasi menampilkan pesan validasi, sama seperti `alasan_kamar_tidak_tersedia()` saat ini.
5. Aplikasi memperbarui `kamar.status = 'terisi'` (nilai cache, lihat 2.3).
6. Penghuni baru otomatis muncul di Dashboard Admin (jumlah kamar terisi bertambah) dan bisa login ke Dashboard Penghuni begitu akunnya aktif.

**Saat penghuni keluar** (bukan dihapus): admin mengubah `penyewaan.status = 'selesai'` + mengisi `tanggal_keluar`, lalu aplikasi mengubah `kamar.status = 'kosong'`. Baris `penghuni`, `penyewaan`, `tagihan`, `pembayaran` miliknya **tidak dihapus** — tetap bisa dilihat sebagai riwayat (memenuhi aturan "data keuangan lama tidak boleh hilang").

## 3.4 Alur Pembuatan Tagihan

**Satu penghuni** (seperti form saat ini di `admin/tagihan.php`):
1. Admin memilih penyewaan aktif (tampil sebagai "nama — kamar") + periode.
2. Aplikasi mengecek `uq_tagihan_penyewaan_periode` — jika sudah ada tagihan untuk penyewaan+periode itu, tolak (sama seperti `cari_tagihan_periode()` saat ini).
3. `jumlah` diambil dari `penyewaan.harga_sewa_disepakati` (bisa diubah manual oleh admin jika perlu).
4. `jatuh_tempo` dihitung dari `penyewaan.hari_jatuh_tempo` + `periode` (lihat aturan pembulatan akhir bulan di `02-rancangan-database.md` §2.10).
5. `INSERT tagihan`.

**Massal** (fitur baru, belum ada di UI — lihat `04-penyesuaian-frontend.md`):
1. Admin memilih satu `periode`.
2. Aplikasi mengambil **semua** `penyewaan` dengan `status='aktif'`.
3. Untuk setiap penyewaan, coba `INSERT tagihan` dengan periode tersebut — penyewaan yang **sudah** punya tagihan periode itu otomatis dilewati (akan gagal pada `uq_tagihan_penyewaan_periode`, ditangkap aplikasi dan dilaporkan sebagai "sudah ada, dilewati" bukan error fatal).
4. Hasil akhir: ringkasan "N tagihan dibuat, M dilewati karena sudah ada."

## 3.5 Alur Pencatatan Pembayaran

1. Penghuni membayar tunai/transfer **langsung ke pemilik**, di luar sistem (tidak ada pembayaran online).
2. Admin membuka **Pembayaran → Catat Pembayaran** (`admin/pembayaran.php`), memilih salah satu tagihan yang **belum lunas** (hasil `LEFT JOIN tagihan` dengan `pembayaran` di mana `pembayaran.id IS NULL`).
3. Admin mengisi tanggal bayar, nominal (divalidasi harus sama dengan `tagihan.jumlah`), metode, keterangan.
4. `INSERT pembayaran` dengan `dicatat_oleh = id admin yang login`.
5. Karena `pembayaran.tagihan_id` UNIQUE, mencoba mencatat pembayaran kedua untuk tagihan yang sama akan **ditolak database** — mencegah pembayaran dobel/cicilan tanpa perlu cek tambahan di aplikasi (meski aplikasi tetap mengecek dulu untuk pesan error yang ramah).
6. Status tagihan otomatis menjadi "Lunas" di semua tampilan (Admin, Penghuni) karena dihitung dari `EXISTS pembayaran`, tanpa perlu `UPDATE` terpisah ke tabel `tagihan`.
7. (Opsional, Opsi B pada §2.8) Proses yang sama memicu `INSERT notifikasi` bertipe `lunas` untuk penghuni terkait.

## 3.6 Alur Monitoring Kamar

- **Daftar kamar tersedia** (publik & admin): `SELECT * FROM kamar WHERE status = 'kosong'` — nilai cache yang dijaga sinkron oleh langkah 3.3/3.5.
- **Jumlah kamar per status** (kartu statistik Dashboard Admin): `SELECT status, COUNT(*) FROM kamar GROUP BY status`, identik dengan `hitung_kamar_per_status()` saat ini.
- **Penghuni yang menempati kamar tertentu**: `SELECT p.* FROM penghuni p JOIN penyewaan s ON s.penghuni_id = p.id WHERE s.kamar_id = ? AND s.status = 'aktif'`, identik dengan `get_penghuni_by_kamar()`.
- Untuk audit/debugging, status cache di `kamar.status` **harus bisa diverifikasi** terhadap sumber aslinya dengan query pembanding:
  `SELECT k.id FROM kamar k WHERE k.status='terisi' AND NOT EXISTS (SELECT 1 FROM penyewaan s WHERE s.kamar_id=k.id AND s.status='aktif')` (harus selalu kosong jika aplikasi konsisten).

## 3.7 Alur Dashboard Penghuni Mengambil Data

Setelah login sungguhan dibuat (session menyimpan `users.id`), setiap halaman
penghuni mengganti pemanggilan `get_penghuni_login()` yang hardcode dengan
data dari session:

1. Dari `$_SESSION['user_id']`, ambil `penghuni` lewat `WHERE user_id = ?`.
2. Ambil `penyewaan` aktif milik penghuni itu (`WHERE penghuni_id = ? AND status = 'aktif'`) → dipakai di **Kamar Saya** dan **Dashboard**.
3. **Tagihan** (`penghuni/tagihan.php`): `SELECT tagihan WHERE penyewaan_id IN (SELECT id FROM penyewaan WHERE penghuni_id = ?)`, status dihitung seperti §2.6.
4. **Riwayat Pembayaran** (`penghuni/riwayat.php`): join dari `tagihan` (lewat `penyewaan_id` milik penghuni) ke `pembayaran` — mencakup **seluruh riwayat**, termasuk dari penyewaan lama yang sudah `selesai`, bukan hanya penyewaan aktif saat ini (konsisten dengan "riwayat pembayaran lama tidak hilang").
5. **Notifikasi** (`penghuni/notifikasi.php`): `SELECT notifikasi WHERE penghuni_id = ? ORDER BY tanggal DESC`.
6. **Profil**: data dari `penghuni` (biodata) + `users` (username, untuk ubah password via `password_hash()`/`password_verify()`).

Pola query ini sengaja dibuat semirip mungkin dengan fungsi `data/*.php` yang
sudah ada, supaya saat integrasi backend nanti, isi fungsi (`get_tagihan_by_penghuni()`,
`get_pembayaran_by_penghuni()`, dst.) bisa diganti isinya ke query SQL **tanpa
mengubah pemanggil/halaman** — lihat `04-penyesuaian-frontend.md`.
