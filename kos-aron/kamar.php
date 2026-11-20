<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/data/kamar.php';

$judulHalaman  = 'Daftar Kamar';
$halamanAktif  = 'kamar';
$deskripsiMeta = 'Daftar kamar ' . SITE_NAME . ' di ' . SITE_LOCATION . ' beserta fasilitas, harga sewa, dan status ketersediaan.';

$daftarKamar = get_semua_kamar();
$totalKamar  = count($daftarKamar);

require __DIR__ . '/includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Daftar Kamar <?= e(SITE_NAME) ?></h1>
        <p>Lihat informasi kamar, fasilitas, harga sewa, dan ketersediaan kamar di <?= e(SITE_NAME) ?>.</p>
    </div>
</section>

<section class="section section--tight">
    <div class="container">
        <?php if ($totalKamar === 0): ?>
            <div class="empty-state">
                <p>Data kamar belum tersedia.</p>
            </div>
        <?php else: ?>
            <form id="filter-kamar" class="filter-bar" role="search" aria-label="Filter kamar">
                <div class="filter-field filter-field--cari">
                    <label for="cari-kamar">Cari kamar</label>
                    <input type="search" id="cari-kamar" name="cari" class="form-control"
                           placeholder="Contoh: 01" autocomplete="off">
                </div>

                <div class="filter-field">
                    <label for="status-kamar">Status</label>
                    <select id="status-kamar" name="status" class="form-control">
                        <option value="semua">Semua Kamar</option>
                        <option value="kosong">Tersedia</option>
                        <option value="terisi">Terisi</option>
                        <option value="dipesan">Dipesan</option>
                    </select>
                </div>

                <p id="info-hasil" class="filter-info" aria-live="polite">
                    Menampilkan <?= $totalKamar ?> dari <?= $totalKamar ?> kamar
                </p>
            </form>

            <h2 class="sr-only">Daftar kamar</h2>
            <div id="grid-kamar" class="grid-kamar">
                <?php foreach ($daftarKamar as $kamar): ?>
                    <?php include __DIR__ . '/includes/card-kamar.php'; ?>
                <?php endforeach; ?>
            </div>

            <div id="kamar-tidak-ditemukan" class="empty-state" hidden>
                <p>Tidak ada kamar yang sesuai dengan pencarian.</p>
                <button type="button" class="btn btn-outline" data-reset-filter>Tampilkan Semua Kamar</button>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
