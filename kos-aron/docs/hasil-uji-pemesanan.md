# Hasil pengujian registrasi dan pemesanan kamar

Pada 20 November 2026, pengujian menjalankan aplikasi PHP melalui HTTP dengan MySQL sementara berisi data fiktif. Akun tamu, admin dan penghuni memakai sesi terpisah. Hasil aktual di bawah berasal dari 23 skenario HTTP serta satu skenario responsif. Skrip memeriksa jumlah dan relasi baris langsung di MySQL untuk operasi yang mengubah data.

| ID | Fitur | Langkah dan input | Hasil yang diharapkan | Hasil aktual | Status |
|---|---|---|---|---|---|
| P01 | Daftar kamar | Buka Daftar Kamar sebagai tamu dan periksa tautan kamar 01. | Tautan pesan kamar kosong tersedia. | Tautan `pesan-kamar.php?id=1` ada di daftar. | Berhasil |
| P02 | Detail kamar | Buka detail kamar lewat tautan dari daftar. | Judul dan tautan pesan menunjuk kamar tersebut. | Judul Kamar 01 dan tautan ke formulir kamar 01 ditemukan. | Berhasil |
| P03 | Registrasi valid | Isi nama, telepon, tanggal masuk dan kirim formulir. | Identitas dan pengajuan tersimpan. | Tersimpan satu identitas dan satu pemesanan. Resi menampilkan referensi dan kontak pemilik. | Berhasil |
| P04 | Identitas kosong | Kosongkan identitas wajib lalu kirim. | Server menolak pengajuan. Jumlah baris tetap. | HTTP 422 dengan pesan nama wajib. Jumlah calon dan pemesanan tetap. | Berhasil |
| P05 | Telepon tidak valid | Kirim karakter nonnomor pada kolom telepon. | Server menolak dan menjelaskan kesalahan. | Nomor `abc` menghasilkan HTTP 422. Jumlah calon dan pemesanan tetap. | Berhasil |
| P06 | Kamar tersedia | Kirim pengajuan ke kamar kosong berkondisi aktif. | Pengajuan tersimpan dengan ID kamar yang dipilih. | Ketiga pemohon dapat mengajukan kamar 01 selama seluruh pengajuan masih menunggu. | Berhasil |
| P07 | Kamar terisi | Coba ajukan kamar dengan penyewaan aktif. | Pemesanan ditolak dan jumlah pengajuan tetap. | POST kamar 04 menghasilkan HTTP 409. Jumlah pengajuan tetap tiga. | Berhasil |
| P08 | Referensi unik | Buat beberapa pengajuan dengan data fiktif. | Referensi berbeda untuk pengajuan berbeda. | Tiga kode KA- yang berbeda cocok dengan referensi di MySQL. | Berhasil |
| P09 | Status awal | Periksa status setelah pengajuan berhasil. | Resi menyebut Menunggu Konfirmasi dan database menyimpan `menunggu`. | Ketiga baris berstatus `menunggu`. Resi menampilkan Menunggu Konfirmasi. | Berhasil |
| P10 | Daftar admin | Masuk sebagai admin dan cari pengajuan pada daftar pemesanan. | Pemesanan tampil pada daftar. Filter dan pencarian sesuai input. | Filter menunggu dan kode pertama menampilkan pengajuan yang dicari. Kode kedua tidak tampil. | Berhasil |
| P11 | Detail admin | Buka detail dari daftar pemesanan. | Admin melihat identitas dan kode pengajuan yang dipilih. | Alamat email fiktif dan kode pengajuan pertama tampil di detail. | Berhasil |
| P12 | Persetujuan | Setujui pengajuan yang masih tersedia. | Keputusan tersimpan dan pesanan siap diproses menjadi penyewaan. | Kolom penahanan kamar 01 merujuk pemesanan pertama. | Berhasil |
| P13 | Penolakan | Tolak pengajuan menunggu dan isikan alasan. | Status ditolak dan alasan dapat dibaca kembali. | Status `ditolak` dan alasan “Rencana berubah” tersimpan di pengajuan ketiga. | Berhasil |
| P14 | Konflik persetujuan | Setujui dua pengajuan menunggu untuk kamar yang sama. | Maksimal satu pengajuan memperoleh penahanan kamar. | Persetujuan kedua menghasilkan HTTP 422. Hanya satu pemesanan disetujui. INT01 juga menguji dua koneksi serentak. | Berhasil |
| P15 | Status sebelum aktivasi | Periksa kamar setelah persetujuan dan sebelum aktivasi. | Kamar tetap kosong secara fisik, tetapi tidak tersedia untuk pengajuan baru. | Kamar 01 memiliki nol penyewaan aktif. Tautan pesan hilang dari detail. | Berhasil |
| P16 | Aktivasi | Isi tanggal masuk, harga kesepakatan dan hari jatuh tempo. Konfirmasikan. | Satu penyewaan aktif terhubung ke pemesanan yang benar. | Aktivasi dengan tanggal uji, Rp700.000 dan jatuh tempo 5 menambah satu akun, satu profil dan satu penyewaan. | Berhasil |
| P17 | Status setelah aktivasi | Periksa status kamar setelah penyewaan aktif. | Kamar tampil terisi dan tidak dapat dipesan lagi. | Kamar 01 memiliki tepat satu sewa aktif dan detail publik menampilkan Terisi. | Berhasil |
| P18 | Persistensi | Baca kembali data penghuni, akun dan penyewaan. | Data yang tersimpan menunjuk penghuni dan kamar yang sama. | Query pengajuan sampai akun mengembalikan username uji, nama pemesan yang benar dan hari jatuh tempo 5. | Berhasil |
| P19 | Kegagalan dan pengulangan | Aktifkan dengan hari jatuh tempo 32. Coba kembali dengan 5, lalu kirim ulang setelah berhasil. | Tidak ada akun, profil atau penyewaan ganda maupun data setengah tersimpan. | Hari 32 ditolak (422), jumlah akun, penghuni dan penyewaan tetap. Pengulangan setelah aktivasi sukses ditolak (422) dan ketiga jumlah tetap. | Berhasil |
| P20 | Tamu dan admin | Buka detail pengajuan sebagai tamu. | Halaman menampilkan login dan menyembunyikan rincian pemesanan. | Tamu menerima formulir login. Kode pengajuan tidak tampil. | Berhasil |
| P21 | Role penghuni | Akses aksi persetujuan sebagai penghuni. | Akses ditolak. Keputusan pemesanan tetap. | POST persetujuan sebagai penghuni menghasilkan HTTP 403. Ketiga pengajuan tetap menunggu. | Berhasil |
| P22 | CSRF | Kirim formulir publik dan tindakan admin dengan token salah. | Permintaan ditolak dan data tetap. | POST publik dan admin tanpa token menghasilkan HTTP 403. Tiga calon, tiga pengajuan, seluruh status tetap menunggu. | Berhasil |
| P23 | Karakter khusus | Masukkan teks fiktif yang memakai tanda kutip dan karakter HTML. | Aplikasi memperlakukan masukan sebagai teks, termasuk saat ditampilkan kembali. | Nama dengan apostrof dan ampersand serta catatan dengan kurung sudut ditampilkan sebagai teks melalui escaping HTML. | Berhasil |
| P24 | Responsif | Buka halaman di Chrome pada lebar 1440, 1024, 768, 390 dan 360 piksel. | Konten dan kontrol utama tetap berada dalam viewport. Tabel lebar dapat digeser. | Chrome 157.0.8103.79 memeriksa 34 keadaan halaman dan 187 kontrol pada setiap lebar. Seluruh 170 kombinasi lulus pemeriksaan tata letak. | Berhasil |

## Pengujian integritas tambahan

| ID | Kondisi yang diuji | Hasil aktual |
|---|---|---|
| INT01 | Dua koneksi mencoba menyetujui kamar yang sama. | MySQL mencatat kedua koneksi menunggu kunci baris kamar. Sesudah kunci dilepas, satu pengajuan disetujui, satu tetap menunggu, dan jumlah penyewaan aktif tetap nol. |
| INT02 | Admin membatalkan penahanan lalu menyetujui pemesan lain. | Kamar tersedia kembali. Pengajuan lain dapat disetujui. Kedua riwayat tetap ada setelah pembatalan. |
| INT03 | Calon menggunakan nomor telepon milik profil lama dalam format berbeda. | Aktivasi memerlukan verifikasi identitas oleh admin. Profil lama, ID akun, hash sandi dan riwayat penyewaan tetap utuh. Satu sewa aktif baru terhubung ke profil lama. |
| INT04 | Empat pengajuan memakai penulisan berbeda dari nomor yang sama. | Tiga pengajuan tersimpan. Pengajuan keempat dibatasi meskipun dijalankan dari proses PHP terpisah. |

## Menjalankan ulang

Jalankan perintah berikut dari akar repositori dengan PHP CLI, PDO MySQL, Python, Node.js dan Chrome tersedia. Konfigurasi database lokal harus dapat membuat dan membersihkan database uji sementara. Skrip menggunakan salinan aplikasi dan memverifikasi nama database uji sebelum mengisi data fiktif.

```text
python scripts/uji-pemesanan-http.py
python scripts/uji-integritas-pemesanan.py
node scripts/uji-responsive.mjs
```

P24 menggunakan ukuran viewport melalui Chrome desktop. Pemeriksaan mencakup batas horizontal formulir, teks, tombol serta pergeseran tabel, termasuk pada daftar pengajuan dan detail berstatus menunggu. Pengujian perangkat fisik dan penilaian pengguna berada di luar pengukuran tersebut.