# Progres pengembangan Kos Aron

## 19 November 2026 — audit awal

- Memeriksa folder aplikasi, komponen PHP, HTML/CSS dan JavaScript, data contoh, dokumen, ERD, Git dan skema langsung via SHOW CREATE TABLE.
- PHP lint awal lulus untuk 44 file. PDO berhasil tersambung. Alat database/uji-koneksi.php gagal pada kolom kamar.status. Port 80 localhost tidak menerima koneksi.
- Risiko utama Tahap 5 adalah pencampuran ID contoh dengan profil nyata, penyewaan ganda, serta perubahan riwayat saat keluar/pindah. Indeks database untuk sewa aktif sudah ada.
- Rencana Tahap 5 mengikuti handover pengguna. Perbaiki pemeriksaan koneksi, gunakan transaksi untuk akun/profil/sewa, pertahankan form dan navigasi, akhiri penyewaan sebelum membuat riwayat berikutnya, lalu uji dengan data terisolasi. Status Tahap 1–10 tersedia di CODEX_HANDOFF.md.

## 19 November 2026 — Tahap 5 selesai

### Hasil implementasi

- Tambah penghuni membuat akun dengan password hash, profil dan sewa sekaligus. Admin mengisi kamar, tanggal mulai, harga yang disepakati, dan hari jatuh tempo.
- Edit profil mendukung penggantian password oleh admin. Password kosong mempertahankan hash akun yang ada. Arsip tanpa akun tetap bisa diedit. Admin dapat mengaktifkan sewa baru pada profil lama.
- Daftar dan detail menampilkan data MySQL, riwayat semua penyewaan, serta tagihan milik penghuni. Akhiri Sewa menyimpan tanggal keluar, melepaskan kamar dan menonaktifkan akun. Permintaan halaman penghuni memeriksa status akun.
- Kamar perawatan dan kamar terisi ditolak oleh server. Indeks unik dan transaksi melindungi penempatan bersamaan. Pembaruan kondisi kamar memakai kunci baris yang sama.
- Profil login mengikuti users.id dari sesi melalui penghuni.user_id. Riwayat keuangan memakai kamar dari penyewaan asal. Perubahan ini hanya membaca transaksi keuangan yang sudah ada.
- Perbaikan Tahap 2 mengganti query pemeriksaan kamar terisi dengan hitungan penyewaan aktif. Alat pemeriksaan hanya berjalan melalui CLI.
- Skema dan data database aplikasi tidak diubah. Penghitungan ulang setelah uji menghasilkan jumlah baris awal yang sama pada delapan tabel.

### File dibuat atau diubah

| Bagian | File relatif terhadap kos-aron/ |
|---|---|
| Admin penghuni | admin/penghuni.php, admin/penghuni-form.php, admin/penghuni-detail.php |
| Data | data/penghuni.php, data/kamar.php, data/tagihan.php, data/pembayaran.php, data/notifikasi.php |
| Integrasi kamar dan tampilan | admin/kamar-form.php, admin/includes/footer.php, assets/js/admin.js, penghuni/index.php, penghuni/kamar.php, penghuni/includes/header.php, penghuni/includes/footer.php |
| Sesi, format uang dan pemeriksaan PDO | includes/auth.php, includes/functions.php, database/uji-koneksi.php |
| Dokumentasi baru | docs/CODEX_HANDOFF.md, docs/DEVELOPMENT_PROGRESS.md |

### Bukti pengujian

Database aplikasi diperiksa dengan SELECT, SHOW CREATE TABLE, dan php database/uji-koneksi.php. Uji mutasi memakai database sementara bernama acak, yang menyalin DDL delapan tabel tanpa menyalin data. Server PHP sementara hanya mendengarkan di loopback. Akun dan kamar uji dibuat khusus di sana. Server dihentikan dan database uji dibersihkan setelah pengujian.

| Kelompok | Hasil yang diamati |
|---|---|
| Tahap 2 | Uji PDO awal gagal karena kolom kamar.status tidak ada. Setelah perbaikan, CLI melaporkan native prepares, exception mode dan 3 kamar terisi. |
| Tahap 3 | Login admin dan penghuni berhasil. Tamu diarahkan ke login. Penghuni mendapat 403 untuk area admin. Akun yang keluar ditolak saat login dan sesi lamanya diarahkan ke login pada akses berikutnya. |
| Tahap 4 | Kamar ditambah, diedit dan dihapus lewat HTTP. Harga baru tampil di detail publik. Penghapusan kamar bersejarah dan kondisi perawatan untuk kamar yang ditempati ditolak. |
| Form Tahap 5 | Tambah, edit, detail, keluar dan sewa ulang berhasil. Token CSRF kosong ditolak saat tambah dan keluar. Tanggal keluar sebelum masuk ditolak. Harga pecahan tampil sesuai DECIMAL. |
| Konsistensi | Kamar terisi/perawatan, username duplikat dan hari ke-32 tidak meninggalkan akun, profil atau sewa parsial. Dua proses PHP mencoba mengambil kamar yang sama. Tepat satu akun dan satu sewa tersimpan. Indeks MySQL juga menolak sewa aktif kedua untuk penghuni yang sama. |
| Riwayat | Setelah sewa ulang, detail menampilkan dua kamar, tagihan lama tetap Lunas, dan pembayarannya menunjuk kamar lama. Pengiriman ulang permintaan keluar lama tidak menutup sewa baru. Profil arsip tanpa akun dapat diedit, diberi akun dan login kembali. |
| Akses dan navigasi | Dua akun penghuni menampilkan profil berbeda. Riwayat pembayaran penghuni pertama tidak muncul untuk penghuni kedua. Halaman publik serta seluruh menu utama admin dan penghuni yang diperiksa memberi respons HTTP 200. |
| Pemeriksaan statis | PHP lint lulus pada 44 file, node --check assets/js/admin.js dan git diff --check lulus. |

Putaran HTTP/MySQL akhir lulus 54 dari 54 pemeriksaan. Pengujian dilakukan melalui permintaan HTTP dan dua proses PHP CLI untuk kompetisi kamar. Upload foto dengan berkas, tampilan responsif di browser, deployment Apache dan audit keamanan menyeluruh belum diuji pada putaran ini.

### Git dan pekerjaan berikut

Branch main mengikuti origin/main menurut referensi lokal. Seluruh hasil sesi berada di working tree. File database/seed.sql sudah tidak terlacak sebelum sesi dimulai dan tidak disunting. Konfigurasi Git serta proses backup tidak disentuh. URL port 80 Laragon belum dapat diverifikasi melalui server web Laragon yang aktif. Saat deployment, periksa kembali pembatasan akses HTTP ke berkas SQL, dokumentasi dan konfigurasi lokal.

Tahap 6 menunggu permintaan pengguna. Baca kedua dokumen ini, gunakan penyewaan.id dan harga_sewa_disepakati untuk pratinjau/simpan tagihan individual dan massal, pertahankan aturan unik penyewaan/periode, dan periksa tanggal akhir bulan. Tahap 7–10 masih menunggu.

## 19 November 2026 — Tahap 6 selesai

### Fitur dan file

- admin/tagihan.php menangani daftar, empat filter gabungan, pilihan individual/massal, pratinjau, konfirmasi, detail dan laporan jumlah berhasil, dilewati, gagal. Pratinjau memuat nama, kamar, harga kesepakatan, hari jatuh tempo dan tanggal hasil perhitungan. Admin dapat menambahkan keterangan pada tagihan individual.
- data/tagihan.php memeriksa periode, menyeleksi sewa aktif, dan menghitung tanggal jatuh tempo. Konfirmasi mengambil data dari sesi admin dan memeriksanya lagi di dalam transaksi menggunakan SELECT ... FOR UPDATE. ID sewa diurutkan untuk pemrosesan serentak. Harga dan tanggal dari klien tidak dipakai untuk penyimpanan. Pembaca status mengakui lunas bila nominal pembayaran sama tepat dengan tagihan.
- assets/js/admin.js menggunakan dialog yang sama untuk melihat dan mengonfirmasi pratinjau. Form tetap bisa dikirim melalui HTTP tanpa JavaScript.
- docs/CODEX_HANDOFF.md dan dokumen ini diperbarui. Skema aplikasi tetap menggunakan indeks unik (penyewaan_id, periode). Tidak ada migrasi, scheduler atau pencatatan pembayaran baru.

### Aturan yang diverifikasi

Tagih penuh jika jatuh tempo berada dalam masa sewa aktif. Bila jatuh tempo sudah berlalu saat masuk, mulai dari bulan berikutnya sesuai jawaban pengguna. Februari dan bulan pendek memakai hari terakhir yang tersedia. Setelah pratinjau, sewa baru memerlukan pratinjau baru. Sewa yang sudah selesai atau harga yang berubah dihitung gagal agar admin bisa meninjau ulang. Tagihan yang telah ada dihitung dilewati. Kegagalan SQL membatalkan seluruh penulisan dalam transaksi dan laporan menghitung ulang hasilnya.

### Pengujian yang dijalankan

Uji regresi awal gagal karena tombol massal masih belum tersedia pada prototipe. Pengujian akhir melalui server PHP loopback dan MySQL 8.4.3 dengan skema sementara yang hanya menyalin struktur tabel. Seluruh 30 pemeriksaan lulus. Data aplikasi diperiksa dengan uji PDO dan tetap berisi tujuh tagihan serta tiga pembayaran pada akhir sesi.

| Kelompok | Hasil |
|---|---|
| Individual | Pratinjau tidak menulis. Setelah konfirmasi, nominal DECIMAL, tanggal, keterangan dan ID admin sesuai. Perubahan nilai yang dikirim klien tidak mengganti data yang ditinjau. |
| Massal | Periode yang berbeda menghasilkan tagihan baru. Pengulangan melewati seluruh tagihan. Dua proses PHP yang mengonfirmasi periode sama menghasilkan empat tagihan unik dan empat dilewati. |
| Kelayakan | Sewa selesai, periode sebelum masuk, bulan tidak valid, dan jatuh tempo sebelum tanggal masuk ditolak. Tanggal masuk tepat pada jatuh tempo diterima. |
| Tarif dan kalender | Beberapa tarif penuh dan hari jatuh tempo berbeda tersimpan sesuai sewa. Hari 29, 30 dan 31 menjadi tanggal 28 atau 29 di bulan Februari sesuai tahun. |
| Perubahan setelah pratinjau | Duplikat yang muncul kemudian dilewati. Sewa yang berakhir atau berubah harga dilaporkan gagal. Penghuni baru tidak ditagih tanpa pratinjau ulang. |
| Transaksi | Trigger khusus database uji sengaja menolak insert kedua. Hasilnya nol tersimpan dan seluruh kandidat baru dilaporkan gagal. Percobaan berikutnya berhasil setelah kondisi uji dilepas. |
| Status dan akses | Penghuni mendapat 403, tamu diarahkan ke login, dan permintaan tanpa CSRF ditolak. Pada hari jatuh tempo status tetap Belum Bayar. Pembayaran kurang dari nominal tetap belum lunas. Filter gabungan membedakan hasil yang cocok dan daftar kosong. |

PHP lint lulus untuk 44 file. node --check pada admin.js, git diff --check, serta database/uji-koneksi.php juga lulus. Tampilan visual pada browser belum diuji pada tahap ini. Database uji dibersihkan setelah pengujian.

Git berada pada main dengan perubahan Tahap 5 dan 6 di working tree. File database/seed.sql sudah berstatus untracked sejak sebelum audit awal. Tahap berikutnya adalah Tahap 7 setelah diminta.
