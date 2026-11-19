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

$nilai = [
    'nama'          => $penghuni['nama'] ?? '',
    'no_telepon'    => $penghuni['no_telepon'] ?? '',
    'akun'          => $penghuni['akun'] ?? '',
    'kamar_id'      => isset($penghuni['kamar_id']) ? (string) $penghuni['kamar_id'] : '',
    'tanggal_masuk' => $penghuni['tanggal_masuk'] ?? '',
    'status'        => $penghuni['status'] ?? 'aktif',
];

/*
 * PROTOTYPE UI: data yang dikirim diperiksa, tetapi TIDAK disimpan ke mana pun.
 * Nanti (backend): validasi CSRF, INSERT/UPDATE tabel penghuni, buat akun login
 * dengan password_hash(), ubah status kamar, semuanya dalam satu transaksi.
 */
$metode    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$csrfValid = $metode !== 'POST' || csrf_valid($_POST['csrf_token'] ?? null);
$dikirim   = $metode === 'POST' && $csrfValid;
$galat     = [];

if ($dikirim) {
    foreach (array_keys($nilai) as $kunci) {
        $nilai[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }
    if (!in_array($nilai['status'], ['aktif', 'tidak_aktif'], true)) {
        $nilai['status'] = 'aktif';
    }

    if ($nilai['nama'] === '' || mb_strlen($nilai['nama']) > 100) {
        $galat[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $nilai['no_telepon'])) {
        $galat[] = 'Nomor telepon hanya boleh berisi angka, spasi, + atau -, 8 sampai 20 karakter.';
    }
    if (!filter_var($nilai['akun'], FILTER_VALIDATE_EMAIL) && !preg_match('/^[A-Za-z0-9._]{3,30}$/', $nilai['akun'])) {
        $galat[] = 'Isi email yang valid atau username 3-30 karakter (huruf, angka, titik, garis bawah).';
    }
    $kamarDipilih = ctype_digit($nilai['kamar_id']) ? get_kamar_by_id((int) $nilai['kamar_id']) : null;
    if ($kamarDipilih === null) {
        $galat[] = 'Pilih nomor kamar.';
    } elseif ($nilai['status'] === 'aktif') {
        $alasan = alasan_kamar_tidak_tersedia($kamarDipilih['id'], $idPenghuni);
        if ($alasan !== null) {
            $galat[] = 'Kamar ' . $kamarDipilih['nomor'] . ' tidak bisa dipilih untuk penyewaan aktif (' . $alasan . '). Satu kamar hanya boleh memiliki satu penyewaan aktif.';
        }
    }
    if (format_tanggal($nilai['tanggal_masuk']) === '-') {
        $galat[] = 'Tanggal mulai sewa tidak valid.';
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

    <?php if ($dikirim && $galat): ?>
        <div class="notice notice--galat" role="alert">
            <strong>Data belum disimpan. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galat as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($dikirim): ?>
        <p class="notice notice--peringatan" role="status">
            <strong>Isian valid, tetapi data belum disimpan.</strong> Penyimpanan data penghuni akan aktif setelah integrasi backend.
        </p>
    <?php elseif ($metode === 'POST' && !$csrfValid): ?>
        <p class="notice notice--galat" role="alert">Sesi formulir sudah tidak valid. Muat ulang halaman ini dan coba lagi.</p>
    <?php else: ?>
        <p class="notice">Form ini masih prototype tampilan. Penyimpanan data akan aktif setelah integrasi backend.</p>
    <?php endif; ?>

    <form method="post" action="<?= e($aksiForm) ?>" class="panel form-panel" data-form-penghuni>
        <?= csrf_field() ?>
        <div class="form-grid form-grid--2">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" maxlength="100" required
                       autocomplete="off" value="<?= e($nilai['nama']) ?>">
            </div>

            <div class="form-group">
                <label for="no_telepon">Nomor Telepon</label>
                <input type="tel" id="no_telepon" name="no_telepon" class="form-control" required
                       pattern="[0-9+\-\s]{8,20}" inputmode="tel" autocomplete="off"
                       value="<?= e($nilai['no_telepon']) ?>" placeholder="Contoh: 08xx-xxxx-xxxx">
            </div>

            <div class="form-group">
                <label for="akun">Email atau Username</label>
                <input type="text" id="akun" name="akun" class="form-control" required autocomplete="off"
                       aria-describedby="akun-hint" value="<?= e($nilai['akun']) ?>">
                <span id="akun-hint" class="teks-catatan">Dipakai penghuni untuk login.</span>
            </div>

            <div class="form-group">
                <label for="kamar_id">Nomor Kamar</label>
                <select id="kamar_id" name="kamar_id" class="form-control" required aria-describedby="kamar-hint">
                    <option value="">Pilih kamar</option>
                    <?php foreach (get_semua_kamar() as $k): ?>
                        <?php
                        $alasan     = alasan_kamar_tidak_tersedia($k['id'], $idPenghuni);
                        $milikSendiri = $penghuni && $penghuni['kamar_id'] === $k['id'];
                        $keterangan = $alasan !== null ? 'terisi, ' . $alasan : ($milikSendiri ? 'kamar saat ini' : 'kosong');
                        $nonaktif   = $alasan !== null && $nilai['status'] === 'aktif';
                        ?>
                        <option value="<?= (int) $k['id'] ?>"
                                <?= $alasan !== null ? 'data-terisi' : '' ?>
                                <?= $nilai['kamar_id'] === (string) $k['id'] ? 'selected' : '' ?>
                                <?= $nonaktif ? 'disabled' : '' ?>>
                            Kamar <?= e($k['nomor']) ?> (<?= e($keterangan) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span id="kamar-hint" class="teks-catatan">Kamar yang sudah terisi tidak dapat dipilih untuk penyewaan aktif.</span>
            </div>

            <div class="form-group">
                <label for="tanggal_masuk">Tanggal Mulai Sewa</label>
                <input type="date" id="tanggal_masuk" name="tanggal_masuk" class="form-control" required
                       value="<?= e($nilai['tanggal_masuk']) ?>">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="aktif"<?= $nilai['status'] === 'aktif' ? ' selected' : '' ?>>Aktif</option>
                    <option value="tidak_aktif"<?= $nilai['status'] === 'tidak_aktif' ? ' selected' : '' ?>>Tidak Aktif</option>
                </select>
            </div>
        </div>

        <p class="form-info">
            Akun login penghuni dibuat oleh admin melalui backend. Password tidak ditampilkan
            dan tidak disimpan di halaman ini.
        </p>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="penghuni.php" class="btn btn-outline">Batal</a>
        </div>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
