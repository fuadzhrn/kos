# Registrasi calon penghuni dan pemesanan kamar

## Bagi calon penghuni

Buka **Daftar Kamar**, pilih kamar yang tersedia, lalu tekan **Pesan Kamar**. Periksa foto, nomor kamar, fasilitas dan harga bulanan. Isi nama, nomor telepon atau WhatsApp, rencana masuk dan, bila diperlukan, email serta catatan. Calon penghuni dapat mengajukan permohonan tanpa akun.

Simpan nomor referensi yang muncul pada konfirmasi. Status pertama adalah **Menunggu Konfirmasi**. Gunakan kontak pemilik yang tercantum untuk menindaklanjuti pengajuan. Beberapa calon dapat mengajukan kamar yang sama selama kamar masih tersedia.

## Bagi admin atau pemilik

1. Masuk sebagai admin. Buka **Data Pemesanan**, gunakan filter atau pencarian, kemudian buka **Detail**.
2. Periksa identitas, kamar, rencana masuk, dan catatan. **Tolak Pemesanan** menyimpan keputusan beserta alasan bila diisi. **Setujui Pemesanan** menahan kamar. Admin dapat membatalkan persetujuan yang belum menjadi penyewaan untuk melepaskan kamar. Riwayat pengajuan tersimpan.
3. Saat calon penghuni benar-benar mulai menyewa, buka kembali detail pemesanan yang disetujui, lalu pilih **Proses Menjadi Penghuni**. Periksa tanggal mulai yang sudah berlangsung, harga sewa kesepakatan dan hari jatuh tempo 1–31.
4. Jika sistem menemukan profil dengan telepon atau email yang cocok, cocokkan identitas secara langsung dan pilih profil yang benar. Akun lama diaktifkan kembali dengan password sebelumnya. Jika profil membutuhkan akun baru, masukkan username atau email login unik dan password awal pribadi melalui form admin. Sampaikan kredensial secara pribadi kepada penghuni.
5. Konfirmasikan aktivasi. Akun, profil, penyewaan aktif dan hubungan ke pemesanan disimpan dalam satu transaksi. Detail pemesanan menampilkan nomor penyewaan. Penyewaan aktif dapat diperiksa melalui **Data Penghuni**. Untuk penghuni yang datang langsung, form tambah penghuni manual tetap tersedia.

| Keadaan | Dampak pada kamar |
|---|---|
| Menunggu | Dapat menerima pengajuan lain selama kamar kosong dan aktif. |
| Disetujui, belum dikonversi | Masih kosong secara fisik. Kamar ditahan untuk pemesan hingga aktivasi atau pembatalan admin. |
| Ditolak atau dibatalkan | Pengajuan tetap tercatat. Ketersediaan mengikuti keadaan kamar dan pengajuan lain. |
| Disetujui dengan penyewaan aktif | Terisi. Setelah penyewaan berakhir, ketersediaan kembali mengikuti kondisi kamar. |

## Instalasi dan integritas

Pada database aplikasi sembilan tabel, buat backup dengan alat MySQL dan simpan di luar document root dan Git. Periksa tabel, jumlah baris serta relasi yang sudah ada. Pilih database sasaran, lalu jalankan [migrasi registrasi dan pemesanan](../database/migrasi-registrasi-pemesanan.sql) melalui phpMyAdmin atau klien MySQL. Migrasi hanya berisi dua perintah pembuatan tabel. Untuk instalasi kosong, pasang [skema dasar](../database/schema.sql) terlebih dahulu. Jalankan `php database/uji-koneksi.php` untuk memeriksa akses PDO ke sebelas tabel.

**calon_penghuni** menyimpan identitas yang diberikan untuk pengajuan. **pemesanan** menyimpan referensi acak unik, kamar, waktu pengajuan, rencana masuk, status, catatan, admin pemroses, dan kaitan ke penyewaan. Keempat foreign key dan indeks unik mencegah rujukan yang terputus, dua penahanan pada satu kamar, serta pemakaian ulang satu penyewaan pada pemesanan lain.

Setiap perubahan ketersediaan mengunci baris kamar di MySQL. Pengajuan dari nomor telepon yang sama dibatasi hingga tiga kali dalam satu jam termasuk dari sesi berbeda. Jumlah kamar terisi tetap dihitung dari penyewaan aktif. Penahanan yang disetujui tetap berlaku hingga admin mengaktifkan sewa atau membatalkannya.

## Berkas implementasi

| Bagian | Berkas baru | Berkas yang disesuaikan |
|---|---|---|
| Situs publik | `pesan-kamar.php` | `kamar.php`, `detail-kamar.php`, `includes/card-kamar.php`, `assets/css/style.css` |
| Admin | `admin/pemesanan.php`, `admin/pemesanan-detail.php` | `admin/includes/header.php`, `admin/index.php`, `admin/penghuni-form.php` |
| Backend dan database | `data/pemesanan.php`, `database/migrasi-registrasi-pemesanan.sql` | `data/kamar.php`, `data/penghuni.php`, `database/uji-koneksi.php`, `.gitignore` di root |
| Pengujian di folder scripts | `uji-pemesanan-http.py`, `uji-integritas-pemesanan.py` | `uji-responsive.mjs`, `uji-tahap-09.py`, `uji-tahap-10.py` |
| Dokumentasi di folder docs | `registrasi-pemesanan.md`, `hasil-uji-pemesanan.md` | `README.md`, `05-panduan-import-database.md`, `10-panduan.md`, `10-struktur-dan-erd.md`, `CODEX_HANDOFF.md`, `DEVELOPMENT_PROGRESS.md` |

[Hasil 24 skenario black box dan empat uji integritas](hasil-uji-pemesanan.md).
