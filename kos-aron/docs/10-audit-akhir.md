# Audit akhir Kos Aron · 20 November 2026

Alur operasional diuji pada PHP 8.3, PDO, MySQL 8.4 dan Chrome di Windows. Aplikasi terhubung ke database di ketiga area. Bukti menjalankan formulir, membaca kembali perubahan, dan memeriksa transaksi tersimpan tersedia pada [26 skenario Black Box](10-black-box.md). Akses tamu dan perilaku server diperiksa di [audit keamanan](10-keamanan.md). Pengukuran lima lebar layar tercatat pada [hasil responsif](10-responsive.md).

## Halaman dan formulir

| Area | Cakupan dan hasil |
|---|---|
| Publik | Beranda, daftar, detail kamar, Tentang, Kontak dan Login dirender. Identitas dan kontak memakai pengaturan. Kamar publik mengikuti kondisi dan ketersediaan database. Ilustrasi ditandai. |
| Admin | Dashboard, daftar/form kamar, daftar/form/detail penghuni, penyewaan, tagihan, pembayaran, laporan dan pengaturan. Form password admin kini menyimpan perubahan dengan verifikasi password lama. Logout mengakhiri sesi. |
| Penghuni | Dashboard, Kamar Saya, Tagihan, Riwayat, Notifikasi dan Profil dibatasi oleh identitas sesi. Penghuni hanya dapat membaca transaksi miliknya. Data dan password profil mempunyai penanganan POST. |
| Pengingat | CLI menghasilkan H-7, H-3, terlambat atau lunas sesuai keadaan tagihan dan eksekusi ulang mempertahankan status baca. Pelunasan yang dibatalkan dapat diaudit. Penjadwalan berkala belum dipasang. |

## Integritas data

Pemeriksaan baca-saja pada database pengembangan menemukan 0 kamar dengan dua sewa aktif, 0 sewa aktif di kamar perawatan, 0 duplikat periode per sewa, 0 pembayaran sah ganda dan 0 pembayaran sah dengan selisih nominal. Tujuh tagihan berjumlah Rp 4.000.000. Tiga pembayaran sah berjumlah Rp 1.750.000 dan sisa tagihan Rp 2.250.000. Pada database uji terpisah, alur keluar penghuni membebaskan kamar sekaligus mempertahankan profil, sewa, tagihan, pembayaran dan audit.

## Bug, perbaikan dan bukti

| Temuan | Perbaikan dan hasil |
|---|---|
| Form password admin hanya memvalidasi bentuk isian | Memakai pemeriksaan password lama, pembaruan hash akun sesi, rotasi sesi dan CSRF. Uji HTTP awal gagal pada password lama keliru. Pada uji ulang password lama ditolak, login dengan password baru berhasil, dan sesi admin lain berakhir. |
| Kontrol koreksi pembayaran terpotong | Lebar kolom form diperbaiki. Uji akhir 155 kombinasi selesai tanpa temuan tata letak pada pemeriksaan yang dijalankan. |
| Jalur internal dan berkas SQL memerlukan pembatasan HTTP | Aturan Apache melindungi folder privat dan menutup listing. Sebelas contoh berkas privat memperoleh 403. Dua berkas gambar publik tetap menghasilkan 200. |
| Detail galat awal berpotensi tampil kepada pengunjung | Bootstrap menampilkan pesan umum. Uji Apache dengan konfigurasi database hilang menghasilkan 500 tanpa detail koneksi. |
| Seed lama memiliki sandi bersama dan menampilkan data contoh bila dipakai sebagai database aplikasi | Seed untuk pengujian kini memuat akun nonaktif dengan penanda yang tak dapat dipakai login. Sandi contoh lama dihapus dari dokumentasi terkini. Skrip uji memberi password acak pada database sementaranya. |
| Pengaturan sesi dan unggahan bergantung pada konfigurasi lingkungan | Sesi memakai cookie saja dan strict mode, serta mengganti token setelah login. Pembatasan Apache di folder foto diperiksa dengan permintaan gambar nyata. Unggahan operasional dikecualikan dari Git. |

## Pekerjaan yang masih terbuka

1. **Pisahkan produksi dari data contoh.** Tiga akun aktif pada database pengembangan masih mempunyai hash yang identik dengan fixture lama. Data demo dan transaksi di database lokal dipertahankan. Gunakan skema kosong untuk instalasi nyata dan masukkan data terverifikasi pemilik. Jika database lokal hendak dipakai untuk operasional, lakukan peninjauan akun dan riwayat bersama pemilik lebih dahulu.
2. **Jalankan sesi SUS dengan 15 responden.** Formulir sudah tersedia untuk 14 penghuni dan satu pemilik. Belum ada jawaban atau skor penelitian. Workbook dibuka ulang menggunakan openpyxl, dengan 150 sel jawaban kosong, validasi angka, 15 formula individual dan dua formula rekap. Penghitungan di aplikasi spreadsheet milik peneliti dapat diperiksa saat persiapan pengumpulan. [Instrumen dan prosedur](10-SUS.md)
3. **Uji lingkungan penerapan.** Verifikasi Apache/XAMPP di mesin tujuan, HTTPS, izin direktori, backup/restore dan pemicu harian Task Scheduler atau cron. Uji lokal tidak mengukur ketersediaan layanan sepanjang hari dan tidak mencakup perangkat fisik.
4. **Validasi konten nyata.** Pemilik perlu mengisi kontak, alamat dan foto dokumentasi sesuai kondisi kos. Aset WebP publik diberi keterangan ilustrasi.

## Dokumen penggunaan

- [Struktur proyek, ERD final dan kamus sembilan tabel](10-struktur-dan-erd.md)
- [Instalasi XAMPP dan panduan per peran](10-panduan.md)
- [Lembar SUS kosong untuk dicetak dan direkap](10-formulir-dan-rekap-SUS.xlsx)
