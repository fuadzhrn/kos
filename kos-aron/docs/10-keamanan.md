# Audit keamanan Kos Aron

Pemeriksaan pada 20 November 2026 mencakup tujuh entry point di root aplikasi, sepuluh halaman admin, enam halaman penghuni, tiga alat CLI, lapisan akses MySQL dan kedua folder foto. Kolom bukti membedakan hasil membaca kode dari respons yang teramati melalui HTTP.

## Kontrol dan bukti

| Area | Kontrol yang diperiksa | Bukti |
|---|---|---|
| Query MySQL | PDO memakai prepared statement native. Nilai dari formulir, ID objek, periode laporan dan batas daftar diikat sebagai parameter. Fragmen SQL dinamis seperti daftar kolom pembaruan kamar dan filter pembayaran berasal dari pilihan tetap dalam kode. | Tinjauan `includes/koneksi.php` dan seluruh modul `data/*.php`. Daftar tabel pada pemeriksaan CLI berasal dari array tetap. |
| Tampilan HTML dan JavaScript | Helper `e()` memakai `htmlspecialchars` dengan `ENT_QUOTES`, `ENT_SUBSTITUTE` dan UTF-8 untuk teks dan atribut. Nama, keterangan, pengaturan dan data detail pembayaran melalui escaping pada templat. Modal menulis teks lewat `textContent`. | Tinjauan `includes/functions.php`, templat publik, admin, penghuni serta `assets/js/admin.js`. Tautan dan embed peta juga dibatasi ke skema HTTPS, host dan path yang diizinkan. |
| Formulir dan CSRF | Login, logout, operasi admin, perubahan profil/password dan penandaan notifikasi memakai POST dan memeriksa token sebelum tindakan. Token sesi dibuat dari 32 byte acak dan dibandingkan dengan `hash_equals`. | Tinjauan handler dan formulir terkait serta `csrf_valid()` dan `csrf_field()` pada `includes/auth.php`. |
| Sesi dan login | ID sesi berbasis cookie, strict mode aktif, cookie sesi memakai HttpOnly dan SameSite=Lax. Secure mengikuti deteksi HTTPS. Login merotasi ID sesi dan token CSRF. Permintaan privat memeriksa peran, status akun dan sidik hash password yang berlaku. Logout menghapus sesi dan cookie. | Tinjauan `includes/auth.php`, `login.php`, `logout.php`. Pesan gagal login generik. Batas lima percobaan dan jeda 60 detik berlaku per identitas dalam satu sesi. |
| Password | CLI admin, pengelolaan akun penghuni dan halaman ganti password menyimpan hasil `password_hash(..., PASSWORD_DEFAULT)`. Login memanggil `password_verify()`. Perubahan password sendiri memverifikasi password saat ini dan mencocokkan hash lama pada operasi UPDATE. | Tinjauan `database/buat-admin.php`, `data/penghuni.php`, `includes/auth.php`, `admin/pengaturan.php` dan `penghuni/profil.php`. |
| Otorisasi admin | Kesepuluh halaman memanggil `require_role('admin', ...)` sebelum logika halaman atau POST. Penolakan untuk pengguna dengan peran berbeda mengakhiri eksekusi dengan 403. | Tinjauan sepuluh entry point `admin/*.php`. HTTP tanpa sesi ke masing-masing menghasilkan 302 menuju login. |
| Pemilik data penghuni | Keenam halaman memakai `penghuni/includes/konteks.php`. Identitas penghuni diturunkan dari ID akun dalam sesi. Tagihan dan pembayaran difilter melalui pemilik penyewaan. Detail pembayaran dan penandaan notifikasi memeriksa ID objek bersama ID penghuni. Perubahan profil memakai ID akun sesi. | Tinjauan `data/penghuni.php`, `data/tagihan.php`, `data/pembayaran.php` dan `data/notifikasi.php`. HTTP tanpa sesi ke masing-masing halaman menghasilkan 302 menuju login. |
| Foto | Hanya admin dapat mengirim foto melalui form kamar atau pengaturan kos. Helper memeriksa sumber unggahan PHP, batas 2 MiB, MIME dari Fileinfo dan metadata gambar via `getimagesize()`. Ekstensi keluaran berasal dari MIME JPEG, PNG atau WebP, dengan nama acak 16 byte. Apache menyajikan foto lewat handler statis. | Tinjauan `includes/upload.php` dan kedua pemanggil. Permintaan HTTP atas PNG contoh di masing-masing folder menghasilkan 200 dengan tipe `image/png`. |
| Data contoh dan rahasia | Seed diberi label fiktif dan membuat akun contoh berstatus nonaktif. Halaman mengambil data operasional melalui PDO. File konfigurasi kredensial lokal diabaikan Git. | Tinjauan `database/seed.sql`, akses data dan hasil `git check-ignore kos-aron/includes/database.local.php`. Isi database operasional tidak disalin ke laporan ini. |

## Berkas internal dan galat

Aturan Apache di root repositori membatasi metadata Git dan direktori skrip saat repositori dilayani dari Laragon. Aturan di root aplikasi membatasi SQL, berkas cadangan, konfigurasi lokal, dokumen dan direktori PHP internal, termasuk fragmen layout admin dan penghuni. Daftar direktori dinonaktifkan. Pada dua direktori unggahan, Apache melayani ekstensi foto yang diizinkan dengan handler statis. Pemeriksaan CLI berjalan sebelum pemuatan koneksi pada alat uji database.

Bootstrap web memasang penanganan galat sebelum mengambil pengaturan situs. Respons untuk pengecualian yang tidak tertangani berisi pesan umum dengan status 500. Buffer halaman dibuang dan log penanganan ini mencatat jenis galat, nama berkas dan nomor baris. Log kegagalan koneksi mencatat kode galat.

## Verifikasi Apache lokal

Apache Laragon 2.4.62 dengan modul PHP 8.3.16 dijalankan di loopback pada port sementara. DocumentRoot berisi salinan aplikasi, aturan Apache yang sama dan berkas contoh tanpa data pribadi. Pengujian memakai `mod_rewrite`, `mod_authz_core` serta `AllowOverride All`. Proses dihentikan setelah verifikasi.

| Permintaan atau pemeriksaan | Hasil yang diamati |
|---|---|
| Validasi konfigurasi Apache | `Syntax OK` |
| Berkas contoh di metadata Git, skrip, database, data, dokumen, tiga folder includes, salinan cadangan serta dua penanda folder unggahan | Semua 11 permintaan menghasilkan 403 |
| PNG di folder foto kamar dan foto kos | Kedua respons 200, bertipe `image/png` dan berisi data PNG |
| Beranda, tentang, daftar kamar, kontak, login | Kelima respons 200 |
| Semua URL halaman admin dan penghuni tanpa sesi | Semua 16 respons 302 menuju `../login.php?alasan=harus-login` |
| Halaman login pada instalasi uji tanpa konfigurasi database lokal | Respons 500, `text/plain`, dengan isi persis “Halaman tidak dapat dimuat saat ini. Silakan coba lagi nanti.” |

## Batas verifikasi

Aturan `.htaccess` memerlukan Apache dengan izin override yang sesuai. PHP built-in server mengabaikannya sehingga penggunaannya terbatas untuk pengembangan lokal di loopback. Pada Nginx atau host lain, aturan direktori privat dan handler foto perlu diterapkan lewat konfigurasi server tersebut. Hasil HTTP di atas berasal dari Apache uji lokal. Konfigurasi host produksi, TLS, hak akses filesystem produksi dan perilaku reverse proxy berada di luar pengujian ini. Bila HTTPS diakhiri pada proxy, proxy tepercaya perlu menetapkan header skema yang diteruskan ke aplikasi.

Audit sumber memeriksa pembatasan objek terhadap identitas sesi. Uji Apache dalam dokumen ini mencakup navigasi tamu, penyajian berkas, dan kegagalan bootstrap. Pengujian transaksi dan autentikasi dengan akun uji dijelaskan terpisah dalam hasil pengujian proyek.
