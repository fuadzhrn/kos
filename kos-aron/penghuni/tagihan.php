<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Tagihan';
$menuAktif    = 'tagihan';

require __DIR__ . '/includes/header.php';

// $penghuniLogin sudah disiapkan oleh includes/header.php (lihat get_penghuni_login()).
$kamarSaya       = $penghuniLogin ? get_kamar_by_id($penghuniLogin['kamar_id']) : null;
$tagihanBerjalan = $penghuniLogin ? get_tagihan_berjalan($penghuniLogin['id']) : null;
$riwayatTagihan  = $penghuniLogin ? get_tagihan_by_penghuni($penghuniLogin['id']) : [];

if ($penghuniLogin !== null) {
    $kontak      = link_hubungi_pemilik(
        'Halo, saya penghuni Kamar ' . ($kamarSaya['nomor'] ?? '-') . ' dan ingin bertanya tentang tagihan sewa saya.',
        '../kontak.php'
    );
    $atributLink = $kontak['eksternal'] ? ' target="_blank" rel="noopener"' : '';
}
?>

<div class="admin-page-head">
    <h1>Tagihan Saya</h1>
    <p>Tagihan sewa bulanan dan status pembayaran Anda.</p>
</div>

<?php if ($penghuniLogin === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada data penghuni</strong>
        Tidak ada informasi tagihan yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <!-- 2. Tagihan Bulan Berjalan -->
    <section class="panel">
        <div class="panel__head">
            <h2>Tagihan Bulan Berjalan</h2>
        </div>
        <?php if ($tagihanBerjalan === null): ?>
            <p class="panel__kosong">Belum ada tagihan untuk Anda saat ini.</p>
        <?php else: ?>
            <dl class="info-tabel info-tabel--panel">
                <div>
                    <dt>Periode Tagihan</dt>
                    <dd><?= e(format_periode($tagihanBerjalan['periode'])) ?></dd>
                </div>
                <div>
                    <dt>Nomor Kamar</dt>
                    <dd><?= $kamarSaya ? 'Kamar ' . e($kamarSaya['nomor']) : '-' ?></dd>
                </div>
                <div>
                    <dt>Nominal Tagihan</dt>
                    <dd><?= e(format_rupiah($tagihanBerjalan['jumlah'])) ?></dd>
                </div>
                <div>
                    <dt>Tanggal Jatuh Tempo</dt>
                    <dd><?= e(format_tanggal($tagihanBerjalan['jatuh_tempo'])) ?></dd>
                </div>
                <div>
                    <dt>Status Pembayaran</dt>
                    <dd>
                        <span class="badge badge--<?= e($tagihanBerjalan['status']) ?>"><?= e(label_status_tagihan($tagihanBerjalan['status'])) ?></span>
                        <?php if ($tagihanBerjalan['status'] === 'lunas'): ?>
                            <span class="tabel__sub">Dibayar <?= e(format_tanggal($tagihanBerjalan['tanggal_bayar'])) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        <?php endif; ?>
    </section>

    <!-- 3. Riwayat Tagihan -->
    <section class="panel">
        <div class="panel__head">
            <h2>Riwayat Tagihan</h2>
        </div>
        <?php if (empty($riwayatTagihan)): ?>
            <p class="panel__kosong">Belum ada riwayat tagihan.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel tabel--tumpuk">
                    <thead>
                        <tr>
                            <th scope="col" class="tabel__no">No</th>
                            <th scope="col">Periode</th>
                            <th scope="col" class="tabel__angka">Nominal</th>
                            <th scope="col">Jatuh Tempo</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatTagihan as $i => $t): ?>
                            <tr>
                                <td class="tabel__no" data-label="No"><?= $i + 1 ?></td>
                                <td data-label="Periode"><?= e(format_periode($t['periode'])) ?></td>
                                <td data-label="Nominal" class="tabel__angka"><?= e(format_rupiah($t['jumlah'])) ?></td>
                                <td data-label="Jatuh Tempo"><?= e(format_tanggal($t['jatuh_tempo'])) ?></td>
                                <td data-label="Status">
                                    <span class="badge badge--<?= e($t['status']) ?>"><?= e(label_status_tagihan($t['status'])) ?></span>
                                    <?php if ($t['status'] === 'lunas'): ?>
                                        <span class="tabel__sub">Dibayar <?= e(format_tanggal($t['tanggal_bayar'])) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- 5. Informasi Pembayaran -->
    <div class="contact-box">
        <p>Pembayaran dilakukan langsung kepada pemilik Kos Aron. Status pembayaran akan diperbarui oleh admin setelah pembayaran dicatat.</p>
        <a href="<?= e($kontak['url']) ?>" class="btn btn-primary"<?= $atributLink ?>>Hubungi Pemilik</a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
