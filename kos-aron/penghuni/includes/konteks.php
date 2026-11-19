<?php
require_once __DIR__ . '/../../includes/config.php';
require_role('penghuni', '../login.php');
require_once __DIR__ . '/../../data/penghuni.php';

// Semua halaman pribadi memakai satu identitas, termasuk sebelum memproses POST.
foreach (['id', 'user_id', 'penghuni_id', 'kamar_id', 'penyewaan_id', 'tagihan_id', 'pembayaran_id'] as $kunci) {
    if (array_key_exists($kunci, $_GET) || array_key_exists($kunci, $_POST)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}
$penghuniLogin = get_penghuni_login();
