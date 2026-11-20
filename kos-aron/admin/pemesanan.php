<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/pemesanan.php';

$judulHalaman = 'Data Pemesanan';
$menuAktif = 'pemesanan';
$pilihanStatus = ['menunggu', 'disetujui', 'ditolak', 'dibatalkan'];
// Pencarian nama dari formulir tetap berada di body, sehingga URL tidak memuat identitas.
$filter = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$status = in_array($filter['status'] ?? '', $pilihanStatus, true) ? $filter['status'] : '';
$cari = is_string($filter['cari'] ?? null) ? mb_substr(trim($filter['cari']), 0, 100) : '';
$daftar = daftar_pemesanan($status, $cari);
require __DIR__ . '/includes/header.php';
?>
<div class="admin-page-head"><h1>Data Pemesanan</h1></div>
<section class="panel">
    <form method="post" action="pemesanan.php" class="toolbar" role="search" aria-label="Filter pemesanan">
        <div class="filter-field filter-field--cari">
            <label for="cari">Kode pemesanan atau nama calon penghuni</label>
            <input id="cari" name="cari" type="search" class="form-control" maxlength="100" value="<?= e($cari) ?>">
        </div>
        <div class="filter-field">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">Semua</option>
                <?php foreach ($pilihanStatus as $opsi): ?>
                    <option value="<?= e($opsi) ?>" <?= $status === $opsi ? 'selected' : '' ?>><?= e(ucfirst($opsi)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
        <?php if ($status || $cari !== ''): ?><a class="btn btn-outline" href="pemesanan.php">Reset</a><?php endif; ?>
    </form>
    <?php if (!$daftar): ?>
        <p class="panel__kosong"><?= $status || $cari !== '' ? 'Tidak ada pemesanan yang sesuai dengan filter.' : 'Belum ada pemesanan kamar.' ?></p>
    <?php else: ?>
        <div class="tabel-wrap">
            <table class="tabel tabel--lebar">
                <thead><tr>
                    <th scope="col">No</th><th scope="col">Kode Pemesanan</th><th scope="col">Nama Calon Penghuni</th>
                    <th scope="col">Nomor Kamar</th><th scope="col">Tanggal Pengajuan</th><th scope="col">Rencana Masuk</th>
                    <th scope="col">Status</th><th scope="col">Aksi</th>
                </tr></thead>
                <tbody>
                <?php foreach ($daftar as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td><td><?= e($p['kode_pemesanan']) ?></td><td><?= e($p['nama_lengkap']) ?></td>
                        <td><?= e($p['nomor_kamar']) ?><span class="tabel__sub"><?= e(label_status($p['ketersediaan'])) ?></span></td><td><?= e(format_tanggal(substr($p['tanggal_pengajuan'], 0, 10))) ?></td>
                        <td><?= e(format_tanggal($p['rencana_tanggal_masuk'])) ?></td><td><?= e(ucfirst($p['status'])) ?>
                            <?php if ($p['penyewaan_id']): ?><span class="tabel__sub">Sudah diproses menjadi penghuni</span><?php endif; ?>
                        </td>
                        <td><div class="aksi-grup">
                            <a class="btn btn-outline btn-sm" href="pemesanan-detail.php?id=<?= (int)$p['id'] ?>">Detail</a>
                            <?php if ($p['status'] === 'menunggu'): ?>
                                <?php if ($p['ketersediaan'] === 'kosong'): ?><a class="btn btn-outline btn-sm" href="pemesanan-detail.php?id=<?= (int)$p['id'] ?>#setujui">Setujui</a><?php endif; ?>
                                <a class="btn btn-outline btn-sm" href="pemesanan-detail.php?id=<?= (int)$p['id'] ?>#tolak">Tolak</a>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
