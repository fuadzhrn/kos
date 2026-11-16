<?php
require_once __DIR__ . '/includes/config.php';

$judulHalaman  = 'Kontak';
$halamanAktif  = 'kontak';
$deskripsiMeta = 'Kontak pemilik Kos Aron di ' . SITE_LOCATION . ' untuk menanyakan informasi kamar dan penyewaan.';

// Semua data kontak diambil dari includes/config.php
$nomorWa   = nomor_whatsapp_tampil();
$pesanWa   = 'Halo, saya ingin menanyakan informasi kamar di Kos Aron.';
$urlWa     = url_whatsapp($pesanWa);
$mapsEmbed = url_maps_embed();
$mapsLink  = url_maps_link();
$alamat    = ALAMAT_LENGKAP !== '' ? ALAMAT_LENGKAP : null;
$email     = filter_var(EMAIL_KONTAK, FILTER_VALIDATE_EMAIL) ?: null;

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Hubungi Kos Aron</h1>
        <p>Silakan hubungi pemilik kos untuk menanyakan informasi kamar dan penyewaan.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="split-grid split-grid--top">
            <!-- Informasi kontak -->
            <div>
                <h2>Informasi Kontak</h2>
                <dl class="info-tabel">
                    <div>
                        <dt>Nama</dt>
                        <dd><?= e(SITE_NAME) ?></dd>
                    </div>
                    <div>
                        <dt>Alamat</dt>
                        <dd>
                            <?php if ($alamat !== null): ?>
                                <?= e($alamat) ?>
                            <?php else: ?>
                                <?= e(SITE_LOCATION_FULL) ?>
                                <span class="teks-catatan">Alamat lengkap akan dilengkapi kemudian.</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>WhatsApp</dt>
                        <dd>
                            <?php if ($nomorWa !== null): ?>
                                <a href="<?= e($urlWa) ?>" target="_blank" rel="noopener"><?= e($nomorWa) ?></a>
                            <?php else: ?>
                                <span class="text-muted">Belum tersedia</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if ($email !== null): ?>
                        <div>
                            <dt>Email</dt>
                            <dd><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="kontak-aksi">
                    <?= tombol_whatsapp($pesanWa, 'Hubungi via WhatsApp') ?>
                    <?php if ($urlWa === null): ?>
                        <p class="teks-catatan">Tombol akan aktif setelah nomor WhatsApp pemilik tersedia.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lokasi -->
            <div>
                <h2>Lokasi Kos</h2>
                <div class="peta">
                    <?php if ($mapsEmbed !== null): ?>
                        <iframe src="<?= e($mapsEmbed) ?>"
                                title="Peta lokasi Kos Aron"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen></iframe>
                    <?php else: ?>
                        <p class="peta__kosong">Peta lokasi akan ditampilkan setelah alamat Kos Aron dilengkapi.</p>
                    <?php endif; ?>
                </div>
                <?php if ($mapsLink !== null): ?>
                    <p class="peta__link">
                        <a href="<?= e($mapsLink) ?>" class="link-more" target="_blank" rel="noopener">Buka di Google Maps <span aria-hidden="true">&rarr;</span></a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <p class="notice mt-section">Untuk memastikan ketersediaan kamar, silakan menghubungi pemilik kos terlebih dahulu. Daftar kamar dapat dilihat di halaman <a href="kamar.php">Kamar</a>.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
