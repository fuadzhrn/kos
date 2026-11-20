<?php
require_once __DIR__ . '/../includes/config.php';
$admin = require_role('admin', '../login.php');
require_once __DIR__ . '/../data/pengajuan-pembayaran.php';

$judulHalaman = 'Detail Pengajuan Pembayaran';
$menuAktif = 'pembayaran';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$p = $id ? detail_pengajuan_transfer($id) : null;
$pesan = $_SESSION['hasil_verifikasi'][$id] ?? '';
unset($_SESSION['hasil_verifikasi'][$id]);
$galat = '';
$catatan = '';
if (!$p) {
    http_response_code(404);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $galat = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } else {
        try {
            if (!is_string($_POST['catatan_admin'] ?? '') || !is_string($_POST['aksi'] ?? null)) {
                throw new DomainException('Format keputusan tidak valid.');
            }
            $aksi = $_POST['aksi'];
            $catatan = trim($_POST['catatan_admin'] ?? '');
            $baru = verifikasi_pengajuan_transfer($id, $admin['id'], $aksi, $catatan);
            $_SESSION['hasil_verifikasi'][$id] = !$baru ? 'Keputusan ini sudah tercatat.'
                : ($aksi === 'terima' ? 'Pembayaran diterima dan tagihan lunas.' : 'Pengajuan ditolak. Alasan tersedia bagi penghuni.');
            header('Location: verifikasi-pembayaran.php?id=' . $id, true, 303);
            exit;
        } catch (DomainException $e) {
            http_response_code(422);
            $galat = $e->getMessage();
        } catch (PDOException $e) {
            http_response_code(503);
            error_log('[Kos Aron] Verifikasi gagal. SQLSTATE ' . $e->getCode());
            $galat = 'Verifikasi gagal disimpan. Muat ulang dan periksa status terbaru.';
        }
        $p = detail_pengajuan_transfer($id);
    }
}
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="Breadcrumb"><ol class="breadcrumb">
    <li><a href="pembayaran.php#pengajuan">Pembayaran</a></li><li aria-current="page">Detail Pengajuan</li>
</ol></nav>
<?php if (!$p): ?>
    <p class="notice" role="alert">Pengajuan pembayaran tidak ditemukan.</p>
<?php else: ?>
    <div class="admin-page-head"><h1>Pengajuan Pembayaran #<?= (int)$id ?></h1></div>
    <?php if ($pesan !== ''): ?><p class="notice notice--berhasil" role="status"><?= e($pesan) ?></p><?php endif; ?>
    <?php if ($galat !== ''): ?><p class="notice notice--galat" role="alert"><?= e($galat) ?></p><?php endif; ?>
    <section class="panel">
        <div class="panel__head"><h2>Penghuni dan Tagihan</h2></div>
        <dl class="info-tabel info-tabel--panel">
            <div><dt>Nama penghuni</dt><dd><?= e($p['nama_penghuni']) ?></dd></div>
            <div><dt>Nomor kamar</dt><dd><?= e($p['nomor_kamar']) ?></dd></div>
            <div><dt>Periode sewa</dt><dd><?= e(format_tanggal($p['tanggal_masuk'])) ?> – <?= $p['tanggal_keluar'] ? e(format_tanggal($p['tanggal_keluar'])) : 'berjalan' ?></dd></div>
            <div><dt>Nomor tagihan</dt><dd>#<?= (int)$p['tagihan_id'] ?></dd></div>
            <div><dt>Periode tagihan</dt><dd><?= e(format_periode($p['periode'])) ?></dd></div>
            <div><dt>Nominal tagihan</dt><dd><?= e(format_rupiah($p['jumlah'])) ?></dd></div>
            <div><dt>Jatuh tempo</dt><dd><?= e(format_tanggal($p['jatuh_tempo'])) ?></dd></div>
            <div><dt>Status tagihan saat ini</dt><dd><?= e(label_status_tagihan(status_tagihan($p))) ?></dd></div>
        </dl>
    </section>
    <section class="panel">
        <div class="panel__head"><h2>Transfer dan Verifikasi</h2></div>
        <dl class="info-tabel info-tabel--panel">
            <div><dt>Tanggal pengajuan</dt><dd><?= e($p['created_at']) ?></dd></div>
            <div><dt>Tanggal transfer</dt><dd><?= e(format_tanggal($p['tanggal_transfer'])) ?></dd></div>
            <div><dt>Nama pengirim</dt><dd><?= e($p['nama_pengirim'] ?? '—') ?></dd></div>
            <div><dt>Metode pembayaran</dt><dd><?= e(label_metode_pembayaran($p['metode'])) ?></dd></div>
            <div><dt>Nominal diajukan</dt><dd><?= e(format_rupiah($p['nominal_diajukan'])) ?></dd></div>
            <div><dt>Keterangan penghuni</dt><dd><?= e($p['keterangan'] ?? '—') ?></dd></div>
            <div><dt>Bukti transfer</dt><dd><a class="btn btn-outline btn-sm" href="../bukti-transfer.php?pengajuan=<?= (int)$id ?>" target="_blank" rel="noopener">Lihat Bukti Transfer</a></dd></div>
            <div><dt>Status verifikasi</dt><dd><?= e(label_status_pengajuan($p['status_verifikasi'])) ?></dd></div>
            <div><dt>Catatan Admin</dt><dd><?= e($p['catatan_admin'] ?? '—') ?></dd></div>
            <div><dt>Admin pemroses</dt><dd><?= e($p['nama_admin'] ?? '—') ?></dd></div>
            <div><dt>Waktu verifikasi</dt><dd><?= e($p['diverifikasi_pada'] ?? '—') ?></dd></div>
            <?php if ($p['pembayaran_id']): ?>
                <div><dt>Transaksi pembayaran</dt><dd><a href="pembayaran.php?lihat=<?= (int)$p['pembayaran_id'] ?>#modal-detail-pembayaran">Pembayaran #<?= (int)$p['pembayaran_id'] ?></a></dd></div>
            <?php endif; ?>
        </dl>
    </section>
    <?php if ($p['status_verifikasi'] === 'menunggu' && $p['diverifikasi_oleh'] === null && $p['diverifikasi_pada'] === null && $p['pembayaran_id'] === null): ?>
        <section class="panel form-panel">
            <h2>Keputusan Admin</h2>
            <?php if ($p['pembayaran_sah_id'] !== null): ?>
                <p class="notice notice--peringatan">Tagihan ini sudah memiliki pembayaran sah. Periksa transaksi sebelum menolak pengajuan yang bertentangan.</p>
            <?php elseif ($p['jumlah'] !== $p['nominal_diajukan']): ?>
                <p class="notice notice--peringatan">Nominal pengajuan berbeda dari nominal penuh tagihan.</p>
            <?php else: ?>
                <form method="post" data-konfirmasi="Terima bukti transfer ini dan catat pelunasan tagihan?">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" name="aksi" value="terima" type="submit">Terima Pembayaran</button>
                </form>
            <?php endif; ?>
            <form method="post" data-konfirmasi="Tolak pengajuan dan kirim alasan kepada penghuni?">
                <?= csrf_field() ?>
                <div class="form-group"><label for="alasan">Alasan penolakan</label>
                    <textarea class="form-control" name="catatan_admin" id="alasan" maxlength="255" required rows="2"><?= e($catatan) ?></textarea></div>
                <button class="btn btn-hapus" name="aksi" value="tolak" type="submit">Tolak Pembayaran</button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
