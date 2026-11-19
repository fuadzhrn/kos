<?php
require_once __DIR__ . '/../includes/config.php';
$admin = require_role('admin', '../login.php');
require_once __DIR__ . '/../data/tagihan.php';

$judulHalaman = 'Data Pembayaran';
$menuAktif = 'pembayaran';
$detailId = filter_input(INPUT_GET, 'lihat', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$modalBuka = isset($_GET['form']);
$nilaiForm = ['tagihan_id' => '', 'tanggal_bayar' => tanggal_hari_ini(), 'nominal_dibayar' => '', 'metode' => 'tunai',
    'keterangan' => '', 'alasan' => '', 'pembayaran_id' => '', 'versi' => ''];
$galat = [];
$aksi = null;
$pesan = $_SESSION['pesan_pembayaran'] ?? null;
unset($_SESSION['pesan_pembayaran']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = is_string($_POST['aksi'] ?? null) ? $_POST['aksi'] : '';
    $modalBuka = $aksi === 'simpan';
    foreach ($nilaiForm as $kunci => $awal) {
        $nilaiForm[$kunci] = is_string($_POST[$kunci] ?? null) ? trim($_POST[$kunci]) : '';
    }
    if (!$modalBuka) {
        $detailId = filter_input(INPUT_POST, 'pembayaran_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }
    try {
        if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            http_response_code(403);
            throw new DomainException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        $formId = is_string($_POST['form_id'] ?? null) ? $_POST['form_id'] : '';
        if ($aksi === 'simpan' && !isset($_SESSION['form_bayar'][$formId])) {
            throw new DomainException('Form sudah diproses atau kedaluwarsa. Periksa riwayat dan buka form baru.');
        }
        $id = proses_pembayaran($aksi, $admin['id'], $nilaiForm);
        unset($_SESSION['form_bayar'][$formId]);
        $_SESSION['pesan_pembayaran'] = match ($aksi) {
            'simpan' => 'Pembayaran tersimpan dan tagihan lunas.',
            'koreksi' => 'Koreksi pembayaran dan catatan audit tersimpan.',
            'batal' => 'Pembayaran dibatalkan. Status tagihan telah dihitung kembali.',
        };
        header('Location: pembayaran.php?lihat=' . $id . '#modal-detail-pembayaran', true, 303);
        exit;
    } catch (DomainException $e) {
        $galat[] = $e->getMessage();
    } catch (PDOException $e) {
        error_log('Gagal memproses pembayaran. SQLSTATE ' . $e->getCode());
        $galat[] = 'Pembayaran gagal diproses. Periksa riwayat sebelum mencoba lagi.';
    }
}
$formId = bin2hex(random_bytes(16));
$_SESSION['form_bayar'][$formId] = true;
$_SESSION['form_bayar'] = array_slice($_SESSION['form_bayar'], -20, null, true);

$daftarPembayaran = get_semua_pembayaran(true);
$totalPembayaran = count($daftarPembayaran);
$daftarPeriode = daftar_periode_tagihan();
$daftarMetode = daftar_metode_pembayaran();
$ringkasanBulan = ringkasan_pembayaran_bulan_ini();
$tagihanBelumLunas = array_filter(get_tagihan_belum_lunas(),
    fn ($t) => $t['pembayaran_sah_id'] === null && $t['jumlah'] !== '0.00');
$tagihanTampil = $modalBuka && ctype_digit($nilaiForm['tagihan_id']) ? get_tagihan_by_id((int)$nilaiForm['tagihan_id']) : null;
$detailPembayaran = $detailId ? get_pembayaran_by_id($detailId, true) : null;
$detailBuka = (bool)$detailId;
$audit = $detailPembayaran ? audit_pembayaran($detailId) : [];

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Pembayaran</h1>
        <p>Catat pembayaran sewa yang diterima pemilik secara tunai atau transfer, dan lihat riwayatnya.</p>
    </div>
    <a href="pembayaran.php?form=baru#modal-pembayaran" class="btn btn-primary" data-buka-pembayaran>Catat Pembayaran</a>
</div>

<?php if ($pesan && !$detailBuka): ?><p class="notice" role="status"><?= e($pesan) ?></p><?php endif; ?>
<?php if ($galat && !$modalBuka && !$detailBuka): ?><p class="notice notice--galat" role="alert"><?= e($galat[0]) ?></p><?php endif; ?>

<div class="ringkasan-bulan">
    <div class="stat-card">
        <p class="stat-card__label">Pembayaran Bulan Ini</p>
        <p class="stat-card__nilai"><?= $ringkasanBulan['jumlah'] ?></p>
        <p class="stat-card__ket">Transaksi tercatat pada <?= e(format_periode($ringkasanBulan['bulan'])) ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Total Diterima Bulan Ini</p>
        <p class="stat-card__nilai stat-card__nilai--rupiah"><?= e(format_rupiah($ringkasanBulan['total'])) ?></p>
        <p class="stat-card__ket">Dari <?= $ringkasanBulan['jumlah'] ?> pembayaran yang dicatat</p>
    </div>
</div>

<section class="panel">
    <?php if ($totalPembayaran === 0): ?>
        <p class="panel__kosong">Belum ada riwayat pembayaran. Gunakan tombol "Catat Pembayaran" untuk mencatat pembayaran pertama.</p>
    <?php else: ?>
        <form class="toolbar" role="search" aria-label="Filter data pembayaran" data-filter-tabel="tabel-pembayaran" data-filter-satuan="pembayaran">
            <div class="filter-field">
                <label for="filter-periode">Periode tagihan</label>
                <select id="filter-periode" name="periode" class="form-control">
                    <option value="semua">Semua periode</option>
                    <?php foreach ($daftarPeriode as $periode): ?>
                        <option value="<?= e($periode) ?>"><?= e(format_periode($periode)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="filter-metode">Metode pembayaran</label>
                <select id="filter-metode" name="metode" class="form-control">
                    <option value="semua">Semua metode</option>
                    <?php foreach ($daftarMetode as $nilaiMetode => $labelMetode): ?>
                        <option value="<?= e($nilaiMetode) ?>"><?= e($labelMetode) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="filter-info" aria-live="polite" data-filter-info>
                Menampilkan <?= $totalPembayaran ?> dari <?= $totalPembayaran ?> pembayaran
            </p>
        </form>

        <div class="tabel-wrap">
            <table class="tabel tabel--lebar" id="tabel-pembayaran">
                <thead>
                    <tr>
                        <th scope="col" class="tabel__no">No</th>
                        <th scope="col">Tanggal Pembayaran</th>
                        <th scope="col">Nama Penghuni</th>
                        <th scope="col">Nomor Kamar</th>
                        <th scope="col">Periode Tagihan</th>
                        <th scope="col" class="tabel__angka">Nominal Pembayaran</th>
                        <th scope="col">Metode</th>
                        <th scope="col">Admin Pencatat</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarPembayaran as $i => $p): ?>
                        <?php
                        $label    = $p['nama_penghuni'] . ' ' . format_periode($p['periode_tagihan']);
                        ?>
                        <tr data-baris data-periode="<?= e($p['periode_tagihan']) ?>" data-metode="<?= e($p['metode']) ?>">
                            <td class="tabel__no"><?= $i + 1 ?></td>
                            <td><?= e(format_tanggal($p['tanggal_bayar'])) ?></td>
                            <td class="tabel__utama"><?= e($p['nama_penghuni']) ?></td>
                            <td>Kamar <?= e($p['nomor_kamar']) ?></td>
                            <td><?= e(format_periode($p['periode_tagihan'])) ?></td>
                            <td class="tabel__angka"><?= e(format_rupiah($p['nominal_dibayar'])) ?>
                                <?php if ($p['status'] === 'dibatalkan'): ?><span class="tabel__sub">Dibatalkan</span><?php endif; ?>
                            </td>
                            <td><?= e(label_metode_pembayaran($p['metode'])) ?></td>
                            <td><?= e($p['admin_pencatat']) ?></td>
                            <td>
                                <a href="pembayaran.php?lihat=<?= (int) $p['id'] ?>#modal-detail-pembayaran" class="btn btn-outline btn-sm"
                                   aria-label="Detail pembayaran <?= e($label) ?>">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="tabel__kosong" data-filter-kosong hidden>
                        <td colspan="9">Tidak ada pembayaran yang sesuai dengan filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="teks-catatan">
    Pembayaran dicatat admin setelah penghuni membayar tunai atau transfer langsung kepada pemilik, di luar website.
    Transaksi yang dibatalkan tetap tercantum dalam riwayat admin dan dikecualikan dari total penerimaan.
</p>

<!-- Modal Catat Pembayaran -->
<dialog id="modal-pembayaran" class="modal" aria-labelledby="modal-pembayaran-judul"<?= $modalBuka ? ' open data-buka-otomatis' : '' ?>>
    <form method="post" action="pembayaran.php#modal-pembayaran" class="modal__form" data-form-pembayaran>
        <div class="modal__head">
            <h2 id="modal-pembayaran-judul">Catat Pembayaran</h2>
            <a href="pembayaran.php" class="modal__tutup" data-tutup-modal aria-label="Tutup form">&times;</a>
        </div>

        <div class="modal__body">
            <?php if ($modalBuka && $galat): ?>
                <div class="notice notice--galat" role="alert" data-hasil-form>
                    <strong>Pembayaran belum disimpan. Periksa kembali isian berikut:</strong>
                    <ul>
                        <?php foreach ($galat as $pesan): ?>
                            <li><?= e($pesan) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?= csrf_field() ?>
            <input type="hidden" name="form_id" value="<?= e($formId) ?>">
            <input type="hidden" name="aksi" value="simpan">

            <div class="form-grid form-grid--2">
                <div class="form-group form-grid__penuh">
                    <label for="tagihan_id">Pilih Tagihan yang Belum Lunas</label>
                    <select id="tagihan_id" name="tagihan_id" class="form-control" required <?= empty($tagihanBelumLunas) ? 'disabled' : '' ?>>
                        <option value="">Pilih tagihan</option>
                        <?php foreach ($tagihanBelumLunas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"
                                data-nama="<?= e($t['nama_penghuni']) ?>"
                                data-kamar="<?= e($t['nomor_kamar']) ?>"
                                data-nominal="<?= e($t['jumlah']) ?>"
                                data-periode="<?= e(format_periode($t['periode'])) ?>"
                                data-nominal-tampil="<?= e(format_rupiah($t['jumlah'])) ?>"
                                <?= $nilaiForm['tagihan_id'] === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['nama_penghuni']) ?> &mdash; Kamar <?= e($t['nomor_kamar']) ?> &mdash; <?= e(format_periode($t['periode'])) ?>
                                (<?= e(format_rupiah($t['jumlah'])) ?> · <?= e(label_status_tagihan($t['status'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($tagihanBelumLunas)): ?>
                        <span class="teks-catatan">Tidak ada tagihan yang belum lunas saat ini.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="nama_penghuni_tampil">Nama Penghuni</label>
                    <input type="text" id="nama_penghuni_tampil" class="form-control" readonly tabindex="-1"
                           value="<?= e($tagihanTampil['nama_penghuni'] ?? '') ?>" placeholder="Terisi otomatis">
                </div>

                <div class="form-group">
                    <label for="nomor_kamar_tampil">Nomor Kamar</label>
                    <input type="text" id="nomor_kamar_tampil" class="form-control" readonly tabindex="-1"
                           value="<?= $tagihanTampil ? 'Kamar ' . e($tagihanTampil['nomor_kamar']) : '' ?>" placeholder="Terisi otomatis">
                </div>

                <div class="form-group">
                    <label for="periode_tampil">Periode Tagihan</label>
                    <input id="periode_tampil" class="form-control" readonly tabindex="-1"
                           value="<?= $tagihanTampil ? e(format_periode($tagihanTampil['periode'])) : '' ?>" placeholder="Terisi otomatis">
                </div>
                <div class="form-group">
                    <label for="nominal_tagihan_tampil">Nominal Tagihan</label>
                    <input type="text" id="nominal_tagihan_tampil" class="form-control" readonly tabindex="-1"
                           value="<?= $tagihanTampil ? e(format_rupiah($tagihanTampil['jumlah'])) : '' ?>" placeholder="Terisi otomatis">
                </div>

                <div class="form-group">
                    <label for="tanggal_bayar">Tanggal Pembayaran</label>
                    <input type="date" id="tanggal_bayar" name="tanggal_bayar" class="form-control" required
                           max="<?= e(tanggal_hari_ini()) ?>" value="<?= e($nilaiForm['tanggal_bayar']) ?>">
                </div>

                <div class="form-group">
                    <label for="nominal_dibayar">Nominal Dibayar (Rp)</label>
                    <input type="number" id="nominal_dibayar" name="nominal_dibayar" class="form-control" required min="0.01" step="0.01"
                           inputmode="decimal" value="<?= e($nilaiForm['nominal_dibayar']) ?>" aria-describedby="nominal-hint">
                    <span id="nominal-hint" class="teks-catatan">Harus sama persis dengan nominal tagihan.</span>
                    <span class="teks-catatan teks-galat" data-peringatan-nominal hidden></span>
                </div>

                <fieldset class="form-group form-grid__penuh">
                    <legend>Metode Pembayaran</legend>
                    <div class="radio-group">
                        <?php foreach ($daftarMetode as $nilaiMetode => $labelMetode): ?>
                            <label class="radio-opsi">
                                <input type="radio" name="metode" value="<?= e($nilaiMetode) ?>"
                                       <?= $nilaiForm['metode'] === $nilaiMetode ? 'checked' : '' ?> required>
                                <?= e($labelMetode) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="form-group form-grid__penuh">
                    <label for="keterangan">Keterangan <span class="text-muted">(opsional)</span></label>
                    <textarea id="keterangan" name="keterangan" class="form-control" rows="2" maxlength="255"><?= e($nilaiForm['keterangan']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="modal__foot">
            <button type="submit" class="btn btn-primary" <?= empty($tagihanBelumLunas) ? 'disabled' : '' ?>>Simpan</button>
            <a href="pembayaran.php" class="btn btn-outline" data-tutup-modal>Batal</a>
        </div>
    </form>
</dialog>

<!-- Modal Detail Pembayaran (hanya tampilan) -->
<dialog id="modal-detail-pembayaran" class="modal modal--detail" aria-labelledby="modal-detail-pembayaran-judul"<?= $detailBuka ? ' open data-buka-otomatis' : '' ?>>
    <div class="modal__head">
        <h2 id="modal-detail-pembayaran-judul">Detail Pembayaran</h2>
        <a href="pembayaran.php" class="modal__tutup" data-tutup-modal aria-label="Tutup">&times;</a>
    </div>
    <div class="modal__body">
        <?php if ($pesan && $detailBuka): ?><p class="notice" role="status"><?= e($pesan) ?></p><?php endif; ?>
        <p class="panel__kosong" data-detail-kosong <?= $detailBuka && $detailPembayaran !== null ? 'hidden' : '' ?>>
            Data pembayaran tidak ditemukan.
        </p>
        <dl class="info-tabel info-tabel--panel" data-detail-isi <?= !($detailBuka && $detailPembayaran !== null) ? 'hidden' : '' ?>>
            <div><dt>Nama Penghuni</dt><dd data-detail-nama><?= e($detailPembayaran['nama_penghuni'] ?? '') ?></dd></div>
            <div><dt>Nomor Kamar</dt><dd data-detail-kamar><?= $detailPembayaran ? 'Kamar ' . e($detailPembayaran['nomor_kamar']) : '' ?></dd></div>
            <div><dt>Periode Tagihan</dt><dd data-detail-periode><?= $detailPembayaran ? e(format_periode($detailPembayaran['periode_tagihan'])) : '' ?></dd></div>
            <div><dt>Nominal Tagihan</dt><dd data-detail-nominal-tagihan><?= $detailPembayaran ? e(format_rupiah($detailPembayaran['jumlah_tagihan'])) : '' ?></dd></div>
            <div><dt>Tanggal Pembayaran</dt><dd data-detail-tanggal><?= $detailPembayaran ? e(format_tanggal($detailPembayaran['tanggal_bayar'])) : '' ?></dd></div>
            <div><dt>Nominal Dibayar</dt><dd data-detail-nominal-dibayar><?= $detailPembayaran ? e(format_rupiah($detailPembayaran['nominal_dibayar'])) : '' ?></dd></div>
            <div><dt>Metode</dt><dd data-detail-metode><?= $detailPembayaran ? e(label_metode_pembayaran($detailPembayaran['metode'])) : '' ?></dd></div>
            <div><dt>Admin Pencatat</dt><dd><?= e($detailPembayaran['admin_pencatat'] ?? '') ?></dd></div>
            <div><dt>Status transaksi</dt><dd><?= e(ucfirst($detailPembayaran['status'] ?? '')) ?></dd></div>
            <div data-detail-keterangan-baris <?= ($detailPembayaran['keterangan'] ?? '') === '' ? 'hidden' : '' ?>>
                <dt>Keterangan</dt><dd><?= e($detailPembayaran['keterangan'] ?? '') ?></dd>
            </div>
        </dl>
        <?php if ($detailPembayaran): ?>
            <?php if ($galat && !$modalBuka): ?><p class="notice notice--galat" role="alert"><?= e($galat[0]) ?></p><?php endif; ?>
            <?php if ($detailPembayaran['status'] === 'sah'): ?>
                <details <?= $galat && !$modalBuka ? 'open' : '' ?>>
                    <summary class="btn btn-outline btn-sm">Koreksi atau batalkan pembayaran</summary>
                    <p class="teks-catatan">Jika salah memilih tagihan, batalkan transaksi ini lalu catat penerimaan pada tagihan yang benar.</p>
                    <form method="post" action="pembayaran.php?lihat=<?= (int)$detailId ?>#modal-detail-pembayaran" class="form-grid">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pembayaran_id" value="<?= (int)$detailId ?>">
                        <input type="hidden" name="tagihan_id" value="<?= (int)$detailPembayaran['tagihan_id'] ?>">
                        <input type="hidden" name="versi" value="<?= (int)($audit[0]['id'] ?? 0) ?>">
                        <label class="form-group">Tanggal pembayaran
                            <input class="form-control" type="date" name="tanggal_bayar" required max="<?= e(tanggal_hari_ini()) ?>" value="<?= e($detailPembayaran['tanggal_bayar']) ?>">
                        </label>
                        <label class="form-group">Nominal dibayar (Rp)
                            <input class="form-control" type="number" min="0.01" step="0.01" required name="nominal_dibayar" value="<?= e($detailPembayaran['jumlah_tagihan']) ?>">
                        </label>
                        <label class="form-group">Metode
                            <select name="metode" class="form-control" required>
                                <?php foreach ($daftarMetode as $kode => $labelMetode): ?>
                                    <option value="<?= e($kode) ?>" <?= $kode === $detailPembayaran['metode'] ? 'selected' : '' ?>><?= e($labelMetode) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-group">Keterangan
                            <textarea name="keterangan" class="form-control" maxlength="255"><?= e($detailPembayaran['keterangan'] ?? '') ?></textarea>
                        </label>
                        <label class="form-group">Alasan koreksi atau pembatalan
                            <textarea name="alasan" class="form-control" maxlength="255" required></textarea>
                        </label>
                        <div class="aksi-grup">
                            <button class="btn btn-primary" name="aksi" value="koreksi">Simpan Koreksi</button>
                            <button class="btn btn-hapus" name="aksi" value="batal">Batalkan Pembayaran</button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
            <h3>Riwayat audit</h3>
            <?php if (!$audit): ?><p class="teks-catatan">Transaksi tercatat sebelum jurnal audit tersedia.</p><?php endif; ?>
            <?php foreach ($audit as $catatan): ?>
                <details>
                    <summary><?= e(ucfirst($catatan['aksi'])) ?> · <?= e($catatan['waktu']) ?> · <?= e($catatan['admin']) ?></summary>
                    <?php if ($catatan['alasan'] !== null): ?><p>Alasan: <?= e($catatan['alasan']) ?></p><?php endif; ?>
                    <?php foreach (['sebelum' => 'Sebelum', 'sesudah' => 'Sesudah'] as $kolom => $label): ?>
                        <?php if ($catatan[$kolom] === null) continue; ?>
                        <?php $salinan = json_decode($catatan[$kolom], true, flags: JSON_THROW_ON_ERROR); ?>
                        <p><strong><?= e($label) ?></strong> · <?= e(format_tanggal($salinan['tanggal_bayar'])) ?> ·
                            <?= e(format_rupiah($salinan['nominal_dibayar'])) ?> · <?= e(label_metode_pembayaran($salinan['metode'])) ?> ·
                            <?= e(ucfirst($salinan['status'])) ?>
                            <?php if ($salinan['keterangan'] !== null): ?><br>Keterangan: <?= e($salinan['keterangan']) ?><?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="modal__foot">
        <a href="pembayaran.php" class="btn btn-outline" data-tutup-modal>Tutup</a>
    </div>
</dialog>

<?php require __DIR__ . '/includes/footer.php'; ?>
