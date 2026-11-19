<?php
/**
 * NOTIFIKASI PENGHUNI (CONTOH / FRONTEND SAJA)
 * ----------------------------------------------------------------
 * Notifikasi di sini DIHASILKAN dari data tagihan & pembayaran milik
 * penghuni yang sedang login, bukan ditulis manual, supaya periode dan
 * tanggal yang ditampilkan selalu konsisten dengan data tagihan yang
 * sebenarnya (tidak ada tanggal yang hardcode terpisah).
 *
 * Status baca mengikuti catatan notifikasi milik penghuni di database.
 *
 * BELUM ADA sistem pengingat otomatis, cron job, atau pengiriman WhatsApp/
 * email sungguhan. "Notifikasi" di halaman ini murni tampilan: artinya
 * "muncul di halaman ini", bukan benar-benar dikirim ke penghuni.
 * Nanti diganti query ke tabel `notifikasi` (atau tetap dihasilkan dari
 * tagihan lewat VIEW/cron yang menulis baris baru + menyimpan status baca).
 *
 * Tipe notifikasi (dihasilkan dari status_tagihan(), lihat data/tagihan.php):
 *   pengingat  -> tagihan belum dibayar, jatuh tempo <= 7 hari lagi
 *   terlambat  -> tagihan belum dibayar, sudah melewati jatuh tempo
 *   lunas      -> tagihan sudah dibayar (dicatat admin)
 * Tagihan yang belum dibayar TAPI jatuh temponya masih jauh (> 7 hari)
 * tidak menghasilkan notifikasi apa pun (belum ada yang perlu diingatkan).
 */

require_once __DIR__ . '/tagihan.php';

/** Berapa hari gandeng CSS dipakai sbg jarak aman pengingat jatuh tempo. */
const BATAS_HARI_PENGINGAT = 7;

/**
 * Kunci unik satu notifikasi: tagihan + tipe. Dipakai untuk mencocokkan
 * status baca pada data_status_baca_notifikasi(). Stabil selama status
 * tagihan itu sendiri tidak berubah (tidak berubah hanya karena halaman dimuat ulang).
 */
function kunci_notifikasi(int $tagihanId, string $tipe): string
{
    return $tagihanId . '-' . $tipe;
}


/** Berapa hari lagi menuju jatuh tempo (bisa negatif jika sudah lewat). */
function sisa_hari_jatuh_tempo(string $jatuhTempo, ?string $hariIni = null): int
{
    $hariIni ??= tanggal_hari_ini();
    return (int) round((strtotime($jatuhTempo) - strtotime($hariIni)) / 86400);
}

/**
 * Satu notifikasi untuk satu tagihan, atau null jika tagihan ini belum
 * perlu menghasilkan notifikasi apa pun (belum dibayar tapi jatuh tempo masih jauh).
 */
function buat_notifikasi_tagihan(array $tagihan): ?array
{

    if ($tagihan['status'] === 'lunas') {
        $tipe    = 'lunas';
        $judul   = 'Pembayaran Diterima';
        $pesan   = 'Tagihan periode ' . format_periode($tagihan['periode']) . ' telah dibayar dan dinyatakan Lunas pada '
                 . format_tanggal($tagihan['tanggal_bayar']) . '. Terima kasih.';
        $tanggal = $tagihan['tanggal_bayar'];
    } elseif ($tagihan['status'] === 'terlambat') {
        $tipe    = 'terlambat';
        $judul   = 'Tagihan Terlambat';
        $pesan   = 'Tagihan periode ' . format_periode($tagihan['periode']) . ' sudah melewati jatuh tempo ('
                 . format_tanggal($tagihan['jatuh_tempo']) . '). Segera lakukan pembayaran kepada pemilik.';
        $tanggal = tanggal_hari_ini();
    } elseif (sisa_hari_jatuh_tempo($tagihan['jatuh_tempo']) <= BATAS_HARI_PENGINGAT) {
        $tipe    = 'pengingat';
        $judul   = 'Pengingat Jatuh Tempo';
        $pesan   = 'Tagihan periode ' . format_periode($tagihan['periode']) . ' akan jatuh tempo pada '
                 . format_tanggal($tagihan['jatuh_tempo']) . '. Segera lakukan pembayaran kepada pemilik.';
        $tanggal = tanggal_hari_ini();
    } else {
        return null; // belum dibayar, jatuh tempo masih jauh -> belum perlu diingatkan
    }

    $kunci = kunci_notifikasi($tagihan['id'], $tipe);
    $stmt = koneksi_database()->prepare('SELECT dibaca FROM notifikasi WHERE penghuni_id = ? AND tagihan_id = ? AND tipe = ?');
    $stmt->execute([$tagihan['penghuni_id'], $tagihan['id'], $tipe]);

    return [
        'kunci'   => $kunci,
        'tipe'    => $tipe,
        'judul'   => $judul,
        'pesan'   => $pesan,
        'tanggal' => $tanggal,
        'dibaca'  => (bool)$stmt->fetchColumn(),
        'tagihan' => $tagihan,
    ];
}

/**
 * Semua notifikasi milik satu penghuni, dihasilkan dari seluruh tagihan
 * mereka. Terurut: tanggal notifikasi terbaru dulu; jika tanggal sama,
 * yang paling mendesak (terlambat > pengingat > lunas) di atas.
 */
function get_notifikasi_penghuni(int $penghuniId): array
{
    $hasil = [];
    foreach (get_tagihan_by_penghuni($penghuniId) as $tagihan) {
        $notifikasi = buat_notifikasi_tagihan($tagihan);
        if ($notifikasi !== null) {
            $hasil[] = $notifikasi;
        }
    }

    $prioritas = ['terlambat' => 0, 'pengingat' => 1, 'lunas' => 2];
    usort($hasil, function (array $a, array $b) use ($prioritas): int {
        $bandingTanggal = $b['tanggal'] <=> $a['tanggal']; // tanggal terbaru dulu
        return $bandingTanggal !== 0 ? $bandingTanggal : ($prioritas[$a['tipe']] <=> $prioritas[$b['tipe']]);
    });

    return $hasil;
}

/** Jumlah notifikasi yang belum dibaca milik satu penghuni. */
function hitung_notifikasi_belum_dibaca(int $penghuniId): int
{
    return count(array_filter(get_notifikasi_penghuni($penghuniId), fn ($n) => !$n['dibaca']));
}
