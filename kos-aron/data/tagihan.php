<?php
require_once __DIR__ . '/penghuni.php';

const SQL_RIWAYAT_TAGIHAN = 'SELECT t.*, s.penghuni_id, p.nama AS nama_penghuni, k.nomor AS nomor_kamar,
    CASE WHEN pb.nominal_dibayar = t.jumlah THEN pb.tanggal_bayar END AS tanggal_bayar,
    a.id AS pengajuan_id, a.status_verifikasi AS status_pengajuan, a.catatan_admin AS alasan_pengajuan,
    pb.id AS pembayaran_sah_id FROM tagihan t JOIN penyewaan s ON s.id = t.penyewaan_id
    JOIN penghuni p ON p.id = s.penghuni_id JOIN kamar k ON k.id = s.kamar_id
    LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik = t.id
    LEFT JOIN pengajuan_pembayaran a ON a.id=(SELECT us.id FROM pengajuan_pembayaran us
        WHERE us.tagihan_id=t.id AND us.penghuni_id=s.penghuni_id ORDER BY us.id DESC LIMIT 1)';

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
function get_tagihan_terbaru(int $batas, ?string $periode = null): array
{
    $sql = SQL_RIWAYAT_TAGIHAN . ($periode === null ? '' : ' WHERE t.periode=?')
        . ' ORDER BY t.periode DESC,t.id DESC LIMIT ?';
    $stmt = koneksi_database()->prepare($sql);
    if ($periode !== null) {
        $stmt->bindValue(1, $periode);
    }
    $stmt->bindValue($periode === null ? 1 : 2, $batas, PDO::PARAM_INT);
    $stmt->execute();
    return array_map('lengkapi_tagihan', $stmt->fetchAll());
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

/** Tagihan sewa aktif pada bulan kalender berjalan (WIB). */
function get_tagihan_berjalan(int $penghuniId, bool $sertakanTerbaru = false): ?array
{
    $periode = substr(tanggal_hari_ini(), 0, 7);
    $sql = SQL_RIWAYAT_TAGIHAN . ' WHERE s.penghuni_id = ?';
    $params = [$penghuniId,$periode];
    if ($sertakanTerbaru) {
        $sql .= " ORDER BY (s.status='aktif' AND t.periode=?) DESC,
            (pb.id IS NULL AND t.periode<=?) DESC, (t.periode<=?) DESC,
            CASE WHEN t.periode>? THEN t.periode END ASC, t.periode DESC,t.id DESC LIMIT 1";
        array_push($params, $periode, $periode, $periode);
    } else {
        $sql .= " AND s.status='aktif' AND t.periode=? LIMIT 1";
    }
    $stmt = koneksi_database()->prepare($sql);
    $stmt->execute($params);
    $baris = $stmt->fetch();
    return $baris ? lengkapi_tagihan($baris) : null;
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
    return koneksi_database()->query('SELECT DISTINCT periode FROM tagihan ORDER BY periode DESC')->fetchAll(PDO::FETCH_COLUMN);
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

/** Semua faktur pada periode tagihan, termasuk riwayat sewa selesai. Ikatan unik mencegah penghitungan ganda. */
function ringkasan_tagihan_periode(?string $periode = null, ?string $akhir = null, ?int $penghuniId = null): array
{
    $sql = "SELECT COUNT(*) jumlah, COUNT(CASE WHEN s.status='aktif' THEN 1 END) jumlah_aktif,
        COALESCE(SUM(t.jumlah),0) total, COUNT(pb.id) jumlah_bayar, COALESCE(SUM(pb.nominal_dibayar),0) total_bayar,
        COUNT(*)-COUNT(pb.id) belum_lunas_jumlah, COALESCE(SUM(IF(pb.id IS NULL,t.jumlah,0)),0) belum_lunas_total,
        COUNT(CASE WHEN pb.id IS NULL AND t.jatuh_tempo < ? THEN 1 END) terlambat,
        COUNT(CASE WHEN pb.id IS NULL AND t.jatuh_tempo BETWEEN ? AND ? THEN 1 END) dekat,
        COUNT(a.id) menunggu
        FROM tagihan t JOIN penyewaan s ON s.id=t.penyewaan_id
        LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik=t.id AND pb.nominal_dibayar=t.jumlah
        LEFT JOIN pengajuan_pembayaran a ON a.tagihan_menunggu_unik=t.id WHERE 1=1";
    $hari = tanggal_hari_ini();
    $params = [$hari,$hari,batas_pengingat_tagihan($hari)];
    if ($periode !== null) {
        $sql .= ' AND t.periode BETWEEN ? AND ?';
        array_push($params, $periode, $akhir ?? $periode);
    }
    if ($penghuniId !== null) {
        $sql .= ' AND s.penghuni_id=?';
        $params[] = $penghuniId;
    }
    $stmt = koneksi_database()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function ringkasan_tagihan_belum_lunas(): array
{
    $hasil = ringkasan_tagihan_periode();
    return ['jumlah'=>$hasil['belum_lunas_jumlah'], 'total'=>$hasil['belum_lunas_total'], 'terlambat'=>$hasil['terlambat']];
}

require_once __DIR__ . '/pembayaran.php';
