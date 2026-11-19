<?php
require_once __DIR__ . '/includes/config.php';

$judulHalaman  = 'Login';
$halamanAktif  = 'login';
$deskripsiMeta = 'Halaman login khusus Admin dan Penghuni Kos Aron.';

// Sudah login? Tidak perlu melihat form login lagi.
$sudahLogin = pengguna_login();
if ($sudahLogin !== null) {
    header('Location: ' . ($sudahLogin['role'] === 'admin' ? 'admin/index.php' : 'penghuni/index.php'));
    exit;
}

$formDikirim = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$pesanGalat  = null;
$identitasIsi = '';

if ($formDikirim) {
    $identitasIsi = trim((string) ($_POST['identitas'] ?? ''));
    $password     = (string) ($_POST['password'] ?? '');

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $pesanGalat = 'Sesi formulir sudah tidak valid. Muat ulang halaman ini dan coba lagi.';
    } elseif ($identitasIsi === '' || mb_strlen($identitasIsi) > 190) {
        $pesanGalat = 'Username/email dan password wajib diisi.';
    } elseif ($password === '') {
        $pesanGalat = 'Username/email dan password wajib diisi.';
    } elseif (login_terkunci($identitasIsi)) {
        $pesanGalat = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . DURASI_KUNCI_LOGIN . ' detik.';
    } else {
        try {
            $akun = cari_dan_verifikasi_login($identitasIsi, $password);
        } catch (Throwable $e) {
            $akun       = null;
            $pesanGalat = 'Tidak dapat memproses login saat ini. Silakan coba lagi.';
        }

        if ($pesanGalat === null) {
            if ($akun !== null) {
                reset_percobaan_login($identitasIsi);
                login_berhasil($akun);
                header('Location: ' . ($akun['role'] === 'admin' ? 'admin/index.php' : 'penghuni/index.php'));
                exit;
            }

            catat_percobaan_login_gagal($identitasIsi);
            // Pesan generik yang SAMA untuk akun tidak ada, password salah,
            // maupun akun nonaktif -- supaya keberadaan/status akun tidak
            // bisa ditebak dari respons.
            $pesanGalat = 'Username/email atau password salah.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="section auth-section">
    <div class="container">
        <div class="auth-box">
            <h1>Login</h1>
            <p class="auth-box__sub">Khusus Admin dan Penghuni Kos Aron.</p>

            <?php if (isset($_GET['keluar'])): ?>
                <p class="notice notice--berhasil" role="status">Anda telah keluar.</p>
            <?php endif; ?>

            <?php if ($pesanGalat !== null): ?>
                <p class="notice notice--galat" role="alert"><?= e($pesanGalat) ?></p>
            <?php endif; ?>

            <form method="post" action="login.php" class="auth-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="identitas">Username atau Email</label>
                    <input type="text" id="identitas" name="identitas" class="form-control"
                           autocomplete="username" maxlength="190" required
                           value="<?= e($identitasIsi) ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-password">
                        <input type="password" id="password" name="password" class="form-control"
                               autocomplete="current-password" required>
                        <button type="button" class="input-password__toggle"
                                aria-controls="password" data-toggle-password>Tampilkan</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Masuk</button>
            </form>
        </div>

        <p class="auth-info">
            Calon penghuni tidak perlu login. Informasi kamar dapat dilihat di halaman
            <a href="kamar.php">Kamar</a>, dan pertanyaan dapat disampaikan melalui halaman <a href="kontak.php">Kontak</a>.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
