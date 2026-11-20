<?php
require_once __DIR__ . '/pembayaran.php';

/** Batas periode inklusif. Pembayaran mengikuti periode tagihan pada faktur. */
function data_laporan(?string $awal, ?string $akhir, string $tanggalKamar): array
{
    $db = koneksi_database();
    $filter = $awal === null ? '' : ' AND t.periode BETWEEN ? AND ?';
    $parameter = $awal === null ? [] : [$awal, $akhir];
    $db->beginTransaction();
    try {
        $ringkasan = ringkasan_tagihan_periode($awal, $akhir);
        $stmt = $db->prepare(SQL_RIWAYAT_PEMBAYARAN . " WHERE pb.status = 'sah' AND pb.nominal_dibayar = t.jumlah"
            . $filter . ' ORDER BY pb.tanggal_bayar DESC, pb.id DESC');
        $stmt->execute($parameter);
        $pembayaran = $stmt->fetchAll();

        // Pada hari pindah, penyewa yang mulai paling akhir mewakili kamar itu.
        $stmt = $db->prepare("WITH hunian AS (
            SELECT s.*, ROW_NUMBER() OVER (PARTITION BY kamar_id ORDER BY tanggal_masuk DESC, id DESC) AS urutan
            FROM penyewaan s WHERE tanggal_masuk <= ? AND (tanggal_keluar IS NULL OR tanggal_keluar > ?)
        ), inventaris AS (
            SELECT k.nomor, COALESCE(h.harga_sewa_disepakati,k.harga) harga, p.nama,
                CASE WHEN ? = ? THEN " . SQL_KETERSEDIAAN_KAMAR . "
                     WHEN h.id IS NOT NULL THEN 'terisi' ELSE 'kosong' END AS status
            FROM kamar k LEFT JOIN hunian h ON h.kamar_id = k.id AND h.urutan = 1
            LEFT JOIN penghuni p ON p.id = h.penghuni_id
            WHERE DATE(k.created_at) <= ? OR EXISTS (
                SELECT 1 FROM penyewaan lama WHERE lama.kamar_id = k.id AND lama.tanggal_masuk <= ?)
        ) SELECT inventaris.*, COUNT(*) OVER () total, SUM(status='terisi') OVER () terisi,
            SUM(status='kosong') OVER () kosong, SUM(status='dipesan') OVER () dipesan, SUM(status='perawatan') OVER () perawatan FROM inventaris ORDER BY nomor");
        $stmt->execute([$tanggalKamar, $tanggalKamar, $tanggalKamar, tanggal_hari_ini(), $tanggalKamar, $tanggalKamar]);
        $kamar = $stmt->fetchAll();
        $db->commit();
        return [$ringkasan, $pembayaran, $kamar];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
