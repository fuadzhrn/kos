<?php
require_once __DIR__ . '/includes/konteks.php';
require_once __DIR__ . '/../data/pengajuan-pembayaran.php';

$judulHalaman = 'Dashboard Penghuni';
$menuAktif    = 'dashboard';

require_once __DIR__ . '/../data/notifikasi.php';

$tagihanBerjalan = $penghuniLogin ? get_tagihan_berjalan($penghuniLogin['id'], true) : null;
$ringkasan = $penghuniLogin ? ringkasan_tagihan_periode(null, null, $penghuniLogin['id']) : null;
$riwayatTerbaru  = $penghuniLogin ? get_pembayaran_by_penghuni($penghuniLogin['id'], 3) : [];

$notifikasi = $penghuniLogin ? get_notifikasi_penghuni($penghuniLogin['id'], 1) : [];
require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head">
    <h1>Dashboard Penghuni</h1>
    <p>Informasi kamar dan pembayaran sewa <?= e(SITE_NAME) ?>.</p>
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
                <dt>Total tagihan belum lunas</dt>
                <dd><?= e(format_rupiah($ringkasan['belum_lunas_total'])) ?> · <?= (int)$ringkasan['belum_lunas_jumlah'] ?> tagihan</dd>
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
            <p class="panel__kosong">Belum ada tagihan untuk Anda.</p>
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
                <?php if ($tagihanBerjalan['status_pengajuan'] !== null): ?>
                    <div><dt>Pengajuan transfer terakhir</dt><dd><?= e(label_status_pengajuan($tagihanBerjalan['status_pengajuan'])) ?>
                        <?php if ($tagihanBerjalan['status_pengajuan'] === 'ditolak' && $tagihanBerjalan['alasan_pengajuan'] !== null): ?>
                            <span class="tabel__sub"><?= e($tagihanBerjalan['alasan_pengajuan']) ?></span>
                        <?php endif; ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($tagihanBerjalan['status'] !== 'lunas'): ?>
                <p class="notice mt-section">
                    <?php if ($tagihanBerjalan['status_pengajuan'] === 'menunggu'): ?>
                        Bukti transfer sedang diperiksa Admin.
                    <?php elseif ($tagihanBerjalan['pembayaran_sah_id'] === null && $tagihanBerjalan['jumlah'] !== '0.00'): ?>
                        <?php if ($tagihanBerjalan['status'] === 'terlambat'): ?>Tagihan telah melewati jatuh tempo.<?php endif; ?>
                        <a href="tagihan.php?ajukan=<?= (int)$tagihanBerjalan['id'] ?>#pengajuan"><?= $tagihanBerjalan['status_pengajuan'] === 'ditolak' ? 'Ajukan Ulang Pembayaran' : 'Ajukan Pembayaran' ?></a>
                        atau hubungi pemilik untuk pembayaran langsung.
                    <?php else: ?>
                        Hubungi pemilik untuk informasi tagihan ini.
                    <?php endif; ?>
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
            <h2>Notifikasi (<?= $belumDibaca ?> belum dibaca)</h2>
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
