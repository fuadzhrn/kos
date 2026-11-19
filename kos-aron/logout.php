<?php
/**
 * Logout. HANYA menerima POST (lihat admin/includes/header.php dan
 * penghuni/includes/header.php untuk form "Keluar" yang memanggil ini),
 * disertai token CSRF, supaya sesi tidak bisa dipaksa keluar lewat
 * tautan/GET dari situs lain.
 */
require_once __DIR__ . '/includes/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_valid($_POST['csrf_token'] ?? null)) {
    header('Location: login.php');
    exit;
}

logout_sesi();
header('Location: login.php?keluar=1');
exit;
