<?php
require_once __DIR__ . '/tagihan.php';

const SQL_RIWAYAT_PEMBAYARAN = 'SELECT pb.*, s.penghuni_id, p.nama AS nama_penghuni, k.nomor AS nomor_kamar,
    t.periode AS periode_tagihan, t.jumlah AS jumlah_tagihan FROM pembayaran pb
    JOIN tagihan t ON t.id = pb.tagihan_id JOIN penyewaan s ON s.id = t.penyewaan_id
    JOIN penghuni p ON p.id = s.penghuni_id JOIN kamar k ON k.id = s.kamar_id';

function daftar_metode_pembayaran(): array
{
    return ['tunai' => 'Tunai', 'transfer' => 'Transfer'];
}

function get_semua_pembayaran(): array
{
    return koneksi_database()->query(SQL_RIWAYAT_PEMBAYARAN . ' ORDER BY pb.tanggal_bayar DESC, pb.id DESC')->fetchAll();
}

function get_pembayaran_by_id(int $id): ?array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_PEMBAYARAN . ' WHERE pb.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_pembayaran_by_penghuni(int $penghuniId, int $batas = PHP_INT_MAX): array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_PEMBAYARAN . ' WHERE s.penghuni_id = ?
        ORDER BY pb.tanggal_bayar DESC, pb.id DESC LIMIT ?');
    $stmt->bindValue(1, $penghuniId, PDO::PARAM_INT);
    $stmt->bindValue(2, $batas, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Ringkasan pembayaran untuk tagihan pada satu PERIODE TAGIHAN tertentu
 * (dipakai di Laporan Admin, selaras dengan filter periode yang sama dengan
 * ringkasan_tagihan_periode()). $periode = null berarti semua periode.
 * Beda dengan ringkasan_pembayaran_bulan_ini(): fungsi itu berdasarkan
 * TANGGAL PEMBAYARAN diterima (kas bulan berjalan), bukan periode tagihannya.
 *
 * @return array{jumlah: int, total: int}
 */
function ringkasan_pembayaran_periode(?string $periode = null): array
{
    $hasil = ['jumlah' => 0, 'total' => 0];
    foreach (get_semua_pembayaran() as $pembayaran) {
        if ($periode !== null && $pembayaran['periode_tagihan'] !== $periode) {
            continue;
        }
        $hasil['jumlah']++;
        $hasil['total'] += $pembayaran['nominal_dibayar'];
    }
    return $hasil;
}

/**
 * Ringkasan pembayaran pada satu bulan kalender (default: bulan berjalan).
 *
 * @param string|null $bulan Format YYYY-MM, default bulan dari tanggal_hari_ini()
 * @return array{jumlah: int, total: int, bulan: string}
 */
function ringkasan_pembayaran_bulan_ini(?string $bulan = null): array
{
    $bulan ??= substr(tanggal_hari_ini(), 0, 7);
    $hasil = ['jumlah' => 0, 'total' => 0, 'bulan' => $bulan];

    foreach (get_semua_pembayaran() as $pembayaran) {
        if (substr($pembayaran['tanggal_bayar'], 0, 7) === $bulan) {
            $hasil['jumlah']++;
            $hasil['total'] += $pembayaran['nominal_dibayar'];
        }
    }
    return $hasil;
}
