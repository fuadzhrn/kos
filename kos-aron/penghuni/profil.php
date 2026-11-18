<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Profil';
$menuAktif    = 'profil';

require __DIR__ . '/includes/header.php';

// $penghuniLogin sudah disiapkan oleh includes/header.php (lihat get_penghuni_login()).
$kamarSaya = $penghuniLogin ? get_kamar_by_id($penghuniLogin['kamar_id']) : null;

/*
 * PROTOTYPE UI: TIDAK ADA penyimpanan nyata di kedua form berikut.
 * data/penghuni.php tidak diubah oleh file ini. Nanti (backend): validasi
 * CSRF, UPDATE kolom nama/no_telepon/akun untuk profil; untuk password,
 * verifikasi password lama dengan password_verify() lalu simpan hash baru
 * dengan password_hash() -- TIDAK PERNAH sebagai teks biasa.
 *
 * Batasan yang sengaja TIDAK disediakan di halaman ini (sesuai aturan):
 * nomor kamar, harga sewa, status pembayaran, dan role akun semuanya
 * hanya bisa diubah dari sisi Admin, bukan oleh penghuni sendiri.
 */
$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$aksi   = $metode === 'POST' ? ($_POST['aksi'] ?? '') : '';

// --- Form Edit Profil: default-nya tampilkan data saat ini. ---
$nilaiProfil = $penghuniLogin
    ? ['nama' => $penghuniLogin['nama'], 'no_telepon' => $penghuniLogin['no_telepon'], 'akun' => $penghuniLogin['akun']]
    : ['nama' => '', 'no_telepon' => '', 'akun' => ''];
$galatProfil   = [];
$dikirimProfil = false;

if ($aksi === 'simpan_profil' && $penghuniLogin !== null) {
    $dikirimProfil = true;
    foreach (array_keys($nilaiProfil) as $kunci) {
        $nilaiProfil[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }

    if ($nilaiProfil['nama'] === '' || mb_strlen($nilaiProfil['nama']) > 100) {
        $galatProfil[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $nilaiProfil['no_telepon'])) {
        $galatProfil[] = 'Nomor telepon hanya boleh berisi angka, spasi, + atau -, 8 sampai 20 karakter.';
    }
    if (!filter_var($nilaiProfil['akun'], FILTER_VALIDATE_EMAIL) && !preg_match('/^[A-Za-z0-9._]{3,30}$/', $nilaiProfil['akun'])) {
        $galatProfil[] = 'Isi email yang valid atau username 3-30 karakter (huruf, angka, titik, garis bawah).';
    }
}

// --- Form Ubah Password ---
// Nilai yang diketik TIDAK PERNAH disimpan ke variabel lain, TIDAK ditulis ke
// log, dan TIDAK dikembalikan ke form (field password selalu dikosongkan lagi).
$galatPassword   = [];
$dikirimPassword = false;

if ($aksi === 'ubah_password' && $penghuniLogin !== null) {
    $dikirimPassword = true;
    $passwordLama      = (string) ($_POST['password_lama'] ?? '');
    $passwordBaru      = (string) ($_POST['password_baru'] ?? '');
    $konfirmasiPassword = (string) ($_POST['konfirmasi_password'] ?? '');

    if ($passwordLama === '') {
        $galatPassword[] = 'Password lama wajib diisi.';
    }
    if (mb_strlen($passwordBaru) < 8) {
        $galatPassword[] = 'Password baru minimal 8 karakter.';
    }
    if ($passwordBaru !== $konfirmasiPassword) {
        $galatPassword[] = 'Konfirmasi password baru tidak sama dengan password baru.';
    }

    unset($passwordLama, $passwordBaru, $konfirmasiPassword);
}
?>

<div class="admin-page-head">
    <h1>Profil Saya</h1>
    <p>Informasi akun dan pengaturan profil Anda.</p>
</div>

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
                <dd><?= $kamarSaya ? 'Kamar ' . e($kamarSaya['nomor']) : '-' ?></dd>
            </div>
            <div>
                <dt>Tanggal Mulai Sewa</dt>
                <dd><?= e(format_tanggal($penghuniLogin['tanggal_masuk'])) ?></dd>
            </div>
        </dl>
    </section>

    <!-- 3. Edit Profil -->
    <?php if ($dikirimProfil && $galatProfil): ?>
        <div class="notice notice--galat mt-section" role="alert">
            <strong>Profil belum disimpan. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galatProfil as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($dikirimProfil): ?>
        <p class="notice notice--peringatan mt-section" role="status">
            <strong>Isian valid, tetapi profil belum disimpan.</strong> Penyimpanan perubahan profil akan aktif setelah integrasi backend.
        </p>
    <?php endif; ?>

    <form method="post" action="profil.php" class="panel form-panel form-panel--berkepala mt-section">
        <div class="panel__head">
            <h2>Edit Profil</h2>
        </div>
        <div class="form-panel__body">
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
                    <label for="akun">Email</label>
                    <input type="text" id="akun" name="akun" class="form-control" required autocomplete="email"
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
    <?php if ($dikirimPassword && $galatPassword): ?>
        <div class="notice notice--galat mt-section" role="alert">
            <strong>Password belum diubah. Periksa kembali isian berikut:</strong>
            <ul>
                <?php foreach ($galatPassword as $pesan): ?>
                    <li><?= e($pesan) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($dikirimPassword): ?>
        <p class="notice notice--peringatan mt-section" role="status">
            <strong>Isian valid, tetapi password belum diubah.</strong> Verifikasi password lama dan penyimpanan password baru akan aktif setelah integrasi backend.
        </p>
    <?php endif; ?>

    <form method="post" action="profil.php" class="panel form-panel form-panel--berkepala mt-section" data-form-password autocomplete="off">
        <div class="panel__head">
            <h2>Ubah Password</h2>
        </div>
        <div class="form-panel__body">
            <input type="hidden" name="aksi" value="ubah_password">
            <p class="teks-catatan">Password tidak ditampilkan di mana pun dan belum diverifikasi atau disimpan pada tahap ini.</p>

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
