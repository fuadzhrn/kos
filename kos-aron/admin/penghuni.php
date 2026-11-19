<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin', '../login.php');
require_once __DIR__ . '/../data/penghuni.php'; // juga memuat data kamar

$judulHalaman = 'Data Penghuni';
$menuAktif    = 'penghuni';

$pesanAksi = $_SESSION['pesan_penghuni'] ?? null;
unset($_SESSION['pesan_penghuni']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $pesanAksi = 'Sesi formulir sudah tidak valid. Muat ulang halaman.';
    } elseif (($_POST['aksi'] ?? '') === 'nonaktifkan') {
        try {
            akhiri_penyewaan((int)filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT),
                (int)filter_input(INPUT_POST, 'sewa_id', FILTER_VALIDATE_INT),
                is_string($_POST['tanggal_keluar'] ?? null) ? $_POST['tanggal_keluar'] : '');
            $_SESSION['pesan_penghuni'] = 'Penyewaan selesai. Kamar telah dilepas dan riwayat tersimpan.';
            header('Location: penghuni.php', true, 303);
            exit;
        } catch (DomainException $e) {
            $pesanAksi = $e->getMessage();
        } catch (PDOException $e) {
            $pesanAksi = 'Penyimpanan gagal. Muat ulang halaman dan coba lagi.';
        }
    }
}
$daftarPenghuni = get_semua_penghuni();
$totalPenghuni = count($daftarPenghuni);

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head admin-page-head--aksi">
    <div>
        <h1>Data Penghuni</h1>
        <p>Kelola data penghuni dan penyewaan kamar Kos Aron.</p>
    </div>
    <a href="penghuni-form.php" class="btn btn-primary">Tambah Penghuni</a>
</div>

<?php if ($pesanAksi !== null): ?>
    <p class="notice notice--muted" role="status"><?= e($pesanAksi) ?></p>
<?php endif; ?>

<section class="panel">
    <?php if ($totalPenghuni === 0): ?>
        <p class="panel__kosong">Belum ada data penghuni. Gunakan tombol "Tambah Penghuni" untuk menambahkan.</p>
    <?php else: ?>
        <form class="toolbar" role="search" aria-label="Filter data penghuni" data-filter-tabel="tabel-penghuni" data-filter-satuan="penghuni">
            <div class="filter-field filter-field--cari">
                <label for="cari-penghuni">Cari nama penghuni</label>
                <input type="search" id="cari-penghuni" name="cari" class="form-control" placeholder="Contoh: Siti" autocomplete="off">
            </div>
            <div class="filter-field">
                <label for="status-penghuni">Status</label>
                <select id="status-penghuni" name="status" class="form-control">
                    <option value="semua">Semua</option>
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
            </div>
            <p class="filter-info" aria-live="polite" data-filter-info>
                Menampilkan <?= $totalPenghuni ?> dari <?= $totalPenghuni ?> penghuni
            </p>
        </form>

        <div class="tabel-wrap">
            <table class="tabel tabel--lebar" id="tabel-penghuni">
                <thead>
                    <tr>
                        <th scope="col" class="tabel__no">No</th>
                        <th scope="col">Nama Penghuni</th>
                        <th scope="col">Nomor Kamar</th>
                        <th scope="col">Nomor Telepon</th>
                        <th scope="col">Tanggal Mulai Sewa</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarPenghuni as $i => $p): ?>
                        <tr data-baris data-status="<?= e($p['status']) ?>" data-cari="<?= e(mb_strtolower($p['nama'])) ?>">
                            <td class="tabel__no"><?= $i + 1 ?></td>
                            <td class="tabel__utama">
                                <?= e($p['nama']) ?>
                                <span class="tabel__sub"><?= e($p['akun']) ?></span>
                            </td>
                            <td><?= $p['nomor'] !== null ? 'Kamar ' . e($p['nomor']) : '-' ?></td>
                            <td><?= e($p['no_telepon']) ?></td>
                            <td class="tabel__tanggal"><?= e(format_tanggal($p['tanggal_masuk'])) ?></td>
                            <td>
                                <span class="badge badge--<?= e($p['status']) ?>"><?= e(label_status_penghuni($p['status'])) ?></span>
                            </td>
                            <td>
                                <div class="aksi-grup">
                                    <a href="penghuni-detail.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm"
                                       aria-label="Detail <?= e($p['nama']) ?>">Detail</a>
                                    <a href="penghuni-form.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm"
                                       aria-label="Edit <?= e($p['nama']) ?>">Edit</a>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <form method="post" action="penghuni.php"
                                              data-konfirmasi="Akhiri penyewaan <?= e($p['nama']) ?> pada tanggal yang dipilih?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sewa_id" value="<?= (int)$p['penyewaan_id'] ?>">
                                            <label>Tanggal keluar
                                                <input type="date" class="form-control" name="tanggal_keluar" required
                                                       min="<?= e($p['tanggal_masuk']) ?>" max="<?= e(tanggal_hari_ini()) ?>" value="<?= e(tanggal_hari_ini()) ?>">
                                            </label>
                                            <input type="hidden" name="aksi" value="nonaktifkan">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-hapus btn-sm" aria-label="Akhiri sewa <?= e($p['nama']) ?>">Akhiri Sewa</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="tabel__kosong" data-filter-kosong hidden>
                        <td colspan="7">Tidak ada penghuni yang sesuai dengan pencarian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p class="teks-catatan">Setelah penghuni keluar, admin dapat melihat riwayat atau memulai penyewaan baru melalui Edit.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
