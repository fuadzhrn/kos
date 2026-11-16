<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/kamar.php';

$menuAktif = 'kamar';

// Mode edit jika ada ?id=, selain itu mode tambah.
$modeEdit = isset($_GET['id']);
$kamar    = null;
if ($modeEdit) {
    $id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $kamar = $id ? get_kamar_by_id($id) : null;
    if ($kamar === null) {
        http_response_code(404);
    }
}

/*
 * Penyimpanan BELUM aktif: data yang dikirim tidak disimpan ke mana pun.
 * Isian dikembalikan ke form agar tidak hilang, disertai pesan yang jelas.
 * Nanti: validasi CSRF & input, upload foto, lalu INSERT / UPDATE ke tabel kamar.
 */
$dikirim = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$nilai = [
    'nomor'     => $kamar['nomor'] ?? '',
    'harga'     => isset($kamar['harga']) ? (string) $kamar['harga'] : '',
    'deskripsi' => $kamar['deskripsi'] ?? '',
    'fasilitas' => isset($kamar['fasilitas']) ? implode("\n", $kamar['fasilitas']) : '',
    'status'    => $kamar['status'] ?? 'kosong',
];
if ($dikirim) {
    foreach (['nomor', 'harga', 'deskripsi', 'fasilitas'] as $kunci) {
        $nilai[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }
    $nilai['status'] = in_array($_POST['status'] ?? '', ['kosong', 'terisi'], true) ? $_POST['status'] : 'kosong';
}

$judulHalaman = $modeEdit ? ($kamar ? 'Edit Kamar ' . $kamar['nomor'] : 'Kamar Tidak Ditemukan') : 'Tambah Kamar';
$aksiForm     = $modeEdit && $kamar ? 'kamar-form.php?id=' . (int) $kamar['id'] : 'kamar-form.php';

require __DIR__ . '/includes/header.php';
?>

<nav aria-label="Breadcrumb">
    <ol class="breadcrumb">
        <li><a href="kamar.php">Data Kamar</a></li>
        <li aria-current="page"><?= e($modeEdit ? 'Edit Kamar' : 'Tambah Kamar') ?></li>
    </ol>
</nav>

<?php if ($modeEdit && $kamar === null): ?>
    <div class="admin-placeholder">
        <strong>Kamar tidak ditemukan</strong>
        <p>Data kamar yang ingin diedit tidak tersedia.</p>
        <a href="kamar.php" class="btn btn-primary">Kembali ke Data Kamar</a>
    </div>
<?php else: ?>
    <div class="admin-page-head">
        <h1><?= e($judulHalaman) ?></h1>
        <p><?= $modeEdit ? 'Ubah informasi kamar.' : 'Isi informasi kamar baru.' ?></p>
    </div>

    <?php if ($dikirim): ?>
        <p class="notice notice--peringatan" role="status">
            <strong>Data belum disimpan.</strong> Penyimpanan data kamar akan aktif setelah integrasi backend.
            Isian Anda tetap ditampilkan di bawah<?= !empty($_FILES['foto']['name']) ? ', tetapi file foto perlu dipilih ulang' : '' ?>.
        </p>
    <?php else: ?>
        <p class="notice">Form ini masih tampilan. Penyimpanan data akan aktif setelah integrasi backend.</p>
    <?php endif; ?>

    <form method="post" action="<?= e($aksiForm) ?>" enctype="multipart/form-data" class="panel form-panel">
        <div class="form-grid">
            <div class="form-group">
                <label for="nomor">Nomor Kamar</label>
                <input type="text" id="nomor" name="nomor" class="form-control" maxlength="10" required
                       value="<?= e($nilai['nomor']) ?>" placeholder="Contoh: 07">
            </div>

            <div class="form-group">
                <label for="harga">Harga Sewa Bulanan (Rp)</label>
                <input type="number" id="harga" name="harga" class="form-control" min="1" step="1" required
                       inputmode="numeric" value="<?= e($nilai['harga']) ?>" placeholder="Contoh: 500000">
            </div>

            <div class="form-group">
                <label for="status">Status Kamar</label>
                <select id="status" name="status" class="form-control">
                    <option value="kosong"<?= $nilai['status'] === 'kosong' ? ' selected' : '' ?>>Kosong</option>
                    <option value="terisi"<?= $nilai['status'] === 'terisi' ? ' selected' : '' ?>>Terisi</option>
                </select>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3"><?= e($nilai['deskripsi']) ?></textarea>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="fasilitas">Fasilitas</label>
                <textarea id="fasilitas" name="fasilitas" class="form-control" rows="4"
                          aria-describedby="fasilitas-hint"><?= e($nilai['fasilitas']) ?></textarea>
                <span id="fasilitas-hint" class="teks-catatan">Tulis satu fasilitas per baris.</span>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="foto">Foto Kamar</label>
                <?php if ($modeEdit && $kamar): ?>
                    <div class="foto-sekarang">
                        <img src="../<?= e(url_foto($kamar['foto'])) ?>" alt="Foto Kamar <?= e($kamar['nomor']) ?> saat ini"
                             data-fallback="../<?= e(FOTO_PLACEHOLDER) ?>">
                        <span class="teks-catatan">Foto saat ini. Kosongkan pilihan file jika tidak ingin mengganti.</span>
                    </div>
                <?php endif; ?>
                <input type="file" id="foto" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp"
                       aria-describedby="foto-hint">
                <span id="foto-hint" class="teks-catatan">Format JPG, PNG, atau WebP.</span>
            </div>
        </div>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="kamar.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
