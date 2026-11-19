<?php
require_once __DIR__ . '/../includes/config.php';
$admin = require_role('admin', '../login.php');
require_once __DIR__ . '/../data/tagihan.php';

$judulHalaman = 'Data Tagihan';
$menuAktif = 'tagihan';
$mode = $_GET['form'] ?? '';
$periode = substr(tanggal_hari_ini(), 0, 7);
$sewaId = null;
$keterangan = '';
$pratinjau = null;
$galat = null;
$hasil = $_SESSION['hasil_tagihan'] ?? null;
unset($_SESSION['hasil_tagihan']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            http_response_code(403);
            throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        $aksi = $_POST['aksi'] ?? '';
        if ($aksi === 'pratinjau') {
            $mode = $_POST['mode'] ?? '';
            if (!in_array($mode, ['baru', 'massal'], true)) {
                throw new DomainException('Pilih jenis tagihan.');
            }
            $periode = is_string($_POST['periode'] ?? null) ? $_POST['periode'] : '';
            if ($mode === 'baru') {
                $sewaId = filter_input(INPUT_POST, 'penyewaan_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$sewaId) {
                    throw new DomainException('Pilih penghuni dengan penyewaan aktif.');
                }
                $keterangan = is_string($_POST['keterangan'] ?? null) ? trim($_POST['keterangan']) : '';
                if (mb_strlen($keterangan) > 255) {
                    throw new DomainException('Keterangan maksimal 255 karakter.');
                }
            }
            $baris = calon_tagihan($periode, $sewaId);
            if (!$baris) {
                throw new DomainException('Tidak ada penyewaan aktif dengan jatuh tempo dalam masa sewa pada periode ini.');
            }
            $pratinjau = ['periode' => $periode, 'baris' => $baris, 'keterangan' => $keterangan === '' ? null : $keterangan,
                'admin_id' => $admin['id']];
            $tiket = bin2hex(random_bytes(24));
            $_SESSION['pratinjau_tagihan'][$tiket] = $pratinjau;
            $_SESSION['pratinjau_tagihan'] = array_slice($_SESSION['pratinjau_tagihan'], -5, null, true);
        } elseif ($aksi === 'konfirmasi') {
            $tiket = is_string($_POST['tiket'] ?? null) ? $_POST['tiket'] : '';
            $tersimpan = $_SESSION['pratinjau_tagihan'][$tiket] ?? null;
            if (!$tersimpan || $tersimpan['admin_id'] !== $admin['id']) {
                throw new DomainException('Pratinjau sudah tidak tersedia. Buat pratinjau kembali.');
            }
            $_SESSION['hasil_tagihan'] = konfirmasi_tagihan($tersimpan, $admin['id']);
            unset($_SESSION['pratinjau_tagihan'][$tiket]);
            header('Location: tagihan.php', true, 303);
            exit;
        } else {
            throw new DomainException('Pilih tindakan melalui formulir tagihan.');
        }
    } catch (DomainException $e) {
        $galat = $e->getMessage();
    }
}

$semua = get_semua_tagihan();
$totalTagihan = count($semua);
$daftarTahun = array_unique(array_map(fn ($t) => substr($t['periode'], 0, 4), $semua));
$filter = [];
foreach (['cari', 'bulan', 'tahun', 'status'] as $nama) {
    $filter[$nama] = is_string($_GET[$nama] ?? null) ? trim($_GET[$nama]) : '';
}
$daftarTagihan = array_values(array_filter($semua, static function ($t) use ($filter) {
    return ($filter['cari'] === '' || mb_stripos($t['nama_penghuni'], $filter['cari']) !== false)
        && ($filter['bulan'] === '' || substr($t['periode'], 5) === $filter['bulan'])
        && ($filter['tahun'] === '' || substr($t['periode'], 0, 4) === $filter['tahun'])
        && ($filter['status'] === '' || $t['status'] === $filter['status']);
}));
$modal = in_array($mode, ['baru', 'massal'], true);
$massal = $mode === 'massal';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Tagihan</h1>
        <p>Kelola tagihan sewa bulanan penghuni Kos Aron.</p>
    </div>
    <div class="aksi-grup">
        <a href="tagihan.php?form=baru#modal-tagihan" class="btn btn-outline">Buat Tagihan Individual</a>
        <a href="tagihan.php?form=massal#modal-tagihan" class="btn btn-primary">Buat Tagihan Massal</a>
    </div>
</div>

<?php if ($hasil !== null): ?>
    <p class="notice <?= $hasil['gagal'] ? 'notice--peringatan' : '' ?>" role="status">
        Berhasil <?= (int)$hasil['berhasil'] ?> · Dilewati <?= (int)$hasil['dilewati'] ?> · Gagal <?= (int)$hasil['gagal'] ?>.
        <?php if ($hasil['gagal']): ?>Buat pratinjau baru untuk memeriksa kembali penyewaan yang gagal.<?php endif; ?>
    </p>
<?php endif; ?>
<?php if ($galat && !$modal): ?><p class="notice notice--galat" role="alert"><?= e($galat) ?></p><?php endif; ?>

<section class="panel">
    <?php if ($totalTagihan === 0): ?>
        <p class="panel__kosong">Belum ada data tagihan.</p>
    <?php else: ?>
        <form class="toolbar" role="search" aria-label="Filter data tagihan" method="get" action="tagihan.php">
            <div class="filter-field filter-field--cari">
                <label for="cari-tagihan">Cari nama penghuni</label>
                <input type="search" id="cari-tagihan" name="cari" class="form-control" value="<?= e($filter['cari']) ?>">
            </div>
            <div class="filter-field">
                <label for="filter-bulan">Bulan</label>
                <select id="filter-bulan" name="bulan" class="form-control">
                    <option value="">Semua bulan</option>
                    <?php for ($b = 1; $b <= 12; $b++): $kode = sprintf('%02d', $b); ?>
                        <option value="<?= $kode ?>" <?= $filter['bulan'] === $kode ? 'selected' : '' ?>><?= e(nama_bulan($b)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="filter-tahun">Tahun</label>
                <select id="filter-tahun" name="tahun" class="form-control">
                    <option value="">Semua tahun</option>
                    <?php foreach ($daftarTahun as $tahun): ?>
                        <option <?= $filter['tahun'] === $tahun ? 'selected' : '' ?>><?= e($tahun) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="filter-status">Status pembayaran</label>
                <select id="filter-status" name="status" class="form-control">
                    <option value="">Semua status</option>
                    <?php foreach (['belum_bayar', 'terlambat', 'lunas'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filter['status'] === $status ? 'selected' : '' ?>><?= e(label_status_tagihan($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-outline">Terapkan</button>
            <a href="tagihan.php" class="btn btn-outline">Reset</a>
            <p class="filter-info">Menampilkan <?= count($daftarTagihan) ?> dari <?= $totalTagihan ?> tagihan</p>
        </form>
        <div class="tabel-wrap">
            <table class="tabel tabel--lebar" id="tabel-tagihan">
                <thead>
                    <tr>
                        <th scope="col" class="tabel__no">No</th>
                        <th scope="col">Nama Penghuni</th>
                        <th scope="col">Nomor Kamar</th>
                        <th scope="col">Periode Tagihan</th>
                        <th scope="col" class="tabel__angka">Nominal</th>
                        <th scope="col">Jatuh Tempo</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarTagihan as $i => $t): ?>
                        <tr>
                            <td class="tabel__no"><?= $i + 1 ?></td>
                            <td class="tabel__utama"><?= e($t['nama_penghuni']) ?></td>
                            <td>Kamar <?= e($t['nomor_kamar']) ?></td>
                            <td><?= e(format_periode($t['periode'])) ?></td>
                            <td class="tabel__angka"><?= e(format_rupiah($t['jumlah'])) ?></td>
                            <td><?= e(format_tanggal($t['jatuh_tempo'])) ?></td>
                            <td>
                                <span class="badge badge--<?= e($t['status']) ?>"><?= e(label_status_tagihan($t['status'])) ?></span>
                                <?php if ($t['status'] === 'lunas'): ?>
                                    <span class="tabel__sub">Dibayar <?= e(format_tanggal($t['tanggal_bayar'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <details>
                                    <summary class="btn btn-outline btn-sm">Detail tagihan</summary>
                                    <p>Tagihan #<?= (int)$t['id'] ?></p>
                                    <?php if ($t['keterangan'] !== null && $t['keterangan'] !== ''): ?>
                                        <p><?= e($t['keterangan']) ?></p>
                                    <?php endif; ?>
                                    <a href="penghuni-detail.php?id=<?= (int)$t['penghuni_id'] ?>">Riwayat penghuni</a>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$daftarTagihan): ?>
                        <tr class="tabel__kosong"><td colspan="8">Tidak ada tagihan yang sesuai dengan filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($modal): ?>
<dialog id="modal-tagihan" class="modal" aria-labelledby="modal-tagihan-judul" open>
    <form method="post" action="tagihan.php#modal-tagihan" class="modal__form">
        <div class="modal__head">
            <h2 id="modal-tagihan-judul"><?= $pratinjau ? 'Pratinjau' : 'Buat' ?> Tagihan <?= $massal ? 'Massal' : 'Individual' ?></h2>
            <a href="tagihan.php" class="modal__tutup" aria-label="Tutup form" data-tutup-modal>&times;</a>
        </div>
        <?php if (!$pratinjau): ?>
            <div class="modal__body">
                <?php if ($galat): ?><p class="notice notice--galat" role="alert"><?= e($galat) ?></p><?php endif; ?>
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="pratinjau">
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <div class="form-grid">
                    <?php if (!$massal): ?>
                        <div class="form-group">
                            <label for="penyewaan_id">Pilih penghuni aktif</label>
                            <select id="penyewaan_id" name="penyewaan_id" class="form-control" required>
                                <option value="">Pilih penghuni dan kamar</option>
                                <?php foreach (get_semua_penghuni() as $p): ?>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <option value="<?= (int)$p['penyewaan_id'] ?>" <?= (int)$p['penyewaan_id'] === $sewaId ? 'selected' : '' ?>>
                                            <?= e($p['nama']) ?> · Kamar <?= e($p['nomor']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="periode">Bulan dan tahun tagihan</label>
                        <input type="month" id="periode" name="periode" class="form-control" required value="<?= e($periode) ?>">
                    </div>
                    <?php if (!$massal): ?>
                        <div class="form-group">
                            <label for="keterangan">Keterangan (opsional)</label>
                            <textarea id="keterangan" name="keterangan" class="form-control" rows="2" maxlength="255"><?= e($keterangan) ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal__foot">
                <button type="submit" class="btn btn-primary">Lihat Pratinjau</button>
                <a href="tagihan.php" class="btn btn-outline" data-tutup-modal>Batal</a>
            </div>
        <?php else: ?>
            <div class="modal__body">
                <p>Periode <strong><?= e(format_periode($periode)) ?></strong></p>
                <div class="tabel-wrap">
                    <table class="tabel">
                        <thead><tr><th>Penghuni</th><th>Kamar</th><th>Nominal</th><th>Jatuh tempo</th><th>Proses</th></tr></thead>
                        <tbody>
                            <?php foreach ($pratinjau['baris'] as $r): ?>
                                <tr>
                                    <td><?= e($r['nama']) ?></td><td><?= e($r['nomor']) ?></td>
                                    <td><?= e(format_rupiah($r['jumlah'])) ?></td>
                                    <td><?= e(format_tanggal($r['jatuh_tempo'])) ?>
                                        <span class="tabel__sub">Hari jatuh tempo: <?= (int)$r['hari_jatuh_tempo'] ?></span></td>
                                    <td><?= $r['sudah_ada'] ? 'Sudah ada · dilewati' : 'Akan dibuat' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($keterangan !== ''): ?><p>Keterangan: <?= e($keterangan) ?></p><?php endif; ?>
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="konfirmasi">
                <input type="hidden" name="tiket" value="<?= e($tiket) ?>">
            </div>
            <div class="modal__foot">
                <button type="submit" class="btn btn-primary">Konfirmasi Tagihan</button>
                <a href="tagihan.php?form=<?= e($mode) ?>" class="btn btn-outline">Ubah Pilihan</a>
            </div>
        <?php endif; ?>
    </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
