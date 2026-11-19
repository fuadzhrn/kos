<?php
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/upload.php';

function pengaturan_website(): array
{
    $nilai = ['nama_kos' => 'Kos Aron', 'deskripsi_kos' => '', 'alamat' => '', 'whatsapp' => '',
        'maps_link' => '', 'maps_embed' => '', 'foto_utama' => 'bangunan.jpg'];
    $tersimpan = koneksi_database()->query('SELECT kunci, nilai FROM pengaturan')->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_replace($nilai, array_intersect_key(array_filter($tersimpan, 'is_string'), $nilai));
}

function maps_diizinkan(string $url, bool $embed = false): bool
{
    if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || str_contains($url, chr(92))) {
        return false;
    }
    $bagian = parse_url($url);
    if (($bagian['scheme'] ?? '') !== 'https' || isset($bagian['user']) || isset($bagian['pass']) || isset($bagian['port'])) {
        return false;
    }
    $host = strtolower($bagian['host'] ?? '');
    $path = $bagian['path'] ?? '/';
    if ($embed) {
        return $host === 'www.google.com' && ($path === '/maps/embed' || str_starts_with($path, '/maps/embed/'));
    }
    if (in_array($host, ['google.com','www.google.com','google.co.id','www.google.co.id'], true)) {
        return $path === '/maps' || str_starts_with($path, '/maps/');
    }
    return (in_array($host, ['maps.google.com','maps.google.co.id'], true) && (in_array($path, ['/','/maps'], true) || str_starts_with($path, '/maps/')))
        || ($host === 'maps.app.goo.gl' && preg_match('~^/[a-zA-Z0-9]+/?$~D', $path))
        || ($host === 'goo.gl' && preg_match('~^/maps/[a-zA-Z0-9]+/?$~D', $path));
}

function simpan_pengaturan_kos(array $nilai, array $foto, int $adminId): void
{
    $db = koneksi_database();
    $unggah = simpan_foto_upload($foto, PATH_FOTO_KOS);
    if (!$unggah['berhasil']) {
        throw new DomainException($unggah['pesan']);
    }
    $namaBaru = $unggah['nama_file'];
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('INSERT INTO pengaturan (kunci,nilai,updated_oleh) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE nilai = ?, updated_oleh = ?');
        $nilai['deskripsi_kos'] = $nilai['deskripsi'];
        unset($nilai['deskripsi']);
        if ($namaBaru !== null) {
            $nilai['foto_utama'] = $namaBaru;
        }
        ksort($nilai);
        foreach ($nilai as $kunci => $isi) {
            if ($kunci === 'foto_utama') {
                $lama = $db->query("SELECT nilai FROM pengaturan WHERE kunci = 'foto_utama' FOR UPDATE")->fetchColumn();
            }
            $stmt->execute([$kunci, $isi, $adminId, $isi, $adminId]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        hapus_foto_lama($namaBaru, PATH_FOTO_KOS);
        throw $e;
    }
    if (isset($lama) && is_string($lama) && preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/D', $lama)) {
        hapus_foto_lama($lama, PATH_FOTO_KOS);
    }
}
