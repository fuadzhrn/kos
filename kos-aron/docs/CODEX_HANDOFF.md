# Handover Kos Aron

Audit 19 November 2026. Aplikasi monitoring kamar dan pembayaran untuk kos di Kolor, Sumenep. Root Git ada di D:\laragon\laragon\www\kos-proposal, aplikasi di kos-aron/. URL Laragon yang diperiksa adalah http://localhost/kos-proposal/kos-aron/. Port 80 tidak menerima koneksi saat audit. PHP CLI 8.3 dan MySQL 8.4.3 tersedia.

## Struktur dan akses

Publik memakai index.php, kamar.php, detail-kamar.php, tentang.php, kontak.php dan login.php. Admin memiliki dashboard, kamar, penghuni, tagihan, pembayaran, laporan dan pengaturan. Penghuni memiliki dashboard, kamar, tagihan, riwayat, notifikasi dan profil. Logout memakai POST. Hanya admin dan penghuni memiliki akun. Pengunjung hanya melihat kamar dan menghubungi pemilik. Admin mendaftarkan penghuni dan mencatat pembayaran tunai atau transfer di luar aplikasi.

- includes/ berisi konfigurasi, koneksi PDO, sesi, CSRF, escaping, upload serta komponen publik. Kredensial lokal dikecualikan dari Git.
- admin/ dan penghuni/ memakai header dan footer masing-masing. assets/ berisi CSS, JS dan gambar. Tampilan PHP native menggunakan Montserrat dan Poppins. JavaScript menangani navigasi, filter, dialog dan konfirmasi.
- data/ memuat fungsi pembaca data. Saat audit awal, kamar memakai PDO. Penghuni, tagihan, pembayaran dan notifikasi memakai data contoh PHP.
- database/ berisi schema.sql, seed.sql lokal, alat uji koneksi dan CLI pembuatan admin. docs/ menyimpan rancangan, ERD dan hasil pengujian terdahulu. scripts/auto-backup/ dan logs/ terdapat di root Git.

## Database yang diperiksa langsung

Delapan tabel InnoDB. Jumlah baris saat audit awal dan fungsi masing-masing tercatat di bawah.

| Tabel | Baris | Relasi dan aturan |
|---|---:|---|
| users | 4 | Username unik, hash password, role, status akun |
| kamar | 6 | Nomor unik, harga DECIMAL, kondisi aktif/perawatan, foto dan fasilitas |
| penghuni | 4 | Profil, email opsional, user_id unik dan boleh NULL untuk arsip |
| penyewaan | 4 | FK penghuni dan kamar, harga disepakati, hari jatuh tempo 1–31, tanggal masuk/keluar, aktif/selesai. Dua indeks unik pada kolom generated menjaga satu sewa aktif per kamar dan per penghuni |
| tagihan | 7 | FK penyewaan, unik per penyewaan dan periode, jumlah historis, tanggal jatuh tempo |
| pembayaran | 3 | FK tagihan unik, nominal, tanggal, metode dan admin pencatat |
| notifikasi | 7 | FK penghuni dan tagihan, tipe, waktu dan status baca. Indeks unik tagihan/tipe perlu ditinjau sebelum pengingat H-7 dan H-3 |
| pengaturan | 7 | Pasangan kunci/nilai dan admin yang memperbarui |

Status terisi berasal dari penyewaan aktif. Model ini menggantikan beberapa contoh lama di docs/. schema.sql memuat DROP dan seed.sql memuat TRUNCATE. Keduanya tidak dijalankan pada database aplikasi. Git mula-mula menunjukkan main mengikuti origin/main dan seed.sql belum terlacak.

## Status saat serah terima awal

1. Tahap 1 memiliki analisis dan ERD draw.io yang dapat dibaca sebagai XML.
2. Tahap 2 memiliki schema.sql dan PDO dengan native prepares dan exception. Koneksi berhasil. Uji bawaan gagal karena masih membaca kamar.status.
3. Tahap 3 memiliki verifikasi hash, pemeriksaan status, regenerasi ID sesi, pembatasan percobaan per sesi, guard role dan token CSRF. Identitas konten penghuni masih menunjuk profil contoh yang sama untuk semua sesi.
4. Tahap 4 memiliki pembaca kamar publik dari MySQL dan CRUD admin dengan validasi foto serta perlindungan riwayat sewa. Status perawatan berbeda dari status terisi. Bukti uji sesi ini tercatat di DEVELOPMENT_PROGRESS.md.
5. Tahap 5 saat audit masih memakai data contoh. Form memvalidasi tetapi tidak menyimpan. Pekerjaan mencakup akun, profil, penempatan, harga dan hari jatuh tempo, keluar, sewa ulang dan riwayat. Gunakan transaksi, penguncian baris dan indeks yang ada.
6. Tahap 6 masih prototipe. Pembuatan tagihan individual dan massal menunggu permintaan berikut.
7. Tahap 7 masih prototipe pencatatan pembayaran.
8. Tahap 8 belum selesai. Seluruh data dashboard dan perubahan password perlu diuji saat integrasi.
9. Tahap 9 belum selesai. Notifikasi, laporan dan pengaturan belum sepenuhnya memakai database.
10. Tahap 10 belum dimulai. Pengujian akhir dan instrumen SUS menunggu tahap tersebut.

Lihat DEVELOPMENT_PROGRESS.md untuk perubahan setelah audit dan hasil uji terbaru.

## Kondisi setelah Tahap 5

Tahap 1–4 sudah diaudit. Tahap 5 selesai pada 19 November 2026. Titik masuk admin adalah admin/penghuni.php. Gunakan Tambah Penghuni untuk membuat akun dan menempatkan penghuni, Edit untuk memperbarui identitas atau memulai sewa kembali, Detail untuk riwayat, dan Akhiri Sewa untuk mencatat tanggal keluar. Penghuni yang selesai menyewa tetap muncul dengan filter Tidak Aktif.

Akun, profil dan sewa baru disimpan dalam satu transaksi. Kamar aktif dan kosong dapat dipilih. Transaksi mengunci profil dan kamar, memeriksa riwayat yang bertumpang tindih dan memakai dua indeks unik yang sudah ada di MySQL. Form keluar membawa ID penyewaan sehingga pengiriman ulang untuk sewa lama tidak menutup sewa baru. Akun dinonaktifkan ketika keluar. Akun dan profil yang sama dipakai lagi saat kembali, termasuk profil lama yang sebelumnya belum memiliki akun.

Keputusan implementasi: tanggal masuk/keluar mencatat kejadian sampai hari ini. Serah-terima kamar pada tanggal keluar yang sama diizinkan. Harga dan hari jatuh tempo melekat pada perjanjian. Untuk pindah kamar atau mengganti perjanjian, admin menutup sewa lama lalu membuka sewa baru di profil yang sama. Hari jatuh tempo tetap 1–31. Efek tanggal 29–31 pada bulan pendek harus dicatat pada Tahap 6 saat membuat tanggal tagihan.

Pembaca tagihan dan pembayaran yang sudah dipakai UI kini mengikuti FK penyewaan agar ID data contoh tidak tercampur dengan profil nyata. Tagihan berjalan difilter menurut sewa aktif. Informasi penghuni mengikuti identitas server dan harga kesepakatan. Pembuatan tagihan, pencatatan pembayaran, perubahan password oleh penghuni, penyimpanan pengingat serta pengaturan masih menunggu tahap masing-masing. Tidak ada scheduler baru.

Verifikasi terakhir mencakup 54 pemeriksaan HTTP/MySQL di database uji, PHP lint 44 file, pemeriksaan sintaks JS dan uji PDO pada database aplikasi. Rincian file dan hasil terdapat di DEVELOPMENT_PROGRESS.md. Lanjutkan ke Tahap 6 hanya ketika diminta.

## Tahap 6 selesai — tagihan bulanan

Admin membuka admin/tagihan.php, memilih Buat Tagihan Individual atau Buat Tagihan Massal, memilih periode, memeriksa pratinjau, lalu mengonfirmasi. Daftar dapat difilter menurut bulan, tahun, status dan nama. Detail baris memuat nomor tagihan, keterangan bila ada, dan tautan ke riwayat penghuni.

Harga penuh mengikuti penyewaan.harga_sewa_disepakati. Hari jatuh tempo 29–31 disesuaikan dengan hari terakhir bulan. Sesuai keputusan pemilik proyek, bila penghuni masuk setelah jatuh tempo bulan tersebut, tagihan pertama dimulai bulan berikutnya. Masuk tepat pada jatuh tempo memenuhi syarat. Hanya sewa aktif dengan jatuh tempo di dalam masa sewa yang masuk pratinjau.

Konfirmasi memeriksa kembali data pratinjau milik sesi admin, mengunci sewa, dan menyimpan tagihan secara transaksional. Indeks uq_tagihan_penyewaan_periode sudah tersedia pada MySQL. Tagihan lama dilewati dan mempertahankan nominal serta tanggal historis. Status lunas memerlukan satu pembayaran dengan nominal tepat sebesar tagihan. Tahap 6 tidak menyediakan operasi penulisan pembayaran.

Lihat DEVELOPMENT_PROGRESS.md untuk hasil 30 pemeriksaan Tahap 6. Pekerjaan berikutnya adalah Tahap 7, pencatatan pembayaran penuh oleh admin, ketika diminta.

## Tahap 7 selesai — pencatatan pembayaran

Admin mencatat penerimaan tunai atau transfer di admin/pembayaran.php. Form mengambil penghuni, kamar, periode dan nominal dari tagihan. Server memeriksa admin aktif, token CSRF, tanggal, metode dan kesamaan nominal sampai dua angka desimal. Transaksi mengunci tagihan dengan SELECT ... FOR UPDATE. Tagihan menjadi lunas saat memiliki satu pembayaran sah dengan nominal penuh. Daftar pembayaran, dashboard, laporan dan riwayat penghuni menggunakan hubungan yang sama.

Dari Detail Pembayaran, admin dapat mengoreksi tanggal, metode, keterangan atau nominal penuh, serta membatalkan transaksi dengan alasan. Untuk salah tagihan, batalkan transaksi lama dan catat pada tagihan yang benar. Pembatalan mengembalikan tagihan ke Belum Bayar atau Terlambat sesuai tanggal jatuh tempo. Jika penerimaan dicatat kembali, transaksi mendapat ID baru. Riwayat admin tetap menampilkan yang dibatalkan, sedangkan total penerimaan dan riwayat pembayaran sah penghuni mengecualikannya.

Database kini memiliki sembilan tabel. pembayaran.status membedakan sah dan dibatalkan. Indeks unik uq_pembayaran_tagihan_aktif memakai kolom generated tagihan_aktif_unik untuk menjaga satu pembayaran sah per tagihan. pembayaran_audit memuat ID pembayaran, tindakan, admin, waktu, alasan koreksi/pembatalan, dan salinan JSON sebelum/sesudah. Pencatatan dan audit berada dalam satu transaksi. Nilai awal tiga pembayaran lokal dipertahankan dan tidak dibuatkan catatan audit fiktif.

Instalasi baru memakai database/schema.sql. Untuk memperbarui instalasi Tahap 6, pilih database aplikasi dan jalankan database/migrasi-07-pembayaran.sql. Migrasi sudah diterapkan pada db_kos_aron lokal dan diuji dijalankan dua kali di database terpisah. Daftar file dan hasil uji tersedia di DEVELOPMENT_PROGRESS.md. Lanjutkan Tahap 8 saat diminta.

## Tahap 8 selesai — Dashboard Penghuni

Enam halaman penghuni memakai profil yang diperoleh melalui users.id di sesi dan penghuni.user_id. Dashboard menampilkan sewa aktif, tagihan bulan kalender berjalan, tiga penerimaan sah terbaru dan notifikasi terbaru. Tanpa tagihan bulan berjalan, kartu menampilkan pesan kosong. Akun yang masih aktif tanpa penyewaan aktif dapat melihat profil dan riwayat miliknya. Kamar Saya memberi pesan bahwa belum ada penyewaan aktif. Akun yang dinonaktifkan saat keluar tetap ditolak sesuai Tahap 5.

Tagihan dan pembayaran sepanjang riwayat mengikuti penyewaan asal. Filter bulan dan tahun di Riwayat memakai periode tagihan. Permintaan detail hanya dapat membaca pembayaran sah pemilik sesi. ID yang di luar cakupan profil menghasilkan HTTP 404. Notifikasi menampilkan judul, pesan, tanggal dan status baca dari tabel notifikasi. Pembuatan pengingat dan perubahan status baca tetap menjadi pekerjaan Tahap 9.

Form Profil Saya menyimpan nama, nomor telepon dan identitas login (username yang juga boleh berbentuk email). Form password memeriksa password lama, mencocokkan konfirmasi, dan menyimpan hash. Perubahan password merotasi ID sesi dan token CSRF serta mengakhiri sesi lain akun itu pada akses berikutnya. Sesi penghuni dari sebelum Tahap 8 perlu login ulang sekali. Tidak diperlukan migrasi skema.

Pengujian isolasi data dan transaksi tercatat di DEVELOPMENT_PROGRESS.md. Tahap berikutnya adalah Tahap 9 setelah diminta.

## 19 November 2026 — Tahap 9 selesai

Pengingat H-7, H-3, terlambat dan lunas disimpan di MySQL. Dashboard dan halaman notifikasi penghuni memakai identitas sesi untuk daftar, penghitung dan tanda baca. Pembayaran sah membuat informasi lunas di transaksi yang sama. Indeks unik baru sudah diterapkan pada db_kos_aron lokal. Lihat [konfigurasi scheduler](09-notifikasi-dan-scheduler.md) untuk perintah CLI dan pengaturan waktu.

Laporan admin memakai periode tagihan dan pembayaran penuh yang masih sah. Ketika bulan dipilih tanpa tahun, halaman menggunakan tahun berjalan. Hunian kamar mengacu ke akhir periode terpilih atau hari ini jika periode masih berjalan. Untuk seluruh periode, ringkasan hunian memakai hari ini. Perawatan hanya diketahui dari kondisi kamar saat ini. Laporan historis menyebut kamar kosong berdasarkan riwayat sewa.

Admin dapat menyimpan identitas, deskripsi, alamat, WhatsApp, tautan atau sematan Maps, dan foto di Pengaturan. Halaman publik membaca pengaturan pada setiap permintaan. Form password admin yang terpisah masih merupakan prototipe dari tahap sebelumnya. Pengujian akhir menyeluruh dan instrumen SUS masuk Tahap 10.

## 20 November 2026 — Audit akhir dan integrasi ilustrasi

Matriks Black Box mencatat 26 skenario berhasil. Chrome memeriksa 31 keadaan halaman pada lima ukuran layar, termasuk pemuatan WebP publik dan admin. Pengujian dilakukan dengan salinan aplikasi dan MySQL sementara. Database operasional tidak dimodifikasi untuk pemasangan ilustrasi. Beranda dan Tentang memakai ilustrasi lokal ketika foto bangunan belum tersedia. Kamar contoh 01, 02 dan 04 memakai gambar konsisten pada kartu, detail dan Data Kamar, dengan prioritas bagi foto utama dan galeri asli.

Lihat [laporan audit, perbaikan dan pekerjaan terbuka](10-audit-akhir.md), [matriks Black Box](10-black-box.md), [hasil tampilan](10-responsive.md) dan [indeks panduan](README.md). Status penerapan masih mencakup pemisahan tiga akun contoh di database development, pengujian scheduler dan host tujuan, serta pengumpulan SUS dari 15 responden.

## 20 November 2026 — Registrasi dan pemesanan kamar (Tahap 2 proposal)

Calon penghuni mengisi identitas saat memesan kamar melalui situs publik. Admin memeriksa pengajuan melalui Data Pemesanan. Persetujuan menahan kamar, pembatalan melepaskannya, dan aktivasi membuat penyewaan tersendiri melalui layanan penghuni. Profil lama yang sudah diverifikasi dapat digunakan kembali. Dua peran login tetap berlaku.

Migrasi `database/migrasi-registrasi-pemesanan.sql` sudah diterapkan pada MySQL lokal setelah backup privat. Sebelas tabel dan 17 foreign key terdeteksi. Jumlah baris dan sidik data seluruh sembilan tabel lama sama sebelum dan sesudah migrasi. Untuk instalasi lain, pasang migrasi setelah skema dasar. Lihat [panduan registrasi, persetujuan dan aktivasi](registrasi-pemesanan.md).
