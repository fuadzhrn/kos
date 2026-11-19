<?php
require_once __DIR__ . '/../includes/koneksi.php';

/** Satu INSERT atomik. Pengulangan mempertahankan isi dan tanda baca yang sudah tersimpan. */
function buat_notifikasi_tagihan(PDO $db, string $hari, ?int $tagihanId = null): int
{
    $filter = $tagihanId === null ? '' : ' AND t.id = ?';
    $sql = "INSERT INTO notifikasi (penghuni_id, tagihan_id, tipe, peristiwa, judul, pesan, tanggal)
        SELECT s.penghuni_id, t.id,
            CASE WHEN pb.id IS NOT NULL THEN 'lunas' WHEN t.jatuh_tempo < acuan.hari THEN 'terlambat' ELSE 'pengingat' END,
            CASE WHEN pb.id IS NOT NULL THEN CONCAT('bayar-', pb.id) WHEN t.jatuh_tempo < acuan.hari THEN 'terlambat'
                 ELSE CONCAT('h', DATEDIFF(t.jatuh_tempo, acuan.hari)) END,
            CASE WHEN pb.id IS NOT NULL THEN 'Pembayaran diterima' WHEN t.jatuh_tempo < acuan.hari THEN 'Tagihan terlambat'
                 ELSE CONCAT('Jatuh tempo dalam ', DATEDIFF(t.jatuh_tempo, acuan.hari), ' hari') END,
            CASE WHEN pb.id IS NOT NULL THEN CONCAT('Pembayaran penuh untuk tagihan ', t.periode, ' telah dicatat.')
                 ELSE CONCAT('Tagihan ', t.periode, ' jatuh tempo pada ', DATE_FORMAT(t.jatuh_tempo, '%d-%m-%Y'), '.') END,
            CASE WHEN pb.id IS NOT NULL THEN pb.tanggal_bayar ELSE acuan.hari END
        FROM tagihan t JOIN penyewaan s ON s.id = t.penyewaan_id
        CROSS JOIN (SELECT CAST(? AS DATE) AS hari) acuan
        LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik = t.id AND pb.nominal_dibayar = t.jumlah
        WHERE (pb.id IS NOT NULL OR t.jatuh_tempo < acuan.hari OR DATEDIFF(t.jatuh_tempo, acuan.hari) IN (3,7))" . $filter . "
        ORDER BY t.id ON DUPLICATE KEY UPDATE id = notifikasi.id";
    $stmt = $db->prepare($sql);
    $stmt->execute($tagihanId === null ? [$hari] : [$hari, $tagihanId]);
    return $stmt->rowCount();
}

// Tampilkan peristiwa yang masih relevan dengan status sah tagihan dan pemilik sewa.
const SQL_NOTIFIKASI_PEMILIK = " FROM notifikasi n
    JOIN tagihan t ON t.id = n.tagihan_id
    JOIN penyewaan s ON s.id = t.penyewaan_id AND s.penghuni_id = n.penghuni_id
    LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik = t.id AND pb.nominal_dibayar = t.jumlah
    WHERE n.penghuni_id = ? AND ((n.tipe = 'lunas' AND n.peristiwa = CONCAT('bayar-', pb.id))
        OR (n.tipe <> 'lunas' AND pb.id IS NULL))";

function get_notifikasi_penghuni(int $penghuniId, int $batas = PHP_INT_MAX): array
{
    $stmt = koneksi_database()->prepare('SELECT n.id, n.tipe, n.judul, n.pesan, n.tanggal, n.dibaca'
        . SQL_NOTIFIKASI_PEMILIK . ' ORDER BY n.tanggal DESC, n.id DESC LIMIT ?');
    $stmt->bindValue(1, $penghuniId, PDO::PARAM_INT);
    $stmt->bindValue(2, $batas, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function jumlah_notifikasi_belum_dibaca(int $penghuniId): int
{
    $stmt = koneksi_database()->prepare('SELECT COUNT(*)' . SQL_NOTIFIKASI_PEMILIK . ' AND n.dibaca = 0');
    $stmt->execute([$penghuniId]);
    return (int) $stmt->fetchColumn();
}

function baca_notifikasi(int $penghuniId, int $id): bool
{
    $db = koneksi_database();
    $stmt = $db->prepare('SELECT n.id' . SQL_NOTIFIKASI_PEMILIK . ' AND n.id = ?');
    $stmt->execute([$penghuniId, $id]);
    if (!$stmt->fetchColumn()) {
        return false;
    }
    $stmt = $db->prepare('UPDATE notifikasi SET dibaca = 1, dibaca_pada = COALESCE(dibaca_pada, NOW()) WHERE id = ? AND penghuni_id = ?');
    $stmt->execute([$id, $penghuniId]);
    return true;
}
