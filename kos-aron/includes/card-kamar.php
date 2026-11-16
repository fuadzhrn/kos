<?php
/**
 * Komponen card kamar.
 * Membutuhkan variabel $kamar (array, lihat struktur di data/kamar.php).
 *
 * Pemakaian:
 *   foreach ($daftarKamar as $kamar) { include __DIR__ . '/card-kamar.php'; }
 *
 * @var array $kamar Diisi oleh halaman yang meng-include file ini.
 */
$urlDetail       = 'detail-kamar.php?id=' . (int) $kamar['id'];
$fasilitasUtama  = array_slice($kamar['fasilitas'], 0, 3);

// Kata kunci pencarian (dipakai filter JS di kamar.php), contoh: "kamar 01 kamar 1"
$nomorTanpaNol   = ltrim((string) $kamar['nomor'], '0');
$kataKunci       = strtolower('kamar ' . $kamar['nomor'] . ' kamar ' . $nomorTanpaNol);
?>
<article class="card-kamar" data-status="<?= e($kamar['status']) ?>" data-cari="<?= e($kataKunci) ?>">
    <a href="<?= e($urlDetail) ?>" class="card-kamar__foto" tabindex="-1" aria-hidden="true">
        <img src="<?= e(url_foto($kamar['foto'])) ?>"
             alt="Foto Kamar <?= e($kamar['nomor']) ?>"
             loading="lazy" data-fallback>
    </a>

    <div class="card-kamar__body">
        <div class="card-kamar__head">
            <h3 class="card-kamar__judul">
                <a href="<?= e($urlDetail) ?>">Kamar <?= e($kamar['nomor']) ?></a>
            </h3>
            <span class="badge badge--<?= e($kamar['status']) ?>"><?= e(label_status($kamar['status'])) ?></span>
        </div>

        <?php if (!empty($fasilitasUtama)): ?>
            <ul class="card-kamar__fasilitas" aria-label="Fasilitas utama">
                <?php foreach ($fasilitasUtama as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="card-kamar__footer">
            <p class="card-kamar__harga">
                <?= e(format_rupiah($kamar['harga'])) ?> <span>/ bulan</span>
            </p>
            <a href="<?= e($urlDetail) ?>" class="btn btn-outline btn-block">Detail Kamar</a>
        </div>
    </div>
</article>
