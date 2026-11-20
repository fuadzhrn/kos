# Panduan penggunaan dan instalasi Kos Aron

## Menjalankan melalui XAMPP di Windows

Aplikasi memakai PHP native, PDO MySQL, HTML, CSS dan JavaScript vanilla. Pengujian proyek menggunakan PHP 8.3 dan MySQL 8.4 dengan InnoDB. Panduan berikut memakai Apache/PHP XAMPP dengan layanan **MySQL 8.4** terpisah. Paket XAMPP modern menyertakan MariaDB. Kompatibilitas penuh dengan MariaDB bawaan belum diuji. [FAQ resmi XAMPP](https://www.apachefriends.org/faq_windows.html)

1. Salin folder aplikasi `kos-aron` ke `C:\xampp\htdocs\kos-aron`, termasuk berkas tersembunyi `.htaccess`. Pastikan Apache memuat mod_rewrite dan konfigurasi direktori mengizinkan aturan .htaccess. Folder scripts, riwayat Git dan jawaban penelitian disimpan di luar document root.
2. Aktifkan Apache dari XAMPP Control Panel dan jalankan layanan MySQL 8.4 pada port yang dipilih. Hindari menjalankan dua mesin database pada port sama. Periksa versi serta modul PHP dari terminal.

   ```powershell
   C:\xampp\php\php.exe -v
   C:\xampp\php\php.exe -m
   ```

   Diperlukan PDO, pdo_mysql, mbstring, fileinfo, JSON dan session. Berikan akses tulis pada direktori sesi PHP serta `assets/images/kamar` dan `assets/images/kos`. Untuk situs publik aktifkan HTTPS dan arahkan log PHP ke lokasi privat.
3. Pada **instalasi baru dengan database kosong**, impor `database/schema.sql` melalui klien MySQL 8.4 atau phpMyAdmin yang tersambung ke layanan itu. Berkas ini memilih `db_kos_aron` dan berisi DROP TABLE. Untuk instalasi terdahulu lakukan backup terpisah, cocokkan versi skema, lalu gunakan migrasi 07 dan 09 yang belum diterapkan. Untuk instalasi baru maupun upgrade dari sembilan tabel, pilih database aplikasi dan impor `database/migrasi-registrasi-pemesanan.sql` setelah skema dasar tersedia. Simpan seed khusus untuk database pengujian.
4. Salin `includes/database.local.example.php` menjadi `includes/database.local.php`. Isi host, port, nama database, dan akun aplikasi yang telah diberi akses. Gunakan akun terpisah untuk operasi DDL. Simpan kredensial di luar Git.
5. Dari terminal, jalankan perintah berikut. Pembuatan admin meminta identitas dan password secara interaktif.

   ```powershell
   cd C:\xampp\htdocs\kos-aron
   C:\xampp\php\php.exe database\uji-koneksi.php
   C:\xampp\php\php.exe database\buat-admin.php
   ```

6. Buka `http://localhost/kos-aron/`, masuk di menu Login, lalu isi Pengaturan. Verifikasi tautan kontak, gambar utama, tampilan kamar, dan akses masing-masing peran. Panduan ini disusun untuk XAMPP. Uji aplikasi pada audit dilakukan di lingkungan PHP/MySQL Laragon. Untuk penjadwalan, lihat [panduan pengingat](09-notifikasi-dan-scheduler.md).

## Hak akses dan panduan pengunjung

| Peran | Kegiatan |
|---|---|
| Pengunjung | Beranda, daftar dan detail kamar, registrasi untuk pemesanan, Tentang, Kontak, dan Login. Tombol WhatsApp membuka percakapan kepada pemilik ketika kontak sudah diisi. |
| Admin atau pemilik | Meninjau dan mengaktifkan pemesanan, mengelola kamar, penghuni dan sewa, membuat tagihan, mencatat atau mengoreksi pembayaran, mencetak laporan, mengubah pengaturan dan password sendiri. |
| Penghuni aktif yang login | Melihat kamar, tagihan, pembayaran dan notifikasi miliknya. Mengubah profil yang diizinkan serta password sendiri. Logout mengakhiri sesi. |

## Panduan admin

1. **Data Kamar.** Tambahkan nomor unik, harga, fasilitas, kondisi, dan foto bila tersedia. Periksa daftar dan halaman publik. Status terisi mengikuti penyewaan aktif. Akhiri penyewaan yang berjalan sebelum menjadikan kamar berstatus perawatan. Penghapusan kamar dibatasi untuk menjaga riwayat.
2. **Data Penghuni.** Tambahkan identitas, nomor telepon, akun serta password awal. Pilih kamar tersedia, tanggal masuk, harga yang disepakati, dan hari jatuh tempo. Gunakan Detail untuk memeriksa riwayat sewa. Bila penghuni keluar, isikan tanggal keluar dan konfirmasi penonaktifan. Jika menyewa kembali, edit profil lama dan mulai penyewaan baru.
3. **Tagihan.** Pilih pembuatan individual atau massal, pilih periode dan periksa pratinjau. Konfirmasi untuk menyimpan. Harga bersumber dari perjanjian sewa. Hari 29–31 disesuaikan ke akhir bulan. Bila tanggal masuk melampaui jatuh tempo bulan pertama, penagihan dimulai bulan berikutnya. Lihat jumlah berhasil, dilewati dan gagal setelah konfirmasi.
4. **Data Pembayaran.** Setelah uang diterima, pilih Catat Pembayaran. Pilih tagihan belum lunas, cocokkan nominal penuh, masukkan tanggal, metode tunai atau transfer, dan keterangan. Periksa Detail. Untuk salah pencatatan, buka koreksi atau pembatalan, jelaskan alasannya. Pembatalan membuka tagihan kembali dan menyimpan transaksi lama beserta jurnal. Untuk mengganti tagihan yang salah, batalkan lalu catat pada tagihan yang benar.
5. **Laporan.** Pilih periode dan jenis laporan. Pembayaran dikelompokkan sesuai periode tagihan. Selisih antara total tagihan dan pembayaran sah menunjukkan sisa tagihan. Kondisi hunian mengacu pada tanggal yang tertulis di laporan. Klik Cetak Laporan untuk mencetak atau menyimpan PDF.
6. **Pengaturan.** Simpan nama kos, deskripsi, alamat, nomor WhatsApp berkode negara, tautan Google Maps dan foto. Periksa Beranda, Tentang dan Kontak sesudah menyimpan. Form password meminta password saat ini, password baru dan konfirmasinya.

Untuk memproses calon penghuni dari pengajuan publik, ikuti [panduan pemesanan](registrasi-pemesanan.md).

## Panduan penghuni

1. Masuk dengan identitas yang diberikan admin. Dashboard memperlihatkan kamar, harga kesepakatan, tagihan bulan ini, jatuh tempo, status, riwayat singkat dan jumlah notifikasi belum dibaca.
2. Buka **Kamar Saya** untuk rincian penyewaan aktif. Di **Tagihan**, periksa periode, nominal dan jatuh tempo. Bayar langsung kepada pemilik. Status berubah menjadi lunas setelah admin mencatat pembayaran penuh.
3. Buka **Riwayat** untuk menyaring periode dan melihat detail transaksi. Di **Notifikasi**, baca pengingat lalu pilih **Tandai dibaca**. Riwayat pembayaran lama tetap terkait dengan penyewaan asal.
4. Di **Profil**, perbarui nama, telepon atau identitas login yang diizinkan. Untuk mengganti password, masukkan password lama dan konfirmasi yang baru. Pilih **Keluar** setelah selesai, terutama pada perangkat bersama.
