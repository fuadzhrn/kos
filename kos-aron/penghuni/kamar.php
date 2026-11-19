<?php
require_once __DIR__ . '/../includes/config.php';
require_role('penghuni', '../login.php');
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Kamar Saya';
$menuAktif    = 'kamar';

require __DIR__ . '/includes/header.php';

// $penghuniLogin sudah disiapkan oleh includes/header.php (lihat get_penghuni_login()).
$kamarSaya       = $penghuniLogin ? get_kamar_by_id($penghuniLogin['kamar_id']) : null;
$tagihanBerjalan = $penghuniLogin ? get_tagihan_berjalan($penghuniLogin['id']) : null;

if ($penghuniLogin !== null) {
    $kontak      = link_hubungi_pemilik(
        'Halo, saya penghuni Kamar ' . ($kamarSaya['nomor'] ?? '-') . ' dan ingin menanyakan informasi kamar.',
        '../kontak.php'
    );
    $atributLink = $kontak['eksternal'] ? ' target="_blank" rel="noopener"' : '';
}
?>

<div class="admin-page-head">
    <h1>Kamar Saya</h1>
    <p>Informasi kamar yang sedang Anda tempati.</p>
</div>

<?php if ($penghuniLogin === null || $kamarSaya === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada data kamar</strong>
        Tidak ada informasi kamar yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <!-- 2. Informasi Kamar -->
    <div class="detail-kamar">
        <div class="foto-frame">
            <img src="../<?= e(url_foto($kamarSaya['foto'])) ?>" alt="Foto Kamar <?= e($kamarSaya['nomor']) ?>"
                 data-fallback="../<?= e(FOTO_PLACEHOLDER) ?>">
        </div>

        <div class="detail-kamar__info">
            <div class="detail-kamar__head">
                <h2>Kamar <?= e($kamarSaya['nomor']) ?></h2>
                <span class="badge badge--aktif">Ditempati</span>
            </div>

            <p class="detail-kamar__harga">
                <?= e(format_rupiah($kamarSaya['harga'])) ?> <span>/ bulan</span>
            </p>

            <?php if (!empty($kamarSaya['deskripsi'])): ?>
                <p><?= e($kamarSaya['deskripsi']) ?></p>
            <?php endif; ?>

            <?php if (!empty($kamarSaya['fasilitas'])): ?>
                <h3 class="detail-label">Fasilitas</h3>
                <ul class="list-fasilitas">
                    <?php foreach ($kamarSaya['fasilitas'] as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Informasi Penyewaan -->
    <section class="panel mt-section">
        <div class="panel__head">
            <h2>Informasi Penyewaan</h2>
        </div>
        <dl class="info-tabel info-tabel--panel">
            <div>
                <dt>Nama Penghuni</dt>
                <dd><?= e($penghuniLogin['nama']) ?></dd>
            </div>
            <div>
                <dt>Tanggal Mulai Sewa</dt>
                <dd><?= e(format_tanggal($penghuniLogin['tanggal_masuk'])) ?></dd>
            </div>
            <div>
                <dt>Tanggal Jatuh Tempo Pembayaran</dt>
                <dd><?= $tagihanBerjalan ? e(format_tanggal($tagihanBerjalan['jatuh_tempo'])) : '-' ?></dd>
            </div>
            <div>
                <dt>Status Penyewaan</dt>
                <dd><span class="badge badge--<?= e($penghuniLogin['status']) ?>"><?= e(label_status_penghuni($penghuniLogin['status'])) ?></span></dd>
            </div>
        </dl>
    </section>

    <!-- 4. Informasi Tambahan -->
    <div class="contact-box">
        <p>Jika terdapat perubahan informasi kamar, silakan hubungi pemilik kos.</p>
        <a href="<?= e($kontak['url']) ?>" class="btn btn-primary"<?= $atributLink ?>>Hubungi Pemilik</a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
