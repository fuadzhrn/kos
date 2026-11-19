<?php
/**
 * DATA DUMMY TAGIHAN
 * ----------------------------------------------------------------
 * Tagihan sewa bulanan per penghuni. Nominal sama dengan harga kamar
 * pada data/kamar.php. Semua data hanya CONTOH.
 * Nanti diganti query ke tabel `tagihan` (relasi penghuni_id -> penghuni.id).
 *
 * Aturan rancangan:
 * - Tagihan hanya dibuat untuk penghuni dengan penyewaan AKTIF.
 * - Satu penghuni hanya punya satu tagihan per periode (tidak ada tagihan ganda).
 * - Status TIDAK disimpan, tetapi dihitung oleh status_tagihan() berdasarkan
 *   ada/tidaknya pembayaran (lihat data/pembayaran.php):
 *     lunas       -> sudah ada pembayaran yang dicatat admin untuk tagihan ini
 *     terlambat   -> belum ada pembayaran dan hari ini sudah melewati jatuh_tempo
 *     belum_bayar -> belum ada pembayaran dan belum melewati jatuh_tempo
 *
 * Struktur satu tagihan:
 *   id           int     ID unik
 *   penghuni_id  int     ID penghuni
 *   periode      string  Bulan tagihan, format YYYY-MM
 *   jumlah       int     Nominal tagihan (Rupiah)
 *   jatuh_tempo  string  Tanggal jatuh tempo (YYYY-MM-DD)
 *   keterangan   string  Catatan (opsional)
 *
 * CATATAN KETERGANTUNGAN: file ini dan data/pembayaran.php saling me-require.
 * Tagihan butuh data pembayaran untuk menghitung status 'lunas', sedangkan
 * pembayaran butuh data tagihan mentah untuk menampilkan nama penghuni/nomor
 * kamar/periode di riwayat pembayaran. Ini aman di PHP karena semua fungsi
 * tingkat atas pada sebuah file didaftarkan SEBELUM baris kode di file itu
 * benar-benar dijalankan, selama tidak ada kode tingkat atas (di luar fungsi)
 * yang langsung memanggil fungsi dari file lain saat file sedang dimuat.
 */

require_once __DIR__ . '/penghuni.php';

function data_tagihan(): array
{
    return [
        ['id' => 1, 'penghuni_id' => 1, 'periode' => '2026-10', 'jumlah' => 500000, 'jatuh_tempo' => '2026-10-20', 'keterangan' => ''],
        ['id' => 2, 'penghuni_id' => 2, 'periode' => '2026-10', 'jumlah' => 600000, 'jatuh_tempo' => '2026-10-20', 'keterangan' => ''],
        ['id' => 3, 'penghuni_id' => 3, 'periode' => '2026-10', 'jumlah' => 550000, 'jatuh_tempo' => '2026-10-20', 'keterangan' => ''],
        ['id' => 4, 'penghuni_id' => 1, 'periode' => '2026-11', 'jumlah' => 500000, 'jatuh_tempo' => '2026-11-20', 'keterangan' => ''],
        ['id' => 5, 'penghuni_id' => 2, 'periode' => '2026-11', 'jumlah' => 600000, 'jatuh_tempo' => '2026-11-20', 'keterangan' => ''],
        ['id' => 6, 'penghuni_id' => 3, 'periode' => '2026-11', 'jumlah' => 550000, 'jatuh_tempo' => '2026-11-20', 'keterangan' => ''],
    ];
}

/** Satu baris tagihan MENTAH (tanpa status/nama/kamar) berdasarkan id, atau null. */
function cari_data_tagihan_mentah(int $id): ?array
{
    foreach (data_tagihan() as $tagihan) {
        if ($tagihan['id'] === $id) {
            return $tagihan;
        }
    }
    return null;
}

/**
 * Status tagihan: 'lunas' | 'terlambat' | 'belum_bayar'.
 * Dihitung dari ada/tidaknya pembayaran (data/pembayaran.php) dan jatuh_tempo.
 */
function status_tagihan(array $tagihan, ?string $hariIni = null): string
{
    if (get_pembayaran_by_tagihan($tagihan['id']) !== null) {
        return 'lunas';
    }
    $hariIni ??= tanggal_hari_ini();
    return $hariIni > $tagihan['jatuh_tempo'] ? 'terlambat' : 'belum_bayar';
}

/** Lengkapi tagihan dengan status, tanggal bayar, nama penghuni, dan nomor kamar. */
function lengkapi_tagihan(array $tagihan): array
{
    $penghuni   = get_penghuni_by_id($tagihan['penghuni_id']);
    $kamar      = $penghuni ? get_kamar_by_id($penghuni['kamar_id']) : null;
    $pembayaran = get_pembayaran_by_tagihan($tagihan['id']);

    $tagihan['status']        = status_tagihan($tagihan);
    $tagihan['tanggal_bayar'] = $pembayaran['tanggal_bayar'] ?? null;
    $tagihan['nama_penghuni'] = $penghuni['nama'] ?? '-';
    $tagihan['nomor_kamar']   = $kamar['nomor'] ?? '-';
    return $tagihan;
}

/**
 * Semua tagihan yang sudah dilengkapi, periode terbaru di atas.
 * Nanti setara dengan:
 *   SELECT t.*, p.nama, k.nomor FROM tagihan t
 *   JOIN penghuni p ON p.id = t.penghuni_id JOIN kamar k ON k.id = p.kamar_id
 *   ORDER BY t.periode DESC, k.nomor
 */
function get_semua_tagihan(): array
{
    $hasil = array_map('lengkapi_tagihan', data_tagihan());
    usort($hasil, fn ($a, $b) => [$b['periode'], $a['nomor_kamar']] <=> [$a['periode'], $b['nomor_kamar']]);
    return $hasil;
}

/** Tagihan terbaru (untuk dashboard). */
function get_tagihan_terbaru(int $batas): array
{
    return array_slice(get_semua_tagihan(), 0, $batas);
}

/** Satu tagihan (sudah dilengkapi) berdasarkan id, atau null. */
function get_tagihan_by_id(int $id): ?array
{
    $tagihan = cari_data_tagihan_mentah($id);
    return $tagihan !== null ? lengkapi_tagihan($tagihan) : null;
}

/** Semua tagihan milik satu penghuni, periode terbaru di atas. */
function get_tagihan_by_penghuni(int $penghuniId): array
{
    return array_values(array_filter(get_semua_tagihan(), fn ($t) => $t['penghuni_id'] === $penghuniId));
}

/**
 * Tagihan "bulan berjalan" milik satu penghuni: tagihan dengan periode yang
 * sama dengan bulan ini jika ada, atau tagihan dengan periode TERBARU jika
 * penghuni belum punya tagihan untuk bulan ini. Null jika belum ada tagihan
 * sama sekali. Dipakai di Dashboard Penghuni untuk kartu "Status Tagihan".
 */
function get_tagihan_berjalan(int $penghuniId): ?array
{
    $daftar = get_tagihan_by_penghuni($penghuniId); // sudah terurut, periode terbaru di atas
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

/**
 * Tagihan penghuni pada periode tertentu (aturan: tidak boleh ganda), atau null.
 *
 * @param int|null $kecualiId ID tagihan yang sedang diedit
 */
function cari_tagihan_periode(int $penghuniId, string $periode, ?int $kecualiId = null): ?array
{
    foreach (data_tagihan() as $tagihan) {
        if ($tagihan['penghuni_id'] === $penghuniId && $tagihan['periode'] === $periode && $tagihan['id'] !== $kecualiId) {
            return $tagihan;
        }
    }
    return null;
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

// Lihat catatan ketergantungan di atas: aman karena hanya fungsi yang saling memanggil.
require_once __DIR__ . '/pembayaran.php';
