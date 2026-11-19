<?php
require_once __DIR__ . '/includes/konteks.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Dashboard Penghuni';
$menuAktif    = 'dashboard';

require_once __DIR__ . '/../data/notifikasi.php';

$tagihanBerjalan = $penghuniLogin ? get_tagihan_berjalan($penghuniLogin['id']) : null;
$riwayatTerbaru  = $penghuniLogin ? get_pembayaran_by_penghuni($penghuniLogin['id'], 3) : [];

$notifikasi = $penghuniLogin ? get_notifikasi_penghuni($penghuniLogin['id'], 1) : [];
require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head">
    <h1>Dashboard Penghuni</h1>
    <p>Informasi kamar dan pembayaran sewa Kos Aron.</p>
</div>

<?php if ($penghuniLogin === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada penghuni aktif</strong>
        Tidak ada data penghuni yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <!-- 2. Informasi Penghuni -->
    <section class="panel">
        <div class="panel__head">
            <h2>Informasi Penghuni</h2>
        </div>
        <dl class="info-tabel info-tabel--panel">
            <div>
                <dt>Nama Penghuni</dt>
                <dd><?= e($penghuniLogin['nama']) ?></dd>
            </div>
            <div>
                <dt>Nomor Kamar</dt>
                <dd><?= $penghuniLogin['kamar_id'] ? 'Kamar ' . e($penghuniLogin['nomor']) : '-' ?></dd>
            </div>
            <div>
                <dt>Harga Sewa Bulanan</dt>
                <dd><?= $penghuniLogin['kamar_id'] ? e(format_rupiah($penghuniLogin['harga_sewa_disepakati'])) . ' / bulan' : '-' ?></dd>
            </div>
            <div>
                <dt>Tanggal Jatuh Tempo</dt>
                <dd><?= $tagihanBerjalan ? e(format_tanggal($tagihanBerjalan['jatuh_tempo'])) : '-' ?></dd>
            </div>
        </dl>
    </section>

    <!-- 3. Status Tagihan (bulan berjalan) -->
    <section class="panel">
        <div class="panel__head">
            <h2>Status Tagihan</h2>
            <a href="tagihan.php" class="link-more">Lihat semua tagihan <span aria-hidden="true">&rarr;</span></a>
        </div>
        <?php if ($tagihanBerjalan === null): ?>
            <p class="panel__kosong">Belum ada tagihan untuk bulan ini.</p>
        <?php else: ?>
            <dl class="info-tabel info-tabel--panel">
                <div>
                    <dt>Periode</dt>
                    <dd><?= e(format_periode($tagihanBerjalan['periode'])) ?></dd>
                </div>
                <div>
                    <dt>Nominal</dt>
                    <dd><?= e(format_rupiah($tagihanBerjalan['jumlah'])) ?></dd>
                </div>
                <div>
                    <dt>Jatuh Tempo</dt>
                    <dd><?= e(format_tanggal($tagihanBerjalan['jatuh_tempo'])) ?></dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>
                        <span class="badge badge--<?= e($tagihanBerjalan['status']) ?>"><?= e(label_status_tagihan($tagihanBerjalan['status'])) ?></span>
                        <?php if ($tagihanBerjalan['status'] === 'lunas'): ?>
                            <span class="tabel__sub">Dibayar <?= e(format_tanggal($tagihanBerjalan['tanggal_bayar'])) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <?php if ($tagihanBerjalan['status'] !== 'lunas'): ?>
                <p class="notice mt-section">
                    Pembayaran dilakukan tunai atau transfer langsung kepada pemilik, di luar website.
                    Status akan berubah menjadi Lunas setelah admin mencatat pembayaran Anda.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- 4. Riwayat Terbaru -->
    <section class="panel">
        <div class="panel__head">
            <h2>Riwayat Pembayaran Terbaru</h2>
            <a href="riwayat.php" class="link-more">Lihat semua riwayat <span aria-hidden="true">&rarr;</span></a>
        </div>
        <?php if (empty($riwayatTerbaru)): ?>
            <p class="panel__kosong">Belum ada riwayat pembayaran.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel tabel--tumpuk">
                    <thead>
                        <tr>
                            <th scope="col">Periode Tagihan</th>
                            <th scope="col">Tanggal Pembayaran</th>
                            <th scope="col" class="tabel__angka">Nominal</th>
                            <th scope="col">Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatTerbaru as $r): ?>
                            <tr>
                                <td data-label="Periode Tagihan"><?= e(format_periode($r['periode_tagihan'])) ?></td>
                                <td data-label="Tanggal Pembayaran"><?= e(format_tanggal($r['tanggal_bayar'])) ?></td>
                                <td data-label="Nominal" class="tabel__angka"><?= e(format_rupiah($r['nominal_dibayar'])) ?></td>
                                <td data-label="Metode"><?= e(label_metode_pembayaran($r['metode'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- 5. Notifikasi -->
    <section class="panel">
        <div class="panel__head">
            <h2>Notifikasi (<?= jumlah_notifikasi_belum_dibaca($penghuniLogin['id']) ?> belum dibaca)</h2>
            <a href="notifikasi.php" class="link-more">Lihat semua notifikasi <span aria-hidden="true">&rarr;</span></a>
        </div>
        <?php if ($notifikasi): ?>
            <p class="notice"><strong><?= e($notifikasi[0]['judul']) ?></strong>
                <span class="badge badge--<?= $notifikasi[0]['dibaca'] ? 'sudah-dibaca' : 'belum-dibaca' ?>">
                    <?= $notifikasi[0]['dibaca'] ? 'Sudah Dibaca' : 'Belum Dibaca' ?>
                </span><br><?= e($notifikasi[0]['pesan']) ?></p>
        <?php else: ?>
            <p class="panel__kosong">Belum ada notifikasi untuk Anda.</p>
        <?php endif; ?>
    </section>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
