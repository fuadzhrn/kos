<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/kamar.php';

$judulHalaman = 'Data Kamar';
$menuAktif    = 'kamar';

$daftarKamar = get_semua_kamar();
$totalKamar  = count($daftarKamar);

/*
 * Hapus kamar: BELUM menghapus apa pun.
 * Permintaan hapus hanya dijawab dengan pesan bahwa fitur aktif setelah integrasi backend.
 * Nanti: validasi CSRF, cek kamar tidak sedang ditempati, lalu DELETE FROM kamar WHERE id = ?
 */
$pesanAksi = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $idHapus    = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $kamarHapus = $idHapus ? get_kamar_by_id($idHapus) : null;
    $pesanAksi  = $kamarHapus
        ? 'Kamar ' . $kamarHapus['nomor'] . ' tidak dihapus. Fitur hapus akan aktif setelah integrasi backend.'
        : 'Data kamar tidak ditemukan.';
}

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Kamar</h1>
        <p>Kelola informasi dan status kamar Kos Aron.</p>
    </div>
    <a href="kamar-form.php" class="btn btn-primary">Tambah Kamar</a>
</div>

<?php if ($pesanAksi !== null): ?>
    <p class="notice notice--muted" role="status"><?= e($pesanAksi) ?></p>
<?php endif; ?>

<section class="panel">
    <?php if ($totalKamar === 0): ?>
        <p class="panel__kosong">Belum ada data kamar. Gunakan tombol "Tambah Kamar" untuk menambahkan.</p>
    <?php else: ?>
        <form class="toolbar" role="search" aria-label="Filter data kamar" data-filter-tabel="tabel-kamar">
            <div class="filter-field filter-field--cari">
                <label for="cari-kamar">Cari nomor kamar</label>
                <input type="search" id="cari-kamar" name="cari" class="form-control" placeholder="Contoh: 01" autocomplete="off">
            </div>
            <div class="filter-field">
                <label for="status-kamar">Status</label>
                <select id="status-kamar" name="status" class="form-control">
                    <option value="semua">Semua</option>
                    <option value="kosong">Kosong</option>
                    <option value="terisi">Terisi</option>
                </select>
            </div>
            <p class="filter-info" aria-live="polite" data-filter-info>
                Menampilkan <?= $totalKamar ?> dari <?= $totalKamar ?> kamar
            </p>
        </form>

        <div class="tabel-wrap">
            <table class="tabel tabel--lebar" id="tabel-kamar">
                <thead>
                    <tr>
                        <th scope="col" class="tabel__no">No</th>
                        <th scope="col">Foto</th>
                        <th scope="col">Nomor Kamar</th>
                        <th scope="col" class="tabel__angka">Harga Sewa</th>
                        <th scope="col">Fasilitas</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarKamar as $i => $kamar): ?>
                        <?php
                        $nama      = 'Kamar ' . $kamar['nomor'];
                        // Kata kunci pencarian, contoh: "kamar 05 kamar 5" (cocok untuk "05", "5", "kamar 5")
                        $kataKunci = strtolower($nama . ' kamar ' . ltrim((string) $kamar['nomor'], '0'));
                        ?>
                        <tr data-status="<?= e($kamar['status']) ?>" data-cari="<?= e($kataKunci) ?>">
                            <td class="tabel__no"><?= $i + 1 ?></td>
                            <td>
                                <img class="tabel__foto" src="../<?= e(url_foto($kamar['foto'])) ?>"
                                     alt="Foto <?= e($nama) ?>" loading="lazy" data-fallback="../<?= e(FOTO_PLACEHOLDER) ?>">
                            </td>
                            <td class="tabel__utama"><?= e($nama) ?></td>
                            <td class="tabel__angka"><?= e(format_rupiah($kamar['harga'])) ?></td>
                            <td class="tabel__fasilitas"><?= e(implode(', ', $kamar['fasilitas'])) ?></td>
                            <td>
                                <span class="badge badge--<?= e($kamar['status']) ?>"><?= e(ucfirst($kamar['status'])) ?></span>
                            </td>
                            <td>
                                <div class="aksi-grup">
                                    <a href="kamar-form.php?id=<?= (int) $kamar['id'] ?>" class="btn btn-outline btn-sm"
                                       aria-label="Edit <?= e($nama) ?>">Edit</a>
                                    <form method="post" action="kamar.php" data-konfirmasi="Hapus <?= e($nama) ?>? (Fitur hapus belum aktif, data tidak akan berubah.)">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $kamar['id'] ?>">
                                        <button type="submit" class="btn btn-hapus btn-sm" aria-label="Hapus <?= e($nama) ?>">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="tabel__kosong" data-filter-kosong hidden>
                        <td colspan="7">Tidak ada kamar yang sesuai dengan pencarian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="teks-catatan">Tambah, edit, dan hapus kamar akan aktif setelah integrasi backend (PHP-MySQL).</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
