<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/penghuni.php'; // juga memuat data kamar

$judulHalaman = 'Data Penghuni';
$menuAktif    = 'penghuni';

$daftarPenghuni = get_semua_penghuni();
$totalPenghuni  = count($daftarPenghuni);

/*
 * Nonaktifkan penghuni: BELUM mengubah data apa pun.
 * Nanti (backend): validasi CSRF, set status = 'tidak_aktif', isi tanggal_keluar,
 * ubah status kamar menjadi 'kosong'. Data penghuni & riwayat tagihan TIDAK dihapus.
 */
$pesanAksi = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['aksi'] ?? '') === 'nonaktifkan') {
    $idTarget = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $target   = $idTarget ? get_penghuni_by_id($idTarget) : null;
    $pesanAksi = $target
        ? $target['nama'] . ' tidak dinonaktifkan. Fitur ini akan aktif setelah integrasi backend; saat itu data dan riwayat penghuni tetap disimpan.'
        : 'Data penghuni tidak ditemukan.';
}

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Penghuni</h1>
        <p>Kelola data penghuni dan penyewaan kamar Kos Aron.</p>
    </div>
    <a href="penghuni-form.php" class="btn btn-primary">Tambah Penghuni</a>
</div>

<?php if ($pesanAksi !== null): ?>
    <p class="notice notice--muted" role="status"><?= e($pesanAksi) ?></p>
<?php endif; ?>

<section class="panel">
    <?php if ($totalPenghuni === 0): ?>
        <p class="panel__kosong">Belum ada data penghuni. Gunakan tombol "Tambah Penghuni" untuk menambahkan.</p>
    <?php else: ?>
        <form class="toolbar" role="search" aria-label="Filter data penghuni" data-filter-tabel="tabel-penghuni" data-filter-satuan="penghuni">
            <div class="filter-field filter-field--cari">
                <label for="cari-penghuni">Cari nama penghuni</label>
                <input type="search" id="cari-penghuni" name="cari" class="form-control" placeholder="Contoh: Siti" autocomplete="off">
            </div>
            <div class="filter-field">
                <label for="status-penghuni">Status</label>
                <select id="status-penghuni" name="status" class="form-control">
                    <option value="semua">Semua</option>
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
            </div>
            <p class="filter-info" aria-live="polite" data-filter-info>
                Menampilkan <?= $totalPenghuni ?> dari <?= $totalPenghuni ?> penghuni
            </p>
        </form>

        <div class="tabel-wrap">
            <table class="tabel tabel--lebar" id="tabel-penghuni">
                <thead>
                    <tr>
                        <th scope="col" class="tabel__no">No</th>
                        <th scope="col">Nama Penghuni</th>
                        <th scope="col">Nomor Kamar</th>
                        <th scope="col">Nomor Telepon</th>
                        <th scope="col">Tanggal Mulai Sewa</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarPenghuni as $i => $p): ?>
                        <?php $kamar = get_kamar_by_id($p['kamar_id']); ?>
                        <tr data-baris data-status="<?= e($p['status']) ?>" data-cari="<?= e(mb_strtolower($p['nama'])) ?>">
                            <td class="tabel__no"><?= $i + 1 ?></td>
                            <td class="tabel__utama">
                                <?= e($p['nama']) ?>
                                <span class="tabel__sub"><?= e($p['akun']) ?></span>
                            </td>
                            <td><?= $kamar ? 'Kamar ' . e($kamar['nomor']) : '-' ?></td>
                            <td><?= e($p['no_telepon']) ?></td>
                            <td class="tabel__tanggal"><?= e(format_tanggal($p['tanggal_masuk'])) ?></td>
                            <td>
                                <span class="badge badge--<?= e($p['status']) ?>"><?= e(label_status_penghuni($p['status'])) ?></span>
                            </td>
                            <td>
                                <div class="aksi-grup">
                                    <a href="penghuni-detail.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm"
                                       aria-label="Detail <?= e($p['nama']) ?>">Detail</a>
                                    <a href="penghuni-form.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm"
                                       aria-label="Edit <?= e($p['nama']) ?>">Edit</a>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <form method="post" action="penghuni.php"
                                              data-konfirmasi="Nonaktifkan <?= e($p['nama']) ?>? (Fitur belum aktif, data tidak akan berubah.)">
                                            <input type="hidden" name="aksi" value="nonaktifkan">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-hapus btn-sm" aria-label="Nonaktifkan <?= e($p['nama']) ?>">Nonaktifkan</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="tabel__kosong" data-filter-kosong hidden>
                        <td colspan="7">Tidak ada penghuni yang sesuai dengan pencarian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="teks-catatan">Tambah, edit, dan nonaktifkan penghuni akan aktif setelah integrasi backend (PHP-MySQL). Penghuni yang keluar dinonaktifkan, bukan dihapus, agar riwayatnya tetap tersimpan.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
