<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../data/tagihan.php'; // juga memuat data penghuni & kamar

$judulHalaman = 'Data Tagihan';
$menuAktif    = 'tagihan';

$daftarTagihan = get_semua_tagihan();
$totalTagihan  = count($daftarTagihan);
$daftarPeriode = daftar_periode_tagihan();

// Tagihan hanya untuk penghuni dengan penyewaan aktif.
$penghuniAktif = array_values(array_filter(get_semua_penghuni(), fn ($p) => $p['status'] === 'aktif'));

/*
 * PROTOTYPE UI: tidak ada penyimpanan / penghapusan / pembuatan tagihan otomatis.
 * Isian diperiksa sesuai aturan rancangan, lalu dijawab dengan pesan "belum disimpan".
 * Nanti (backend): validasi CSRF, INSERT/UPDATE/DELETE tabel tagihan, dengan
 * UNIQUE (penghuni_id, periode) di database untuk mencegah tagihan ganda.
 */
$metode     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$aksi       = $metode === 'POST' ? ($_POST['aksi'] ?? '') : '';
$pesanAksi  = null;
$modal      = null; // null = modal tertutup saat halaman dimuat

$nilaiKosong = ['id' => '', 'penghuni_id' => '', 'periode' => '', 'jumlah' => '', 'jatuh_tempo' => '', 'keterangan' => ''];

if ($aksi === 'hapus') {
    $idHapus = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $target  = $idHapus ? get_tagihan_by_id($idHapus) : null;
    if ($target === null) {
        $pesanAksi = 'Data tagihan tidak ditemukan.';
    } elseif ($target['status'] === 'lunas') {
        $pesanAksi = 'Tagihan yang sudah lunas tidak dapat dihapus.';
    } else {
        $pesanAksi = 'Tagihan ' . $target['nama_penghuni'] . ' periode ' . format_periode($target['periode'])
                   . ' tidak dihapus. Fitur hapus akan aktif setelah integrasi backend.';
    }
} elseif ($aksi === 'simpan') {
    $nilai = [];
    foreach (array_keys($nilaiKosong) as $kunci) {
        $nilai[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }
    $galat  = [];
    $idEdit = ctype_digit($nilai['id']) ? (int) $nilai['id'] : null;

    if ($idEdit !== null) {
        $lama = get_tagihan_by_id($idEdit);
        if ($lama === null) {
            $galat[] = 'Tagihan yang diedit tidak ditemukan.';
        } elseif ($lama['status'] === 'lunas') {
            $galat[] = 'Tagihan yang sudah lunas tidak dapat diubah.';
        }
    }

    $penghuni = ctype_digit($nilai['penghuni_id']) ? get_penghuni_by_id((int) $nilai['penghuni_id']) : null;
    if ($penghuni === null || $penghuni['status'] !== 'aktif') {
        $galat[] = 'Pilih penghuni dengan penyewaan aktif.';
    }
    $periodeValid = (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $nilai['periode']);
    if (!$periodeValid) {
        $galat[] = 'Periode tagihan tidak valid (format bulan dan tahun).';
    }
    if (!ctype_digit($nilai['jumlah']) || (int) $nilai['jumlah'] < 1 || (int) $nilai['jumlah'] > 100000000) {
        $galat[] = 'Nominal tagihan harus berupa angka lebih dari 0.';
    }
    if (format_tanggal($nilai['jatuh_tempo']) === '-') {
        $galat[] = 'Tanggal jatuh tempo tidak valid.';
    } elseif ($periodeValid && $nilai['jatuh_tempo'] < $nilai['periode'] . '-01') {
        $galat[] = 'Tanggal jatuh tempo tidak boleh sebelum awal periode tagihan.';
    }
    if (mb_strlen($nilai['keterangan']) > 255) {
        $galat[] = 'Keterangan maksimal 255 karakter.';
    }
    if ($penghuni && $periodeValid && cari_tagihan_periode($penghuni['id'], $nilai['periode'], $idEdit) !== null) {
        $galat[] = 'Tagihan ' . $penghuni['nama'] . ' untuk periode ' . format_periode($nilai['periode'])
                 . ' sudah ada. Satu penghuni hanya boleh memiliki satu tagihan per periode.';
    }

    $modal = ['mode' => $idEdit !== null ? 'edit' : 'baru', 'nilai' => $nilai, 'galat' => $galat, 'dikirim' => true];
} elseif (isset($_GET['form'])) {
    // Tanpa JavaScript: buka form lewat ?form=baru
    $modal = ['mode' => 'baru', 'nilai' => $nilaiKosong, 'galat' => [], 'dikirim' => false];
} elseif (isset($_GET['edit'])) {
    $idEdit = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $lama   = $idEdit ? get_tagihan_by_id($idEdit) : null;
    if ($lama === null) {
        $pesanAksi = 'Data tagihan tidak ditemukan.';
    } elseif ($lama['status'] === 'lunas') {
        $pesanAksi = 'Tagihan yang sudah lunas tidak dapat diubah.';
    } else {
        $modal = ['mode' => 'edit', 'galat' => [], 'dikirim' => false,
                  'nilai' => array_map('strval', array_intersect_key($lama, $nilaiKosong))];
    }
}

$nilaiForm = $modal['nilai'] ?? $nilaiKosong;

// Data untuk pemeriksaan tagihan ganda di sisi browser (server tetap memeriksa ulang).
$tagihanAda = array_map(fn ($t) => $t['penghuni_id'] . '|' . $t['periode'] . '|' . $t['id'], data_tagihan());

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Tagihan</h1>
        <p>Kelola tagihan sewa bulanan penghuni Kos Aron.</p>
    </div>
    <a href="tagihan.php?form=baru#modal-tagihan" class="btn btn-primary" data-buka-tagihan>Buat Tagihan</a>
</div>

<?php if ($pesanAksi !== null): ?>
    <p class="notice notice--muted" role="status"><?= e($pesanAksi) ?></p>
<?php endif; ?>

<section class="panel">
    <?php if ($totalTagihan === 0): ?>
        <p class="panel__kosong">Belum ada data tagihan. Gunakan tombol "Buat Tagihan" untuk menambahkan.</p>
    <?php else: ?>
        <form class="toolbar" role="search" aria-label="Filter data tagihan" data-filter-tabel="tabel-tagihan" data-filter-satuan="tagihan">
            <div class="filter-field filter-field--cari">
                <label for="cari-tagihan">Cari nama penghuni</label>
                <input type="search" id="cari-tagihan" name="cari" class="form-control" placeholder="Contoh: Budi" autocomplete="off">
            </div>
            <div class="filter-field">
                <label for="filter-periode">Periode</label>
                <select id="filter-periode" name="periode" class="form-control">
                    <option value="semua">Semua periode</option>
                    <?php foreach ($daftarPeriode as $periode): ?>
                        <option value="<?= e($periode) ?>"><?= e(format_periode($periode)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="filter-status">Status pembayaran</label>
                <select id="filter-status" name="status" class="form-control">
                    <option value="semua">Semua status</option>
                    <option value="belum_bayar">Belum Bayar</option>
                    <option value="terlambat">Terlambat</option>
                    <option value="lunas">Lunas</option>
                </select>
            </div>
            <p class="filter-info" aria-live="polite" data-filter-info>
                Menampilkan <?= $totalTagihan ?> dari <?= $totalTagihan ?> tagihan
            </p>
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
                        <?php
                        $label    = $t['nama_penghuni'] . ' ' . format_periode($t['periode']);
                        $dataEdit = json_encode(array_intersect_key($t, $nilaiKosong), JSON_UNESCAPED_UNICODE);
                        ?>
                        <tr data-baris data-status="<?= e($t['status']) ?>" data-periode="<?= e($t['periode']) ?>" data-cari="<?= e(mb_strtolower($t['nama_penghuni'])) ?>">
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
                                <?php if ($t['status'] === 'lunas'): ?>
                                    <span class="tabel__sub" title="Tagihan yang sudah lunas tidak dapat diubah atau dihapus">Terkunci (lunas)</span>
                                <?php else: ?>
                                    <div class="aksi-grup">
                                        <a href="tagihan.php?edit=<?= (int) $t['id'] ?>#modal-tagihan" class="btn btn-outline btn-sm"
                                           data-edit-tagihan="<?= e($dataEdit) ?>" aria-label="Edit tagihan <?= e($label) ?>">Edit</a>
                                        <form method="post" action="tagihan.php"
                                              data-konfirmasi="Hapus tagihan <?= e($label) ?>? (Fitur hapus belum aktif, data tidak akan berubah.)">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <button type="submit" class="btn btn-hapus btn-sm" aria-label="Hapus tagihan <?= e($label) ?>">Hapus</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="tabel__kosong" data-filter-kosong hidden>
                        <td colspan="8">Tidak ada tagihan yang sesuai dengan filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="teks-catatan">
    Status <strong>Lunas</strong> hanya muncul setelah pembayaran dicatat admin. Status <strong>Terlambat</strong> dihitung otomatis
    dari tanggal jatuh tempo (hari ini: <?= e(format_tanggal(tanggal_hari_ini())) ?>). Buat, edit, dan hapus tagihan akan aktif setelah integrasi backend.
</p>

<!-- Modal Buat / Edit Tagihan -->
<dialog id="modal-tagihan" class="modal" aria-labelledby="modal-tagihan-judul"<?= $modal ? ' open data-buka-otomatis' : '' ?>>
    <form method="post" action="tagihan.php#modal-tagihan" class="modal__form" data-form-tagihan
          data-tagihan-ada="<?= e(json_encode($tagihanAda)) ?>">
        <div class="modal__head">
            <h2 id="modal-tagihan-judul" data-judul-modal><?= ($modal['mode'] ?? 'baru') === 'edit' ? 'Edit Tagihan' : 'Buat Tagihan' ?></h2>
            <a href="tagihan.php" class="modal__tutup" data-tutup-modal aria-label="Tutup form">&times;</a>
        </div>

        <div class="modal__body">
            <?php if (!empty($modal['dikirim']) && $modal['galat']): ?>
                <div class="notice notice--galat" role="alert" data-hasil-form>
                    <strong>Tagihan belum disimpan. Periksa kembali isian berikut:</strong>
                    <ul>
                        <?php foreach ($modal['galat'] as $pesan): ?>
                            <li><?= e($pesan) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (!empty($modal['dikirim'])): ?>
                <p class="notice notice--peringatan" role="status" data-hasil-form>
                    <strong>Isian valid, tetapi tagihan belum disimpan.</strong> Penyimpanan tagihan akan aktif setelah integrasi backend.
                </p>
            <?php endif; ?>
            <p class="teks-catatan modal__info">Form prototype: tidak ada tagihan yang benar-benar dibuat atau dikirim ke penghuni.</p>

            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= e($nilaiForm['id']) ?>">

            <div class="form-grid form-grid--2">
                <div class="form-group">
                    <label for="penghuni_id">Pilih Penghuni</label>
                    <select id="penghuni_id" name="penghuni_id" class="form-control" required>
                        <option value="">Pilih penghuni aktif</option>
                        <?php foreach ($penghuniAktif as $p): ?>
                            <?php $k = get_kamar_by_id($p['kamar_id']); ?>
                            <option value="<?= (int) $p['id'] ?>" data-kamar="<?= e($k['nomor'] ?? '') ?>" data-harga="<?= (int) ($k['harga'] ?? 0) ?>"
                                <?= $nilaiForm['penghuni_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nomor_kamar">Nomor Kamar</label>
                    <?php
                    $kamarTerpilih = '';
                    foreach ($penghuniAktif as $p) {
                        if ($nilaiForm['penghuni_id'] === (string) $p['id']) {
                            $kamarTerpilih = 'Kamar ' . (get_kamar_by_id($p['kamar_id'])['nomor'] ?? '');
                        }
                    }
                    ?>
                    <input type="text" id="nomor_kamar" class="form-control" readonly tabindex="-1"
                           value="<?= e($kamarTerpilih) ?>" placeholder="Terisi otomatis" aria-describedby="kamar-hint">
                    <span id="kamar-hint" class="teks-catatan">Mengikuti kamar penghuni.</span>
                </div>

                <div class="form-group">
                    <label for="periode">Bulan / Periode</label>
                    <input type="month" id="periode" name="periode" class="form-control" required
                           pattern="\d{4}-\d{2}" placeholder="YYYY-MM" value="<?= e($nilaiForm['periode']) ?>">
                    <span class="teks-catatan teks-galat" data-peringatan-ganda hidden></span>
                </div>

                <div class="form-group">
                    <label for="jumlah">Nominal Tagihan (Rp)</label>
                    <input type="number" id="jumlah" name="jumlah" class="form-control" required min="1" step="1"
                           inputmode="numeric" value="<?= e($nilaiForm['jumlah']) ?>" aria-describedby="jumlah-hint">
                    <span id="jumlah-hint" class="teks-catatan">Terisi dari harga kamar, bisa diubah.</span>
                </div>

                <div class="form-group">
                    <label for="jatuh_tempo">Tanggal Jatuh Tempo</label>
                    <input type="date" id="jatuh_tempo" name="jatuh_tempo" class="form-control" required
                           value="<?= e($nilaiForm['jatuh_tempo']) ?>">
                </div>

                <div class="form-group form-grid__penuh">
                    <label for="keterangan">Keterangan <span class="text-muted">(opsional)</span></label>
                    <textarea id="keterangan" name="keterangan" class="form-control" rows="2" maxlength="255"><?= e($nilaiForm['keterangan']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="modal__foot">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="tagihan.php" class="btn btn-outline" data-tutup-modal>Batal</a>
        </div>
    </form>
</dialog>

<?php require __DIR__ . '/includes/footer.php'; ?>
