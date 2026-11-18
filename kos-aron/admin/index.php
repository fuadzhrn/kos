<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni & kamar

$judulHalaman = 'Dashboard';
$menuAktif    = 'dashboard';

$statKamar     = hitung_kamar_per_status();
$belumLunas    = ringkasan_tagihan_belum_lunas();
$tagihanBaru   = get_tagihan_terbaru(5);
$daftarKamar   = get_semua_kamar();
$kamarKosong   = array_values(array_filter($daftarKamar, fn ($k) => $k['status'] === 'kosong'));
$kamarTerisi   = array_values(array_filter($daftarKamar, fn ($k) => $k['status'] === 'terisi'));

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head">
    <h1>Dashboard</h1>
    <p>Ringkasan informasi pengelolaan Kos Aron.</p>
</div>

<!-- Statistik -->
<section class="stat-grid" aria-label="Statistik">
    <div class="stat-card">
        <p class="stat-card__label">Total Kamar</p>
        <p class="stat-card__nilai"><?= $statKamar['total'] ?></p>
        <p class="stat-card__ket">Seluruh kamar terdata</p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Kamar Terisi</p>
        <p class="stat-card__nilai"><?= $statKamar['terisi'] ?></p>
        <p class="stat-card__ket">dari <?= $statKamar['total'] ?> kamar</p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Kamar Kosong</p>
        <p class="stat-card__nilai"><?= $statKamar['kosong'] ?></p>
        <p class="stat-card__ket">Tersedia untuk disewa</p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Tagihan Belum Lunas</p>
        <p class="stat-card__nilai"><?= $belumLunas['jumlah'] ?></p>
        <p class="stat-card__ket">
            Total <?= e(format_rupiah($belumLunas['total'])) ?>
            <?php if ($belumLunas['terlambat'] > 0): ?>
                &middot; <?= $belumLunas['terlambat'] ?> terlambat
            <?php endif; ?>
        </p>
    </div>
</section>

<!-- Status pembayaran terbaru -->
<section class="panel">
    <div class="panel__head">
        <h2>Status Pembayaran Terbaru</h2>
        <a href="tagihan.php" class="link-more">Lihat semua tagihan <span aria-hidden="true">&rarr;</span></a>
    </div>

    <?php if (empty($tagihanBaru)): ?>
        <p class="panel__kosong">Belum ada data tagihan.</p>
    <?php else: ?>
        <div class="tabel-wrap">
        <table class="tabel tabel--tumpuk">
            <thead>
                <tr>
                    <th scope="col">Nama Penghuni</th>
                    <th scope="col">Nomor Kamar</th>
                    <th scope="col">Periode</th>
                    <th scope="col" class="tabel__angka">Jumlah Tagihan</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tagihanBaru as $t): ?>
                    <tr>
                        <td data-label="Nama Penghuni"><?= e($t['nama_penghuni']) ?></td>
                        <td data-label="Nomor Kamar">Kamar <?= e($t['nomor_kamar']) ?></td>
                        <td data-label="Periode"><?= e(format_periode($t['periode'])) ?></td>
                        <td data-label="Jumlah Tagihan" class="tabel__angka"><?= e(format_rupiah($t['jumlah'])) ?></td>
                        <td data-label="Status">
                            <span class="badge badge--<?= e($t['status']) ?>"><?= e(label_status_tagihan($t['status'])) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<!-- Informasi kamar -->
<section class="panel">
    <div class="panel__head">
        <h2>Informasi Kamar</h2>
        <a href="kamar.php" class="link-more">Kelola data kamar <span aria-hidden="true">&rarr;</span></a>
    </div>

    <div class="info-kamar">
        <div class="info-kamar__kolom">
            <h3>Kamar Kosong <span>(<?= count($kamarKosong) ?>)</span></h3>
            <?php if (empty($kamarKosong)): ?>
                <p class="panel__kosong">Tidak ada kamar kosong.</p>
            <?php else: ?>
                <ul class="info-kamar__list">
                    <?php foreach ($kamarKosong as $k): ?>
                        <li>
                            <span>Kamar <?= e($k['nomor']) ?></span>
                            <span class="text-muted"><?= e(format_rupiah($k['harga'])) ?> / bulan</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="info-kamar__kolom">
            <h3>Kamar Terisi <span>(<?= count($kamarTerisi) ?>)</span></h3>
            <?php if (empty($kamarTerisi)): ?>
                <p class="panel__kosong">Belum ada kamar terisi.</p>
            <?php else: ?>
                <ul class="info-kamar__list">
                    <?php foreach ($kamarTerisi as $k): ?>
                        <?php $penghuni = get_penghuni_by_kamar($k['id']); ?>
                        <li>
                            <span>Kamar <?= e($k['nomor']) ?></span>
                            <span class="text-muted"><?= e($penghuni['nama'] ?? 'Penghuni belum tercatat') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
