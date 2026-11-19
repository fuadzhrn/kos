-- Jalankan pada skema Kos Aron yang dipilih. Semua pembayaran lama tetap sah.
-- Satu perubahan ALTER atomik mengganti indeks dan mempertahankan FK tagihan.
CREATE TABLE IF NOT EXISTS pembayaran_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pembayaran_id BIGINT UNSIGNED NOT NULL,
    aksi ENUM('catat','koreksi','batal') NOT NULL,
    admin_id BIGINT UNSIGNED NOT NULL,
    waktu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    alasan VARCHAR(255) NULL,
    sebelum JSON NULL,
    sesudah JSON NOT NULL,
    KEY idx_audit_pembayaran (pembayaran_id, id),
    CONSTRAINT fk_audit_pembayaran FOREIGN KEY (pembayaran_id) REFERENCES pembayaran(id) ON DELETE RESTRICT,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sudah_tahap7 = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'pembayaran' AND column_name = 'tagihan_aktif_unik');
SET @perubahan = IF(@sudah_tahap7 = 0, 'ALTER TABLE pembayaran
    ADD COLUMN status ENUM(''sah'',''dibatalkan'') NOT NULL DEFAULT ''sah'',
    ADD COLUMN tagihan_aktif_unik BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status = ''sah'', tagihan_id, NULL)) VIRTUAL,
    ADD KEY idx_pembayaran_tagihan (tagihan_id),
    ADD UNIQUE KEY uq_pembayaran_tagihan_aktif (tagihan_aktif_unik),
    DROP INDEX uq_pembayaran_tagihan', 'SELECT 1');
PREPARE migrasi_pembayaran FROM @perubahan;
EXECUTE migrasi_pembayaran;
DEALLOCATE PREPARE migrasi_pembayaran;
