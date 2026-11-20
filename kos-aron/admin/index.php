<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/pengajuan-pembayaran.php';

$judulHalaman = 'Dashboard';
$menuAktif    = 'dashboard';

$periodePilihan = daftar_periode_tagihan();
$periode = in_array($_GET['periode'] ?? '', $periodePilihan, true) ? $_GET['periode'] : null;
$filterTagihan = $periode ? ['tahun'=>substr($periode,0,4),'bulan'=>substr($periode,5,2)] : [];
$ringkasan = ringkasan_tagihan_periode($periode);
$tagihanBaru = get_tagihan_terbaru(5, $periode);
$daftarKamar = get_semua_kamar(true);
$statKamar = hitung_kamar_per_status($daftarKamar);
$kamarKosong = array_filter($daftarKamar, fn($k) => $k['ketersediaan'] !== 'terisi');
$kamarTerisi = array_filter($daftarKamar, fn($k) => $k['ketersediaan'] === 'terisi');

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
        <p class="stat-card__label">Kamar Tersedia</p>
        <p class="stat-card__nilai"><?= $statKamar['kosong'] ?></p>
        <p class="stat-card__ket"><?= $statKamar['dipesan'] ?> dipesan · <?= $statKamar['perawatan'] ?> perawatan</p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Tagihan Belum Lunas</p>
        <p class="stat-card__nilai"><?= (int)$ringkasan['belum_lunas_jumlah'] ?></p>
        <p class="stat-card__ket"><?= e(format_rupiah($ringkasan['belum_lunas_total'])) ?> · <?= (int)$ringkasan['terlambat'] ?> terlambat
        </p>
    </div>
</section>

<!-- Status pembayaran terbaru -->
<section class="panel">
    <div class="panel__head">
        <h2>Status Pembayaran Terbaru</h2>
        <a href="tagihan.php?<?= e(http_build_query($filterTagihan)) ?>" class="link-more">Lihat semua tagihan <span aria-hidden="true">&rarr;</span></a>
    </div>

    <form class="toolbar" method="get" action="index.php">
        <div class="filter-field"><label for="periode">Periode tagihan</label>
            <select id="periode" class="form-control" name="periode">
                <option value="">Semua periode</option>
                <?php foreach ($periodePilihan as $bulan): ?>
                    <option value="<?= e($bulan) ?>" <?= $periode === $bulan ? 'selected' : '' ?>><?= e(format_periode($bulan)) ?></option>
                <?php endforeach; ?>
            </select></div>
        <button type="submit" class="btn btn-outline">Tampilkan</button>
    </form>
    <dl class="info-tabel info-tabel--panel">
        <div><dt>Total tagihan</dt><dd><?= (int)$ringkasan['jumlah'] ?> · <?= e(format_rupiah($ringkasan['total'])) ?>
            <span class="tabel__sub"><?= (int)$ringkasan['jumlah_aktif'] ?> dari penyewaan aktif</span></dd></div>
        <div><dt>Pembayaran sah</dt><dd><?= e(format_rupiah($ringkasan['total_bayar'])) ?> · <?= (int)$ringkasan['jumlah_bayar'] ?> tagihan lunas</dd></div>
        <div><dt>Jatuh tempo hari ini hingga <?= e(format_tanggal(batas_pengingat_tagihan(tanggal_hari_ini()))) ?></dt>
            <dd><a href="tagihan.php?<?= e(http_build_query($filterTagihan + ['tempo'=>'dekat'])) ?>"><?= (int)$ringkasan['dekat'] ?> tagihan belum lunas</a></dd></div>
        <div><dt>Terlambat</dt><dd><a href="tagihan.php?<?= e(http_build_query($filterTagihan + ['status'=>'terlambat'])) ?>"><?= (int)$ringkasan['terlambat'] ?> tagihan</a></dd></div>
        <div><dt>Menunggu verifikasi</dt><dd><a href="pembayaran.php?<?= e(http_build_query(['status_pengajuan'=>'menunggu','periode_pengajuan'=>$periode])) ?>#pengajuan"><?= (int)$ringkasan['menunggu'] ?> pengajuan</a></dd></div>
    </dl>

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
                            <?php if ($t['status_pengajuan'] !== null): ?><span class="tabel__sub"><?= e(label_status_pengajuan($t['status_pengajuan'])) ?></span><?php endif; ?>
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
            <h3>Kamar Belum Terisi <span>(<?= count($kamarKosong) ?>)</span></h3>
            <?php if (empty($kamarKosong)): ?>
                <p class="panel__kosong">Tidak ada kamar yang belum terisi.</p>
            <?php else: ?>
                <ul class="info-kamar__list">
                    <?php foreach ($kamarKosong as $k): ?>
                        <li>
                            <span>Kamar <?= e($k['nomor']) ?></span>
                            <span class="text-muted"><?= e(label_status($k['ketersediaan'])) ?> · <?= e(format_rupiah($k['harga'])) ?> / bulan</span>
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
                        <li>
                            <span>Kamar <?= e($k['nomor']) ?></span>
                            <span class="text-muted"><?= e($k['nama_penghuni'] ?? '—') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
