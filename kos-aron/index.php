<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/data/kamar.php';

$judulHalaman  = SITE_NAME;
$halamanAktif  = 'beranda';
$deskripsiMeta = 'Informasi kamar, fasilitas, dan harga sewa Kos Aron di ' . SITE_LOCATION . '.';

$kamarTersedia = get_kamar_tersedia(3);

// Tombol "Hubungi Pemilik": ke WhatsApp jika nomor sudah diatur, jika belum ke halaman Kontak.
$kontak           = link_hubungi_pemilik('Halo, saya ingin bertanya tentang kamar di Kos Aron.');
$atributEksternal = $kontak['eksternal'] ? ' target="_blank" rel="noopener"' : '';

require __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-text">
            <p class="eyebrow">Informasi Kos</p>
            <h1>Kos Aron di Kolor</h1>
            <p>Lihat informasi kamar kos, fasilitas, dan harga sewa yang tersedia di Kos Aron.</p>
            <a href="kamar.php" class="btn btn-primary">Lihat Kamar</a>
        </div>

        <div class="foto-frame">
            <img src="<?= e(url_foto(FOTO_BANGUNAN_KOS, PATH_FOTO_KOS)) ?>"
                 alt="Foto bangunan Kos Aron" data-fallback>
        </div>
    </div>
</section>

<!-- Kamar yang tersedia -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Kamar yang Tersedia</h2>
                <p>Lihat kamar yang masih dapat disewa.</p>
            </div>
            <a href="kamar.php" class="link-more">Lihat Semua <span aria-hidden="true">&rarr;</span></a>
        </div>

        <?php if (empty($kamarTersedia)): ?>
            <div class="empty-state">
                <p>Saat ini belum ada kamar yang tersedia.</p>
                <a href="kontak.php">Hubungi pemilik untuk informasi lebih lanjut</a>
            </div>
        <?php else: ?>
            <div class="grid-kamar">
                <?php foreach ($kamarTersedia as $kamar): ?>
                    <?php include __DIR__ . '/includes/card-kamar.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Tentang singkat -->
<section class="section section--alt">
    <div class="container">
        <div class="about-short">
            <h2>Tentang Kos Aron</h2>
            <p>Kos Aron adalah tempat penyewaan kamar kos di <?= e(SITE_LOCATION) ?>. Melalui website ini, calon penghuni dapat melihat daftar kamar, status ketersediaan, dan harga sewa sebelum menghubungi pemilik.</p>
            <a href="tentang.php" class="link-more">Selengkapnya <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</section>

<!-- Kontak singkat -->
<section class="section">
    <div class="container contact-short">
        <div>
            <h2>Ada Pertanyaan?</h2>
            <p>Hubungi pemilik untuk menanyakan ketersediaan kamar atau informasi penyewaan.</p>
        </div>
        <a href="<?= e($kontak['url']) ?>" class="btn btn-primary"<?= $atributEksternal ?>>Hubungi Pemilik</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
