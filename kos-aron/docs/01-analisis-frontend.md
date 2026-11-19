# 1. Hasil Analisis Project (Frontend)

Pemeriksaan dilakukan atas seluruh struktur folder `kos-aron/` pada kondisi
project saat ini (frontend PHP Native, data dummy, tanpa database).

## 1.1 Halaman Website Publik (tanpa login)

| Halaman | File | Keterangan |
|---|---|---|
| Beranda | `index.php` | Hero, ringkasan kamar tersedia, tombol WhatsApp/Kontak |
| Daftar Kamar | `kamar.php` | Daftar seluruh kamar dari `data/kamar.php` |
| Detail Kamar | `detail-kamar.php` | Detail satu kamar (`?id=`), galeri foto |
| Tentang | `tentang.php` | Profil kos |
| Kontak | `kontak.php` | Info kontak, WhatsApp, embed Maps |
| Login | `login.php` | Form tampilan saja — **belum ada autentikasi nyata**, submit apa pun hanya menampilkan pesan "fitur belum aktif" |

Navigasi publik (`includes/header.php`): Beranda, Kamar, Tentang, Kontak, tombol Login — dikelola lewat `nav_active()`.

## 1.2 Halaman Dashboard Admin (`admin/`)

| Halaman | File | Keterangan |
|---|---|---|
| Dashboard | `admin/index.php` | Ringkasan kamar, tagihan belum lunas, pembayaran bulan ini |
| Data Kamar | `admin/kamar.php` | Daftar kamar + status |
| Tambah/Edit Kamar | `admin/kamar-form.php` | Form kamar (nomor, harga, deskripsi, fasilitas, status, foto) — **prototype, tidak menyimpan** |
| Data Penghuni | `admin/penghuni.php` | Daftar penghuni |
| Detail Penghuni | `admin/penghuni-detail.php` | Detail satu penghuni |
| Tambah/Edit Penghuni | `admin/penghuni-form.php` | Form penghuni (nama, telepon, akun, kamar, tanggal masuk, status) — **prototype, tidak menyimpan** |
| Tagihan | `admin/tagihan.php` | Daftar tagihan, filter, modal "Buat Tagihan" (satu penghuni per form, **belum ada tagihan massal di UI**) |
| Pembayaran | `admin/pembayaran.php` | Daftar pembayaran, modal "Catat Pembayaran", modal Detail |
| Laporan | `admin/laporan.php` | Filter periode/jenis, ringkasan, tabel pembayaran & kamar, cetak (selesai pada tahap sebelumnya) |
| Pengaturan | `admin/pengaturan.php` | Info kos (read-only prototype dari `includes/config.php`), info akun admin, form ubah password (selesai pada tahap sebelumnya) |

Navigasi admin (`admin/includes/header.php`, sidebar): Dashboard, Data Kamar, Data Penghuni, Tagihan, Pembayaran, Laporan, Pengaturan.

## 1.3 Halaman Dashboard Penghuni (`penghuni/`)

| Halaman | File | Keterangan |
|---|---|---|
| Dashboard | `penghuni/index.php` | Ringkasan kamar, status tagihan berjalan |
| Kamar Saya | `penghuni/kamar.php` | Info kamar yang ditempati |
| Tagihan | `penghuni/tagihan.php` | Tagihan berjalan + riwayat tagihan |
| Riwayat Pembayaran | `penghuni/riwayat.php` | Riwayat pembayaran + filter bulan/tahun + modal detail (selesai pada tahap sebelumnya) |
| Notifikasi | `penghuni/notifikasi.php` | Notifikasi dihasilkan dari status tagihan |
| Profil | `penghuni/profil.php` | Info profil, form ubah password (prototype) |

Navigasi penghuni (`penghuni/includes/header.php`, sidebar): Dashboard, Kamar Saya, Tagihan, Riwayat Pembayaran, Notifikasi, Profil — **tidak ada tautan ke halaman admin mana pun**.

Catatan penting: `$penghuniLogin = get_penghuni_login()` di `data/penghuni.php` selalu mengembalikan data Siti Aminah (id=2) secara hardcode — **belum ada session/login sungguhan** di sisi penghuni maupun admin.

## 1.4 File Penyimpanan Data Dummy (`data/`)

| File | Fungsi utama | Struktur baris |
|---|---|---|
| `data/kamar.php` | `get_semua_kamar()`, `get_kamar_by_id()`, `get_kamar_tersedia()`, `hitung_kamar_per_status()` | id, nomor, harga, fasilitas[], deskripsi, foto, galeri[], status |
| `data/penghuni.php` | `get_semua_penghuni()`, `get_penghuni_by_id()`, `get_penghuni_by_kamar()`, `get_penghuni_login()`, `alasan_kamar_tidak_tersedia()` | id, nama, no_telepon, akun, kamar_id, tanggal_masuk, tanggal_keluar, status |
| `data/tagihan.php` | `get_semua_tagihan()`, `get_tagihan_by_id()`, `get_tagihan_by_penghuni()`, `get_tagihan_berjalan()`, `get_tagihan_belum_lunas()`, `daftar_periode_tagihan()`, `ringkasan_tagihan_periode()`, `ringkasan_tagihan_belum_lunas()`, `status_tagihan()` (dihitung, bukan disimpan) | id, penghuni_id, periode (YYYY-MM), jumlah, jatuh_tempo, keterangan |
| `data/pembayaran.php` | `get_semua_pembayaran()`, `get_pembayaran_by_id()`, `get_pembayaran_by_tagihan()`, `get_pembayaran_by_penghuni()`, `ringkasan_pembayaran_periode()`, `ringkasan_pembayaran_bulan_ini()` | id, tagihan_id, tanggal_bayar, nominal_dibayar, metode (tunai/transfer), keterangan |
| `data/notifikasi.php` | `get_notifikasi_penghuni()`, `hitung_notifikasi_belum_dibaca()`, `buat_notifikasi_tagihan()` | Dihasilkan dari tagihan; hanya status baca yang disimpan sebagai data contoh |

Penting: `data/tagihan.php` dan `data/pembayaran.php` saling `require_once` (aman karena hanya memanggil fungsi, tidak ada kode tingkat atas yang saling bergantung).

## 1.5 Komponen PHP Include (`includes/`)

- `includes/config.php` — konstanta situs (nama, lokasi, WhatsApp, alamat, Maps, path foto) + memuat `functions.php`.
- `includes/functions.php` — helper lintas halaman: `e()`, `format_rupiah()`, `format_tanggal()`, `format_periode()`, `nama_bulan()`, `url_foto()`, `daftar_foto_kamar()`, `link_hubungi_pemilik()`, `url_whatsapp()`, `tombol_whatsapp()`, `url_maps_embed()`, `url_maps_link()`, `label_status()`, `label_status_penghuni()`, `label_status_tagihan()`, `label_metode_pembayaran()`, `nav_active()`, `tanggal_hari_ini()`.
- `includes/header.php` / `includes/footer.php` — layout publik.
- `includes/card-kamar.php` — kartu kamar dipakai ulang di beranda & daftar kamar.
- `admin/includes/header.php` / `penghuni/includes/header.php` — layout dashboard (sidebar + topbar), berbagi `assets/css/admin.css` dan `assets/js/admin.js`.

## 1.6 Form yang Sudah Ada

| Form | Lokasi | Field | Status penyimpanan |
|---|---|---|---|
| Tambah/Edit Kamar | `admin/kamar-form.php` | nomor, harga, deskripsi, fasilitas, status, foto (upload) | Prototype — tidak disimpan |
| Tambah/Edit Penghuni | `admin/penghuni-form.php` | nama, no_telepon, akun (email/username), kamar_id, tanggal_masuk, status | Prototype — tidak disimpan |
| Buat Tagihan | `admin/tagihan.php` (modal) | penghuni_id, periode, jumlah, jatuh_tempo, keterangan | Prototype — tidak disimpan, **hanya mode satu penghuni per tagihan** |
| Catat Pembayaran | `admin/pembayaran.php` (modal) | tagihan_id, tanggal_bayar, nominal_dibayar, metode, keterangan | Prototype — tidak disimpan, validasi nominal harus sama persis dengan tagihan |
| Pengaturan Info Kos | `admin/pengaturan.php` | nama_kos, whatsapp, alamat, deskripsi, maps_link, maps_embed, foto_kos | Prototype — tidak disimpan (nilai dari konstanta `config.php`) |
| Ubah Password Admin | `admin/pengaturan.php` | password_lama, password_baru, konfirmasi_password | Prototype — tidak diverifikasi/disimpan |
| Ubah Password Penghuni | `penghuni/profil.php` | password_lama, password_baru, konfirmasi_password | Prototype — tidak diverifikasi/disimpan |
| Login | `login.php` | username, password | Tampilan saja, tidak diproses |

## 1.7 Struktur Navigasi

Tiga navigasi independen (sesuai desain yang sudah berjalan, **tidak diubah**):
- Publik: Beranda / Kamar / Tentang / Kontak / Login.
- Admin (sidebar): Dashboard / Data Kamar / Data Penghuni / Tagihan / Pembayaran / Laporan / Pengaturan.
- Penghuni (sidebar): Dashboard / Kamar Saya / Tagihan / Riwayat Pembayaran / Notifikasi / Profil.

Tidak ada tautan lintas-role (penghuni tidak melihat menu admin, dan sebaliknya) — ini **pembatasan tampilan saja**, bukan otorisasi sungguhan (lihat bagian keamanan di `04-penyesuaian-frontend.md`).

## 1.8 Status Placeholder

Berdasarkan audit menyeluruh pada tahap sebelumnya (termasuk pengujian browser otomatis), **tidak ada lagi halaman placeholder atau fitur frontend yang belum lengkap**. Tiga halaman yang sebelumnya placeholder (`admin/laporan.php`, `admin/pengaturan.php`, `penghuni/riwayat.php`) sudah selesai dibangun dan diuji. Satu-satunya fitur yang belum ada di UI (bukan placeholder, tetapi memang belum pernah dirancang di frontend) adalah **form "Buat Tagihan Massal"** untuk membuat tagihan ke seluruh penyewaan aktif sekaligus — lihat `04-penyesuaian-frontend.md`.
