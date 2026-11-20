<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/penghuni.php'; // juga memuat data kamar

$menuAktif = 'penghuni';

// Mode edit jika ada ?id=, selain itu mode tambah.
$modeEdit = isset($_GET['id']);
$penghuni = null;
if ($modeEdit) {
    $id       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $penghuni = $id ? get_penghuni_by_id($id) : null;
    if ($penghuni === null) {
        http_response_code(404);
    }
}
$idPenghuni = $penghuni['id'] ?? null;

$aktif = ($penghuni['status'] ?? '') === 'aktif';
$nilai = [
    'nama' => $penghuni['nama'] ?? '',
    'no_telepon' => $penghuni['no_telepon'] ?? '',
    'akun' => $penghuni['akun'] ?? '',
    'sewa_aktif' => $aktif ? (string)$penghuni['penyewaan_id'] : '',
    'mulai_sewa' => $modeEdit ? '' : '1',
    'kamar_id' => '',
    'tanggal_masuk' => tanggal_hari_ini(),
    'harga_sewa_disepakati' => '',
    'hari_jatuh_tempo' => '',
];
$galat = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (!$modeEdit || $penghuni)) {
    foreach ($nilai as $key => $awal) {
        $nilai[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $galat[] = 'Sesi formulir sudah tidak valid. Muat ulang halaman.';
    } else {
        try {
            $idDisimpan = simpan_penghuni($idPenghuni, $nilai, is_string($_POST['password'] ?? null) ? $_POST['password'] : '', pengguna_login()['id']);
            $_SESSION['pesan_penghuni'] = 'Data penghuni berhasil disimpan.';
            header('Location: penghuni-detail.php?id=' . $idDisimpan, true, 303);
            exit;
        } catch (DomainException $e) {
            $galat[] = $e->getMessage();
        } catch (PDOException $e) {
            $galat[] = 'Penyimpanan gagal. Muat ulang formulir dan coba lagi.';
        }
    }
}

$judulHalaman = $modeEdit ? ($penghuni ? 'Edit Penghuni' : 'Penghuni Tidak Ditemukan') : 'Tambah Penghuni';
$aksiForm     = $penghuni ? 'penghuni-form.php?id=' . (int) $penghuni['id'] : 'penghuni-form.php';

require __DIR__ . '/includes/header.php';
?>

<nav aria-label="Breadcrumb">
    <ol class="breadcrumb">
        <li><a href="penghuni.php">Data Penghuni</a></li>
        <li aria-current="page"><?= e($modeEdit ? 'Edit Penghuni' : 'Tambah Penghuni') ?></li>
    </ol>
</nav>

<?php if ($modeEdit && $penghuni === null): ?>
    <div class="admin-placeholder">
        <strong>Penghuni tidak ditemukan</strong>
        <p>Data penghuni yang ingin diedit tidak tersedia.</p>
        <a href="penghuni.php" class="btn btn-primary">Kembali ke Data Penghuni</a>
    </div>
<?php else: ?>
    <div class="admin-page-head">
        <h1><?= e($judulHalaman) ?></h1>
        <p><?= $modeEdit ? 'Ubah data ' . e($penghuni['nama']) . '.' : 'Daftarkan penghuni baru secara manual.' ?></p>
    </div>

    <?php if ($galat): ?>
        <div class="notice notice--galat" role="alert">
            <strong>Data belum disimpan. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galat as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($aksiForm) ?>" class="panel form-panel">
        <?= csrf_field() ?>
        <input type="hidden" name="sewa_aktif" value="<?= e($nilai['sewa_aktif']) ?>">
        <div class="form-grid form-grid--2">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" maxlength="100" required
                       autocomplete="off" value="<?= e($nilai['nama']) ?>">
            </div>

            <div class="form-group">
                <label for="no_telepon">Nomor Telepon</label>
                <input type="tel" id="no_telepon" name="no_telepon" class="form-control" required
                       maxlength="20" inputmode="tel" autocomplete="off"
                       value="<?= e($nilai['no_telepon']) ?>" placeholder="Contoh: 08xx-xxxx-xxxx">
            </div>

            <div class="form-group">
                <label for="akun">Email atau Username</label>
                <input type="text" id="akun" name="akun" class="form-control" maxlength="50" <?= !$modeEdit || $penghuni['user_id'] ? 'required' : '' ?> autocomplete="off"
                       aria-describedby="akun-hint" value="<?= e($nilai['akun']) ?>">
                <span id="akun-hint" class="teks-catatan">Dipakai penghuni untuk login.</span>
            </div>

            <div class="form-group">
                <label for="password"><?= $penghuni && $penghuni['user_id'] ? 'Password baru (opsional)' : 'Password akun penghuni' ?></label>
                <input type="password" name="password" id="password" class="form-control" autocomplete="new-password"
                       minlength="8" <?= $modeEdit ? '' : 'required' ?>>
                <span class="teks-catatan">Isi saat membuat akun atau mengganti password.</span>
            </div>
        </div>
        <?php if ($aktif): ?>
            <p class="form-info">Kamar <?= e($penghuni['nomor']) ?> sejak <?= e(format_tanggal($penghuni['tanggal_masuk'])) ?>.
                Untuk pindah kamar atau memulai perjanjian baru, akhiri sewa saat ini melalui Data Penghuni.</p>
        <?php else: ?>
            <?php if ($modeEdit): ?>
                <label><input type="checkbox" name="mulai_sewa" value="1" <?= $nilai['mulai_sewa'] === '1' ? 'checked' : '' ?>>
                    Mulai penyewaan baru untuk penghuni ini</label>
                <p class="teks-catatan">Isi bagian sewa dan akun jika memulai penyewaan baru.</p>
            <?php endif; ?>
            <div class="form-grid form-grid--2">
                <div class="form-group">
                    <label for="kamar_id">Nomor Kamar</label>
                    <select id="kamar_id" name="kamar_id" class="form-control" <?= $modeEdit ? '' : 'required' ?>>
                        <option value="">Pilih kamar</option>
                        <?php foreach (get_semua_kamar() as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" <?= !$k['dapat_dipesan'] ? 'disabled' : '' ?>
                                <?= $nilai['kamar_id'] === (string)$k['id'] ? 'selected' : '' ?>>
                                Kamar <?= e($k['nomor']) ?> (<?= e(label_status($k['ketersediaan'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tanggal_masuk">Tanggal Mulai Sewa</label>
                    <input type="date" id="tanggal_masuk" name="tanggal_masuk" class="form-control"
                           max="<?= e(tanggal_hari_ini()) ?>" value="<?= e($nilai['tanggal_masuk']) ?>" <?= $modeEdit ? '' : 'required' ?>>
                </div>
                <div class="form-group">
                    <label for="harga">Harga Sewa Disepakati per Bulan (Rp)</label>
                    <input type="number" id="harga" name="harga_sewa_disepakati" class="form-control" min="0" max="9999999999.99" step="0.01"
                           value="<?= e($nilai['harga_sewa_disepakati']) ?>" <?= $modeEdit ? '' : 'required' ?>>
                </div>
                <div class="form-group">
                    <label for="hari">Hari Jatuh Tempo Setiap Bulan</label>
                    <input type="number" id="hari" name="hari_jatuh_tempo" class="form-control" min="1" max="31"
                           value="<?= e($nilai['hari_jatuh_tempo']) ?>" <?= $modeEdit ? '' : 'required' ?>>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="penghuni.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
