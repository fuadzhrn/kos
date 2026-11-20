<?php
require_once __DIR__ . '/../includes/koneksi.php';

const HARI_PENGINGAT_TAGIHAN = 3;

function batas_pengingat_tagihan(string $hari): string
{
    return (new DateTimeImmutable($hari))->modify('+' . HARI_PENGINGAT_TAGIHAN . ' days')->format('Y-m-d');
}

function buat_notifikasi_tagihan(PDO $db, string $hari, ?int $tagihanId = null): int
{
    if ($tagihanId !== null) {
        // Jalur pembayaran langsung, dipanggil dalam transaksi pencatatan pembayaran.
        $stmt = $db->prepare("INSERT INTO notifikasi (penghuni_id,tagihan_id,tipe,peristiwa,judul,pesan,tanggal)
            SELECT s.penghuni_id,t.id,'lunas',CONCAT('bayar-',pb.id),'Pembayaran diterima',
                CONCAT('Pembayaran penuh untuk tagihan ',t.periode,' telah dicatat.'),?
            FROM tagihan t JOIN penyewaan s ON s.id=t.penyewaan_id
            JOIN pembayaran pb ON pb.tagihan_aktif_unik=t.id AND pb.nominal_dibayar=t.jumlah
            WHERE t.id=? ON DUPLICATE KEY UPDATE id=notifikasi.id");
        $stmt->execute([$hari,$tagihanId]);
        return $stmt->rowCount();
    }
    $stmt = $db->prepare("INSERT INTO notifikasi (penghuni_id,tagihan_id,tipe,peristiwa,judul,pesan,tanggal)
        SELECT s.penghuni_id,t.id,IF(t.jatuh_tempo<h.hari,'terlambat','pengingat'),
            CASE WHEN t.jatuh_tempo<h.hari THEN 'terlambat' WHEN t.jatuh_tempo=h.hari THEN 'h0' ELSE ? END,
            CASE WHEN t.jatuh_tempo<h.hari THEN 'Tagihan terlambat' WHEN t.jatuh_tempo=h.hari THEN 'Jatuh tempo hari ini'
                 ELSE 'Tagihan mendekati jatuh tempo' END,
            CONCAT('Tagihan ',t.periode,' jatuh tempo pada ',DATE_FORMAT(t.jatuh_tempo,'%d-%m-%Y'),'.'), h.hari
        FROM tagihan t JOIN penyewaan s ON s.id=t.penyewaan_id
        JOIN penghuni p ON p.id=s.penghuni_id JOIN users u ON u.id=p.user_id
        CROSS JOIN (SELECT CAST(? AS DATE) hari) h
        LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik=t.id AND pb.nominal_dibayar=t.jumlah
        LEFT JOIN pengajuan_pembayaran a ON a.tagihan_menunggu_unik=t.id
        WHERE s.status='aktif' AND u.status='aktif' AND u.role='penghuni' AND s.tanggal_masuk<=h.hari
            AND pb.id IS NULL AND a.id IS NULL AND t.jatuh_tempo<=?
        ORDER BY t.id ON DUPLICATE KEY UPDATE id=notifikasi.id");
    $stmt->execute(['h' . HARI_PENGINGAT_TAGIHAN,$hari,batas_pengingat_tagihan($hari)]);
    return $stmt->rowCount();
}

// Daftar, lencana dan aksi baca memakai aturan visibilitas yang sama.
const SQL_NOTIFIKASI_PEMILIK = " FROM notifikasi n
    JOIN tagihan t ON t.id=n.tagihan_id
    JOIN penyewaan s ON s.id=t.penyewaan_id AND s.penghuni_id=n.penghuni_id
    CROSS JOIN (SELECT CAST(? AS DATE) hari) h
    LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik=t.id AND pb.nominal_dibayar=t.jumlah
    LEFT JOIN pengajuan_pembayaran a ON a.tagihan_menunggu_unik=t.id
    WHERE n.penghuni_id=? AND ((n.tipe='lunas' AND n.peristiwa=CONCAT('bayar-',pb.id))
        OR n.tipe='verifikasi'
        OR (pb.id IS NULL AND a.id IS NULL AND s.status='aktif' AND (
            (n.tipe='terlambat' AND t.jatuh_tempo<h.hari)
            OR (n.tipe='pengingat' AND n.peristiwa='h0' AND t.jatuh_tempo=h.hari)
            OR (n.tipe='pengingat' AND n.peristiwa<>'h0' AND DATEDIFF(t.jatuh_tempo,h.hari) BETWEEN 1 AND " . HARI_PENGINGAT_TAGIHAN . ")
        )))";

function get_notifikasi_penghuni(int $penghuniId, int $batas = PHP_INT_MAX, int $offset = 0): array
{
    $stmt = koneksi_database()->prepare('SELECT n.id, n.tipe, n.judul, n.pesan, n.tanggal, n.dibaca'
        . SQL_NOTIFIKASI_PEMILIK . ' ORDER BY n.tanggal DESC, n.id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, tanggal_hari_ini());
    $stmt->bindValue(2, $penghuniId, PDO::PARAM_INT);
    $stmt->bindValue(3, $batas, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function jumlah_notifikasi_belum_dibaca(int $penghuniId): int
{
    $stmt = koneksi_database()->prepare('SELECT COUNT(*)' . SQL_NOTIFIKASI_PEMILIK . ' AND n.dibaca = 0');
    $stmt->execute([tanggal_hari_ini(), $penghuniId]);
    return (int) $stmt->fetchColumn();
}

function baca_notifikasi(int $penghuniId, int $id): bool
{
    $db = koneksi_database();
    $stmt = $db->prepare('SELECT n.id' . SQL_NOTIFIKASI_PEMILIK . ' AND n.id = ?');
    $stmt->execute([tanggal_hari_ini(), $penghuniId, $id]);
    if (!$stmt->fetchColumn()) {
        return false;
    }
    $stmt = $db->prepare('UPDATE notifikasi SET dibaca = 1, dibaca_pada = COALESCE(dibaca_pada, NOW()) WHERE id = ? AND penghuni_id = ?');
    $stmt->execute([$id, $penghuniId]);
    return true;
}
