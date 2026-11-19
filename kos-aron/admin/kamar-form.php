<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/kamar.php';
require_once __DIR__ . '/../includes/upload.php';

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

$metode    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$csrfValid = $metode !== 'POST' || csrf_valid($_POST['csrf_token'] ?? null);
$dikirim   = $metode === 'POST' && $csrfValid && !($modeEdit && $kamar === null);

$nilai = [
    'nomor'     => $kamar['nomor'] ?? '',
    'harga'     => isset($kamar['harga']) ? (string) $kamar['harga'] : '',
    'deskripsi' => $kamar['deskripsi'] ?? '',
    'fasilitas' => isset($kamar['fasilitas']) ? implode("\n", $kamar['fasilitas']) : '',
    'kondisi'   => $kamar['kondisi'] ?? 'aktif',
];
$galat = [];

if ($dikirim) {
    foreach (['nomor', 'harga', 'deskripsi', 'fasilitas'] as $kunci) {
        $nilai[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }
    $nilai['kondisi'] = in_array($_POST['kondisi'] ?? '', ['aktif', 'perawatan'], true) ? $_POST['kondisi'] : 'aktif';

    // --- Validasi server (tidak pernah percaya validasi HTML/JS saja) ---
    if ($nilai['nomor'] === '' || mb_strlen($nilai['nomor']) > 10) {
        $galat[] = 'Nomor kamar wajib diisi (maksimal 10 karakter).';
    } elseif (nomor_kamar_terpakai($nilai['nomor'], $kamar['id'] ?? null)) {
        $galat[] = 'Nomor kamar "' . $nilai['nomor'] . '" sudah dipakai kamar lain.';
    }

    if (!ctype_digit($nilai['harga']) || (int) $nilai['harga'] < 1 || (int) $nilai['harga'] > 100000000) {
        $galat[] = 'Harga sewa harus berupa angka lebih dari 0 (maksimal Rp100.000.000).';
    }

    if (mb_strlen($nilai['deskripsi']) > 1000) {
        $galat[] = 'Deskripsi maksimal 1000 karakter.';
    }

    $daftarFasilitas = array_values(array_filter(array_map(
        fn ($baris) => mb_substr(trim($baris), 0, 50),
        preg_split('/\r\n|\r|\n/', $nilai['fasilitas'])
    ), fn ($f) => $f !== ''));
    if (count($daftarFasilitas) > 20) {
        $galat[] = 'Maksimal 20 fasilitas.';
    }

    // Kamar yang sedang ditempati (penyewaan aktif) tidak boleh diubah
    // menjadi kondisi 'perawatan' tanpa mengeluarkan penghuni terlebih
    // dahulu -- ini bukan backend penghuni, jadi cukup ditolak di sini.
    if ($modeEdit && $kamar !== null && $nilai['kondisi'] === 'perawatan' && $kamar['status'] === 'terisi') {
        $galat[] = 'Kamar ini sedang ditempati (penyewaan aktif) dan tidak dapat diubah ke kondisi Perawatan.';
    }

    $unggahan = simpan_foto_upload($_FILES['foto'] ?? [], PATH_FOTO_KAMAR);
    if (!$unggahan['berhasil']) {
        $galat[] = $unggahan['pesan'];
    }

    if (empty($galat)) {
        $dataSimpan = [
            'nomor'     => $nilai['nomor'],
            'harga'     => (int) $nilai['harga'],
            'deskripsi' => $nilai['deskripsi'],
            'fasilitas' => $daftarFasilitas,
            'kondisi'   => $nilai['kondisi'],
        ];

        if ($unggahan['ada_file'] && $unggahan['nama_file'] !== null) {
            $dataSimpan['foto_utama'] = $unggahan['nama_file'];
        }

        try {
            if ($modeEdit && $kamar !== null) {
                perbarui_kamar($kamar['id'], $dataSimpan);
                if ($unggahan['ada_file'] && $unggahan['nama_file'] !== null) {
                    hapus_foto_lama($kamar['foto'], PATH_FOTO_KAMAR);
                }
            } else {
                $dataSimpan['foto_utama'] = $unggahan['nama_file'] ?? null;
                tambah_kamar($dataSimpan);
            }

            header('Location: kamar.php?disimpan=1');
            exit;
        } catch (DomainException $e) {
            $galat[] = $e->getMessage();
        } catch (PDOException $e) {
            $galat[] = 'Kamar gagal disimpan. Periksa nomor kamar dan coba lagi.';
        }
    }

    // Gagal validasi: hapus foto yang SUDAH terunggah ke disk (tidak jadi dipakai)
    // supaya tidak ada file yatim menumpuk di folder upload.
    if ($unggahan['ada_file'] && $unggahan['nama_file'] !== null) {
        hapus_foto_lama($unggahan['nama_file'], PATH_FOTO_KAMAR);
    }
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

    <?php if ($dikirim && $galat): ?>
        <div class="notice notice--galat" role="alert">
            <strong>Kamar belum disimpan. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galat as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($metode === 'POST' && !$csrfValid): ?>
        <p class="notice notice--galat" role="alert">Sesi formulir sudah tidak valid. Muat ulang halaman ini dan coba lagi.</p>
    <?php endif; ?>

    <form method="post" action="<?= e($aksiForm) ?>" enctype="multipart/form-data" class="panel form-panel">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="nomor">Nomor Kamar</label>
                <input type="text" id="nomor" name="nomor" class="form-control" maxlength="10" required
                       value="<?= e($nilai['nomor']) ?>" placeholder="Contoh: 07">
            </div>

            <div class="form-group">
                <label for="harga">Harga Sewa Bulanan (Rp)</label>
                <input type="number" id="harga" name="harga" class="form-control" min="1" max="100000000" step="1" required
                       inputmode="numeric" value="<?= e($nilai['harga']) ?>" placeholder="Contoh: 500000">
            </div>

            <div class="form-group">
                <label for="kondisi">Kondisi Operasional</label>
                <select id="kondisi" name="kondisi" class="form-control" aria-describedby="kondisi-hint">
                    <option value="aktif"<?= $nilai['kondisi'] === 'aktif' ? ' selected' : '' ?>>Aktif</option>
                    <option value="perawatan"<?= $nilai['kondisi'] === 'perawatan' ? ' selected' : '' ?>>Perawatan</option>
                </select>
                <span id="kondisi-hint" class="teks-catatan">
                    Kamar berkondisi "Perawatan" tidak ditampilkan di website publik dan tidak dapat disewakan.
                    Status Kosong/Terisi mengikuti data penyewaan secara otomatis, bukan diatur di sini.
                </span>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" maxlength="1000"><?= e($nilai['deskripsi']) ?></textarea>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="fasilitas">Fasilitas</label>
                <textarea id="fasilitas" name="fasilitas" class="form-control" rows="4"
                          aria-describedby="fasilitas-hint"><?= e($nilai['fasilitas']) ?></textarea>
                <span id="fasilitas-hint" class="teks-catatan">Tulis satu fasilitas per baris (maksimal 20 baris).</span>
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
                <span id="foto-hint" class="teks-catatan">Format JPG, PNG, atau WebP, maksimal 2 MB. Boleh dikosongkan.</span>
            </div>
        </div>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="kamar.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
