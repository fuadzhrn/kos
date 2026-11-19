<?php
/**
 * CONTOH konfigurasi koneksi database. TIDAK berisi kredensial asli.
 *
 * Cara pakai:
 *   1. Salin file ini menjadi "database.local.php" (di folder yang sama).
 *   2. Isi DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS sesuai
 *      environment lokal Anda (XAMPP/Laragon).
 *   3. JANGAN commit file "database.local.php" ke Git — nama file itu
 *      sudah otomatis diabaikan lewat .gitignore (pola *.local.php).
 *
 * Lihat docs/05-panduan-import-database.md untuk langkah lengkap.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'db_kos_aron');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP/Laragon default: root tanpa password. Ganti sesuai environment Anda.
