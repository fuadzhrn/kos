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
        $stmt = $db->prepare("SELECT COUNT(*) jumlah, COALESCE(SUM(t.jumlah),0) total,
            COUNT(pb.id) jumlah_bayar, COALESCE(SUM(pb.nominal_dibayar),0) total_bayar,
            COUNT(*) - COUNT(pb.id) belum_lunas_jumlah,
            COALESCE(SUM(IF(pb.id IS NULL,t.jumlah,0)),0) belum_lunas_total
            FROM tagihan t LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik = t.id AND pb.nominal_dibayar = t.jumlah
            WHERE 1=1" . $filter);
        $stmt->execute($parameter);
        $ringkasan = $stmt->fetch();
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
                CASE WHEN h.id IS NOT NULL THEN 'terisi'
                     WHEN ? = ? AND k.kondisi = 'perawatan' THEN 'perawatan' ELSE 'kosong' END AS status
            FROM kamar k LEFT JOIN hunian h ON h.kamar_id = k.id AND h.urutan = 1
            LEFT JOIN penghuni p ON p.id = h.penghuni_id
            WHERE DATE(k.created_at) <= ? OR EXISTS (
                SELECT 1 FROM penyewaan lama WHERE lama.kamar_id = k.id AND lama.tanggal_masuk <= ?)
        ) SELECT inventaris.*, COUNT(*) OVER () total, SUM(status='terisi') OVER () terisi,
            SUM(status='kosong') OVER () kosong FROM inventaris ORDER BY nomor");
        $stmt->execute([$tanggalKamar, $tanggalKamar, $tanggalKamar, tanggal_hari_ini(), $tanggalKamar, $tanggalKamar]);
        $kamar = $stmt->fetchAll();
        $db->commit();
        return [$ringkasan, $pembayaran, $kamar];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
