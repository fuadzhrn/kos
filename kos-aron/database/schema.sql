-- ============================================================
-- Sistem Informasi Kos Aron — Skema Database
-- db_kos_aron
-- ----------------------------------------------------------
-- Diturunkan dari rancangan yang disetujui di
-- docs/02-rancangan-database.md dan docs/erd.drawio.
--
-- PERUBAHAN TERHADAP RANCANGAN YANG DISETUJUI (didokumentasikan,
-- lihat juga docs/02-rancangan-database.md bagian "Perubahan pada
-- tahap implementasi"):
--   - Kolom nominal uang (kamar.harga, penyewaan.harga_sewa_disepakati,
--     tagihan.jumlah, pembayaran.nominal_dibayar) diubah dari
--     INT UNSIGNED menjadi DECIMAL(12,2), sesuai instruksi tahap
--     implementasi ini ("gunakan DECIMAL untuk nominal uang").
--     Tidak ada tabel, kolom, PK/FK, atau relasi lain yang berubah.
--   - DECIMAL di MySQL 8 tidak lagi disarankan dipasangkan dengan
--     UNSIGNED (akan dihapus di versi mendatang), sehingga validasi
--     "tidak boleh negatif" dipindah ke CHECK constraint.
--
-- Urutan CREATE TABLE mengikuti urutan dependensi FK (induk dahulu).
-- Aman dijalankan berulang kali (DROP TABLE IF EXISTS di awal) HANYA
-- pada database pengujian/baru — lihat docs/05-panduan-import-database.md
-- untuk peringatan sebelum menjalankan ulang pada database yang sudah
-- berisi data sungguhan.
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_kos_aron
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_kos_aron;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifikasi;
DROP TABLE IF EXISTS pembayaran;
DROP TABLE IF EXISTS tagihan;
DROP TABLE IF EXISTS penyewaan;
DROP TABLE IF EXISTS penghuni;
DROP TABLE IF EXISTS kamar;
DROP TABLE IF EXISTS pengaturan;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. users — akun login admin & penghuni
-- ------------------------------------------------------------
CREATE TABLE users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)     NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('admin', 'penghuni') NOT NULL,
    nama            VARCHAR(100)    NOT NULL,
    status          ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    last_login_at   DATETIME        NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. kamar
-- ------------------------------------------------------------
-- PERUBAHAN (tahap backend Data Kamar, didokumentasikan di
-- docs/02-rancangan-database.md §2.13): kolom `status` (kosong/terisi)
-- DIHAPUS -- nilai itu sekarang SELALU dihitung dari ada/tidaknya baris
-- `penyewaan` berstatus 'aktif' untuk kamar ini (lihat data/kamar.php),
-- tidak lagi disimpan sebagai kolom yang bisa diisi manual lewat form dan
-- berisiko bertentangan dengan data penyewaan yang sebenarnya. Kolom
-- `kondisi` (BARU) adalah hal yang BERBEDA: status OPERASIONAL yang
-- memang diatur manual oleh admin (kamar sedang direnovasi/diperbaiki),
-- independen dari ketersediaan penyewaan.
CREATE TABLE kamar (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomor       VARCHAR(10)     NOT NULL,
    harga       DECIMAL(12, 2)  NOT NULL,
    deskripsi   TEXT            NULL,
    fasilitas   JSON            NULL,
    foto_utama  VARCHAR(255)    NULL,
    galeri      JSON            NULL,
    kondisi     ENUM('aktif', 'perawatan') NOT NULL DEFAULT 'aktif',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kamar_nomor (nomor),
    KEY idx_kamar_kondisi (kondisi),
    CONSTRAINT chk_kamar_harga CHECK (harga >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. penghuni — biodata saja (riwayat sewa ada di tabel penyewaan)
-- ------------------------------------------------------------
CREATE TABLE penghuni (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    nama        VARCHAR(100)    NOT NULL,
    no_telepon  VARCHAR(20)     NOT NULL,
    email       VARCHAR(100)    NULL,
    keterangan  VARCHAR(255)    NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_penghuni_user_id (user_id),
    KEY idx_penghuni_nama (nama),
    CONSTRAINT fk_penghuni_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. penyewaan — riwayat penempatan penghuni di suatu kamar
-- ------------------------------------------------------------
CREATE TABLE penyewaan (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    penghuni_id             BIGINT UNSIGNED NOT NULL,
    kamar_id                BIGINT UNSIGNED NOT NULL,
    harga_sewa_disepakati   DECIMAL(12, 2)  NOT NULL,
    hari_jatuh_tempo        TINYINT UNSIGNED NOT NULL,
    tanggal_masuk           DATE            NOT NULL,
    tanggal_keluar          DATE            NULL,
    status                  ENUM('aktif', 'selesai') NOT NULL DEFAULT 'aktif',
    keterangan              VARCHAR(255)    NULL,
    dibuat_oleh             BIGINT UNSIGNED NULL,
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Kolom generated: hanya bernilai non-NULL saat status = 'aktif'.
    -- MySQL mengizinkan banyak NULL pada UNIQUE INDEX, sehingga baris
    -- 'selesai' tidak saling membentur, tetapi hanya SATU baris 'aktif'
    -- per kamar / per penghuni yang boleh ada di waktu yang sama.
    kamar_aktif_unik        BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(status = 'aktif', kamar_id, NULL)
    ) VIRTUAL,
    penghuni_aktif_unik     BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(status = 'aktif', penghuni_id, NULL)
    ) VIRTUAL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kamar_aktif (kamar_aktif_unik),
    UNIQUE KEY uq_penghuni_aktif (penghuni_aktif_unik),
    KEY idx_penyewaan_penghuni (penghuni_id, status),
    KEY idx_penyewaan_kamar (kamar_id, status),
    CONSTRAINT fk_penyewaan_penghuni
        FOREIGN KEY (penghuni_id) REFERENCES penghuni (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_penyewaan_kamar
        FOREIGN KEY (kamar_id) REFERENCES kamar (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_penyewaan_dibuat_oleh
        FOREIGN KEY (dibuat_oleh) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_penyewaan_harga CHECK (harga_sewa_disepakati >= 0),
    CONSTRAINT chk_penyewaan_hari_jatuh_tempo CHECK (hari_jatuh_tempo BETWEEN 1 AND 31),
    CONSTRAINT chk_penyewaan_tanggal CHECK (tanggal_keluar IS NULL OR tanggal_keluar >= tanggal_masuk)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. tagihan — status (lunas/terlambat/belum_bayar) DIHITUNG lewat
--    query/VIEW dari ada-tidaknya baris pembayaran, TIDAK disimpan
--    sebagai kolom, supaya tidak ada status yang bisa basi/tidak sinkron.
-- ------------------------------------------------------------
CREATE TABLE tagihan (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    penyewaan_id    BIGINT UNSIGNED NOT NULL,
    periode         CHAR(7)         NOT NULL COMMENT 'Format YYYY-MM',
    jumlah          DECIMAL(12, 2)  NOT NULL,
    jatuh_tempo     DATE            NOT NULL,
    keterangan      VARCHAR(255)    NULL,
    dibuat_oleh     BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tagihan_penyewaan_periode (penyewaan_id, periode),
    KEY idx_tagihan_periode (periode),
    KEY idx_tagihan_jatuh_tempo (jatuh_tempo),
    CONSTRAINT fk_tagihan_penyewaan
        FOREIGN KEY (penyewaan_id) REFERENCES penyewaan (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tagihan_dibuat_oleh
        FOREIGN KEY (dibuat_oleh) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_tagihan_jumlah CHECK (jumlah >= 0),
    CONSTRAINT chk_tagihan_periode_format CHECK (periode REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. pembayaran — satu tagihan maksimal satu pembayaran (tidak ada cicilan)
-- ------------------------------------------------------------
CREATE TABLE pembayaran (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tagihan_id          BIGINT UNSIGNED NOT NULL,
    tanggal_bayar        DATE            NOT NULL,
    nominal_dibayar      DECIMAL(12, 2)  NOT NULL,
    metode              ENUM('tunai', 'transfer') NOT NULL,
    keterangan          VARCHAR(255)    NULL,
    dicatat_oleh        BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pembayaran_tagihan (tagihan_id),
    KEY idx_pembayaran_tanggal (tanggal_bayar),
    CONSTRAINT fk_pembayaran_tagihan
        FOREIGN KEY (tagihan_id) REFERENCES tagihan (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pembayaran_dicatat_oleh
        FOREIGN KEY (dicatat_oleh) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_pembayaran_nominal CHECK (nominal_dibayar >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. notifikasi
-- ------------------------------------------------------------
CREATE TABLE notifikasi (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    penghuni_id BIGINT UNSIGNED NOT NULL,
    tagihan_id  BIGINT UNSIGNED NULL,
    tipe        ENUM('pengingat', 'terlambat', 'lunas') NOT NULL,
    judul       VARCHAR(100)    NOT NULL,
    pesan       TEXT            NOT NULL,
    tanggal     DATE            NOT NULL,
    dibaca      TINYINT(1)      NOT NULL DEFAULT 0,
    dibaca_pada DATETIME        NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifikasi_tagihan_tipe (tagihan_id, tipe),
    KEY idx_notifikasi_penghuni_dibaca (penghuni_id, dibaca),
    CONSTRAINT fk_notifikasi_penghuni
        FOREIGN KEY (penghuni_id) REFERENCES penghuni (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_notifikasi_tagihan
        FOREIGN KEY (tagihan_id) REFERENCES tagihan (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. pengaturan — key-value, info kos yang dapat diubah admin
-- ------------------------------------------------------------
CREATE TABLE pengaturan (
    kunci           VARCHAR(50)     NOT NULL,
    nilai           TEXT            NULL,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_oleh    BIGINT UNSIGNED NULL,
    PRIMARY KEY (kunci),
    CONSTRAINT fk_pengaturan_updated_oleh
        FOREIGN KEY (updated_oleh) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
