<?php
/**
 * Konfigurasi umum website Kos Aron.
 * File ini di-include paling awal oleh setiap halaman.
 */

// Zona waktu Sumenep (WIB), dipakai untuk tanggal hari ini & status tagihan terlambat.
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../data/pengaturan.php';
$pengaturanSitus = pengaturan_website();
define('SITE_NAME', $pengaturanSitus['nama_kos']);
define('WHATSAPP_NUMBER', $pengaturanSitus['whatsapp']);
define('ALAMAT_LENGKAP', $pengaturanSitus['alamat']);
define('TENTANG_DESKRIPSI_TAMBAHAN', $pengaturanSitus['deskripsi_kos']);
define('MAPS_EMBED_URL', $pengaturanSitus['maps_embed']);
define('MAPS_LINK_URL', $pengaturanSitus['maps_link']);
define('FOTO_BANGUNAN_KOS', $pengaturanSitus['foto_utama']);
const SITE_LOCATION = 'Kolor, Sumenep';
const SITE_LOCATION_FULL = SITE_LOCATION . ', Jawa Timur';
const EMAIL_KONTAK = '';

/** Folder gambar (relatif terhadap root website). */
const PATH_FOTO_KAMAR   = 'assets/images/kamar/';
const PATH_FOTO_KOS     = 'assets/images/kos/';
const FOTO_PLACEHOLDER  = 'assets/images/placeholder.svg';


require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Sesi PHP harus aktif sebelum output apa pun dikirim (lihat includes/auth.php).
mulai_sesi();
