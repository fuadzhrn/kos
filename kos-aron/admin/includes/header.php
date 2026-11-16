<?php
/**
 * Layout admin: <head>, sidebar, dan topbar.
 * Variabel yang diatur sebelum include:
 *   $judulHalaman  string  Judul halaman (tab browser & topbar)
 *   $menuAktif     string  dashboard | kamar | penghuni | tagihan | pembayaran | laporan | pengaturan
 *
 * Catatan: belum ada autentikasi. Nama admin di bawah masih dummy,
 * nanti diambil dari session setelah login dibuat.
 */
$judulHalaman = $judulHalaman ?? 'Dashboard';
$menuAktif    = $menuAktif ?? '';
$namaAdmin    = 'Admin Kos Aron'; // dummy

$menuAdmin = [
    'dashboard'  => ['Dashboard',     'index.php'],
    'kamar'      => ['Data Kamar',    'kamar.php'],
    'penghuni'   => ['Data Penghuni', 'penghuni.php'],
    'tagihan'    => ['Tagihan',       'tagihan.php'],
    'pembayaran' => ['Pembayaran',    'pembayaran.php'],
    'laporan'    => ['Laporan',       'laporan.php'],
    'pengaturan' => ['Pengaturan',    'pengaturan.php'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($judulHalaman) ?> - Admin <?= e(SITE_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <aside id="sidebar" class="sidebar" aria-label="Menu admin">
        <div class="sidebar__brand">
            <a href="index.php">KOS ARON</a>
            <span>Admin</span>
        </div>
        <nav class="sidebar__nav">
            <ul>
                <?php foreach ($menuAdmin as $kunci => [$label, $url]): ?>
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
                    <span class="topbar__avatar" aria-hidden="true"><?= e(mb_substr($namaAdmin, 0, 1)) ?></span>
                    <span class="topbar__nama"><?= e($namaAdmin) ?></span>
                </summary>
                <ul class="topbar__menu">
                    <li><a href="pengaturan.php">Profil</a></li>
                    <li><a href="../login.php">Keluar</a></li>
                </ul>
            </details>
        </header>

        <main class="admin-content">
