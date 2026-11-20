<?php
require_once __DIR__ . '/includes/konteks.php';
require_once __DIR__ . '/../data/notifikasi.php'; // juga memuat data tagihan, penghuni, kamar & pembayaran

$judulHalaman = 'Notifikasi';
$menuAktif    = 'notifikasi';

$halaman = filter_input(INPUT_GET, 'halaman', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]]) ?: 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'notifikasi_id', FILTER_VALIDATE_INT);
    $token = $_POST['csrf_token'] ?? null;
    if (!$penghuniLogin || !is_string($token) || !csrf_valid($token) || !$id || !baca_notifikasi($penghuniLogin['id'], $id)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    header('Location: notifikasi.php?halaman=' . $halaman, true, 303);
    exit;
}
require __DIR__ . '/includes/header.php';

$perHalaman = 20;
$daftarNotifikasi = $penghuniLogin ? get_notifikasi_penghuni($penghuniLogin['id'], $perHalaman + 1, ($halaman - 1) * $perHalaman) : [];
$adaBerikutnya = count($daftarNotifikasi) > $perHalaman;
if ($adaBerikutnya) {
    array_pop($daftarNotifikasi);
}

// Warna tepi kiri notifikasi mengikuti komponen .notice yang sudah ada.
$kelasNotice = [
    'terlambat' => 'notice--galat',
    'verifikasi' => 'notice--galat',
    'pengingat' => 'notice--peringatan',
    'lunas'     => 'notice--berhasil',
];
?>

<div class="admin-page-head">
    <h1>Notifikasi</h1>
    <p>Pengingat dan informasi seputar tagihan sewa Anda.</p>
</div>

<?php if ($penghuniLogin === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada data penghuni</strong>
        Tidak ada notifikasi yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <section class="panel">
        <div class="panel__head">
            <h2>Daftar Notifikasi</h2>
            <span><?= $belumDibaca ?> belum dibaca</span>
        </div>

        <?php if (empty($daftarNotifikasi)): ?>
            <p class="panel__kosong">Belum ada notifikasi untuk Anda saat ini.</p>
        <?php else: ?>
            <ul class="notif-list mt-section">
                <?php foreach ($daftarNotifikasi as $n): ?>
                    <li>
                        <div class="notice <?= e($kelasNotice[$n['tipe']]) ?> notif-item<?= $n['dibaca'] ? ' notif-item--dibaca' : '' ?>">
                            <div class="notif-item__head">
                                <span class="notif-item__judul"><?= e($n['judul']) ?></span>
                                <span class="badge badge--<?= $n['dibaca'] ? 'sudah-dibaca' : 'belum-dibaca' ?>">
                                    <?= $n['dibaca'] ? 'Sudah Dibaca' : 'Belum Dibaca' ?>
                                </span>
                            </div>
                            <p><?= e($n['pesan']) ?></p>
                            <?php if (!$n['dibaca']): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-outline" name="notifikasi_id" value="<?= (int) $n['id'] ?>">Tandai dibaca</button>
                                </form>
                            <?php endif; ?>
                            <p class="notif-item__waktu"><?= e(format_tanggal($n['tanggal'])) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($halaman > 1 || $adaBerikutnya): ?>
            <nav class="form-aksi" aria-label="Halaman notifikasi">
                <?php if ($halaman > 1): ?><a class="btn btn-outline btn-sm" href="?halaman=<?= $halaman - 1 ?>">Sebelumnya</a><?php endif; ?>
                <?php if ($adaBerikutnya): ?><a class="btn btn-outline btn-sm" href="?halaman=<?= $halaman + 1 ?>">Berikutnya</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>


<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
