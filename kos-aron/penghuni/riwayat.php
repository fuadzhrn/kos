<?php
require_once __DIR__ . '/includes/konteks.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni, kamar & pembayaran

$judulHalaman = 'Riwayat Pembayaran';
$menuAktif    = 'riwayat';


$riwayatPembayaran = $penghuniLogin ? get_pembayaran_by_penghuni($penghuniLogin['id']) : [];

// Pilihan filter Bulan & Tahun diambil dari data riwayat itu sendiri (tidak hardcode).
$daftarBulan = [];
$daftarTahun = [];
foreach ($riwayatPembayaran as $p) {
    [$tahun, $bulan] = explode('-', $p['periode_tagihan']);
    $daftarBulan[(int) $bulan] = nama_bulan((int) $bulan);
    $daftarTahun[$tahun] = $tahun;
}
ksort($daftarBulan);
krsort($daftarTahun);

// Detail pembayaran (hanya tampilan), dibuka lewat ?lihat=ID -- sama seperti admin/pembayaran.php.
$detailId         = filter_input(INPUT_GET, 'lihat', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$detailBuka = array_key_exists('lihat', $_GET);
$detailPembayaran = $detailId && $penghuniLogin ? get_detail_pembayaran_penghuni($detailId, $penghuniLogin['id']) : null;
if ($detailBuka && !$detailPembayaran) {
    http_response_code(404);
}
require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head">
    <h1>Riwayat Pembayaran</h1>
    <p>Seluruh riwayat pembayaran sewa yang telah dicatat admin.</p>
</div>

<?php if ($penghuniLogin === null): ?>

    <div class="admin-placeholder">
        <strong>Belum ada data penghuni</strong>
        Tidak ada riwayat pembayaran yang bisa ditampilkan saat ini.
    </div>

<?php else: ?>

    <section class="panel">
        <?php if (empty($riwayatPembayaran)): ?>
            <p class="panel__kosong">Belum ada riwayat pembayaran.</p>
        <?php else: ?>
            <form class="toolbar" role="search" aria-label="Filter riwayat pembayaran" data-filter-tabel="tabel-riwayat" data-filter-satuan="pembayaran">
                <div class="filter-field">
                    <label for="filter-bulan">Bulan</label>
                    <select id="filter-bulan" name="bulan" class="form-control">
                        <option value="semua">Semua Bulan</option>
                        <?php foreach ($daftarBulan as $nomor => $nama): ?>
                            <option value="<?= $nomor ?>"><?= e($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="filter-tahun">Tahun</label>
                    <select id="filter-tahun" name="tahun" class="form-control">
                        <option value="semua">Semua Tahun</option>
                        <?php foreach ($daftarTahun as $tahun): ?>
                            <option value="<?= e($tahun) ?>"><?= e($tahun) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="filter-info" aria-live="polite" data-filter-info>
                    Menampilkan <?= count($riwayatPembayaran) ?> dari <?= count($riwayatPembayaran) ?> pembayaran
                </p>
            </form>

            <div class="tabel-wrap">
                <table class="tabel tabel--lebar" id="tabel-riwayat">
                    <thead>
                        <tr>
                            <th scope="col" class="tabel__no">No</th>
                            <th scope="col">Tanggal Pembayaran</th>
                            <th scope="col">Periode Tagihan</th>
                            <th scope="col">Nomor Kamar</th>
                            <th scope="col" class="tabel__angka">Nominal</th>
                            <th scope="col">Metode</th>
                            <th scope="col">Status</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatPembayaran as $i => $p): ?>
                            <?php
                            [$tahunBaris, $bulanBaris] = explode('-', $p['periode_tagihan']);
                            $status = status_tagihan([
                                'tanggal_bayar' => $p['jumlah_tagihan'] === $p['nominal_dibayar'] ? $p['tanggal_bayar'] : null,
                                'jatuh_tempo' => $p['jatuh_tempo'],
                            ]);
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
                            <tr data-baris data-bulan="<?= (int) $bulanBaris ?>" data-tahun="<?= e($tahunBaris) ?>">
                                <td class="tabel__no" data-label="No"><?= $i + 1 ?></td>
                                <td data-label="Tanggal Pembayaran"><?= e(format_tanggal($p['tanggal_bayar'])) ?></td>
                                <td data-label="Periode Tagihan"><?= e(format_periode($p['periode_tagihan'])) ?></td>
                                <td data-label="Nomor Kamar">Kamar <?= e($p['nomor_kamar']) ?></td>
                                <td class="tabel__angka" data-label="Nominal"><?= e(format_rupiah($p['nominal_dibayar'])) ?></td>
                                <td data-label="Metode"><?= e(label_metode_pembayaran($p['metode'])) ?></td>
                                <td data-label="Status"><span class="badge badge--<?= e($status) ?>"><?= e(label_status_tagihan($status)) ?></span></td>
                                <td data-label="Aksi">
                                    <a href="riwayat.php?lihat=<?= (int) $p['id'] ?>#modal-detail-pembayaran" class="btn btn-outline btn-sm"
                                       data-lihat-pembayaran="<?= e($dataLihat) ?>" aria-label="Detail pembayaran <?= e(format_periode($p['periode_tagihan'])) ?>">Detail</a>
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

<?php endif; ?>

<!-- Modal Detail Pembayaran (hanya tampilan, sama seperti admin/pembayaran.php) -->
<dialog id="modal-detail-pembayaran" class="modal modal--detail" aria-labelledby="modal-detail-pembayaran-judul"<?= $detailBuka ? ' open data-buka-otomatis' : '' ?>>
    <div class="modal__head">
        <h2 id="modal-detail-pembayaran-judul">Detail Pembayaran</h2>
        <a href="riwayat.php" class="modal__tutup" data-tutup-modal aria-label="Tutup">&times;</a>
    </div>
    <div class="modal__body">
        <p class="panel__kosong" data-detail-kosong <?= $detailBuka && $detailPembayaran !== null ? 'hidden' : '' ?>>
            Data pembayaran tidak ditemukan.
        </p>
        <dl class="info-tabel info-tabel--panel" data-detail-isi <?= !($detailBuka && $detailPembayaran !== null) ? 'hidden' : '' ?>>
            <div><dt>Periode Tagihan</dt><dd data-detail-periode><?= $detailPembayaran ? e(format_periode($detailPembayaran['periode_tagihan'])) : '' ?></dd></div>
            <div><dt>Nomor Kamar</dt><dd data-detail-kamar><?= $detailPembayaran ? 'Kamar ' . e($detailPembayaran['nomor_kamar']) : '' ?></dd></div>
            <div><dt>Nominal Tagihan</dt><dd data-detail-nominal-tagihan><?= $detailPembayaran ? e(format_rupiah($detailPembayaran['jumlah_tagihan'])) : '' ?></dd></div>
            <div><dt>Tanggal Pembayaran</dt><dd data-detail-tanggal><?= $detailPembayaran ? e(format_tanggal($detailPembayaran['tanggal_bayar'])) : '' ?></dd></div>
            <div><dt>Nominal Dibayar</dt><dd data-detail-nominal-dibayar><?= $detailPembayaran ? e(format_rupiah($detailPembayaran['nominal_dibayar'])) : '' ?></dd></div>
            <div><dt>Metode</dt><dd data-detail-metode><?= $detailPembayaran ? e(label_metode_pembayaran($detailPembayaran['metode'])) : '' ?></dd></div>
            <div data-detail-keterangan-baris <?= ($detailPembayaran['keterangan'] ?? '') === '' ? 'hidden' : '' ?>>
                <dt>Keterangan</dt><dd><?= e($detailPembayaran['keterangan'] ?? '') ?></dd>
            </div>
        </dl>
    </div>
    <div class="modal__foot">
        <a href="riwayat.php" class="btn btn-outline" data-tutup-modal>Tutup</a>
    </div>
</dialog>

<?php require __DIR__ . '/includes/footer.php'; ?>
