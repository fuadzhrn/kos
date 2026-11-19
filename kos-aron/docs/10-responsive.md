# Pemeriksaan responsif dan ilustrasi

Chrome 157 headless merender 31 keadaan halaman publik, admin dan penghuni untuk setiap lebar. Skrip menggunakan salinan aplikasi dengan database uji, akun per peran dan profil Chrome sementara. Permintaan font eksternal diblokir selama pengukuran. Pengujian mengukur tata letak DOM dan dekode gambar. Perangkat fisik, rendering font melalui internet dan perbandingan piksel terhadap rancangan awal belum diuji.

| Viewport CSS | Keadaan halaman | Kontrol diperiksa | Tabel dengan gulir horizontal internal | Dialog | Navigasi peran | Hasil |
|---|---:|---:|---:|---:|---:|---|
| 1440 × 900 | 31 | 167 | 0 | 5 | 3 | Berhasil |
| 1024 × 768 | 31 | 167 | 6 | 5 | 3 | Berhasil |
| 768 × 1024 | 31 | 167 | 6 | 5 | 3 | Berhasil |
| 390 × 844 | 31 | 167 | 8 | 5 | 3 | Berhasil |
| 360 × 800 | 31 | 167 | 8 | 5 | 3 | Berhasil |

Run awal menemukan tombol koreksi pembayaran melampaui area dialog sekitar 62–63 piksel pada lebar 768, 1024 dan 1440. CSS form koreksi diperbaiki. Pada run akhir semua dialog berada dalam viewport, kontrol di luar tabel tetap terlihat, dan tabel lebar yang membutuhkan gulir dapat digeser di dalam wadahnya.

## Paket ilustrasi publik

Keenam berkas lokal terdeteksi sebagai WebP 1280 × 960. Beranda memakai ilustrasi hero, Tentang memakai ilustrasi pengenalan. Ilustrasi kamar 01, 02 dan 03 dipakai sebagai cadangan berurutan untuk data contoh kamar bernomor 01, 02 dan 04. Kamar 03 sedang dalam perawatan. Daftar, detail serta tabel kamar admin memakai pemilihan foto yang sama. Ilustrasi alternatif disimpan sebagai cadangan. Foto asli yang tersedia dan galeri kamar mendapat prioritas. Tidak ada penulisan pada data kamar aplikasi untuk memasang gambar.

Pada kelima viewport, browser memeriksa Beranda, Daftar Kamar, tiga detail kamar, Tentang, dan Data Kamar admin. Gambar yang digunakan di konten utama dapat diambil dari origin lokal dan berhasil didekode. Pemeriksaan menguji URL ilustrasi yang diharapkan, resolusi, alt, teks “Foto ilustrasi” dan object-fit cover. Bingkai publik tetap berasio 4:3. Regresi unggahan memeriksa foto asli pada Beranda/Tentang, prioritas foto kamar atau galeri, serta penghilangan label ilustrasi pada foto asli.

Ulangi dari root repositori dengan `node scripts/uji-responsive.mjs`. Dibutuhkan Node 22 atau lebih baru dengan WebSocket, Chrome, PHP CLI dan MySQL untuk database sementara. Skrip mengukur 155 kombinasi halaman dan viewport.
