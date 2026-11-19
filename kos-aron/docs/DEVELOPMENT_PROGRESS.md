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

## 19 November 2026 — Tahap 7 selesai

### Fitur dan keputusan

- Admin mencatat tunai atau transfer lewat tombol Catat Pembayaran. Form memuat identitas penghuni, kamar, periode, jumlah tagihan dan tanggal. Server memvalidasi nominal desimal secara tepat, jumlah positif, tanggal sah sampai hari ini, metode, identitas admin aktif, CSRF dan pengiriman ulang form.
- Pembayaran, status lunas dan riwayat audit konsisten secara transaksional. Pencatat asli tetap tercantum setelah koreksi oleh admin lain. Detail menampilkan kronologi, admin pelaku, waktu, alasan dan nilai sebelum/sesudah.
- Pengguna memilih mendukung pembatalan. Transaksi batal tetap terlihat untuk admin dan dapat diaudit. Tagihan dapat dibayar lagi melalui transaksi ber-ID baru. Untuk salah pilih tagihan, batalkan pembayaran asal dengan alasan, lalu pilih tagihan yang benar. Detail lama ditolak bila transaksi sudah berubah.
- Dashboard admin, dashboard penghuni, laporan serta riwayat memperoleh status dan nominal dari MySQL. Riwayat sah terkait ke penyewaan asal meskipun penghuni telah keluar.

### File yang dibuat atau diubah

| Bagian | File |
|---|---|
| Halaman dan form | admin/pembayaran.php, assets/js/admin.js |
| Logika pencatatan, koreksi, pembatalan dan pembacaan | data/pembayaran.php, data/tagihan.php |
| Struktur dan migrasi | database/schema.sql, database/migrasi-07-pembayaran.sql |
| Pemeriksaan dan distribusi migrasi | database/uji-koneksi.php, .gitignore pada root repositori |
| Handover | docs/CODEX_HANDOFF.md, docs/DEVELOPMENT_PROGRESS.md |

Migrasi menambah tabel pembayaran_audit, status pembayaran dan indeks unik untuk tagihan yang pembayarannya sah. Migrasi diterapkan ke db_kos_aron lokal setelah diuji pada database terpisah. Seluruh kolom lama pada tiga pembayaran dibandingkan sebelum dan sesudah penerapan dan hasilnya identik. File migrasi diberi satu pengecualian spesifik pada .gitignore karena aturan umum mengabaikan dump SQL.

### Bukti pengujian

| Pengujian | Hasil |
|---|---|
| Uji awal | Form prototipe gagal menyimpan pembayaran tunai desimal. |
| Alur HTTP dan MySQL | 36 pemeriksaan lulus, mencakup tunai, transfer, nominal nol/negatif/kurang/lebih, pecahan sen, periode berjalan, tanggal, metode, tagihan tidak ada atau sudah lunas, akses tamu dan penghuni, CSRF, status kedua dashboard dan riwayat. |
| Koreksi dan pembatalan | Admin kedua dapat mengoreksi sambil mempertahankan identitas pencatat pertama. Alasan, admin, waktu serta nilai sebelum/sesudah tercatat. Form lama ditolak. Pembatalan mengubah status tagihan dan penerimaan baru mendapat ID baru. |
| Rollback | Kegagalan insert jurnal yang sengaja dipicu di database uji membatalkan perubahan pembayaran saat pencatatan, koreksi dan pembatalan. |
| Instalasi, konkurensi, keluar | Enam pemeriksaan tambahan lulus. DDL instalasi baru menghasilkan sembilan tabel. Dua proses PHP yang menunggu kunci pada tagihan yang sama menghasilkan satu pembayaran sah dan satu entri audit. MySQL juga menolak insert duplikat langsung. Checkout melalui fungsi aplikasi mempertahankan nominal, kamar asal, status lunas dan audit. |
| Browser | Sembilan pemeriksaan Chrome headless pada halaman pembayaran yang dirender dengan data uji lulus. Modal terbuka, nama/kamar/periode/harga/tanggal terisi, dan nominal yang salah ditolak sebelum submit. Pengiriman HTTP dan penyimpanan diuji terpisah. |
| Pemeriksaan akhir | PHP lint 44 file, node --check assets/js/admin.js, git diff --check dan uji koneksi PDO lulus. Tujuh tagihan dan tiga pembayaran lama masih ada pada database aplikasi. Semua mutasi skenario uji dijalankan pada database sementara yang dibersihkan setelah uji. |

MySQL Laragon sempat berhenti pada sesi lanjutan dan dijalankan kembali menggunakan my.ini yang sudah ada. Git berada di main. Backup otomatis 1eb32be sudah mencakup implementasi inti. Migrasi dan pembaruan handover tersedia di working tree saat penyelesaian tahap. database/seed.sql telah berstatus untracked sebelum pekerjaan ini.

Tahap berikutnya adalah Tahap 8, integrasi dan verifikasi area penghuni, termasuk penggantian password oleh penghuni. Pengujian responsif menyeluruh tetap dijadwalkan pada Tahap 10.

## 19 November 2026 — Tahap 8 selesai

### Halaman dan batas akses

- Dashboard dan Kamar Saya mengambil penyewaan aktif dari profil milik akun di sesi. Harga bulanan mengikuti perjanjian. Tagihan bulan berjalan dibatasi bulan dan tahun saat ini pada zona waktu aplikasi.
- Tagihan menampilkan seluruh riwayat milik penghuni dan petunjuk membayar langsung kepada pemilik. Riwayat Pembayaran mempertahankan filter bulan/tahun dan modal detail. Server membatasi detail sejak query MySQL berdasarkan pembayaran, profil sesi dan status transaksi sah.
- Notifikasi memakai baris tersimpan di MySQL, termasuk isi pesan dan status baca. Dashboard menampilkan notifikasi terbaru dari sumber yang sama.
- Profil dapat memperbarui nama, nomor telepon, serta username/email pada kolom identitas login yang sudah tersedia. Perubahan users dan penghuni dibuat dalam satu transaksi. Penggantian password menggunakan verifikasi hash lama dan pembaruan bersyarat agar perubahan bersamaan tidak menimpa password baru. Sesi lain akun tersebut harus login kembali.
- Semua halaman memeriksa role penghuni dan menggunakan konteks akun di server sebelum memproses input. Form profil hanya menerima kolom yang diizinkan dan CSRF yang sah. Detail di luar cakupan profil mendapat respons 404 yang sama dengan detail yang tidak tersedia. Tidak ada migrasi Tahap 8.

### File

- Enam halaman di penghuni/ meliputi index.php, kamar.php, tagihan.php, riwayat.php, notifikasi.php dan profil.php.
- penghuni/includes/konteks.php dibuat untuk identitas sesi. penghuni/includes/header.php memakai konteks yang sudah diverifikasi.
- data/kamar.php, data/penghuni.php, data/tagihan.php, data/pembayaran.php dan data/notifikasi.php menangani pembacaan terikat profil serta pembaruan akun. includes/auth.php memeriksa perubahan password pada sesi penghuni.
- docs/CODEX_HANDOFF.md dan docs/DEVELOPMENT_PROGRESS.md diperbarui.

### Pengujian aktual

Server PHP loopback dan database MySQL uji sementara digunakan untuk 37 pemeriksaan HTTP/database. Dua akun dengan kamar, harga, pesan dan kuitansi berbeda membuka keenam halaman. Akun ketiga yang aktif hanya memiliki sewa historis. Akun admin mencatat pembayaran lewat halaman admin yang sudah tersedia. Database uji dibersihkan setelah pengujian.

| Skenario | Hasil |
|---|---|
| Isolasi dan peran | Dua akun melihat data masing-masing pada enam halaman. Detail sendiri dapat dibuka. Detail milik akun lain mendapat 404. Parameter pengganti identitas, kolom role dan endpoint admin ditolak. Tamu diarahkan ke login. |
| Masa sewa dan tagihan | Kartu bulan berjalan kosong ketika hanya tersedia tagihan lama. Harga mengikuti kesepakatan. Profil tanpa sewa aktif tidak menampilkan kamar lama, sementara profil dan riwayat tetap tersedia. |
| Pembayaran dan notifikasi | Pembayaran admin mengubah kartu tagihan menjadi Lunas dan muncul di riwayat akun terkait. Pembayaran lama yang kurang dari nominal penuh tidak diberi label Lunas. Pesan dari tabel notifikasi muncul di daftar dan dashboard pemiliknya. |
| Profil dan password | Form menolak CSRF tidak valid, telepon tidak valid, identitas login duplikat, password lama salah dan konfirmasi berbeda. Nama, nomor dan akun baru tersimpan. Password baru tersimpan sebagai hash, sesi pengubah dirotasi, dan sesi lain akun yang sama berakhir. Akun penghuni lain tetap dapat digunakan. |
| Chrome | Tujuh pemeriksaan pada riwayat berbasis data uji lulus, termasuk filter bulan, filter gabungan bulan/tahun, membuka/menutup modal detail dan memulihkan seluruh baris. |
| Pemeriksaan statis | PHP lint lulus pada 45 file, node --check pada admin.js lulus, git diff --check lulus, dan uji PDO pada database aplikasi berhasil. |

Uji awal menangkap kartu yang masih memakai tagihan lama ketika bulan kini belum ditagih. Putaran akhir lulus 37/37 pemeriksaan HTTP/MySQL dan 7/7 pemeriksaan Chrome. Uji tampilan responsif menyeluruh tetap dijadwalkan di Tahap 10. Konfigurasi Git dan proses backup tetap mengikuti proyek pada branch main. Tahap 9 menunggu permintaan berikutnya.

## 19 November 2026 — Tahap 9 selesai

Pengujian integrasi menggunakan PHP 8.3, MySQL 8.4, dua sesi penghuni dan satu sesi admin. Skrip `scripts/uji-tahap-09.py` menyalin aplikasi ke direktori sementara, membuat skema uji khusus, menjalankan HTTP lokal, dan membersihkannya. Jalankan dari root dengan `python scripts/uji-tahap-09.py` menggunakan Python 3 yang memiliki pustaka standar, PHP CLI di PATH serta Chrome. Di Laragon lokal pengujian memakai Python 3.13 dari direktori bin. Chrome alternatif dapat ditentukan melalui CHROME_BIN.

| Bagian | Hasil verifikasi |
|---|---|
| Notifikasi | Migrasi mempertahankan status baca. Hari H-7, H-3, tanggal jatuh tempo, hari terlambat serta pengulangan CLI sesuai hasil yang diharapkan. Perubahan status baca menurunkan penghitung tepat satu. Pemilik lain dan token CSRF salah ditolak. |
| Pembayaran | Notifikasi lunas langsung tampil. Setelah pembatalan, informasi tersebut tersembunyi. Pembayaran pengganti menghasilkan tepat satu pemberitahuan lunas yang berlaku. |
| Laporan | Pada data uji Oktober total tagihan Rp 1.700.000, diterima Rp 1.150.000, tersisa Rp 550.000. Seluruh periode menghasilkan Rp 4.000.000, Rp 2.350.000 dan Rp 1.650.000. Filter tahun, penghuni lama, hunian historis, dan cetak laporan bulanan ke PDF melalui Chrome lulus. |
| Pengaturan publik | Nama, deskripsi, alamat, nomor WhatsApp, tautan Maps dan foto PNG berhasil disimpan. Foto tampil pada Beranda dan Tentang serta dapat diunduh dengan isi yang sama. Penyimpanan teks berikutnya mempertahankan foto. Nomor, tautan, tipe unggahan dan CSRF yang tidak valid ditolak. |
| Akses | Penghuni ditolak saat membuka laporan atau mengirim pengaturan. Akses HTTP ke generator CLI ditolak. Log server uji bersih dari warning dan fatal error PHP. |

Putaran akhir lulus 19 kelompok pemeriksaan. Lint 48 file PHP dan pemeriksaan sintaks admin.js lulus. Perubahan skema lokal mempertahankan 7 notifikasi, termasuk 3 yang sudah dibaca. Eksekusi manual CLI pada 19 November 2026 pukul 22.53 WIB menghasilkan 0 notifikasi baru. Pengujian scheduler berkala menunggu pemasangan sesuai [panduan scheduler](09-notifikasi-dan-scheduler.md).

## 20 November 2026 — Audit akhir dan integrasi ilustrasi

Matriks Black Box mencatat 26 skenario berhasil. Chrome memeriksa 31 keadaan halaman pada lima ukuran layar, termasuk pemuatan WebP publik dan admin. Pengujian dilakukan dengan salinan aplikasi dan MySQL sementara. Database operasional tidak dimodifikasi untuk pemasangan ilustrasi. Beranda dan Tentang memakai ilustrasi lokal ketika foto bangunan belum tersedia. Kamar contoh 01, 02 dan 04 memakai gambar konsisten pada kartu, detail dan Data Kamar, dengan prioritas bagi foto utama dan galeri asli.

Lihat [laporan audit, perbaikan dan pekerjaan terbuka](10-audit-akhir.md), [matriks Black Box](10-black-box.md), [hasil tampilan](10-responsive.md) dan [indeks panduan](README.md). Status penerapan masih mencakup pemisahan tiga akun contoh di database development, pengujian scheduler dan host tujuan, serta pengumpulan SUS dari 15 responden.
