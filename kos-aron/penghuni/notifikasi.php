<?php
require_once __DIR__ . '/../includes/config.php';
require_role('penghuni', '../login.php');
require_once __DIR__ . '/../data/notifikasi.php'; // juga memuat data tagihan, penghuni, kamar & pembayaran

$judulHalaman = 'Notifikasi';
$menuAktif    = 'notifikasi';

require __DIR__ . '/includes/header.php';

// $penghuniLogin sudah disiapkan oleh includes/header.php (lihat get_penghuni_login()).
$daftarNotifikasi = $penghuniLogin ? get_notifikasi_penghuni($penghuniLogin['id']) : [];

// Warna tepi kiri notifikasi mengikuti komponen .notice yang sudah ada.
$kelasNotice = [
    'terlambat' => 'notice--galat',
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
                            <p class="notif-item__waktu"><?= e(format_tanggal($n['tanggal'])) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <p class="teks-catatan">
        Notifikasi di atas hanya contoh tampilan dan dihasilkan dari data tagihan Anda saat halaman ini dibuka.
        Belum ada sistem pengingat otomatis, dan notifikasi di sini tidak dikirim lewat WhatsApp atau email.
    </p>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
