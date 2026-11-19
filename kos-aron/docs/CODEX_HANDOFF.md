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
