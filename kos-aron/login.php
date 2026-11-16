<?php
require_once __DIR__ . '/includes/config.php';

$judulHalaman  = 'Login';
$halamanAktif  = 'login';
$deskripsiMeta = 'Halaman login khusus Admin dan Penghuni Kos Aron.';

/*
 * Tampilan saja: belum ada proses autentikasi.
 * Data yang dikirim TIDAK dibaca, disimpan, maupun dicocokkan.
 * Proses login (PHP session + tabel pengguna MySQL) dibuat pada tahap sistem internal.
 */
$formDikirim = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

require __DIR__ . '/includes/header.php';
?>

<section class="section auth-section">
    <div class="container">
        <div class="auth-box">
            <h1>Login</h1>
            <p class="auth-box__sub">Khusus Admin dan Penghuni Kos Aron.</p>

            <?php if ($formDikirim): ?>
                <p class="notice notice--muted" role="status">
                    Fitur login belum aktif. Login untuk Admin dan Penghuni akan tersedia setelah sistem internal selesai dibuat.
                </p>
            <?php endif; ?>

            <form method="post" action="login.php" class="auth-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control"
                           autocomplete="username" required>
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

            <p class="teks-catatan auth-box__catatan">Fitur login masih dalam tahap pengembangan.</p>
        </div>

        <p class="auth-info">
            Calon penghuni tidak perlu login. Informasi kamar dapat dilihat di halaman
            <a href="kamar.php">Kamar</a>, dan pertanyaan dapat disampaikan melalui halaman <a href="kontak.php">Kontak</a>.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
