<?php
require_once __DIR__ . '/../includes/config.php';
$admin = require_role('admin', '../login.php');
require_once __DIR__ . '/../data/pemesanan.php';

$judulHalaman = 'Detail Pemesanan';
$menuAktif = 'pemesanan';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$p = $id ? detail_pemesanan($id) : null;
$pesan = $_SESSION['pesan_pemesanan_admin'][$id] ?? '';
unset($_SESSION['pesan_pemesanan_admin'][$id]);
$galat = '';
$nilai = [
    'penghuni_id' => '', 'akun' => '', 'hari_jatuh_tempo' => '',
    'harga_sewa_disepakati' => $p['harga_kamar'] ?? '',
    'tanggal_masuk' => ($p && $p['rencana_tanggal_masuk'] <= tanggal_hari_ini()) ? $p['rencana_tanggal_masuk'] : '',
    'catatan_admin' => '',
];
if (!$p) {
    http_response_code(404);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $galat = 'Sesi formulir sudah tidak valid. Muat ulang halaman.';
    } else {
        foreach ($nilai as $kunci => $awal) {
            $nilai[$kunci] = is_string($_POST[$kunci] ?? null) ? trim($_POST[$kunci]) : '';
        }
        $aksi = $_POST['aksi'] ?? null;
        try {
            if ($aksi === 'aktifkan') {
                $form = $nilai;
                $form['identitas_terverifikasi'] = ($_POST['identitas_terverifikasi'] ?? null) === '1' ? '1' : '';
                $form['password'] = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                aktifkan_pemesanan($id, $form, $admin['id']);
                $pesan = 'Penyewaan aktif berhasil dibuat dan terhubung ke pemesanan ini.';
            } elseif (in_array($aksi, ['setujui', 'tolak', 'batalkan'], true)) {
                keputusan_pemesanan($id, $aksi, $nilai['catatan_admin'], $admin['id']);
                $pesan = match ($aksi) {
                    'setujui' => 'Pemesanan disetujui. Kamar ditahan sampai penyewaan dibuat atau pemesanan dibatalkan.',
                    'tolak' => 'Pemesanan ditolak. Keputusan tersimpan dalam riwayat.',
                    'batalkan' => 'Pemesanan dibatalkan. Riwayat pemesanan tersimpan.',
                };
            } else {
                throw new DomainException('Pilih tindakan pemesanan yang tersedia.');
            }
            $_SESSION['pesan_pemesanan_admin'][$id] = $pesan;
            header('Location: pemesanan-detail.php?id=' . $id, true, 303);
            exit;
        } catch (DomainException $e) {
            http_response_code(422);
            $galat = $e->getMessage();
            $p = detail_pemesanan($id);
        } catch (PDOException $e) {
            http_response_code(503);
            error_log('[Kos Aron] Gagal menyimpan tindakan pemesanan admin.');
            $galat = 'Penyimpanan gagal. Muat ulang halaman dan periksa status pemesanan.';
        }
    }
}
$siapAktif = $p && $p['status'] === 'disetujui' && !$p['penyewaan_id'];
$profil = $siapAktif ? pilihan_penghuni_pemesanan($id) : [];
$butuhAkun = !$profil || in_array(null, array_column($profil, 'user_id'), true);
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="Breadcrumb"><ol class="breadcrumb">
    <li><a href="pemesanan.php">Data Pemesanan</a></li><li aria-current="page">Detail</li>
</ol></nav>
<?php if (!$p): ?>
    <div class="admin-placeholder"><h1>Pemesanan tidak ditemukan</h1>
        <a class="btn btn-outline" href="pemesanan.php">Kembali ke Data Pemesanan</a>
    </div>
<?php else: ?>
    <div class="admin-page-head"><h1>Pemesanan <?= e($p['kode_pemesanan']) ?></h1></div>
    <?php if ($galat !== ''): ?><p class="notice" role="alert"><?= e($galat) ?></p><?php endif; ?>
    <?php if ($pesan !== ''): ?><p class="notice" role="status"><?= e($pesan) ?></p><?php endif; ?>
    <section class="panel">
        <div class="panel__head"><h2>Rincian Pengajuan</h2></div>
        <dl class="info-tabel info-tabel--panel detail-pemesanan">
            <div><dt>Nama calon penghuni</dt><dd><?= e($p['nama_lengkap']) ?></dd></div>
            <div><dt>Nomor telepon / WhatsApp</dt><dd><?= e($p['nomor_telepon']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($p['email'] ?: '—') ?></dd></div>
            <div><dt>Nomor kamar</dt><dd><?= e($p['nomor_kamar']) ?></dd></div>
            <div><dt>Status kamar saat ini</dt><dd><?= e(label_status($p['ketersediaan'])) ?></dd></div>
            <div><dt>Harga kamar per bulan</dt><dd><?= e(format_rupiah($p['harga_kamar'])) ?></dd></div>
            <div><dt>Tanggal pengajuan</dt><dd><?= e(format_tanggal(substr($p['tanggal_pengajuan'], 0, 10))) ?></dd></div>
            <div><dt>Rencana masuk</dt><dd><?= e(format_tanggal($p['rencana_tanggal_masuk'])) ?></dd></div>
            <div><dt>Status pemesanan</dt><dd><?= e(ucfirst($p['status'])) ?></dd></div>
            <?php if ($p['penyewaan_id']): ?>
                <div><dt>Penyewaan</dt><dd>Terhubung ke penyewaan #<?= (int)$p['penyewaan_id'] ?></dd></div>
            <?php endif; ?>
            <div><dt>Catatan pemesan</dt><dd><?= nl2br(e($p['catatan_pemesan'] ?: '—')) ?></dd></div>
            <div><dt>Catatan admin</dt><dd><?= nl2br(e($p['catatan_admin'] ?: '—')) ?></dd></div>
        </dl>
    </section>
    <?php if ($p['status'] === 'menunggu'): ?>
        <section class="panel form-panel">
            <h2>Keputusan Admin</h2>
            <form method="post" id="setujui" data-konfirmasi="Setujui pengajuan ini dan tahan kamar untuk calon penghuni ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="setujui">
                <p class="form-info">Penyewaan dimulai setelah Anda memproses calon penghuni menjadi penghuni.</p>
                <button class="btn btn-primary" type="submit" <?= $p['ketersediaan'] === 'kosong' ? '' : 'disabled' ?>>Setujui Pemesanan</button>
            </form>
            <form method="post" id="tolak" data-konfirmasi="Tolak pengajuan ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tolak">
                <div class="form-group">
                    <label for="alasan-tolak">Alasan penolakan (opsional)</label>
                    <textarea id="alasan-tolak" name="catatan_admin" class="form-control" maxlength="500" rows="2"><?= e($nilai['catatan_admin']) ?></textarea>
                </div>
                <button class="btn btn-hapus" type="submit">Tolak Pemesanan</button>
            </form>
        </section>
    <?php endif; ?>
    <?php if ($siapAktif): ?>
        <section class="panel form-panel">
            <h2>Proses Menjadi Penghuni</h2>
            <form method="post" data-konfirmasi="Aktifkan penyewaan sesuai tanggal, harga, dan hari jatuh tempo yang telah diperiksa?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="aktifkan">
                <div class="form-grid form-grid--2">
                    <?php if ($profil): ?>
                        <div class="form-group form-grid__penuh">
                            <label for="profil">Profil penghuni dengan kontak yang cocok</label>
                            <select class="form-control" id="profil" name="penghuni_id" required>
                                <option value="">Pilih profil yang sudah diverifikasi</option>
                                <?php foreach ($profil as $lama): ?>
                                    <option value="<?= (int)$lama['id'] ?>" <?= $nilai['penghuni_id'] === (string)$lama['id'] ? 'selected' : '' ?>>
                                        <?= e($lama['nama_lengkap']) ?> · ID <?= (int)$lama['id'] ?> · <?= $lama['user_id'] ? 'Memiliki akun' : 'Memerlukan akun' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label><input type="checkbox" name="identitas_terverifikasi" value="1" required>
                                Saya sudah memeriksa identitas pemesan dan memastikan profil tersebut milik orang yang sama.</label>
                        </div>
                    <?php else: ?><input type="hidden" name="penghuni_id" value="0"><?php endif; ?>
                    <?php if ($butuhAkun): ?>
                        <div class="form-group">
                            <label for="akun">Email atau username akun baru</label>
                            <input class="form-control" id="akun" name="akun" minlength="3" maxlength="50" autocomplete="off"
                                   value="<?= e($nilai['akun']) ?>" <?= $profil ? '' : 'required' ?>>
                            <?php if ($profil): ?><span class="teks-catatan">Isi hanya bila profil terpilih memerlukan akun.</span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="password">Password akun baru</label>
                            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password"
                                   minlength="8" maxlength="72" <?= $profil ? '' : 'required' ?>>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="tanggal-masuk">Tanggal mulai sewa</label>
                        <input class="form-control" type="date" id="tanggal-masuk" name="tanggal_masuk" required
                               max="<?= e(tanggal_hari_ini()) ?>" value="<?= e($nilai['tanggal_masuk']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="harga">Harga sewa disepakati per bulan (Rp)</label>
                        <input class="form-control" type="number" id="harga" name="harga_sewa_disepakati" required
                               min="0" max="9999999999.99" step="0.01" value="<?= e($nilai['harga_sewa_disepakati']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="tempo">Hari jatuh tempo setiap bulan</label>
                        <input class="form-control" type="number" id="tempo" name="hari_jatuh_tempo" required
                               min="1" max="31" value="<?= e($nilai['hari_jatuh_tempo']) ?>">
                    </div>
                </div>
                <div class="form-aksi"><button class="btn btn-primary" type="submit">Proses Menjadi Penghuni</button></div>
            </form>
        </section>
        <section class="panel form-panel">
            <h2>Pembatalan</h2>
            <form method="post" data-konfirmasi="Batalkan pemesanan dan lepaskan penahanan kamar ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="batalkan">
                <div class="form-group">
                    <label for="alasan-batal">Catatan pembatalan (opsional)</label>
                    <textarea class="form-control" id="alasan-batal" name="catatan_admin" maxlength="500" rows="2"><?= e($nilai['catatan_admin']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-hapus">Batalkan Pemesanan</button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
