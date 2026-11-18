<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Data Pembayaran';
$menuAktif    = 'pembayaran';

$daftarPembayaran  = get_semua_pembayaran();
$totalPembayaran   = count($daftarPembayaran);
$daftarPeriode     = daftar_periode_tagihan();
$daftarMetode      = daftar_metode_pembayaran();
$ringkasanBulan    = ringkasan_pembayaran_bulan_ini();
$tagihanBelumLunas = get_tagihan_belum_lunas();

/*
 * PENTING: pembayaran hanya DICATAT ADMIN. Penghuni membayar tunai atau
 * transfer LANGSUNG kepada pemilik di luar website; tidak ada upload bukti
 * bayar maupun tombol verifikasi dari penghuni di sini.
 *
 * PROTOTYPE UI: tidak ada penyimpanan nyata. Isian diperiksa sesuai aturan
 * rancangan, lalu dijawab dengan pesan "belum disimpan".
 * Nanti (backend) alurnya:
 *   1. Admin mencatat pembayaran.
 *   2. Sistem memvalidasi (tagihan belum lunas, nominal sama persis, dsb).
 *   3. Pembayaran disimpan (INSERT ke tabel pembayaran, dalam transaksi).
 *   4. Status tagihan terkait otomatis menjadi Lunas (dihitung dari JOIN).
 *   5. Riwayat pembayaran diperbarui (muncul di tabel ini).
 */
$metodeHttp = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$dikirim    = $metodeHttp === 'POST' && ($_POST['aksi'] ?? '') === 'simpan';
$modalBuka  = false;

$nilaiKosong = ['tagihan_id' => '', 'tanggal_bayar' => '', 'nominal_dibayar' => '', 'metode' => 'tunai', 'keterangan' => ''];
$nilaiForm   = $nilaiKosong;
$galat       = [];

if ($dikirim) {
    $modalBuka = true;
    foreach (array_keys($nilaiKosong) as $kunci) {
        $nilaiForm[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }

    $tagihanId = ctype_digit($nilaiForm['tagihan_id']) ? (int) $nilaiForm['tagihan_id'] : null;
    $tagihan   = $tagihanId ? get_tagihan_by_id($tagihanId) : null;

    if ($tagihan === null) {
        $galat[] = 'Pilih tagihan yang akan dibayar.';
    } elseif ($tagihan['status'] === 'lunas') {
        $galat[] = 'Tagihan ' . $tagihan['nama_penghuni'] . ' periode ' . format_periode($tagihan['periode'])
                 . ' sudah lunas. Satu tagihan hanya boleh memiliki satu pembayaran.';
    }

    if (format_tanggal($nilaiForm['tanggal_bayar']) === '-') {
        $galat[] = 'Tanggal pembayaran tidak valid.';
    } elseif ($nilaiForm['tanggal_bayar'] > tanggal_hari_ini()) {
        $galat[] = 'Tanggal pembayaran tidak boleh di masa depan.';
    }

    if (!ctype_digit($nilaiForm['nominal_dibayar']) || (int) $nilaiForm['nominal_dibayar'] < 1) {
        $galat[] = 'Nominal dibayar harus berupa angka lebih dari 0.';
    } elseif ($tagihan !== null && (int) $nilaiForm['nominal_dibayar'] !== $tagihan['jumlah']) {
        $galat[] = 'Nominal dibayar (' . format_rupiah((int) $nilaiForm['nominal_dibayar']) . ') harus sama persis dengan nominal tagihan ('
                 . format_rupiah($tagihan['jumlah']) . '). Pembayaran cicilan belum didukung pada versi ini.';
    }

    if (!array_key_exists($nilaiForm['metode'], $daftarMetode)) {
        $galat[] = 'Pilih metode pembayaran.';
    }
    if (mb_strlen($nilaiForm['keterangan']) > 255) {
        $galat[] = 'Keterangan maksimal 255 karakter.';
    }
} elseif (isset($_GET['form'])) {
    // Tanpa JavaScript: tautan ?form=baru membuka form lewat server.
    $modalBuka = true;
}

// Tagihan yang sedang ditampilkan pada field Nama Penghuni/Nomor Kamar/Nominal Tagihan (readonly).
$tagihanTampil = null;
if ($nilaiForm['tagihan_id'] !== '' && ctype_digit($nilaiForm['tagihan_id'])) {
    $tagihanTampil = get_tagihan_by_id((int) $nilaiForm['tagihan_id']);
}

// Detail pembayaran (hanya tampilan), dibuka lewat ?lihat=ID.
$detailId         = filter_input(INPUT_GET, 'lihat', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$detailPembayaran = $detailId ? get_pembayaran_by_id($detailId) : null;
$detailBuka       = $detailId !== null && $detailId !== false;

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Pembayaran</h1>
        <p>Catat pembayaran sewa yang diterima pemilik secara tunai atau transfer, dan lihat riwayatnya.</p>
    </div>
    <a href="pembayaran.php?form=baru#modal-pembayaran" class="btn btn-primary" data-buka-pembayaran>Catat Pembayaran</a>
</div>

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
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarPembayaran as $i => $p): ?>
                        <?php
                        $label    = $p['nama_penghuni'] . ' ' . format_periode($p['periode_tagihan']);
                        $dataLihat = json_encode([
                            'nama_penghuni'          => $p['nama_penghuni'],
                            'nomor_kamar'             => $p['nomor_kamar'],
                            'periode_tampil'          => format_periode($p['periode_tagihan']),
                            'tanggal_tampil'          => format_tanggal($p['tanggal_bayar']),
                            'nominal_tagihan_tampil'  => format_rupiah($p['jumlah_tagihan']),
                            'nominal_dibayar_tampil'  => format_rupiah($p['nominal_dibayar']),
                            'metode_tampil'           => label_metode_pembayaran($p['metode']),
                            'keterangan'              => $p['keterangan'],
                        ], JSON_UNESCAPED_UNICODE);
                        ?>
                        <tr data-baris data-periode="<?= e($p['periode_tagihan']) ?>" data-metode="<?= e($p['metode']) ?>">
                            <td class="tabel__no"><?= $i + 1 ?></td>
                            <td><?= e(format_tanggal($p['tanggal_bayar'])) ?></td>
                            <td class="tabel__utama"><?= e($p['nama_penghuni']) ?></td>
                            <td>Kamar <?= e($p['nomor_kamar']) ?></td>
                            <td><?= e(format_periode($p['periode_tagihan'])) ?></td>
                            <td class="tabel__angka"><?= e(format_rupiah($p['nominal_dibayar'])) ?></td>
                            <td><?= e(label_metode_pembayaran($p['metode'])) ?></td>
                            <td>
                                <a href="pembayaran.php?lihat=<?= (int) $p['id'] ?>#modal-detail-pembayaran" class="btn btn-outline btn-sm"
                                   data-lihat-pembayaran="<?= e($dataLihat) ?>" aria-label="Detail pembayaran <?= e($label) ?>">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="tabel__kosong" data-filter-kosong hidden>
                        <td colspan="8">Tidak ada pembayaran yang sesuai dengan filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="teks-catatan">
    Pembayaran dicatat admin setelah penghuni membayar tunai atau transfer langsung kepada pemilik, di luar website.
    Nominal dibayar harus sama persis dengan nominal tagihan (belum mendukung cicilan). Setelah integrasi backend,
    mencatat pembayaran akan otomatis mengubah status tagihan terkait menjadi Lunas.
</p>

<!-- Modal Catat Pembayaran -->
<dialog id="modal-pembayaran" class="modal" aria-labelledby="modal-pembayaran-judul"<?= $modalBuka ? ' open data-buka-otomatis' : '' ?>>
    <form method="post" action="pembayaran.php#modal-pembayaran" class="modal__form" data-form-pembayaran>
        <div class="modal__head">
            <h2 id="modal-pembayaran-judul">Catat Pembayaran</h2>
            <a href="pembayaran.php" class="modal__tutup" data-tutup-modal aria-label="Tutup form">&times;</a>
        </div>

        <div class="modal__body">
            <?php if ($dikirim && $galat): ?>
                <div class="notice notice--galat" role="alert" data-hasil-form>
                    <strong>Pembayaran belum disimpan. Periksa kembali isian berikut:</strong>
                    <ul>
                        <?php foreach ($galat as $pesan): ?>
                            <li><?= e($pesan) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($dikirim): ?>
                <p class="notice notice--peringatan" role="status" data-hasil-form>
                    <strong>Isian valid, tetapi pembayaran belum disimpan.</strong> Pencatatan pembayaran akan aktif setelah integrasi backend.
                </p>
            <?php endif; ?>
            <p class="teks-catatan modal__info">Form prototype: tidak ada pembayaran yang benar-benar dicatat, dan tidak ada status tagihan yang berubah.</p>

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
                                data-nominal="<?= (int) $t['jumlah'] ?>"
                                data-nominal-tampil="<?= e(format_rupiah($t['jumlah'])) ?>"
                                <?= $nilaiForm['tagihan_id'] === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['nama_penghuni']) ?> &mdash; Kamar <?= e($t['nomor_kamar']) ?> &mdash; <?= e(format_periode($t['periode'])) ?>
                                (<?= e(label_status_tagihan($t['status'])) ?>)
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
                    <input type="number" id="nominal_dibayar" name="nominal_dibayar" class="form-control" required min="1" step="1"
                           inputmode="numeric" value="<?= e($nilaiForm['nominal_dibayar']) ?>" aria-describedby="nominal-hint">
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
            <div data-detail-keterangan-baris <?= !($detailPembayaran && $detailPembayaran['keterangan'] !== '') ? 'hidden' : '' ?>>
                <dt>Keterangan</dt><dd><?= e($detailPembayaran['keterangan'] ?? '') ?></dd>
            </div>
        </dl>
    </div>
    <div class="modal__foot">
        <a href="pembayaran.php" class="btn btn-outline" data-tutup-modal>Tutup</a>
    </div>
</dialog>

<?php require __DIR__ . '/includes/footer.php'; ?>
