<?php
/**
 * Konfigurasi umum website Kos Aron.
 * File ini di-include paling awal oleh setiap halaman.
 */

// Zona waktu Sumenep (WIB), dipakai untuk tanggal hari ini & status tagihan terlambat.
date_default_timezone_set('Asia/Jakarta');

const SITE_NAME     = 'Kos Aron';
const SITE_LOCATION = 'Kolor, Sumenep';
const SITE_LOCATION_FULL = SITE_LOCATION . ', Jawa Timur';

/**
 * Nomor WhatsApp pemilik kos.
 * Format: kode negara tanpa tanda + dan tanpa spasi, contoh pola: 62xxxxxxxxxx
 * Biarkan kosong sampai nomor asli dikonfirmasi oleh pemilik.
 * Selama kosong, tombol WhatsApp ditampilkan dalam keadaan nonaktif.
 */
const WHATSAPP_NUMBER = '';

/**
 * Informasi kontak lain (opsional). Kosongkan jika belum ada data asli;
 * baris/elemen terkait otomatis tidak ditampilkan.
 */
const ALAMAT_LENGKAP = '';   // contoh pola: 'Jl. ..., Kolor, Sumenep'
const EMAIL_KONTAK   = '';

/**
 * Peta lokasi (Google Maps).
 * MAPS_EMBED_URL : URL dari Google Maps > Bagikan > Sematkan peta (atribut src iframe),
 *                  harus diawali https://www.google.com/maps/embed
 * MAPS_LINK_URL  : link biasa untuk membuka lokasi di Google Maps (opsional).
 * Selama kosong, halaman Kontak menampilkan placeholder tanpa pin/koordinat.
 */
const MAPS_EMBED_URL = '';
const MAPS_LINK_URL  = '';

/**
 * Deskripsi tambahan untuk halaman Tentang (paragraf kedua).
 * Isi berdasarkan keterangan dari pemilik kos. Selama kosong,
 * halaman menampilkan kalimat netral sebagai pengganti.
 */
const TENTANG_DESKRIPSI_TAMBAHAN = '';

/** Folder gambar (relatif terhadap root website). */
const PATH_FOTO_KAMAR   = 'assets/images/kamar/';
const PATH_FOTO_KOS     = 'assets/images/kos/';
const FOTO_PLACEHOLDER  = 'assets/images/placeholder.svg';

/** Nama file foto bangunan kos di assets/images/kos/ (untuk hero beranda). */
const FOTO_BANGUNAN_KOS = 'bangunan.jpg';

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Sesi PHP harus aktif sebelum output apa pun dikirim (lihat includes/auth.php).
mulai_sesi();
