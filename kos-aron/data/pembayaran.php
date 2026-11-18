<?php
/**
 * DATA DUMMY PEMBAYARAN
 * ----------------------------------------------------------------
 * Riwayat pembayaran yang DICATAT ADMIN untuk tagihan sewa. Penghuni membayar
 * tunai atau transfer LANGSUNG kepada pemilik, di luar website; admin yang
 * mencatat bukti pembayaran tersebut di sini. Semua data hanya CONTOH.
 * Nanti diganti query ke tabel `pembayaran` (relasi tagihan_id -> tagihan.id).
 * Status tagihan ('lunas') akan dihitung dari JOIN ke tabel ini, bukan
 * disimpan langsung pada tabel tagihan.
 *
 * Aturan rancangan:
 * - Satu tagihan hanya boleh memiliki SATU pembayaran (tidak ada cicilan
 *   pada versi awal ini).
 * - Nominal yang dibayar harus SAMA PERSIS dengan nominal tagihan.
 * - Pembayaran hanya dapat dicatat untuk tagihan yang belum lunas.
 *
 * Struktur satu pembayaran:
 *   id               int     ID unik
 *   tagihan_id       int     ID tagihan yang dibayar
 *   tanggal_bayar    string  Tanggal pembayaran dicatat admin (YYYY-MM-DD)
 *   nominal_dibayar  int     Nominal yang dibayar (Rupiah)
 *   metode           string  'tunai' | 'transfer'
 *   keterangan       string  Catatan (opsional)
 *
 * Lihat catatan ketergantungan mutual dengan data/tagihan.php di file itu.
 */

require_once __DIR__ . '/tagihan.php';

function data_pembayaran(): array
{
    return [
        ['id' => 1, 'tagihan_id' => 1, 'tanggal_bayar' => '2026-10-05', 'nominal_dibayar' => 500000, 'metode' => 'tunai',    'keterangan' => ''],
        ['id' => 2, 'tagihan_id' => 2, 'tanggal_bayar' => '2026-10-18', 'nominal_dibayar' => 600000, 'metode' => 'transfer', 'keterangan' => ''],
        ['id' => 3, 'tagihan_id' => 4, 'tanggal_bayar' => '2026-11-03', 'nominal_dibayar' => 500000, 'metode' => 'transfer', 'keterangan' => ''],
    ];
}

/** Daftar nama metode pembayaran yang valid, untuk validasi & pilihan form. */
function daftar_metode_pembayaran(): array
{
    return ['tunai' => 'Tunai', 'transfer' => 'Transfer'];
}

/** Pembayaran (mentah) untuk satu tagihan, atau null jika belum dibayar. */
function get_pembayaran_by_tagihan(int $tagihanId): ?array
{
    foreach (data_pembayaran() as $pembayaran) {
        if ($pembayaran['tagihan_id'] === $tagihanId) {
            return $pembayaran;
        }
    }
    return null;
}

/** Lengkapi pembayaran dengan data tagihan terkait: nama penghuni, nomor kamar, periode, nominal tagihan. */
function lengkapi_pembayaran(array $pembayaran): array
{
    $tagihan  = cari_data_tagihan_mentah($pembayaran['tagihan_id']);
    $penghuni = $tagihan ? get_penghuni_by_id($tagihan['penghuni_id']) : null;
    $kamar    = $penghuni ? get_kamar_by_id($penghuni['kamar_id']) : null;

    $pembayaran['penghuni_id']     = $tagihan['penghuni_id'] ?? null;
    $pembayaran['nama_penghuni']   = $penghuni['nama'] ?? '-';
    $pembayaran['nomor_kamar']     = $kamar['nomor'] ?? '-';
    $pembayaran['periode_tagihan'] = $tagihan['periode'] ?? '-';
    $pembayaran['jumlah_tagihan']  = $tagihan['jumlah'] ?? 0;
    return $pembayaran;
}

/**
 * Semua pembayaran yang sudah dilengkapi, tanggal bayar terbaru di atas.
 * Nanti setara dengan:
 *   SELECT pb.*, t.periode, t.jumlah AS jumlah_tagihan, p.nama, k.nomor
 *   FROM pembayaran pb
 *   JOIN tagihan t ON t.id = pb.tagihan_id
 *   JOIN penghuni p ON p.id = t.penghuni_id JOIN kamar k ON k.id = p.kamar_id
 *   ORDER BY pb.tanggal_bayar DESC, pb.id DESC
 */
function get_semua_pembayaran(): array
{
    $hasil = array_map('lengkapi_pembayaran', data_pembayaran());
    usort($hasil, fn ($a, $b) => [$b['tanggal_bayar'], $b['id']] <=> [$a['tanggal_bayar'], $a['id']]);
    return $hasil;
}

/** Satu pembayaran (sudah dilengkapi) berdasarkan id, atau null. */
function get_pembayaran_by_id(int $id): ?array
{
    foreach (data_pembayaran() as $pembayaran) {
        if ($pembayaran['id'] === $id) {
            return lengkapi_pembayaran($pembayaran);
        }
    }
    return null;
}

/**
 * Pembayaran milik satu penghuni, tanggal bayar terbaru di atas.
 * Dipakai untuk "Riwayat Pembayaran" di Dashboard Penghuni.
 *
 * @param int $batas Jumlah maksimal yang dikembalikan
 */
function get_pembayaran_by_penghuni(int $penghuniId, int $batas = PHP_INT_MAX): array
{
    $hasil = array_values(array_filter(get_semua_pembayaran(), fn ($p) => $p['penghuni_id'] === $penghuniId));
    return array_slice($hasil, 0, $batas);
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

    foreach (data_pembayaran() as $pembayaran) {
        if (substr($pembayaran['tanggal_bayar'], 0, 7) === $bulan) {
            $hasil['jumlah']++;
            $hasil['total'] += $pembayaran['nominal_dibayar'];
        }
    }
    return $hasil;
}
