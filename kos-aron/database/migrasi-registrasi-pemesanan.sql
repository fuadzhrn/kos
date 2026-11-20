-- Jalankan di database aplikasi setelah backup.
CREATE TABLE IF NOT EXISTS calon_penghuni (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    nomor_telepon VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL,
    tanggal_daftar DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_calon_telepon (nomor_telepon, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pemesanan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_pemesanan CHAR(19) NOT NULL,
    calon_penghuni_id BIGINT UNSIGNED NOT NULL,
    kamar_id BIGINT UNSIGNED NOT NULL,
    tanggal_pengajuan DATETIME NOT NULL,
    rencana_tanggal_masuk DATE NOT NULL,
    status ENUM('menunggu','disetujui','ditolak','dibatalkan') NOT NULL DEFAULT 'menunggu',
    catatan_pemesan VARCHAR(500) NULL,
    catatan_admin VARCHAR(500) NULL,
    diproses_oleh BIGINT UNSIGNED NULL,
    tanggal_diproses DATETIME NULL,
    penyewaan_id BIGINT UNSIGNED NULL,
    kamar_ditahan_unik BIGINT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN status = 'disetujui' AND penyewaan_id IS NULL THEN kamar_id END) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pemesanan_kode (kode_pemesanan),
    UNIQUE KEY uq_pemesanan_sewa (penyewaan_id),
    UNIQUE KEY uq_pemesanan_tahan (kamar_ditahan_unik),
    KEY idx_pemesanan_antrian (status,tanggal_pengajuan),
    CONSTRAINT fk_pemesanan_calon FOREIGN KEY (calon_penghuni_id) REFERENCES calon_penghuni(id),
    CONSTRAINT fk_pemesanan_kamar FOREIGN KEY (kamar_id) REFERENCES kamar(id),
    CONSTRAINT fk_pemesanan_admin FOREIGN KEY (diproses_oleh) REFERENCES users(id),
    CONSTRAINT fk_pemesanan_sewa FOREIGN KEY (penyewaan_id) REFERENCES penyewaan(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
