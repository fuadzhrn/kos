<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Laporan';
$menuAktif    = 'laporan';

$daftarPeriode = daftar_periode_tagihan(); // terbaru di atas, mis. ['2026-11', '2026-10']

// Filter "Bulan dan Tahun": satu periode (format sama dengan filter Tagihan/Pembayaran),
// atau 'semua' untuk seluruh periode. Default ke periode TERBARU yang tersedia.
$periodeDipilih = $_GET['periode'] ?? ($daftarPeriode[0] ?? 'semua');
if ($periodeDipilih !== 'semua' && !in_array($periodeDipilih, $daftarPeriode, true)) {
    $periodeDipilih = $daftarPeriode[0] ?? 'semua';
}
$periodeUntukData = $periodeDipilih === 'semua' ? null : $periodeDipilih;

// Filter "Jenis Laporan": tabel mana yang ditampilkan.
$jenisDipilih = $_GET['jenis'] ?? 'semua';
if (!in_array($jenisDipilih, ['semua', 'pembayaran', 'kamar'], true)) {
    $jenisDipilih = 'semua';
}
$tampilkanPembayaran = $jenisDipilih !== 'kamar';
$tampilkanKamar       = $jenisDipilih !== 'pembayaran';

// --- Ringkasan (4 kartu) ---
$ringkasanTagihan   = ringkasan_tagihan_periode($periodeUntukData);
$ringkasanPembayaran = ringkasan_pembayaran_periode($periodeUntukData);
$statKamar           = hitung_kamar_per_status();

// --- Data tabel ---
$daftarPembayaranLaporan = array_values(array_filter(
    get_semua_pembayaran(),
    fn ($p) => $periodeUntukData === null || $p['periode_tagihan'] === $periodeUntukData
));
$daftarKamarLaporan = get_semua_kamar(true); // admin: laporan mencakup juga kamar berkondisi 'perawatan'

$labelPeriode = $periodeDipilih === 'semua' ? 'Semua Periode' : format_periode($periodeDipilih);

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Laporan</h1>
        <p>Rekap tagihan, pembayaran, dan kamar Kos Aron.</p>
    </div>
    <button type="button" class="btn btn-outline" data-cetak-laporan>Cetak Laporan</button>
</div>

<form method="get" action="laporan.php" class="toolbar" role="search" aria-label="Filter laporan">
    <div class="filter-field">
        <label for="periode">Bulan dan Tahun</label>
        <select id="periode" name="periode" class="form-control">
            <option value="semua"<?= $periodeDipilih === 'semua' ? ' selected' : '' ?>>Semua Periode</option>
            <?php foreach ($daftarPeriode as $periode): ?>
                <option value="<?= e($periode) ?>"<?= $periodeDipilih === $periode ? ' selected' : '' ?>><?= e(format_periode($periode)) ?></option>
            <?php endforeach; ?>
        </select>
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
        <p class="stat-card__nilai"><?= $ringkasanPembayaran['jumlah'] ?></p>
        <p class="stat-card__ket"><?= e(format_rupiah($ringkasanPembayaran['total'])) ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Tagihan Belum Lunas</p>
        <p class="stat-card__nilai"><?= $ringkasanTagihan['belum_lunas_jumlah'] ?></p>
        <p class="stat-card__ket"><?= e(format_rupiah($ringkasanTagihan['belum_lunas_total'])) ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Kamar Terisi</p>
        <p class="stat-card__nilai"><?= $statKamar['terisi'] ?></p>
        <p class="stat-card__ket">dari <?= $statKamar['total'] ?> kamar</p>
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
                            <td class="tabel__angka"><strong><?= e(format_rupiah($ringkasanPembayaran['total'])) ?></strong></td>
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
            <span class="text-muted">Kondisi saat ini</span>
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
                        <?php $penghuniKamar = get_penghuni_by_kamar($k['id']); ?>
                        <tr>
                            <td class="tabel__no" data-label="No"><?= $i + 1 ?></td>
                            <td class="tabel__utama" data-label="Nomor Kamar">Kamar <?= e($k['nomor']) ?></td>
                            <td class="tabel__angka" data-label="Harga Sewa"><?= e(format_rupiah($k['harga'])) ?></td>
                            <td data-label="Status"><span class="badge badge--<?= e($k['status']) ?>"><?= e(ucfirst($k['status'])) ?></span></td>
                            <td data-label="Penghuni"><?= $penghuniKamar ? e($penghuniKamar['nama']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<p class="teks-catatan">Laporan dihitung langsung dari data tagihan, pembayaran, dan kamar yang sama dengan halaman lain -- bukan sumber data terpisah.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
