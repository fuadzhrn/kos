<?php
require_once __DIR__ . '/includes/konteks.php';
require_once __DIR__ . '/../data/pengajuan-pembayaran.php';

$judulHalaman = 'Tagihan';
$menuAktif    = 'tagihan';



$tagihanBerjalan = $penghuniLogin ? get_tagihan_berjalan($penghuniLogin['id']) : null;
$riwayatTagihan  = $penghuniLogin ? get_tagihan_by_penghuni($penghuniLogin['id']) : [];

$ajukan = filter_input(INPUT_GET, 'ajukan', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$pilihan = $ajukan ? (array_column($riwayatTagihan, null, 'id')[$ajukan] ?? null) : null;
$galat = null;
$pesan = $_SESSION['pesan_pengajuan_transfer'] ?? null;
unset($_SESSION['pesan_pengajuan_transfer']);
$bisaAjukan = $pilihan && !$pilihan['pembayaran_sah_id'] && $pilihan['jumlah'] !== '0.00'
    && $pilihan['status_pengajuan'] !== 'menunggu';
if (isset($_GET['ajukan']) && !$pilihan) {
    http_response_code(404);
    $galat = 'Tagihan tidak ditemukan.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            http_response_code(403);
            throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        if (!$bisaAjukan) {
            throw new DomainException('Tagihan ini tidak dapat diajukan kembali. Periksa status tagihan dan pengajuannya.');
        }
        simpan_pengajuan_transfer($penghuniLogin['id'], $ajukan, $_POST, $_FILES['bukti_transfer'] ?? []);
        $_SESSION['pesan_pengajuan_transfer'] = 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi Admin.';
        header('Location: tagihan.php', true, 303);
        exit;
    } catch (DomainException $e) {
        $galat = $e->getMessage();
    } catch (PDOException $e) {
        error_log('[Kos Aron] Penyimpanan pengajuan transfer gagal: ' . $e->getCode());
        $galat = 'Pengajuan gagal disimpan. Periksa riwayat sebelum mencoba kembali.';
    }
} elseif ($pilihan && !$bisaAjukan) {
    $galat = 'Periksa status tagihan dan pengajuan di bawah.';
}
require __DIR__ . '/includes/header.php';

if ($penghuniLogin !== null) {
    $kontak      = link_hubungi_pemilik(
        'Halo, saya penghuni Kamar ' . ($penghuniLogin['nomor'] ?? '-') . ' dan ingin bertanya tentang tagihan sewa saya.',
        '../kontak.php'
    );
    $atributLink = $kontak['eksternal'] ? ' target="_blank" rel="noopener"' : '';
}
?>

<div class="admin-page-head">
    <h1>Tagihan Saya</h1>
    <p>Tagihan sewa bulanan dan status pembayaran Anda.</p>
</div>

<?php if ($pesan): ?><p class="notice notice--berhasil" role="status"><?= e($pesan) ?></p><?php endif; ?>
<?php if ($galat): ?><p class="notice notice--galat" role="alert"><?= e($galat) ?></p><?php endif; ?>
<?php if ($bisaAjukan): ?>
    <section id="pengajuan" class="panel form-panel">
        <h2>Ajukan Pembayaran</h2>
        <dl class="info-tabel info-tabel--panel">
            <div><dt>Nomor tagihan</dt><dd>#<?= (int)$pilihan['id'] ?></dd></div>
            <div><dt>Periode pembayaran</dt><dd><?= e(format_periode($pilihan['periode'])) ?></dd></div>
            <div><dt>Nomor kamar</dt><dd><?= e($pilihan['nomor_kamar']) ?></dd></div>
            <div><dt>Nominal tagihan</dt><dd><?= e(format_rupiah($pilihan['jumlah'])) ?></dd></div>
        </dl>
        <form method="post" enctype="multipart/form-data" action="tagihan.php?ajukan=<?= (int)$ajukan ?>#pengajuan">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--2">
                <div class="form-group"><label for="tanggal-transfer">Tanggal transfer</label>
                    <input class="form-control" type="date" id="tanggal-transfer" name="tanggal_transfer" required max="<?= e(tanggal_hari_ini()) ?>" value="<?= e(is_string($_POST['tanggal_transfer'] ?? null) ? $_POST['tanggal_transfer'] : tanggal_hari_ini()) ?>"></div>
                <div class="form-group"><label for="pengirim">Nama pengirim (opsional)</label>
                    <input class="form-control" id="pengirim" name="nama_pengirim" maxlength="100" value="<?= e(is_string($_POST['nama_pengirim'] ?? null) ? $_POST['nama_pengirim'] : '') ?>"></div>
                <div class="form-group"><label for="bukti">Bukti transfer (JPG, JPEG, PNG, WEBP hingga 3 MB)</label>
                    <input class="form-control" type="file" id="bukti" name="bukti_transfer" accept="image/jpeg,image/png,image/webp" required></div>
                <div class="form-group"><label for="keterangan-transfer">Keterangan (opsional)</label>
                    <textarea class="form-control" id="keterangan-transfer" name="keterangan" maxlength="255" rows="2"><?= e(is_string($_POST['keterangan'] ?? null) ? $_POST['keterangan'] : '') ?></textarea></div>
            </div>
            <div class="form-aksi"><button class="btn btn-primary" type="submit">Kirim Bukti Pembayaran</button>
                <a class="btn btn-outline" href="tagihan.php">Kembali</a></div>
        </form>
    </section>
<?php endif; ?>
<?php if ($penghuniLogin === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada data penghuni</strong>
        Tidak ada informasi tagihan yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <!-- 2. Tagihan Bulan Berjalan -->
    <section class="panel">
        <div class="panel__head">
            <h2>Tagihan Bulan Berjalan</h2>
        </div>
        <?php if ($tagihanBerjalan === null): ?>
            <p class="panel__kosong">Belum ada tagihan untuk bulan ini.</p>
        <?php else: ?>
            <dl class="info-tabel info-tabel--panel">
                <div>
                    <dt>Periode Tagihan</dt>
                    <dd><?= e(format_periode($tagihanBerjalan['periode'])) ?></dd>
                </div>
                <div>
                    <dt>Nomor Kamar</dt>
                    <dd><?= 'Kamar ' . e($tagihanBerjalan['nomor_kamar']) ?></dd>
                </div>
                <div>
                    <dt>Nominal Tagihan</dt>
                    <dd><?= e(format_rupiah($tagihanBerjalan['jumlah'])) ?></dd>
                </div>
                <div>
                    <dt>Tanggal Jatuh Tempo</dt>
                    <dd><?= e(format_tanggal($tagihanBerjalan['jatuh_tempo'])) ?></dd>
                </div>
                <div>
                    <dt>Status Pembayaran</dt>
                    <dd>
                        <span class="badge badge--<?= e($tagihanBerjalan['status']) ?>"><?= e(label_status_tagihan($tagihanBerjalan['status'])) ?></span>

                        <?php if ($tagihanBerjalan['status_pengajuan'] !== null): ?>
                            <span class="tabel__sub">Pengajuan transfer: <?= e(label_status_pengajuan($tagihanBerjalan['status_pengajuan'])) ?></span>
                        <?php endif; ?>
                        <?php if (!$tagihanBerjalan['pembayaran_sah_id'] && $tagihanBerjalan['jumlah'] !== '0.00' && $tagihanBerjalan['status_pengajuan'] !== 'menunggu'): ?>
                            <a class="btn btn-outline btn-sm" href="tagihan.php?ajukan=<?= (int)$tagihanBerjalan['id'] ?>#pengajuan"><?= $tagihanBerjalan['status_pengajuan'] === 'ditolak' ? 'Ajukan Ulang Pembayaran' : 'Ajukan Pembayaran' ?></a>
                        <?php endif; ?>
                        <?php if ($tagihanBerjalan['status'] === 'lunas'): ?>
                            <span class="tabel__sub">Dibayar <?= e(format_tanggal($tagihanBerjalan['tanggal_bayar'])) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        <?php endif; ?>
    </section>

    <!-- 3. Riwayat Tagihan -->
    <section class="panel">
        <div class="panel__head">
            <h2>Riwayat Tagihan</h2>
        </div>
        <?php if (empty($riwayatTagihan)): ?>
            <p class="panel__kosong">Belum ada riwayat tagihan.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel tabel--tumpuk">
                    <thead>
                        <tr>
                            <th scope="col" class="tabel__no">No</th>
                            <th scope="col">Periode</th>
                            <th scope="col" class="tabel__angka">Nominal</th>
                            <th scope="col">Jatuh Tempo</th>
                            <th scope="col">Status</th>
                            <th scope="col">Pengajuan transfer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatTagihan as $i => $t): ?>
                            <tr>
                                <td class="tabel__no" data-label="No"><?= $i + 1 ?></td>
                                <td data-label="Periode"><?= e(format_periode($t['periode'])) ?></td>
                                <td data-label="Nominal" class="tabel__angka"><?= e(format_rupiah($t['jumlah'])) ?></td>
                                <td data-label="Jatuh Tempo"><?= e(format_tanggal($t['jatuh_tempo'])) ?></td>
                                <td data-label="Status">
                                    <span class="badge badge--<?= e($t['status']) ?>"><?= e(label_status_tagihan($t['status'])) ?></span>
                                    <?php if ($t['status'] === 'lunas'): ?>
                                        <span class="tabel__sub">Dibayar <?= e(format_tanggal($t['tanggal_bayar'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Pengajuan transfer">

                                    <?php if ($t['status_pengajuan'] !== null): ?>
                                        <span><?= e(label_status_pengajuan($t['status_pengajuan'])) ?></span>
                                        <a class="tabel__sub" href="../bukti-transfer.php?pengajuan=<?= (int)$t['pengajuan_id'] ?>">Lihat bukti</a>
                                        <?php if ($t['status_pengajuan'] === 'ditolak' && $t['alasan_pengajuan'] !== null): ?>
                                            <span class="tabel__sub"><?= e($t['alasan_pengajuan']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!$t['pembayaran_sah_id'] && $t['jumlah'] !== '0.00' && $t['status_pengajuan'] !== 'menunggu'): ?>
                                        <a class="btn btn-outline btn-sm" href="tagihan.php?ajukan=<?= (int)$t['id'] ?>#pengajuan"><?= $t['status_pengajuan'] === 'ditolak' ? 'Ajukan Ulang Pembayaran' : 'Ajukan Pembayaran' ?></a>
                                    <?php elseif ($t['status_pengajuan'] === null): ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- 5. Informasi Pembayaran -->
    <div class="contact-box">
        <p>Setelah transfer kepada pemilik, kirim bukti melalui tagihan yang sesuai. Tagihan tetap belum lunas selama bukti menunggu verifikasi Admin.</p>
        <a href="<?= e($kontak['url']) ?>" class="btn btn-primary"<?= $atributLink ?>>Hubungi Pemilik</a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
