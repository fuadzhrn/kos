<?php
/**
 * DATA DUMMY TAGIHAN
 * ----------------------------------------------------------------
 * Tagihan sewa bulanan per penghuni. Jumlah tagihan sama dengan harga
 * kamar pada data/kamar.php. Semua data hanya CONTOH.
 * Nanti diganti query ke tabel `tagihan` (relasi penghuni_id -> penghuni.id).
 *
 * Struktur satu tagihan:
 *   id           int     ID unik
 *   penghuni_id  int     ID penghuni
 *   periode      string  Bulan tagihan, format YYYY-MM
 *   jumlah       int     Nominal tagihan (Rupiah)
 *   status       string  'lunas' | 'menunggu' (bukti bayar belum diverifikasi) | 'belum_lunas'
 */

require_once __DIR__ . '/penghuni.php';

function data_tagihan(): array
{
    return [
        ['id' => 1, 'penghuni_id' => 1, 'periode' => '2026-10', 'jumlah' => 500000, 'status' => 'lunas'],
        ['id' => 2, 'penghuni_id' => 2, 'periode' => '2026-10', 'jumlah' => 600000, 'status' => 'lunas'],
        ['id' => 3, 'penghuni_id' => 3, 'periode' => '2026-10', 'jumlah' => 550000, 'status' => 'lunas'],
        ['id' => 4, 'penghuni_id' => 1, 'periode' => '2026-11', 'jumlah' => 500000, 'status' => 'lunas'],
        ['id' => 5, 'penghuni_id' => 2, 'periode' => '2026-11', 'jumlah' => 600000, 'status' => 'menunggu'],
        ['id' => 6, 'penghuni_id' => 3, 'periode' => '2026-11', 'jumlah' => 550000, 'status' => 'belum_lunas'],
    ];
}

/**
 * Tagihan terbaru beserta nama penghuni dan nomor kamar.
 * Nanti setara dengan:
 *   SELECT t.*, p.nama, k.nomor FROM tagihan t
 *   JOIN penghuni p ON p.id = t.penghuni_id JOIN kamar k ON k.id = p.kamar_id
 *   ORDER BY t.periode DESC, t.id DESC LIMIT ?
 */
function get_tagihan_terbaru(int $batas): array
{
    $daftar = data_tagihan();
    usort($daftar, fn ($a, $b) => [$b['periode'], $b['id']] <=> [$a['periode'], $a['id']]);

    $hasil = [];
    foreach (array_slice($daftar, 0, $batas) as $tagihan) {
        $penghuni = get_penghuni_by_id($tagihan['penghuni_id']);
        $kamar    = $penghuni ? get_kamar_by_id($penghuni['kamar_id']) : null;

        $tagihan['nama_penghuni'] = $penghuni['nama'] ?? '-';
        $tagihan['nomor_kamar']   = $kamar['nomor'] ?? '-';
        $hasil[] = $tagihan;
    }
    return $hasil;
}

/** Semua tagihan milik satu penghuni, periode terbaru di atas. */
function get_tagihan_by_penghuni(int $penghuniId): array
{
    $hasil = array_values(array_filter(data_tagihan(), fn ($t) => $t['penghuni_id'] === $penghuniId));
    usort($hasil, fn ($a, $b) => strcmp($b['periode'], $a['periode']));
    return $hasil;
}

/**
 * Ringkasan tagihan yang belum lunas (status selain 'lunas').
 *
 * @return array{jumlah: int, total: int, menunggu: int}
 */
function ringkasan_tagihan_belum_lunas(): array
{
    $hasil = ['jumlah' => 0, 'total' => 0, 'menunggu' => 0];
    foreach (data_tagihan() as $tagihan) {
        if ($tagihan['status'] === 'lunas') {
            continue;
        }
        $hasil['jumlah']++;
        $hasil['total'] += $tagihan['jumlah'];
        if ($tagihan['status'] === 'menunggu') {
            $hasil['menunggu']++;
        }
    }
    return $hasil;
}
