<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/laporan.php';

$judulHalaman = 'Laporan';
$menuAktif    = 'laporan';

$periode = $_GET['periode'] ?? null;
$tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1000, 'max_range' => 9999]]) ?: null;
$bulan = filter_input(INPUT_GET, 'bulan', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: null;
if (!isset($_GET['bulan']) && !isset($_GET['tahun']) && $periode === null) {
    $periode = koneksi_database()->query('SELECT MAX(periode) FROM tagihan')->fetchColumn();
}
if (!isset($_GET['bulan']) && !isset($_GET['tahun']) && is_string($periode) && preg_match('/^([1-9][0-9]{3})-(0[1-9]|1[0-2])$/D', $periode, $cocok)) {
    [$tahun, $bulan] = [(int) $cocok[1], (int) $cocok[2]];
}
if ($bulan !== null && $tahun === null) {
    $tahun = (int) date('Y');
}
$awal = $tahun ? sprintf('%04d-%02d', $tahun, $bulan ?? 1) : null;
$akhir = $tahun ? sprintf('%04d-%02d', $tahun, $bulan ?? 12) : null;
$tanggalKamar = $akhir ? min(tanggal_hari_ini(), (new DateTimeImmutable($akhir . '-01'))->format('Y-m-t')) : tanggal_hari_ini();
$labelPeriode = $bulan ? format_periode($awal) : ($tahun ? (string) $tahun : 'Semua Periode');

$jenisDipilih = $_GET['jenis'] ?? 'semua';
if (!in_array($jenisDipilih, ['semua', 'pembayaran', 'kamar'], true)) {
    $jenisDipilih = 'semua';
}
$tampilkanPembayaran = $jenisDipilih !== 'kamar';
$tampilkanKamar       = $jenisDipilih !== 'pembayaran';

[$ringkasanTagihan, $daftarPembayaranLaporan, $daftarKamarLaporan] = data_laporan($awal, $akhir, $tanggalKamar);
$statKamar = $daftarKamarLaporan[0] ?? ['terisi' => 0, 'kosong' => 0, 'total' => 0, 'dipesan' => 0, 'perawatan' => 0];

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Laporan</h1>
        <p>Rekap tagihan, pembayaran, dan kamar <?= e(SITE_NAME) ?>.</p>
    </div>
    <button type="button" class="btn btn-outline" data-cetak-laporan>Cetak Laporan</button>
</div>

<form method="get" action="laporan.php" class="toolbar" role="search" aria-label="Filter laporan">
    <div class="filter-field">
        <label for="bulan">Bulan</label>
        <select id="bulan" name="bulan" class="form-control">
            <option value="">Semua bulan</option>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>"<?= $bulan === $i ? ' selected' : '' ?>><?= e(explode(' ', format_periode(sprintf('2000-%02d', $i)))[0]) ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="tahun">Tahun</label>
        <input id="tahun" name="tahun" class="form-control" type="number" min="1000" max="9999" placeholder="Semua tahun" value="<?= e((string) $tahun) ?>">
    </div>
    <div class="filter-field">
        <label for="jenis">Jenis Laporan</label>
        <select id="jenis" name="jenis" class="form-control">
            <option value="semua"<?= $jenisDipilih === 'semua' ? ' selected' : '' ?>>Semua Laporan</option>
            <option value="pembayaran"<?= $jenisDipilih === 'pembayaran' ? ' selected' : '' ?>>Laporan Pembayaran</option>
            <option value="kamar"<?= $jenisDipilih === 'kamar' ? ' selected' : '' ?>>Laporan Kamar</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Terapkan</button>
</form>

<p class="laporan-periode-cetak">Periode laporan: <strong><?= e($labelPeriode) ?></strong> &middot; Dicetak <?= e(format_tanggal(tanggal_hari_ini())) ?></p>

<section class="stat-grid" aria-label="Ringkasan laporan">
    <div class="stat-card">
        <p class="stat-card__label">Total Tagihan</p>
        <p class="stat-card__nilai"><?= $ringkasanTagihan['jumlah'] ?></p>
        <p class="stat-card__ket"><?= e(format_rupiah($ringkasanTagihan['total'])) ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Pembayaran Diterima</p>
        <p class="stat-card__nilai"><?= $ringkasanTagihan['jumlah_bayar'] ?></p>
        <p class="stat-card__ket"><?= e(format_rupiah($ringkasanTagihan['total_bayar'])) ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Tagihan Belum Lunas</p>
        <p class="stat-card__nilai"><?= $ringkasanTagihan['belum_lunas_jumlah'] ?></p>
        <p class="stat-card__ket"><?= e(format_rupiah($ringkasanTagihan['belum_lunas_total'])) ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Kamar Terisi</p>
        <p class="stat-card__nilai"><?= $statKamar['terisi'] ?></p>
        <p class="stat-card__ket"><?= $statKamar['kosong'] ?> <?= $tanggalKamar === tanggal_hari_ini() ? 'tersedia' : 'kosong' ?>
            <?php if ($tanggalKamar === tanggal_hari_ini()): ?> · <?= $statKamar['dipesan'] ?> dipesan · <?= $statKamar['perawatan'] ?> perawatan<?php endif; ?>
            · <?= $statKamar['total'] ?> kamar</p>
    </div>
</section>

<?php if ($tampilkanPembayaran): ?>
    <section class="panel">
        <div class="panel__head">
            <h2>Laporan Pembayaran</h2>
            <span class="text-muted"><?= e($labelPeriode) ?></span>
        </div>
        <?php if (empty($daftarPembayaranLaporan)): ?>
            <p class="panel__kosong">Tidak ada pembayaran pada periode ini.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel tabel--lebar">
                    <thead>
                        <tr>
                            <th scope="col" class="tabel__no">No</th>
                            <th scope="col">Tanggal Pembayaran</th>
                            <th scope="col">Nama Penghuni</th>
                            <th scope="col">Nomor Kamar</th>
                            <th scope="col">Periode Tagihan</th>
                            <th scope="col" class="tabel__angka">Nominal</th>
                            <th scope="col">Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarPembayaranLaporan as $i => $p): ?>
                            <tr>
                                <td class="tabel__no" data-label="No"><?= $i + 1 ?></td>
                                <td data-label="Tanggal Pembayaran"><?= e(format_tanggal($p['tanggal_bayar'])) ?></td>
                                <td class="tabel__utama" data-label="Nama Penghuni"><?= e($p['nama_penghuni']) ?></td>
                                <td data-label="Nomor Kamar">Kamar <?= e($p['nomor_kamar']) ?></td>
                                <td data-label="Periode Tagihan"><?= e(format_periode($p['periode_tagihan'])) ?></td>
                                <td class="tabel__angka" data-label="Nominal"><?= e(format_rupiah($p['nominal_dibayar'])) ?></td>
                                <td data-label="Metode"><?= e(label_metode_pembayaran($p['metode'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="tabel__angka"><strong>Total</strong></td>
                            <td class="tabel__angka"><strong><?= e(format_rupiah($ringkasanTagihan['total_bayar'])) ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($tampilkanKamar): ?>
    <section class="panel">
        <div class="panel__head">
            <h2>Laporan Kamar</h2>
            <span class="text-muted">Hunian per <?= e(format_tanggal($tanggalKamar)) ?></span>
        </div>
        <div class="tabel-wrap">
            <table class="tabel tabel--lebar">
                <thead>
                    <tr>
                        <th scope="col" class="tabel__no">No</th>
                        <th scope="col">Nomor Kamar</th>
                        <th scope="col" class="tabel__angka">Harga Sewa</th>
                        <th scope="col">Status</th>
                        <th scope="col">Penghuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarKamarLaporan as $i => $k): ?>
                        <tr>
                            <td class="tabel__no" data-label="No"><?= $i + 1 ?></td>
                            <td class="tabel__utama" data-label="Nomor Kamar">Kamar <?= e($k['nomor']) ?></td>
                            <td class="tabel__angka" data-label="Harga Sewa"><?= e(format_rupiah($k['harga'])) ?></td>
                            <td data-label="Status"><span class="badge badge--<?= e($k['status']) ?>"><?= e($tanggalKamar === tanggal_hari_ini() ? label_status($k['status']) : ucfirst($k['status'])) ?></span></td>
                            <td data-label="Penghuni"><?= e($k['nama'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<p class="teks-catatan">Pembayaran dikelompokkan menurut periode tagihan. Hunian mengikuti tanggal masuk dan keluar. Harga kamar kosong mengikuti daftar harga saat ini.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
