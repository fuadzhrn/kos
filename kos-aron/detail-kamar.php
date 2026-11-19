<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/data/kamar.php';

// Validasi parameter id: harus bilangan bulat positif.
$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$kamar = $id ? get_kamar_publik_by_id($id) : null;

if ($kamar === null) {
    http_response_code(404);
}

$halamanAktif = 'kamar';

if ($kamar !== null) {
    $namaKamar     = 'Kamar ' . $kamar['nomor'];
    $judulHalaman  = $namaKamar;
    $deskripsiMeta = $namaKamar . ' di ' . SITE_NAME . ', ' . SITE_LOCATION . '. Harga sewa '
                   . format_rupiah($kamar['harga']) . ' per bulan.';

    $tersedia   = $kamar['status'] === 'kosong';
    $daftarFoto = daftar_foto_kamar($kamar);
    $fotoUtama  = $daftarFoto[0] ?? FOTO_PLACEHOLDER;

    $kontak      = link_hubungi_pemilik('Halo, saya ingin menanyakan informasi ' . $namaKamar . ' di Kos Aron.');
    $labelTombol = $tersedia ? 'Hubungi Pemilik' : 'Tanyakan Informasi';
    $atributLink = $kontak['eksternal'] ? ' target="_blank" rel="noopener"' : '';
} else {
    $judulHalaman = 'Kamar Tidak Ditemukan';
}

require __DIR__ . '/includes/header.php';
?>

<section class="section section--tight">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="kamar.php">Daftar Kamar</a></li>
                <li aria-current="page"><?= e($kamar ? $namaKamar : 'Tidak ditemukan') ?></li>
            </ol>
        </nav>

        <?php if ($kamar === null): ?>
            <!-- Kamar tidak ditemukan / id tidak valid -->
            <div class="empty-state empty-state--lg">
                <h1>Kamar tidak ditemukan</h1>
                <p>Data kamar yang Anda cari tidak tersedia atau tautannya tidak valid.</p>
                <a href="kamar.php" class="btn btn-primary">Kembali ke Daftar Kamar</a>
            </div>

        <?php else: ?>
            <!-- Detail utama -->
            <div class="detail-kamar">
                <div class="detail-kamar__media" data-galeri>
                    <div class="foto-frame">
                        <img src="<?= e($fotoUtama) ?>" alt="Foto <?= e($namaKamar) ?>"
                             data-galeri-utama data-fallback>
                    </div>

                    <?php if (count($daftarFoto) > 1): ?>
                        <ul class="galeri-thumb" aria-label="Foto lainnya">
                            <?php foreach ($daftarFoto as $i => $urlFoto): ?>
                                <li>
                                    <a href="<?= e($urlFoto) ?>"
                                       class="galeri-thumb__item<?= $i === 0 ? ' is-active' : '' ?>"
                                       data-galeri-thumb
                                       <?= $i === 0 ? 'aria-current="true"' : '' ?>
                                       aria-label="Tampilkan foto <?= $i + 1 ?>">
                                        <img src="<?= e($urlFoto) ?>" alt="" loading="lazy" data-fallback>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="detail-kamar__info">
                    <div class="detail-kamar__head">
                        <h1><?= e($namaKamar) ?></h1>
                        <span class="badge badge--<?= e($kamar['status']) ?>"><?= e(label_status($kamar['status'])) ?></span>
                    </div>

                    <p class="detail-kamar__harga">
                        <?= e(format_rupiah($kamar['harga'])) ?> <span>/ bulan</span>
                    </p>

                    <?php if (!empty($kamar['deskripsi'])): ?>
                        <p><?= e($kamar['deskripsi']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($kamar['fasilitas'])): ?>
                        <h2 class="detail-label">Fasilitas</h2>
                        <ul class="list-fasilitas">
                            <?php foreach ($kamar['fasilitas'] as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!$tersedia): ?>
                        <p class="notice notice--muted">Kamar ini sedang terisi dan belum tersedia untuk disewa.</p>
                    <?php endif; ?>

                    <div class="detail-kamar__aksi">
                        <a href="<?= e($kontak['url']) ?>" class="btn btn-primary"<?= $atributLink ?>><?= e($labelTombol) ?></a>
                    </div>
                </div>
            </div>

            <!-- Informasi kamar -->
            <div class="detail-blok">
                <h2>Informasi Kamar</h2>
                <dl class="info-tabel">
                    <div>
                        <dt>Nomor kamar</dt>
                        <dd><?= e($kamar['nomor']) ?></dd>
                    </div>
                    <div>
                        <dt>Harga sewa</dt>
                        <dd><?= e(format_rupiah($kamar['harga'])) ?> per bulan</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="badge badge--<?= e($kamar['status']) ?>"><?= e(label_status($kamar['status'])) ?></span></dd>
                    </div>
                    <div>
                        <dt>Fasilitas</dt>
                        <dd><?= !empty($kamar['fasilitas']) ? e(implode(', ', $kamar['fasilitas'])) : '-' ?></dd>
                    </div>
                    <?php if (!empty($kamar['deskripsi'])): ?>
                        <div>
                            <dt>Keterangan</dt>
                            <dd><?= e($kamar['deskripsi']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Kontak pemilik -->
            <div class="contact-box">
                <p>
                    <?php if ($tersedia): ?>
                        Tertarik dengan kamar ini? Hubungi pemilik Kos Aron untuk informasi lebih lanjut.
                    <?php else: ?>
                        Kamar ini sedang terisi. Hubungi pemilik Kos Aron untuk menanyakan ketersediaan atau informasi lainnya.
                    <?php endif; ?>
                </p>
                <a href="<?= e($kontak['url']) ?>" class="btn btn-primary"<?= $atributLink ?>><?= e($labelTombol) ?></a>
            </div>

            <p class="detail-kembali">
                <a href="kamar.php" class="link-more"><span aria-hidden="true">&larr;</span> Kembali ke Daftar Kamar</a>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
