<?php
/**
 * Header + navbar.
 * Variabel yang bisa diatur sebelum include:
 *   $judulHalaman   string  Judul di tab browser
 *   $halamanAktif   string  beranda | kamar | tentang | kontak | login
 *   $deskripsiMeta  string  Meta description (opsional)
 */
$judulHalaman  = $judulHalaman  ?? SITE_NAME;
$halamanAktif  = $halamanAktif  ?? '';
$deskripsiMeta = $deskripsiMeta ?? 'Informasi kamar ' . SITE_NAME . ' di ' . SITE_LOCATION . '.';

$judulLengkap = $judulHalaman === SITE_NAME ? SITE_NAME : $judulHalaman . ' - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($deskripsiMeta) ?>">
    <title><?= e($judulLengkap) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container navbar">
        <a href="index.php" class="brand">KOS ARON</a>

        <button type="button" class="nav-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="nav-menu">
            <span></span><span></span><span></span>
        </button>

        <nav id="nav-menu" class="nav-menu" aria-label="Navigasi utama">
            <ul class="nav-links">
                <li><a href="index.php"<?= nav_active('beranda', $halamanAktif) ?>>Beranda</a></li>
                <li><a href="kamar.php"<?= nav_active('kamar', $halamanAktif) ?>>Kamar</a></li>
                <li><a href="tentang.php"<?= nav_active('tentang', $halamanAktif) ?>>Tentang</a></li>
                <li><a href="kontak.php"<?= nav_active('kontak', $halamanAktif) ?>>Kontak</a></li>
            </ul>
            <a href="login.php" class="btn btn-primary nav-login"<?= $halamanAktif === 'login' ? ' aria-current="page"' : '' ?>>Login</a>
        </nav>
    </div>
</header>

<main class="site-main">
