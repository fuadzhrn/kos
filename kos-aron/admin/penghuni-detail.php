<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni & kamar

$menuAktif = 'penghuni';

$id       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$penghuni = $id ? get_penghuni_by_id($id) : null;
if ($penghuni === null) {
    http_response_code(404);
}

$riwayat = $penghuni ? riwayat_sewa_penghuni($id) : [];
$pesan = $_SESSION['pesan_penghuni'] ?? null;
unset($_SESSION['pesan_penghuni']);
$tagihan = $penghuni ? get_tagihan_by_penghuni($penghuni['id']) : [];

$judulHalaman = $penghuni ? 'Detail Penghuni' : 'Penghuni Tidak Ditemukan';

require __DIR__ . '/includes/header.php';
?>

<?php if ($pesan): ?><p class="notice" role="status"><?= e($pesan) ?></p><?php endif; ?>
<nav aria-label="Breadcrumb">
    <ol class="breadcrumb">
        <li><a href="penghuni.php">Data Penghuni</a></li>
        <li aria-current="page">Detail</li>
    </ol>
</nav>

<?php if ($penghuni === null): ?>
    <div class="admin-placeholder">
        <strong>Penghuni tidak ditemukan</strong>
        <p>Data penghuni yang Anda cari tidak tersedia.</p>
        <a href="penghuni.php" class="btn btn-primary">Kembali ke Data Penghuni</a>
    </div>
<?php else: ?>
    <div class="admin-page-head admin-page-head--aksi">
        <div>
            <h1><?= e($penghuni['nama']) ?></h1>
            <p>
                <span class="badge badge--<?= e($penghuni['status']) ?>"><?= e(label_status_penghuni($penghuni['status'])) ?></span>
            </p>
        </div>
        <a href="penghuni-form.php?id=<?= (int) $penghuni['id'] ?>" class="btn btn-outline">Edit Data</a>
    </div>

    <section class="panel">
        <div class="panel__head">
            <h2>Informasi Penghuni</h2>
        </div>
        <dl class="info-tabel info-tabel--panel">
            <div><dt>Nama lengkap</dt><dd><?= e($penghuni['nama']) ?></dd></div>
            <div><dt>Nomor telepon</dt><dd><?= e($penghuni['no_telepon']) ?></dd></div>
            <div><dt>Email / username</dt><dd><?= e($penghuni['akun']) ?></dd></div>
            <div>
                <dt>Kamar</dt>
                <dd>
                    <?php if ($penghuni['kamar_id']): ?>
                        Kamar <?= e($penghuni['nomor']) ?> (<?= e(format_rupiah($penghuni['harga_sewa_disepakati'])) ?> / bulan)
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </dd>
            </div>
            <div><dt>Tanggal mulai sewa</dt><dd><?= e(format_tanggal($penghuni['tanggal_masuk'])) ?></dd></div>
            <?php if ($penghuni['tanggal_keluar']): ?>
                <div><dt>Tanggal selesai sewa</dt><dd><?= e(format_tanggal($penghuni['tanggal_keluar'])) ?></dd></div>
            <?php endif; ?>
            <div><dt>Status</dt><dd><?= e(label_status_penghuni($penghuni['status'])) ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="panel__head"><h2>Riwayat Penyewaan</h2></div>
        <?php if (!$riwayat): ?>
            <p class="panel__kosong">Belum ada penyewaan.</p>
        <?php else: ?>
            <div class="tabel-wrap"><table class="tabel">
                <thead><tr><th>Kamar</th><th>Tanggal masuk</th><th>Tanggal keluar</th><th>Harga per bulan</th><th>Hari jatuh tempo</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($riwayat as $sewa): ?>
                    <tr><td><?= e($sewa['nomor']) ?></td><td><?= e(format_tanggal($sewa['tanggal_masuk'])) ?></td>
                        <td><?= e(format_tanggal($sewa['tanggal_keluar'])) ?></td><td><?= e(format_rupiah($sewa['harga_sewa_disepakati'])) ?></td>
                        <td><?= (int)$sewa['hari_jatuh_tempo'] ?></td><td><?= e(ucfirst($sewa['status'])) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2>Riwayat Tagihan</h2>
        </div>
        <?php if (empty($tagihan)): ?>
            <p class="panel__kosong">Belum ada data tagihan untuk penghuni ini.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel tabel--tumpuk">
                    <thead>
                        <tr>
                            <th scope="col">Periode</th>
                            <th scope="col" class="tabel__angka">Jumlah Tagihan</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tagihan as $t): ?>
                            <tr>
                                <td data-label="Periode"><?= e(format_periode($t['periode'])) ?></td>
                                <td data-label="Jumlah Tagihan" class="tabel__angka"><?= e(format_rupiah($t['jumlah'])) ?></td>
                                <td data-label="Status"><span class="badge badge--<?= e($t['status']) ?>"><?= e(label_status_tagihan($t['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p><a href="penghuni.php" class="link-more"><span aria-hidden="true">&larr;</span> Kembali ke Data Penghuni</a></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
