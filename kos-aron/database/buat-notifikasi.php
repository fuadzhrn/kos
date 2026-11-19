<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
date_default_timezone_set('Asia/Jakarta');
try {
    require_once __DIR__ . '/../data/notifikasi.php';
    $baru = buat_notifikasi_tagihan(koneksi_database(), date('Y-m-d'));
    echo date('Y-m-d H:i:s') . " WIB | Notifikasi baru: $baru\n";
} catch (Throwable $e) {
    error_log('Pembuatan notifikasi gagal: ' . $e->getMessage());
    exit(1);
}
