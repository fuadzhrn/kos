ALTER TABLE notifikasi
    DROP INDEX uq_notifikasi_tagihan_tipe,
    ADD COLUMN peristiwa   VARCHAR(40) NOT NULL DEFAULT 'lama' AFTER tipe,
    ADD UNIQUE KEY uq_notifikasi_peristiwa (tagihan_id, tipe, peristiwa);

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
