<?php
require_once __DIR__ . '/includes/konteks.php';

$judulHalaman = 'Profil';
$menuAktif = 'profil';
$nilaiProfil = [
    'nama' => $penghuniLogin['nama'] ?? '',
    'no_telepon' => $penghuniLogin['no_telepon'] ?? '',
    'akun' => $penghuniLogin['akun'] ?? '',
];
$galatProfil = [];
$galatPassword = [];
$pesan = $_SESSION['pesan_profil'] ?? null;
unset($_SESSION['pesan_profil']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $ubahPassword = $aksi === 'ubah_password';
    $kolom = $ubahPassword ? ['password_lama', 'password_baru', 'konfirmasi_password'] : array_keys($nilaiProfil);
    if (!$penghuniLogin || !in_array($aksi, ['simpan_profil', 'ubah_password'], true)
        || array_diff(array_keys($_POST), [...$kolom, 'aksi', 'csrf_token'])) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    try {
        if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            http_response_code(403);
            throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        foreach ($kolom as $key) {
            if (!is_string($_POST[$key] ?? null)) {
                throw new DomainException('Lengkapi isian formulir.');
            }
        }
        if ($ubahPassword) {
            ubah_password_sendiri($_POST['password_lama'], $_POST['password_baru'], $_POST['konfirmasi_password']);
            $_SESSION['pesan_profil'] = 'Password berhasil diubah.';
        } else {
            foreach ($kolom as $key) {
                $nilaiProfil[$key] = trim($_POST[$key]);
            }
            ubah_profil_sendiri($nilaiProfil['nama'], $nilaiProfil['no_telepon'], $nilaiProfil['akun']);
            $_SESSION['pesan_profil'] = 'Perubahan profil tersimpan.';
        }
        header('Location: profil.php', true, 303);
        exit;
    } catch (DomainException | PDOException $e) {
        $pesanGalat = $e instanceof DomainException ? $e->getMessage() : 'Perubahan gagal disimpan. Coba lagi.';
        if ($ubahPassword) {
            $galatPassword[] = $pesanGalat;
        } else {
            $galatProfil[] = $pesanGalat;
        }
    }
}
require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head">
    <h1>Profil Saya</h1>
    <p>Informasi akun dan pengaturan profil Anda.</p>
</div>

<?php if ($pesan): ?><p class="notice notice--berhasil" role="status"><?= e($pesan) ?></p><?php endif; ?>
<?php if ($penghuniLogin === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada data penghuni</strong>
        Tidak ada informasi profil yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <!-- 2. Informasi Akun -->
    <section class="panel">
        <div class="panel__head">
            <h2>Informasi Akun</h2>
        </div>
        <dl class="info-tabel info-tabel--panel">
            <div>
                <dt>Nama Lengkap</dt>
                <dd><?= e($penghuniLogin['nama']) ?></dd>
            </div>
            <div>
                <dt>Email / Username</dt>
                <dd><?= e($penghuniLogin['akun']) ?></dd>
            </div>
            <div>
                <dt>Nomor Telepon</dt>
                <dd><?= e($penghuniLogin['no_telepon']) ?></dd>
            </div>
            <div>
                <dt>Nomor Kamar</dt>
                <dd><?= $penghuniLogin['nomor'] !== null ? 'Kamar ' . e($penghuniLogin['nomor']) : '-' ?></dd>
            </div>
            <div>
                <dt>Tanggal Mulai Sewa</dt>
                <dd><?= e(format_tanggal($penghuniLogin['tanggal_masuk'])) ?></dd>
            </div>
        </dl>
    </section>

    <!-- 3. Edit Profil -->
    <?php if ($galatProfil): ?>
        <div class="notice notice--galat mt-section" role="alert">
            <strong>Profil belum disimpan. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galatProfil as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="profil.php" class="panel form-panel form-panel--berkepala mt-section">
        <div class="panel__head">
            <h2>Edit Profil</h2>
        </div>
        <div class="form-panel__body">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan_profil">

            <div class="form-grid form-grid--2">
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" maxlength="100" required
                           autocomplete="name" value="<?= e($nilaiProfil['nama']) ?>">
                </div>

                <div class="form-group">
                    <label for="no_telepon">Nomor Telepon</label>
                    <input type="tel" id="no_telepon" name="no_telepon" class="form-control" required
                           pattern="[0-9+\-\s]{8,20}" inputmode="tel" autocomplete="tel"
                           value="<?= e($nilaiProfil['no_telepon']) ?>" placeholder="Contoh: 08xx-xxxx-xxxx">
                </div>

                <div class="form-group form-grid__penuh">
                    <label for="akun">Email / Username</label>
                    <input type="text" id="akun" name="akun" class="form-control" required maxlength="50" autocomplete="username"
                           aria-describedby="akun-hint" value="<?= e($nilaiProfil['akun']) ?>">
                    <span id="akun-hint" class="teks-catatan">Dipakai untuk login. Boleh email atau username.</span>
                </div>
            </div>

            <div class="form-aksi">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </div>
    </form>

    <!-- 4. Ubah Password -->
    <?php if ($galatPassword): ?>
        <div class="notice notice--galat mt-section" role="alert">
            <strong>Password belum diubah. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galatPassword as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="profil.php" class="panel form-panel form-panel--berkepala mt-section" data-form-password autocomplete="off">
        <div class="panel__head">
            <h2>Ubah Password</h2>
        </div>
        <div class="form-panel__body">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="ubah_password">

            <div class="form-grid">
                <div class="form-group">
                    <label for="password_lama">Password Lama</label>
                    <div class="input-password">
                        <input type="password" id="password_lama" name="password_lama" class="form-control"
                               autocomplete="current-password" required>
                        <button type="button" class="input-password__toggle" aria-controls="password_lama" data-toggle-password>Tampilkan</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_baru">Password Baru</label>
                    <div class="input-password">
                        <input type="password" id="password_baru" name="password_baru" class="form-control"
                               autocomplete="new-password" minlength="8" required aria-describedby="password-baru-hint">
                        <button type="button" class="input-password__toggle" aria-controls="password_baru" data-toggle-password>Tampilkan</button>
                    </div>
                    <span id="password-baru-hint" class="teks-catatan">Minimal 8 karakter.</span>
                </div>

                <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                    <div class="input-password">
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control"
                               autocomplete="new-password" minlength="8" required>
                        <button type="button" class="input-password__toggle" aria-controls="konfirmasi_password" data-toggle-password>Tampilkan</button>
                    </div>
                    <span class="teks-catatan teks-galat" data-peringatan-konfirmasi hidden></span>
                </div>
            </div>

            <div class="form-aksi">
                <button type="submit" class="btn btn-primary">Ubah Password</button>
            </div>
        </div>
    </form>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
