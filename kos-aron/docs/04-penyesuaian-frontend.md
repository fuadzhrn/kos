# 4. Daftar Penyesuaian yang Mungkin Diperlukan pada Frontend

Daftar ini **bukan daftar kerjaan yang langsung dilakukan sekarang** — sesuai
instruksi, implementasi backend belum dijalankan. Ini adalah catatan tinjauan
untuk tahap integrasi nanti, agar desain dan navigasi yang sudah jadi
**tidak perlu dirombak**, hanya disambungkan ke database.

## 4.1 Penyesuaian yang TIDAK mengubah desain (hanya mengganti isi fungsi `data/*.php`)

Ini bagian terbesar dan paling aman: setiap fungsi di `data/kamar.php`,
`data/penghuni.php`, `data/tagihan.php`, `data/pembayaran.php`,
`data/notifikasi.php` tetap punya nama dan bentuk array hasil yang sama,
hanya isinya diganti dari `return [...]` (array hardcode) menjadi query PDO
ke `db_kos_aron`. Halaman yang memanggilnya (`admin/*.php`, `penghuni/*.php`,
halaman publik) **tidak perlu diubah sama sekali**. Ini sudah menjadi
komentar desain yang konsisten di setiap file `data/*.php` saat ini.

## 4.2 Penyesuaian struktur data penghuni (akibat pemisahan `penyewaan`)

Karena `kamar_id`, `tanggal_masuk`, `tanggal_keluar`, `status` dipindah dari
`penghuni` ke tabel baru `penyewaan`, fungsi-fungsi berikut perlu sedikit
penyesuaian **internal** (query-nya, bukan tampilannya):
- `get_penghuni_by_id()`, `get_semua_penghuni()` — perlu join ke `penyewaan`
  aktif untuk tetap mengembalikan `kamar_id`, `tanggal_masuk`, `status` di
  dalam array hasil (supaya halaman seperti `admin/penghuni.php`,
  `admin/penghuni-detail.php`, `admin/penghuni-form.php` yang sudah memakai
  nama kunci ini **tidak perlu diubah**).
- `alasan_kamar_tidak_tersedia()` — logikanya tetap sama, hanya sumber datanya
  dari `penyewaan` bukan `penghuni.kamar_id` langsung.
- Riwayat penyewaan (penghuni pindah/keluar-masuk kembali) belum punya
  tampilan di `admin/penghuni-detail.php` saat ini — **boleh ditambahkan**
  sebagai bagian baru (misalnya tabel "Riwayat Penyewaan") tanpa mengubah
  bagian yang sudah ada di halaman itu.

## 4.3 Fitur baru yang perlu elemen UI tambahan (bukan perubahan desain yang sudah ada)

- **Tagihan massal**: `admin/tagihan.php` saat ini hanya punya modal "Buat
  Tagihan" untuk satu penghuni. Aturan bisnis meminta kemampuan membuat
  tagihan untuk seluruh penyewaan aktif sekaligus. Disarankan: tombol kedua
  di samping "Buat Tagihan" yang sudah ada (misalnya "Buat Tagihan Massal"),
  memakai gaya tombol & modal yang sama (`.btn`, `.modal`) — bukan desain baru.
- **Login sungguhan**: `login.php` saat ini hanya menampilkan pesan "fitur
  belum aktif". Saat backend siap, form yang sama (username, password, toggle
  lihat password) **dipakai apa adanya**, hanya `$_SERVER['REQUEST_METHOD'] ===
  'POST'` di bagian atas file diganti proses `password_verify()` + membuat
  session + redirect ke `admin/index.php` atau `penghuni/index.php` sesuai
  `role`.
- **Ubah password (admin & penghuni)**: form di `admin/pengaturan.php` dan
  `penghuni/profil.php` sudah lengkap secara tampilan (termasuk validasi
  client-side). Saat terintegrasi, bagian PHP di atasnya tinggal diisi
  `password_verify($password_lama, ...)` lalu `password_hash($password_baru, ...)`
  — markup form tidak berubah.
- **Pengaturan info kos**: form di `admin/pengaturan.php` saat ini membaca
  dari konstanta `config.php` (tidak bisa disimpan). Saat terintegrasi, nilai
  awal form dibaca dari tabel `pengaturan`, dan submit melakukan
  `INSERT ... ON DUPLICATE KEY UPDATE` per `kunci` — markup form tidak berubah.

## 4.4 Keamanan — wajib di tahap backend, bukan sekarang

Pembatasan akses saat ini (penghuni tidak melihat menu admin, dan sebaliknya)
**murni tampilan/navigasi**, bukan otorisasi nyata — siapa pun yang mengetik
langsung URL `admin/*.php` atau `penghuni/*.php` saat ini bisa mengaksesnya
tanpa login sama sekali. Ini **harus** diperbaiki saat backend dibuat:
- Setiap halaman di `admin/` harus memverifikasi `$_SESSION['role'] === 'admin'`.
- Setiap halaman di `penghuni/` harus memverifikasi `$_SESSION['role'] === 'penghuni'`
  (pola pengecekan kepemilikan data seperti yang sudah dipakai di
  `penghuni/riwayat.php?lihat=ID` saat ini — memverifikasi `penghuni_id` milik
  sendiri — harus diterapkan konsisten di semua halaman penghuni lainnya).
- Ini sudah dicatat sebagai pengingat berulang di seluruh project dan tetap
  berlaku di tahap ini: **jangan menganggap pembatasan tampilan sebagai
  keamanan sungguhan.**

## 4.5 Yang TIDAK perlu disesuaikan (aman persis seperti sekarang)

- Seluruh CSS (`assets/css/style.css`, `assets/css/admin.css`) — tidak ada
  perubahan desain yang diperlukan rancangan ini.
- Struktur navigasi tiga sisi (publik / admin / penghuni) — tetap seperti
  sekarang.
- `includes/functions.php` — semua helper (`format_rupiah()`, `format_tanggal()`,
  dll.) dipakai apa adanya oleh kode backend nanti.
- Field dan validasi pada form yang sudah ada (`kamar-form.php`,
  `penghuni-form.php`, modal tagihan & pembayaran) — nama field (`name="..."`)
  pada form-form ini **sengaja** sudah dicocokkan dengan nama kolom rancangan
  database ini (`nama`, `no_telepon`, `nomor`, `harga`, `periode`, `jumlah`,
  `jatuh_tempo`, `tanggal_bayar`, `nominal_dibayar`, `metode`, dst.), sehingga
  nanti `$_POST[...]` bisa langsung dipetakan ke kolom tanpa mengganti nama
  field di HTML.
