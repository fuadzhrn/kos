<?php
/**
 * Layout Penghuni: <head>, sidebar, dan topbar.
 * Memakai komponen & stylesheet dashboard yang sama dengan sisi Admin
 * (assets/css/admin.css, assets/js/admin.js), tetapi menu sidebar dan
 * halaman yang bisa diakses TERPISAH dari /admin/ -- penghuni tidak
 * memiliki tautan ke menu admin mana pun.
 *
 * Variabel yang diatur sebelum include:
 *   $judulHalaman  string  Judul halaman (tab browser & topbar)
 *   $menuAktif     string  dashboard | kamar | tagihan | riwayat | notifikasi | profil
 *
 * Catatan: belum ada autentikasi. $penghuniLogin di bawah masih dummy
 * (lihat get_penghuni_login() di data/penghuni.php), nanti diambil dari
 * session setelah login penghuni dibuat.
 */
require_once __DIR__ . '/../../data/penghuni.php';

$judulHalaman  = $judulHalaman ?? 'Dashboard';
$menuAktif     = $menuAktif ?? '';
$penghuniLogin = get_penghuni_login();
$namaPenghuni  = $penghuniLogin['nama'] ?? 'Penghuni';

$menuPenghuni = [
    'dashboard'  => ['Dashboard',          'index.php'],
    'kamar'      => ['Kamar Saya',         'kamar.php'],
    'tagihan'    => ['Tagihan',            'tagihan.php'],
    'riwayat'    => ['Riwayat Pembayaran', 'riwayat.php'],
    'notifikasi' => ['Notifikasi',         'notifikasi.php'],
    'profil'     => ['Profil',             'profil.php'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($judulHalaman) ?> - Penghuni <?= e(SITE_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <aside id="sidebar" class="sidebar" aria-label="Menu penghuni">
        <div class="sidebar__brand">
            <a href="index.php">KOS ARON</a>
            <span>Penghuni</span>
        </div>
        <nav class="sidebar__nav">
            <ul>
                <?php foreach ($menuPenghuni as $kunci => [$label, $url]): ?>
                    <li>
                        <a href="<?= e($url) ?>"<?= $kunci === $menuAktif ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close hidden></div>

    <div class="admin-main">
        <header class="topbar">
            <button type="button" class="topbar__toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar" data-sidebar-toggle>
                <span></span><span></span><span></span>
            </button>
            <p class="topbar__title"><?= e($judulHalaman) ?></p>

            <details class="topbar__user">
                <summary>
                    <span class="topbar__avatar" aria-hidden="true"><?= e(mb_substr($namaPenghuni, 0, 1)) ?></span>
                    <span class="topbar__nama"><?= e($namaPenghuni) ?></span>
                </summary>
                <ul class="topbar__menu">
                    <li><a href="profil.php">Profil</a></li>
                    <li><a href="../login.php">Keluar</a></li>
                </ul>
            </details>
        </header>

        <main class="admin-content">
