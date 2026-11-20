<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/data/pemesanan.php';

header('Cache-Control: no-store');
$judulHalaman = 'Pesan Kamar';
$halamanAktif = 'kamar';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range'=>1]]);
$kamar = $id ? get_kamar_publik_by_id($id) : null;
$selesai = isset($_GET['selesai']);
$tiket = is_string($_GET['tiket'] ?? null) ? $_GET['tiket'] : '';
$kode = $selesai ? ($_SESSION['tanda_terima_kamar'][$tiket] ?? null) : null;
$pesan = '';
$nilai = array_fill_keys(['nama_lengkap','nomor_telepon','email','rencana_tanggal_masuk','catatan_pemesan'], '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $pesan = 'Sesi formulir sudah tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!$kamar || $selesai || !$kamar['dapat_dipesan']) {
        http_response_code(409);
        $pesan = 'Kamar tidak tersedia untuk pemesanan.';
    } else {
        foreach ($nilai as $kunci => $awal) {
            $nilai[$kunci] = is_string($_POST[$kunci] ?? null) ? $_POST[$kunci] : '';
        }
        $sidik = hash('sha256', serialize([$id, $nilai]));
        $terakhir = $_SESSION['konfirmasi_kamar'] ?? [];
        $ulang = ($terakhir['sidik'] ?? '') === $sidik && ($terakhir['waktu'] ?? 0) >= time() - 120;
        try {
            if ($ulang) {
                $tiket = $terakhir['tiket'];
            } else {
                $percobaan = array_values(array_filter($_SESSION['waktu_pengajuan'] ?? [], fn($t) => $t > time() - 600));
                if (count($percobaan) >= 10) {
                    http_response_code(429);
                    throw new DomainException('Terlalu banyak pengajuan. Silakan coba kembali dalam sepuluh menit.');
                }
                $percobaan[] = time();
                $_SESSION['waktu_pengajuan'] = $percobaan;
                $kode = ajukan_pemesanan($id, $_POST);
                $tiket = bin2hex(random_bytes(16));
                $_SESSION['tanda_terima_kamar'][$tiket] = $kode;
                $_SESSION['konfirmasi_kamar'] = ['tiket'=>$tiket, 'sidik'=>$sidik, 'waktu'=>time()];
            }
            header('Location: pesan-kamar.php?selesai=1&tiket=' . $tiket, true, 303);
            exit;
        } catch (DomainException $e) {
            if (http_response_code() !== 429) {
                http_response_code(422);
            }
            $pesan = $e->getMessage();
            $kamar = get_kamar_publik_by_id($id);
        } catch (PDOException $e) {
            http_response_code(503);
            error_log('[Kos Aron] Penyimpanan pemesanan gagal.');
            $pesan = 'Pengajuan belum dapat diproses. Silakan coba kembali.';
        }
    }
} elseif (($selesai && !$kode) || (!$selesai && !$kamar)) {
    http_response_code(404);
}
require __DIR__ . '/includes/header.php';
?>
<section class="section section--tight">
    <div class="container">
        <nav aria-label="Breadcrumb"><ol class="breadcrumb">
            <li><a href="kamar.php">Daftar Kamar</a></li><li aria-current="page">Pesan Kamar</li>
        </ol></nav>
        <?php if ($pesan !== ''): ?><p class="notice notice--galat" role="alert"><?= e($pesan) ?></p><?php endif; ?>
        <?php if ($selesai && $kode): ?>
            <?php $kontak = link_hubungi_pemilik(); ?>
            <div class="auth-box">
                <h1>Pengajuan diterima</h1>
                <p>Nomor referensi pemesanan Anda <strong id="kode-pemesanan"><?= e($kode) ?></strong>.</p>
                <p>Status saat diajukan: <strong>Menunggu Konfirmasi</strong>.</p>
                <p>Simpan nomor referensi ini. Pemilik akan memeriksa pengajuan sebelum menyetujui pemesanan Anda.</p>
                <a class="btn btn-primary" href="<?= e($kontak['url']) ?>">Hubungi Pemilik</a>
                <a class="btn btn-outline" href="kamar.php">Kembali ke Daftar Kamar</a>
            </div>
        <?php elseif ($selesai || !$kamar || !$kamar['dapat_dipesan']): ?>
            <div class="empty-state empty-state--lg">
                <h1><?= $selesai ? 'Konfirmasi tidak ditemukan' : 'Kamar tidak tersedia' ?></h1>
                <p><?= $selesai ? 'Konfirmasi hanya tersedia pada sesi yang mengajukan pemesanan.' : 'Silakan periksa pilihan kamar lain yang masih tersedia.' ?></p>
                <a class="btn btn-primary" href="kamar.php">Kembali ke Daftar Kamar</a>
            </div>
        <?php else: ?>
            <?php $foto = daftar_foto_kamar($kamar)[0] ?? FOTO_PLACEHOLDER; ?>
            <div class="detail-kamar">
                <div class="detail-kamar__info">
                    <h1>Pesan Kamar <?= e($kamar['nomor']) ?></h1>
                    <div class="foto-frame">
                        <img src="<?= e($foto) ?>" alt="<?= foto_adalah_ilustrasi($foto) ? 'Ilustrasi' : 'Foto' ?> kamar <?= e($kamar['nomor']) ?>" loading="lazy" data-fallback>
                        <?php if (foto_adalah_ilustrasi($foto)): ?><small class="keterangan-ilustrasi">Foto ilustrasi</small><?php endif; ?>
                    </div>
                    <p class="detail-kamar__harga"><?= e(format_rupiah($kamar['harga'])) ?> <span>/ bulan</span></p>
                    <p><span class="badge badge--kosong">Tersedia</span></p>
                    <p>Fasilitas: <?= e(implode(', ', $kamar['fasilitas']) ?: 'Belum dicantumkan') ?></p>
                </div>
                <form method="post" action="pesan-kamar.php?id=<?= (int)$id ?>" class="auth-box auth-form">
                    <?= csrf_field() ?>
                    <h2>Identitas calon penghuni</h2>
                    <p>Data Anda digunakan pemilik kos untuk memeriksa dan menindaklanjuti pengajuan kamar ini.</p>
                    <div class="form-group"><label for="nama">Nama lengkap</label>
                        <input class="form-control" name="nama_lengkap" id="nama" maxlength="100" autocomplete="name" required value="<?= e($nilai['nama_lengkap']) ?>"></div>
                    <div class="form-group"><label for="telepon">Nomor WhatsApp / telepon</label>
                        <input class="form-control" type="tel" name="nomor_telepon" id="telepon" maxlength="20" pattern="\+?(?:[0-9][ \-]?){7,14}[0-9]" title="Isi 8–15 digit. Boleh memakai awalan +, spasi, atau tanda hubung." autocomplete="tel" required value="<?= e($nilai['nomor_telepon']) ?>"></div>
                    <div class="form-group"><label for="email">Email (opsional)</label>
                        <input class="form-control" type="email" id="email" name="email" autocomplete="email" maxlength="100" value="<?= e($nilai['email']) ?>"></div>
                    <div class="form-group"><label for="tanggal">Rencana tanggal masuk</label>
                        <input class="form-control" type="date" id="tanggal" name="rencana_tanggal_masuk" min="<?= e(tanggal_hari_ini()) ?>" required value="<?= e($nilai['rencana_tanggal_masuk']) ?>"></div>
                    <div class="form-group"><label for="catatan">Catatan tambahan (opsional)</label>
                        <textarea class="form-control" id="catatan" name="catatan_pemesan" rows="3" maxlength="500"><?= e($nilai['catatan_pemesan']) ?></textarea></div>
                    <button class="btn btn-primary btn-block" type="submit">Ajukan Pemesanan</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
