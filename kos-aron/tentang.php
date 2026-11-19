<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/data/kamar.php';

$judulHalaman  = 'Tentang';
$halamanAktif  = 'tentang';
$deskripsiMeta = 'Informasi mengenai ' . SITE_NAME . ' yang berlokasi di ' . SITE_LOCATION . '.';

$jumlahKamar = count(get_semua_kamar());
$nomorWa     = nomor_whatsapp_tampil();
$urlWa       = url_whatsapp('Halo, saya ingin bertanya tentang ' . SITE_NAME . '.');

// Paragraf kedua: dari konfigurasi jika sudah diisi pemilik, jika belum pakai kalimat netral.
$deskripsiTambahan = TENTANG_DESKRIPSI_TAMBAHAN !== ''
    ? TENTANG_DESKRIPSI_TAMBAHAN
    : 'Keterangan lebih lengkap mengenai ' . SITE_NAME . ' akan dilengkapi berdasarkan informasi dari pemilik. '
      . 'Untuk saat ini, calon penghuni dapat melihat daftar kamar atau menghubungi pemilik secara langsung.';

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Tentang <?= e(SITE_NAME) ?></h1>
        <p>Informasi mengenai <?= e(SITE_NAME) ?> yang berlokasi di <?= e(SITE_LOCATION) ?>.</p>
    </div>
</section>

<!-- Pengenalan -->
<section class="section">
    <div class="container split-grid">
        <div class="split-grid__teks">
            <h2>Mengenal <?= e(SITE_NAME) ?></h2>
            <p><?= e(SITE_NAME) ?> merupakan tempat penyewaan kamar yang berlokasi di <?= e(SITE_LOCATION) ?>. Website ini menyediakan informasi mengenai kamar, fasilitas, harga sewa, dan status ketersediaan kamar.</p>
            <p><?= e($deskripsiTambahan) ?></p>
        </div>

        <div class="foto-frame">
            <img src="<?= e(url_foto(FOTO_BANGUNAN_KOS, PATH_FOTO_KOS)) ?>"
                 alt="Foto bangunan <?= e(SITE_NAME) ?>" loading="lazy" data-fallback>
        </div>
    </div>
</section>

<!-- Informasi umum -->
<section class="section section--border">
    <div class="container">
        <div class="info-umum">
            <h2>Informasi Umum</h2>
            <dl class="info-tabel">
                <div>
                    <dt>Nama kos</dt>
                    <dd><?= e(SITE_NAME) ?></dd>
                </div>
                <div>
                    <dt>Lokasi</dt>
                    <dd><?= e(SITE_LOCATION_FULL) ?></dd>
                </div>
                <div>
                    <dt>Kontak</dt>
                    <dd>
                        <?php if ($nomorWa !== null): ?>
                            WhatsApp <a href="<?= e($urlWa) ?>" target="_blank" rel="noopener"><?= e($nomorWa) ?></a>
                        <?php else: ?>
                            Melalui halaman <a href="kontak.php">Kontak</a>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if ($jumlahKamar > 0): ?>
                    <div>
                        <dt>Jumlah kamar</dt>
                        <dd><?= $jumlahKamar ?> kamar (<a href="kamar.php">lihat daftar kamar</a>)</dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>Cara menyewa</dt>
                    <dd>Menghubungi pemilik secara langsung. Pemesanan dan pembayaran tidak dilakukan melalui website.</dd>
                </div>
            </dl>
        </div>
    </div>
</section>

<!-- Penutup -->
<section class="section section--border">
    <div class="container contact-short">
        <p>Untuk informasi mengenai kamar yang tersedia, silakan lihat daftar kamar atau hubungi pemilik kos.</p>
        <a href="kamar.php" class="btn btn-primary">Lihat Daftar Kamar</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
