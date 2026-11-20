<?php
/**
 * Upload foto yang aman (dipakai oleh admin/kamar-form.php).
 * ----------------------------------------------------------------
 * Lapisan pertahanan:
 *   1. Ukuran file dibatasi.
 *   2. Tipe sebenarnya diperiksa lewat fileinfo (finfo_file), BUKAN
 *      Content-Type yang dikirim browser (mudah dipalsukan).
 *   3. getimagesize() dipanggil juga sebagai pemeriksaan tambahan --
 *      file yang bukan gambar sungguhan akan gagal di sini.
 *   4. Nama file baru dibuat ACAK (bin2hex(random_bytes())), bukan dari
 *      nama asli -- mencegah path traversal & tebakan nama file.
 *   5. Ekstensi final ditentukan dari MIME yang terdeteksi, bukan dari
 *      ekstensi asli yang dikirim pengguna.
 *   6. Folder tujuan (assets/images/kamar/) punya .htaccess yang
 *      menolak eksekusi PHP, sebagai pertahanan berlapis.
 */

const MAKS_UKURAN_FOTO_KAMAR = 2 * 1024 * 1024; // 2 MB

const MIME_FOTO_DIIZINKAN = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

function mime_gambar_unggahan(string $path): ?string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!$mime || !isset(MIME_FOTO_DIIZINKAN[$mime])) {
        return null;
    }
    $info = @getimagesize($path);
    return $info && $info['mime'] === $mime ? $mime : null;
}

/**
 * Memproses satu file yang diunggah (dari $_FILES['...']).
 *
 * @param array       $file         Elemen $_FILES['nama_field']
 * @param string      $folderRelatif Folder tujuan relatif ke root project, contoh PATH_FOTO_KAMAR
 * @return array{berhasil:bool, ada_file:bool, nama_file:?string, pesan:?string}
 *         ada_file=false artinya pengguna memang tidak memilih file apa pun
 *         (bukan error -- form boleh dikirim tanpa mengganti foto).
 */
function simpan_foto_upload(array $file, string $folderRelatif): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['berhasil' => true, 'ada_file' => false, 'nama_file' => null, 'pesan' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['berhasil' => false, 'ada_file' => true, 'nama_file' => null, 'pesan' => 'Unggahan foto gagal (kode error: ' . $file['error'] . ').'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['berhasil' => false, 'ada_file' => true, 'nama_file' => null, 'pesan' => 'Unggahan foto tidak valid.'];
    }

    if ($file['size'] <= 0 || $file['size'] > MAKS_UKURAN_FOTO_KAMAR) {
        return ['berhasil' => false, 'ada_file' => true, 'nama_file' => null, 'pesan' => 'Ukuran foto maksimal 2 MB.'];
    }

    $mimeAsli = mime_gambar_unggahan($file['tmp_name']);
    if ($mimeAsli === null) {
        return ['berhasil' => false, 'ada_file' => true, 'nama_file' => null, 'pesan' => 'Format foto harus berupa gambar JPG, PNG, atau WebP yang valid.'];
    }

    $ekstensi     = MIME_FOTO_DIIZINKAN[$mimeAsli];
    $namaBaru     = bin2hex(random_bytes(16)) . '.' . $ekstensi;
    $folderAbsolut = rtrim(dirname(__DIR__) . '/' . $folderRelatif, '/');

    if (!is_dir($folderAbsolut) || !is_writable($folderAbsolut)) {
        return ['berhasil' => false, 'ada_file' => true, 'nama_file' => null, 'pesan' => 'Folder unggahan tidak tersedia. Hubungi admin sistem.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $folderAbsolut . '/' . $namaBaru)) {
        return ['berhasil' => false, 'ada_file' => true, 'nama_file' => null, 'pesan' => 'Gagal menyimpan foto ke server.'];
    }

    return ['berhasil' => true, 'ada_file' => true, 'nama_file' => $namaBaru, 'pesan' => null];
}

/**
 * Menghapus foto lama dari disk dengan aman: HANYA nama file (basename),
 * HANYA di dalam folder yang dimaksud -- tidak pernah menerima path dari
 * input pengguna secara langsung sebagai path absolut.
 */
function hapus_foto_lama(?string $namaFile, string $folderRelatif): void
{
    if ($namaFile === null || $namaFile === '') {
        return;
    }

    $namaAman      = basename($namaFile);
    $folderAbsolut = rtrim(dirname(__DIR__) . '/' . $folderRelatif, '/');
    $path          = $folderAbsolut . '/' . $namaAman;

    if (is_file($path)) {
        @unlink($path);
    }
}
