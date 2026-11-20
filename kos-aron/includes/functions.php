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
function format_rupiah(int|float|string $angka): string
{
    $nominal = (float)$angka;
    return 'Rp ' . number_format($nominal, floor($nominal) === $nominal ? 0 : 2, ',', '.');
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

    // Identitas tiga kamar contoh publik dari seed. Utamakan seluruh foto dan galeri asli.
    $contoh = ['1:01' => '01', '2:02' => '02', '4:04' => '03'];
    $key = $kamar['id'] . ':' . $kamar['nomor'];
    if (!$hasil && isset($contoh[$key])) {
        $url = url_foto('kamar-ilustrasi-' . $contoh[$key] . '.webp');
        if ($url !== FOTO_PLACEHOLDER) {
            $hasil[] = $url;
        }
    }
    return $hasil;
}

function foto_bangunan_publik(string $ilustrasi): string
{
    $foto = url_foto(FOTO_BANGUNAN_KOS, PATH_FOTO_KOS);
    return $foto === FOTO_PLACEHOLDER ? url_foto($ilustrasi, PATH_FOTO_KOS) : $foto;
}

function foto_adalah_ilustrasi(string $url): bool
{
    return in_array($url, [
        PATH_FOTO_KOS . 'kos-aron-hero-ilustrasi.webp',
        PATH_FOTO_KOS . 'kos-aron-tentang-ilustrasi.webp',
        PATH_FOTO_KOS . 'kos-aron-alternatif-ilustrasi.webp',
        PATH_FOTO_KAMAR . 'kamar-ilustrasi-01.webp',
        PATH_FOTO_KAMAR . 'kamar-ilustrasi-02.webp',
        PATH_FOTO_KAMAR . 'kamar-ilustrasi-03.webp',
    ], true);
}

/**
 * Tujuan tombol "Hubungi Pemilik": WhatsApp jika nomor sudah diatur,
 * jika belum diarahkan ke halaman Kontak.
 *
 * @param string $fallbackUrl Tujuan jika WhatsApp belum diatur. Defaultnya
 *                             cocok untuk halaman di root (mis. index.php);
 *                             halaman di dalam subfolder (mis. admin/, penghuni/)
 *                             perlu mengirim path relatif sendiri, contoh '../kontak.php'.
 * @return array{url: string, eksternal: bool}
 */
function link_hubungi_pemilik(string $pesan = '', string $fallbackUrl = 'kontak.php'): array
{
    $urlWhatsapp = url_whatsapp($pesan);

    return [
        'url'       => $urlWhatsapp ?? $fallbackUrl,
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
        'lunas'     => 'Lunas',
        'terlambat' => 'Belum Lunas · Terlambat',
        default     => 'Belum Lunas',
    };
}

/**
 * Tanggal hari ini (YYYY-MM-DD), dipakai untuk menentukan tagihan terlambat.
 * Satu tempat agar mudah diganti saat pengujian.
 */
function tanggal_hari_ini(): string
{
    return date('Y-m-d');
}

/** Label metode pembayaran untuk ditampilkan. */
function label_metode_pembayaran(string $metode): string
{
    return $metode === 'transfer' ? 'Transfer' : 'Tunai';
}

/** Label status kamar untuk ditampilkan. */
function label_status(string $status): string
{
    return match ($status) {
        'kosong' => 'Tersedia', 'terisi' => 'Terisi', 'dipesan' => 'Dipesan', 'perawatan' => 'Perawatan',
        default => 'Tidak tersedia',
    };
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
    return maps_diizinkan($url, true) ? $url : null;
}

/** Link "buka di Google Maps", atau null jika belum diatur / bukan https. */
function url_maps_link(): ?string
{
    $url = trim(MAPS_LINK_URL);
    return maps_diizinkan($url) ? $url : null;
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
