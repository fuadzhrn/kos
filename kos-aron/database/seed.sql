-- ============================================================
-- Sistem Informasi Kos Aron — Data Uji (FIKTIF)
-- db_kos_aron
-- ----------------------------------------------------------
-- SELURUH nama, nomor telepon, dan email di file ini adalah FIKTIF,
-- dibuat khusus untuk pengujian struktur database. BUKAN data
-- penghuni Kos Aron yang sebenarnya.
--
-- Akun fixture memakai penanda yang tidak dapat diverifikasi sebagai password.
-- Pengujian terisolasi mengaktifkan akun dan membuat hash acak saat runtime.
--
-- Akun ADMIN (id 1) SENGAJA dibuat TIDAK BISA login (status 'nonaktif'
-- + password_hash berisi nilai acak yang tidak cocok dengan password
-- apa pun): project ini TIDAK membuat akun admin contoh yang bisa
-- diakses publik. Baris ini hanya ada untuk memenuhi referensi FK
-- (dibuat_oleh/dicatat_oleh) pada data uji di bawah. Akun admin yang
-- SUNGGUHAN dibuat lewat `php database/buat-admin.php` (lihat
-- docs/07-login-dan-keamanan.md) -- skrip itu akan mengganti baris ini
-- (username yang sama) dengan password pilihan Anda sendiri.
--
-- Khusus database uji kosong setelah schema.sql. Semua INSERT berada
-- dalam satu transaksi. Gunakan database terpisah untuk pengujian.
--
-- Tanggal acuan skenario (untuk status terlambat/belum_bayar yang
-- realistis): 2026-11-19.
-- ============================================================

USE db_kos_aron;

SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- ------------------------------------------------------------
-- users (1 admin + 3 akun penghuni uji nonaktif; Yusuf/penghuni lama
-- sengaja TIDAK diberi akun, untuk mencontohkan riwayat yang
-- dibuat sebelum sistem login ada)
-- ------------------------------------------------------------
INSERT INTO users (id, username, password_hash, role, nama, status) VALUES
    (1, 'admin',        'TIDAK-BISA-LOGIN-jalankan-php-database-buat-admin.php-untuk-mengisi-akun-ini', 'admin',    'Admin Kos Aron',  'nonaktif'),
    (2, 'ahmad.fauzi',   'AKUN-UJI-NONAKTIF', 'penghuni', 'Ahmad Fauzi',     'nonaktif'),
    (3, 'lina.marlina',  'AKUN-UJI-NONAKTIF', 'penghuni', 'Lina Marlina',    'nonaktif'),
    (4, 'dedi.kurniawan','AKUN-UJI-NONAKTIF', 'penghuni', 'Dedi Kurniawan',  'nonaktif');

-- ------------------------------------------------------------
-- kamar (6 kamar: status kosong/terisi TIDAK disimpan di sini --
-- selalu dihitung dari tabel `penyewaan` di bawah, lihat data/kamar.php.
-- Kamar '03' sengaja diberi kondisi 'perawatan' untuk menguji aturan
-- "kamar dalam perawatan tidak dapat disewakan" (TUGAS 2).)
-- ------------------------------------------------------------
INSERT INTO kamar (id, nomor, harga, deskripsi, fasilitas, foto_utama, galeri, kondisi) VALUES
    (1, '01', 500000.00, 'Kamar contoh untuk pengujian.', JSON_ARRAY('Kasur', 'Lemari', 'Meja', 'Kamar mandi luar'), NULL, JSON_ARRAY(), 'aktif'),
    (2, '02', 500000.00, 'Kamar contoh untuk pengujian.', JSON_ARRAY('Kasur', 'Lemari', 'Meja', 'Kamar mandi luar'), NULL, JSON_ARRAY(), 'aktif'),
    (3, '03', 600000.00, 'Kamar contoh untuk pengujian. Sedang dalam perawatan.', JSON_ARRAY('Kasur', 'Lemari', 'Meja', 'Kipas angin', 'Kamar mandi dalam'), NULL, JSON_ARRAY(), 'perawatan'),
    (4, '04', 600000.00, 'Kamar contoh untuk pengujian.', JSON_ARRAY('Kasur', 'Lemari', 'Meja', 'Kipas angin', 'Kamar mandi dalam'), NULL, JSON_ARRAY(), 'aktif'),
    (5, '05', 550000.00, 'Kamar contoh untuk pengujian.', JSON_ARRAY('Kasur', 'Lemari', 'Kipas angin', 'Kamar mandi luar'), NULL, JSON_ARRAY(), 'aktif'),
    (6, '06', 550000.00, 'Kamar contoh untuk pengujian.', JSON_ARRAY('Kasur', 'Lemari', 'Kipas angin', 'Kamar mandi luar'), NULL, JSON_ARRAY(), 'aktif');

-- ------------------------------------------------------------
-- penghuni (3 aktif + 1 riwayat/sudah keluar)
-- ------------------------------------------------------------
INSERT INTO penghuni (id, user_id, nama, no_telepon, email, keterangan) VALUES
    (1, 2,    'Ahmad Fauzi',    '0811-0000-0101', 'ahmad.fauzi@contoh.test',    NULL),
    (2, 3,    'Lina Marlina',   '0811-0000-0102', 'lina.marlina@contoh.test',   NULL),
    (3, 4,    'Dedi Kurniawan', '0811-0000-0103', 'dedi.kurniawan@contoh.test', NULL),
    (4, NULL, 'Yusuf Hidayat',  '0811-0000-0104', NULL,                         'Riwayat penyewaan lama, sebelum sistem login dibuat.');

-- ------------------------------------------------------------
-- penyewaan (3 aktif, selaras dengan kamar 04/05/06 = terisi;
-- 1 riwayat yang sudah 'selesai' di kamar 04 SEBELUM Ahmad masuk,
-- untuk mencontohkan "riwayat penyewaan tidak hilang")
-- ------------------------------------------------------------
INSERT INTO penyewaan (id, penghuni_id, kamar_id, harga_sewa_disepakati, hari_jatuh_tempo, tanggal_masuk, tanggal_keluar, status, keterangan, dibuat_oleh) VALUES
    (1, 4, 4, 600000.00, 20, '2025-08-01', '2026-07-31', 'selesai', 'Penyewaan lama, sudah keluar.', 1),
    (2, 1, 4, 600000.00, 20, '2026-08-01', NULL,         'aktif',   NULL,                            1),
    (3, 2, 5, 550000.00, 15, '2026-06-15', NULL,         'aktif',   NULL,                            1),
    (4, 3, 6, 550000.00, 25, '2026-09-01', NULL,         'aktif',   NULL,                            1);

-- ------------------------------------------------------------
-- tagihan
-- Skenario (acuan tanggal hari ini: 2026-11-19):
--   id 1  Yusuf   2026-07  jatuh tempo 2026-07-20  -> LUNAS   (riwayat lama)
--   id 2  Ahmad   2026-10  jatuh tempo 2026-10-20  -> LUNAS
--   id 3  Lina    2026-10  jatuh tempo 2026-10-15  -> TERLAMBAT (belum bayar, sudah lewat)
--   id 4  Lina    2026-11  jatuh tempo 2026-11-15  -> TERLAMBAT (belum bayar, sudah lewat)
--   id 5  Ahmad   2026-11  jatuh tempo 2026-11-20  -> BELUM BAYAR (belum lewat, besok)
--   id 6  Dedi    2026-10  jatuh tempo 2026-10-25  -> LUNAS
--   id 7  Dedi    2026-11  jatuh tempo 2026-11-25  -> BELUM BAYAR (belum lewat)
-- ------------------------------------------------------------
INSERT INTO tagihan (id, penyewaan_id, periode, jumlah, jatuh_tempo, keterangan, dibuat_oleh) VALUES
    (1, 1, '2026-07', 600000.00, '2026-07-20', NULL, 1),
    (2, 2, '2026-10', 600000.00, '2026-10-20', NULL, 1),
    (3, 3, '2026-10', 550000.00, '2026-10-15', NULL, 1),
    (4, 3, '2026-11', 550000.00, '2026-11-15', NULL, 1),
    (5, 2, '2026-11', 600000.00, '2026-11-20', NULL, 1),
    (6, 4, '2026-10', 550000.00, '2026-10-25', NULL, 1),
    (7, 4, '2026-11', 550000.00, '2026-11-25', NULL, 1);

-- ------------------------------------------------------------
-- pembayaran (hanya untuk tagihan yang LUNAS: id 1, 2, 6 di atas;
-- nominal_dibayar SAMA PERSIS dengan tagihan.jumlah, sesuai aturan
-- "tidak ada pembayaran cicilan")
-- ------------------------------------------------------------
INSERT INTO pembayaran (id, tagihan_id, tanggal_bayar, nominal_dibayar, metode, keterangan, dicatat_oleh) VALUES
    (1, 1, '2026-07-18', 600000.00, 'tunai',    NULL, 1),
    (2, 2, '2026-10-19', 600000.00, 'transfer', NULL, 1),
    (3, 6, '2026-10-24', 550000.00, 'tunai',    NULL, 1);

-- ------------------------------------------------------------
-- notifikasi (contoh; tipe harus unik per tagihan_id, lihat
-- uq_notifikasi_tagihan_tipe)
-- ------------------------------------------------------------
INSERT INTO notifikasi (penghuni_id, tagihan_id, tipe, judul, pesan, tanggal, dibaca, dibaca_pada) VALUES
    (4, 1, 'lunas',     'Pembayaran Diterima',   'Tagihan periode Juli 2026 telah dibayar dan dinyatakan Lunas pada 18 Juli 2026.', '2026-07-18', 1, '2026-07-19 09:00:00'),
    (1, 2, 'lunas',     'Pembayaran Diterima',   'Tagihan periode Oktober 2026 telah dibayar dan dinyatakan Lunas pada 19 Oktober 2026.', '2026-10-19', 1, '2026-10-20 08:30:00'),
    (3, 6, 'lunas',     'Pembayaran Diterima',   'Tagihan periode Oktober 2026 telah dibayar dan dinyatakan Lunas pada 24 Oktober 2026.', '2026-10-24', 1, '2026-10-25 07:10:00'),
    (2, 3, 'terlambat', 'Tagihan Terlambat',     'Tagihan periode Oktober 2026 sudah melewati jatuh tempo (15 Oktober 2026). Segera lakukan pembayaran kepada pemilik.', '2026-11-19', 0, NULL),
    (2, 4, 'terlambat', 'Tagihan Terlambat',     'Tagihan periode November 2026 sudah melewati jatuh tempo (15 November 2026). Segera lakukan pembayaran kepada pemilik.', '2026-11-19', 0, NULL),
    (1, 5, 'pengingat', 'Pengingat Jatuh Tempo', 'Tagihan periode November 2026 akan jatuh tempo pada 20 November 2026. Segera lakukan pembayaran kepada pemilik.', '2026-11-19', 0, NULL),
    (3, 7, 'pengingat', 'Pengingat Jatuh Tempo', 'Tagihan periode November 2026 akan jatuh tempo pada 25 November 2026. Segera lakukan pembayaran kepada pemilik.', '2026-11-19', 0, NULL);

-- ------------------------------------------------------------
UPDATE notifikasi n
JOIN tagihan t ON t.id = n.tagihan_id
LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik = t.id AND pb.nominal_dibayar = t.jumlah
SET n.peristiwa = CASE
    WHEN n.tipe = 'lunas' AND pb.id IS NOT NULL THEN CONCAT('bayar-', pb.id)
    WHEN n.tipe = 'terlambat' THEN 'terlambat'
    WHEN n.tipe = 'pengingat' AND DATEDIFF(t.jatuh_tempo,n.tanggal) IN (3,7)
        THEN CONCAT('h', DATEDIFF(t.jatuh_tempo,n.tanggal))
    ELSE 'lama' END
WHERE n.peristiwa = 'lama';

-- pengaturan (nilai yang sudah publik/sama dengan includes/config.php
-- saat ini diisi; yang belum dikonfirmasi pemilik dibiarkan kosong,
-- sama seperti konstanta di config.php)
-- ------------------------------------------------------------
INSERT INTO pengaturan (kunci, nilai, updated_oleh) VALUES
    ('nama_kos',      'Kos Aron',               1),
    ('deskripsi_kos', '',                       1),
    ('alamat',        '',                       1),
    ('whatsapp',      '',                       1),
    ('maps_link',     '',                       1),
    ('maps_embed',    '',                       1),
    ('foto_utama',    'bangunan.jpg',           1);

COMMIT;
