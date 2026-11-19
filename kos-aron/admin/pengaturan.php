<?php
require_once __DIR__ . '/../includes/config.php';

$judulHalaman = 'Pengaturan';
$menuAktif    = 'pengaturan';
$namaAdminAkun = 'Admin Kos Aron'; // dummy -- sama dengan yang tampil di topbar (includes/header.php)

/*
 * PROTOTYPE UI: TIDAK ADA penyimpanan nyata di kedua form berikut.
 * Konstanta di includes/config.php TIDAK bisa diubah dari sini (PHP const
 * memang tidak bisa ditulis ulang saat runtime) -- form ini hanya menampilkan
 * nilainya SEKARANG dan memeriksa bentuk isian baru, tanpa menyimpan apa pun.
 * Nanti (backend): kolom pengaturan di tabel `pengaturan` (satu baris) untuk
 * info kos, dan verifikasi password lama + password_hash() untuk password admin.
 */
$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$aksi   = $metode === 'POST' ? ($_POST['aksi'] ?? '') : '';

// --- Form Informasi Kos: default-nya tampilkan konfigurasi saat ini. ---
$nilaiKos = [
    'nama_kos'    => SITE_NAME,
    'deskripsi'   => TENTANG_DESKRIPSI_TAMBAHAN,
    'alamat'      => ALAMAT_LENGKAP,
    'whatsapp'    => WHATSAPP_NUMBER,
    'maps_link'   => MAPS_LINK_URL,
    'maps_embed'  => MAPS_EMBED_URL,
];
$galatKos   = [];
$dikirimKos = false;

if ($aksi === 'simpan_kos') {
    $dikirimKos = true;
    foreach (array_keys($nilaiKos) as $kunci) {
        $nilaiKos[$kunci] = trim((string) ($_POST[$kunci] ?? ''));
    }

    if ($nilaiKos['nama_kos'] === '' || mb_strlen($nilaiKos['nama_kos']) > 100) {
        $galatKos[] = 'Nama kos wajib diisi (maksimal 100 karakter).';
    }
    if (mb_strlen($nilaiKos['deskripsi']) > 500) {
        $galatKos[] = 'Deskripsi kos maksimal 500 karakter.';
    }
    if ($nilaiKos['whatsapp'] !== '' && !preg_match('/^[0-9]{8,15}$/', $nilaiKos['whatsapp'])) {
        $galatKos[] = 'Nomor WhatsApp hanya boleh angka (kode negara tanpa +), 8 sampai 15 digit. Contoh: 62812xxxxxxx.';
    }
    if ($nilaiKos['maps_link'] !== '' && !str_starts_with($nilaiKos['maps_link'], 'https://')) {
        $galatKos[] = 'Link Google Maps harus diawali https://';
    }
    if ($nilaiKos['maps_embed'] !== '' && !str_starts_with($nilaiKos['maps_embed'], 'https://www.google.com/maps/embed')) {
        $galatKos[] = 'Kode sematan peta harus URL embed resmi Google Maps (diawali https://www.google.com/maps/embed).';
    }
}

// --- Form Ubah Password Admin ---
// Nilai yang diketik TIDAK PERNAH disimpan ke variabel lain, TIDAK ditulis ke
// log, dan TIDAK dikembalikan ke form (field password selalu dikosongkan lagi).
$galatPassword   = [];
$dikirimPassword = false;

if ($aksi === 'ubah_password') {
    $dikirimPassword = true;
    $passwordLama       = (string) ($_POST['password_lama'] ?? '');
    $passwordBaru        = (string) ($_POST['password_baru'] ?? '');
    $konfirmasiPassword  = (string) ($_POST['konfirmasi_password'] ?? '');

    if ($passwordLama === '') {
        $galatPassword[] = 'Password lama wajib diisi.';
    }
    if (mb_strlen($passwordBaru) < 8) {
        $galatPassword[] = 'Password baru minimal 8 karakter.';
    }
    if ($passwordBaru !== $konfirmasiPassword) {
        $galatPassword[] = 'Konfirmasi password baru tidak sama dengan password baru.';
    }

    unset($passwordLama, $passwordBaru, $konfirmasiPassword);
}

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-head">
    <h1>Pengaturan</h1>
    <p>Informasi Kos Aron yang tampil di website publik, dan akun admin.</p>
</div>

<!-- Informasi Kos -->
<?php if ($dikirimKos && $galatKos): ?>
    <div class="notice notice--galat" role="alert">
        <strong>Pengaturan belum disimpan. Periksa kembali isian berikut:</strong>
        <ul>
            <?php foreach ($galatKos as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($dikirimKos): ?>
    <p class="notice notice--peringatan" role="status">
        <strong>Isian valid, tetapi pengaturan belum disimpan.</strong> Penyimpanan pengaturan kos akan aktif setelah integrasi backend.
    </p>
<?php else: ?>
    <p class="notice">Form di bawah menampilkan konfigurasi website saat ini (includes/config.php). Penyimpanan perubahan akan aktif setelah integrasi backend.</p>
<?php endif; ?>

<form method="post" action="pengaturan.php" class="panel form-panel form-panel--berkepala mt-section">
    <div class="panel__head">
        <h2>Informasi Kos</h2>
    </div>
    <div class="form-panel__body">
        <input type="hidden" name="aksi" value="simpan_kos">

        <div class="form-grid form-grid--2">
            <div class="form-group">
                <label for="nama_kos">Nama Kos</label>
                <input type="text" id="nama_kos" name="nama_kos" class="form-control" maxlength="100" required
                       value="<?= e($nilaiKos['nama_kos']) ?>">
            </div>

            <div class="form-group">
                <label for="whatsapp">Nomor WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" class="form-control" inputmode="numeric"
                       aria-describedby="whatsapp-hint" placeholder="Contoh: 62812xxxxxxx" value="<?= e($nilaiKos['whatsapp']) ?>">
                <span id="whatsapp-hint" class="teks-catatan">Kode negara tanpa tanda +. Kosongkan jika belum ada.</span>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="alamat">Alamat</label>
                <input type="text" id="alamat" name="alamat" class="form-control" maxlength="200"
                       placeholder="Contoh: Jl. ..., Kolor, Sumenep" value="<?= e($nilaiKos['alamat']) ?>">
            </div>

            <div class="form-group form-grid__penuh">
                <label for="deskripsi">Deskripsi Kos</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" maxlength="500"
                          aria-describedby="deskripsi-hint"><?= e($nilaiKos['deskripsi']) ?></textarea>
                <span id="deskripsi-hint" class="teks-catatan">Tampil di halaman Tentang website publik.</span>
            </div>

            <div class="form-group">
                <label for="maps_link">Link Google Maps</label>
                <input type="text" id="maps_link" name="maps_link" class="form-control" inputmode="url"
                       placeholder="https://maps.google.com/..." value="<?= e($nilaiKos['maps_link']) ?>">
            </div>

            <div class="form-group">
                <label for="maps_embed">Kode Sematan Peta (Embed)</label>
                <input type="text" id="maps_embed" name="maps_embed" class="form-control" inputmode="url"
                       aria-describedby="maps-embed-hint" placeholder="https://www.google.com/maps/embed?..." value="<?= e($nilaiKos['maps_embed']) ?>">
                <span id="maps-embed-hint" class="teks-catatan">Dari Google Maps &gt; Bagikan &gt; Sematkan peta.</span>
            </div>

            <div class="form-group form-grid__penuh">
                <label for="foto_kos">Foto Utama Kos</label>
                <div class="foto-sekarang">
                    <img src="../<?= e(url_foto(FOTO_BANGUNAN_KOS, PATH_FOTO_KOS)) ?>" alt="Foto utama Kos Aron saat ini"
                         data-fallback="../<?= e(FOTO_PLACEHOLDER) ?>">
                    <span class="teks-catatan">Foto saat ini. Kosongkan pilihan file jika tidak ingin mengganti.</span>
                </div>
                <input type="file" id="foto_kos" name="foto_kos" class="form-control" accept="image/jpeg,image/png,image/webp"
                       aria-describedby="foto-kos-hint">
                <span id="foto-kos-hint" class="teks-catatan">Format JPG, PNG, atau WebP. Tampil di halaman Beranda dan Tentang.</span>
            </div>
        </div>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </div>
</form>

<!-- Informasi Akun Admin -->
<section class="panel mt-section">
    <div class="panel__head">
        <h2>Informasi Akun Admin</h2>
    </div>
    <dl class="info-tabel info-tabel--panel">
        <div>
            <dt>Nama Admin</dt>
            <dd><?= e($namaAdminAkun) ?></dd>
        </div>
        <div>
            <dt>Peran</dt>
            <dd>Administrator</dd>
        </div>
    </dl>
</section>

<!-- Ubah Password Admin -->
<?php if ($dikirimPassword && $galatPassword): ?>
    <div class="notice notice--galat mt-section" role="alert">
        <strong>Password belum diubah. Periksa kembali isian berikut:</strong>
        <ul>
            <?php foreach ($galatPassword as $pesan): ?>
                <li><?= e($pesan) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($dikirimPassword): ?>
    <p class="notice notice--peringatan mt-section" role="status">
        <strong>Isian valid, tetapi password belum diubah.</strong> Verifikasi password lama dan penyimpanan password baru akan aktif setelah integrasi backend.
    </p>
<?php endif; ?>

<form method="post" action="pengaturan.php" class="panel form-panel form-panel--berkepala mt-section" data-form-password autocomplete="off">
    <div class="panel__head">
        <h2>Ubah Password</h2>
    </div>
    <div class="form-panel__body">
        <input type="hidden" name="aksi" value="ubah_password">
        <p class="teks-catatan">Password tidak ditampilkan di mana pun dan belum diverifikasi atau disimpan pada tahap ini.</p>

        <div class="form-grid">
            <div class="form-group">
                <label for="password_lama">Password Lama</label>
                <div class="input-password">
                    <input type="password" id="password_lama" name="password_lama" class="form-control"
                           autocomplete="current-password" required>
                    <button type="button" class="input-password__toggle" aria-controls="password_lama" data-toggle-password>Tampilkan</button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_baru">Password Baru</label>
                <div class="input-password">
                    <input type="password" id="password_baru" name="password_baru" class="form-control"
                           autocomplete="new-password" minlength="8" required aria-describedby="password-baru-hint">
                    <button type="button" class="input-password__toggle" aria-controls="password_baru" data-toggle-password>Tampilkan</button>
                </div>
                <span id="password-baru-hint" class="teks-catatan">Minimal 8 karakter.</span>
            </div>

            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                <div class="input-password">
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control"
                           autocomplete="new-password" minlength="8" required>
                    <button type="button" class="input-password__toggle" aria-controls="konfirmasi_password" data-toggle-password>Tampilkan</button>
                </div>
                <span class="teks-catatan teks-galat" data-peringatan-konfirmasi hidden></span>
            </div>
        </div>

        <div class="form-aksi">
            <button type="submit" class="btn btn-primary">Ubah Password</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
