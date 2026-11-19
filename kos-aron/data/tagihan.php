<?php
require_once __DIR__ . '/penghuni.php';

const SQL_RIWAYAT_TAGIHAN = 'SELECT t.*, s.penghuni_id, p.nama AS nama_penghuni, k.nomor AS nomor_kamar,
    CASE WHEN pb.nominal_dibayar = t.jumlah THEN pb.tanggal_bayar END AS tanggal_bayar,
    pb.id AS pembayaran_sah_id FROM tagihan t JOIN penyewaan s ON s.id = t.penyewaan_id
    JOIN penghuni p ON p.id = s.penghuni_id JOIN kamar k ON k.id = s.kamar_id
    LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik = t.id';

function data_tagihan(): array
{
    return koneksi_database()->query(SQL_RIWAYAT_TAGIHAN . ' ORDER BY t.periode DESC, k.nomor, t.id')->fetchAll();
}

function status_tagihan(array $tagihan, ?string $hariIni = null): string
{
    if ($tagihan['tanggal_bayar'] !== null) {
        return 'lunas';
    }
    return ($hariIni ?? tanggal_hari_ini()) > $tagihan['jatuh_tempo'] ? 'terlambat' : 'belum_bayar';
}

function lengkapi_tagihan(array $tagihan): array
{
    $tagihan['status'] = status_tagihan($tagihan);
    return $tagihan;
}

function get_semua_tagihan(): array
{
    return array_map('lengkapi_tagihan', data_tagihan());
}

/** Tagihan terbaru (untuk dashboard). */
function get_tagihan_terbaru(int $batas): array
{
    return array_slice(get_semua_tagihan(), 0, $batas);
}

/** Satu tagihan (sudah dilengkapi) berdasarkan id, atau null. */
function get_tagihan_by_id(int $id): ?array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_TAGIHAN . ' WHERE t.id = ?');
    $stmt->execute([$id]);
    $tagihan = $stmt->fetch();
    return $tagihan ? lengkapi_tagihan($tagihan) : null;
}

/** Semua tagihan milik satu penghuni, periode terbaru di atas. */
function get_tagihan_by_penghuni(int $penghuniId): array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_TAGIHAN . ' WHERE s.penghuni_id = ? ORDER BY t.periode DESC, t.id DESC');
    $stmt->execute([$penghuniId]);
    return array_map('lengkapi_tagihan', $stmt->fetchAll());
}

/**
 * Tagihan "bulan berjalan" milik satu penghuni: tagihan dengan periode yang
 * sama dengan bulan ini jika ada, atau tagihan dengan periode TERBARU jika
 * penghuni belum punya tagihan untuk bulan ini. Null jika belum ada tagihan
 * sama sekali. Dipakai di Dashboard Penghuni untuk kartu "Status Tagihan".
 */
function get_tagihan_berjalan(int $penghuniId): ?array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_TAGIHAN . " WHERE s.penghuni_id = ? AND s.status = 'aktif'
        ORDER BY t.periode DESC, t.id DESC");
    $stmt->execute([$penghuniId]);
    $daftar = array_map('lengkapi_tagihan', $stmt->fetchAll());
    if (empty($daftar)) {
        return null;
    }

    $bulanIni = substr(tanggal_hari_ini(), 0, 7);
    foreach ($daftar as $tagihan) {
        if ($tagihan['periode'] === $bulanIni) {
            return $tagihan;
        }
    }
    return $daftar[0];
}

/**
 * Semua tagihan yang BELUM lunas (belum_bayar atau terlambat), periode terbaru
 * di atas. Dipakai sebagai pilihan pada form "Catat Pembayaran".
 */
function get_tagihan_belum_lunas(): array
{
    return array_values(array_filter(get_semua_tagihan(), fn ($t) => $t['status'] !== 'lunas'));
}

/** Daftar periode yang memiliki tagihan, terbaru di atas (untuk filter). */
function daftar_periode_tagihan(): array
{
    $periode = array_unique(array_column(data_tagihan(), 'periode'));
    rsort($periode);
    return $periode;
}

/** Satu periode kalender MySQL, misalnya 2026-11. */
function akhir_bulan_tagihan(string $periode): string
{
    if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $periode)) {
        throw new DomainException('Pilih bulan dan tahun yang valid.');
    }
    return (new DateTimeImmutable($periode . '-01'))->format('Y-m-t');
}

/** Data yang ditinjau admin. Saat konfirmasi, baca ulang satu penyewaan dengan kunci transaksi. */
function calon_tagihan(string $periode, ?int $sewaId = null, bool $kunci = false): array
{
    $akhir = akhir_bulan_tagihan($periode);
    $sql = "SELECT s.id AS penyewaan_id, s.penghuni_id, s.kamar_id, p.nama, k.nomor,
                s.tanggal_masuk, s.tanggal_keluar, s.hari_jatuh_tempo, s.harga_sewa_disepakati,
                t.id AS tagihan_id, t.jumlah AS jumlah_lama, t.jatuh_tempo AS tempo_lama
            FROM penyewaan s JOIN penghuni p ON p.id = s.penghuni_id JOIN kamar k ON k.id = s.kamar_id
            LEFT JOIN tagihan t ON t.penyewaan_id = s.id AND t.periode = ?
            WHERE s.status = 'aktif' AND s.tanggal_masuk <= ?
                AND (s.tanggal_keluar IS NULL OR s.tanggal_keluar >= ?)";
    $params = [$periode, $akhir, $periode . '-01'];
    if ($sewaId !== null) {
        $sql .= ' AND s.id = ?';
        $params[] = $sewaId;
    }
    $sql .= ' ORDER BY s.id';
    if ($kunci) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = koneksi_database()->prepare($sql);
    $stmt->execute($params);
    $hasil = [];
    foreach ($stmt as $sewa) {
        $hari = min((int)$sewa['hari_jatuh_tempo'], (int)substr($akhir, -2));
        $tempo = sprintf('%s-%02d', $periode, $hari);
        if ($tempo < $sewa['tanggal_masuk'] || ($sewa['tanggal_keluar'] !== null && $tempo > $sewa['tanggal_keluar'])) {
            continue;
        }
        $hasil[] = [
            'penyewaan_id' => (int)$sewa['penyewaan_id'],
            'penghuni_id' => (int)$sewa['penghuni_id'],
            'kamar_id' => (int)$sewa['kamar_id'],
            'nama' => $sewa['nama'],
            'nomor' => $sewa['nomor'],
            'hari_jatuh_tempo' => (int)$sewa['hari_jatuh_tempo'],
            'jumlah' => $sewa['jumlah_lama'] ?? $sewa['harga_sewa_disepakati'],
            'jatuh_tempo' => $sewa['tempo_lama'] ?? $tempo,
            'sudah_ada' => $sewa['tagihan_id'] !== null,
        ];
    }
    return $hasil;
}

/** Hanya baris dalam pratinjau sesi yang boleh dibuat. Kegagalan SQL membatalkan seluruh transaksi. */
function konfirmasi_tagihan(array $pratinjau, int $adminId): array
{
    $db = koneksi_database();
    $baris = $pratinjau['baris'];
    $hasil = ['berhasil' => 0, 'dilewati' => 0, 'gagal' => 0];
    // Baris yang sejak awal diberi label "dilewati" tidak pernah ditulis.
    $baru = array_filter($baris, fn ($r) => !$r['sudah_ada']);
    $hasil['dilewati'] = count($baris) - count($baru);
    try {
        $db->beginTransaction();
        $insert = $db->prepare('INSERT INTO tagihan (penyewaan_id, periode, jumlah, jatuh_tempo, keterangan, dibuat_oleh)
            VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($baru as $lama) {
            $sekarang = calon_tagihan($pratinjau['periode'], $lama['penyewaan_id'], true)[0] ?? null;
            if ($sekarang !== null && $sekarang['sudah_ada']) {
                $hasil['dilewati']++;
                continue;
            }
            if ($sekarang !== $lama) {
                // Sewa berakhir atau data yang diperiksa admin berubah.
                $hasil['gagal']++;
                continue;
            }
            try {
                $insert->execute([$lama['penyewaan_id'], $pratinjau['periode'], $sekarang['jumlah'],
                    $sekarang['jatuh_tempo'], $pratinjau['keterangan'], $adminId]);
                $hasil['berhasil']++;
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
                $hasil['dilewati']++;
            }
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Konfirmasi tagihan gagal. SQLSTATE ' . $e->getCode());
        $hasil['berhasil'] = 0;
        $hasil['gagal'] = count($baris) - $hasil['dilewati'];
    }
    return $hasil;
}

/**
 * Ringkasan tagihan untuk satu periode tertentu (dipakai di Laporan Admin).
 * $periode = null berarti semua periode.
 *
 * @return array{jumlah: int, total: int, belum_lunas_jumlah: int, belum_lunas_total: int}
 */
function ringkasan_tagihan_periode(?string $periode = null): array
{
    $hasil = ['jumlah' => 0, 'total' => 0, 'belum_lunas_jumlah' => 0, 'belum_lunas_total' => 0];
    foreach (get_semua_tagihan() as $tagihan) {
        if ($periode !== null && $tagihan['periode'] !== $periode) {
            continue;
        }
        $hasil['jumlah']++;
        $hasil['total'] += $tagihan['jumlah'];
        if ($tagihan['status'] !== 'lunas') {
            $hasil['belum_lunas_jumlah']++;
            $hasil['belum_lunas_total'] += $tagihan['jumlah'];
        }
    }
    return $hasil;
}

/**
 * Ringkasan tagihan yang belum lunas (belum bayar + terlambat).
 *
 * @return array{jumlah: int, total: int, terlambat: int}
 */
function ringkasan_tagihan_belum_lunas(): array
{
    $hasil = ['jumlah' => 0, 'total' => 0, 'terlambat' => 0];
    foreach (data_tagihan() as $tagihan) {
        $status = status_tagihan($tagihan);
        if ($status === 'lunas') {
            continue;
        }
        $hasil['jumlah']++;
        $hasil['total'] += $tagihan['jumlah'];
        if ($status === 'terlambat') {
            $hasil['terlambat']++;
        }
    }
    return $hasil;
}

require_once __DIR__ . '/pembayaran.php';
