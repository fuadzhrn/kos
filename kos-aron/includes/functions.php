<?php
/**
 * Fungsi bantu (helper) yang dipakai di semua halaman.
 */

/** Escape teks sebelum ditampilkan ke HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format angka menjadi Rupiah, contoh: 650000 -> "Rp 650.000". */
function format_rupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Mengembalikan URL foto jika file ada, atau gambar placeholder jika tidak ada.
 *
 * @param string|null $namaFile Nama file foto (contoh: "kamar-01.jpg")
 * @param string      $folder   Folder relatif, contoh PATH_FOTO_KAMAR
 */
function url_foto(?string $namaFile, string $folder = PATH_FOTO_KAMAR): string
{
    if ($namaFile === null || $namaFile === '') {
        return FOTO_PLACEHOLDER;
    }

    $relatif = $folder . basename($namaFile);
    $absolut = dirname(__DIR__) . '/' . $relatif;

    return is_file($absolut) ? $relatif : FOTO_PLACEHOLDER;
}

/**
 * Daftar URL foto kamar (foto utama + galeri) yang file-nya benar-benar ada.
 * Mengembalikan array kosong jika belum ada foto sama sekali.
 */
function daftar_foto_kamar(array $kamar): array
{
    $namaFile = array_merge([$kamar['foto'] ?? ''], $kamar['galeri'] ?? []);
    $hasil    = [];

    foreach ($namaFile as $file) {
        $url = url_foto($file);
        if ($url !== FOTO_PLACEHOLDER && !in_array($url, $hasil, true)) {
            $hasil[] = $url;
        }
    }

    return $hasil;
}

/**
 * Tujuan tombol "Hubungi Pemilik": WhatsApp jika nomor sudah diatur,
 * jika belum diarahkan ke halaman Kontak.
 *
 * @return array{url: string, eksternal: bool}
 */
function link_hubungi_pemilik(string $pesan = ''): array
{
    $urlWhatsapp = url_whatsapp($pesan);

    return [
        'url'       => $urlWhatsapp ?? 'kontak.php',
        'eksternal' => $urlWhatsapp !== null,
    ];
}

/** Nama bulan bahasa Indonesia (1-12), atau string kosong. */
function nama_bulan(int $nomor): string
{
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
              'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $bulan[$nomor] ?? '';
}

/** Periode "2026-11" -> "November 2026". */
function format_periode(string $periode): string
{
    [$tahun, $nomorBulan] = array_map('intval', explode('-', $periode) + [0, 0]);
    $bulan = nama_bulan($nomorBulan);

    return $bulan !== '' ? $bulan . ' ' . $tahun : $periode;
}

/** Tanggal "2026-03-01" -> "1 Maret 2026". Mengembalikan "-" jika kosong/tidak valid. */
function format_tanggal(?string $tanggal): string
{
    if (!$tanggal || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return '-';
    }
    return (int) $m[3] . ' ' . nama_bulan((int) $m[2]) . ' ' . $m[1];
}

/** Label status penghuni. */
function label_status_penghuni(string $status): string
{
    return $status === 'aktif' ? 'Aktif' : 'Tidak Aktif';
}

/** Label status tagihan untuk ditampilkan. */
function label_status_tagihan(string $status): string
{
    return match ($status) {
        'lunas'    => 'Lunas',
        'menunggu' => 'Menunggu Verifikasi',
        default    => 'Belum Lunas',
    };
}

/** Label status kamar untuk ditampilkan. */
function label_status(string $status): string
{
    return $status === 'kosong' ? 'Tersedia' : 'Terisi';
}

/**
 * Membuat link WhatsApp. Mengembalikan null jika nomor belum dikonfigurasi.
 */
function url_whatsapp(string $pesan = ''): ?string
{
    $nomor = preg_replace('/\D+/', '', WHATSAPP_NUMBER);

    if ($nomor === '') {
        return null;
    }

    $url = 'https://wa.me/' . $nomor;
    return $pesan !== '' ? $url . '?text=' . rawurlencode($pesan) : $url;
}

/**
 * URL Google Maps embed yang valid, atau null jika belum diatur / tidak valid.
 * Hanya menerima URL embed resmi Google Maps agar iframe tidak memuat situs lain.
 */
function url_maps_embed(): ?string
{
    $url = trim(MAPS_EMBED_URL);
    return str_starts_with($url, 'https://www.google.com/maps/embed') ? $url : null;
}

/** Link "buka di Google Maps", atau null jika belum diatur / bukan https. */
function url_maps_link(): ?string
{
    $url = trim(MAPS_LINK_URL);
    return str_starts_with($url, 'https://') ? $url : null;
}

/** Nomor WhatsApp untuk ditampilkan (contoh: +62xxxx), atau null jika belum diatur. */
function nomor_whatsapp_tampil(): ?string
{
    $nomor = preg_replace('/\D+/', '', WHATSAPP_NUMBER);
    return $nomor !== '' ? '+' . $nomor : null;
}

/**
 * Komponen tombol WhatsApp.
 * Jika nomor belum diatur, tombol tampil nonaktif beserta keterangannya.
 */
function tombol_whatsapp(string $pesan = '', string $label = 'Hubungi via WhatsApp', string $varian = 'btn-primary'): string
{
    $url   = url_whatsapp($pesan);
    $kelas = trim('btn ' . $varian);

    if ($url === null) {
        return '<span class="' . e($kelas) . ' is-disabled" aria-disabled="true" '
            . 'title="Nomor WhatsApp belum diatur">' . e($label) . '</span>';
    }

    return '<a class="' . e($kelas) . '" href="' . e($url) . '" target="_blank" rel="noopener">'
        . e($label) . '</a>';
}

/** Menandai menu navbar yang sedang aktif. */
function nav_active(string $halaman, string $aktif): string
{
    return $halaman === $aktif ? ' class="is-active" aria-current="page"' : '';
}
