<?php
/**
 * DATA DUMMY PENGHUNI
 * ----------------------------------------------------------------
 * Nama, nomor telepon, dan tanggal di bawah ini FIKTIF, hanya untuk
 * pengembangan tampilan sistem internal. Bukan data penghuni Kos Aron.
 * Nomor telepon sengaja memakai pola 0800-0000-xxxx agar tidak menyerupai
 * nomor seluler milik orang lain.
 *
 * Aturan rancangan:
 * - Satu kamar hanya boleh memiliki SATU penghuni berstatus 'aktif'.
 * - Penghuni aktif menempati kamar berstatus 'terisi' pada data/kamar.php
 *   (kamar 02, 04, 06), sehingga data antarhalaman konsisten.
 * - Penghuni yang keluar TIDAK dihapus: statusnya menjadi 'tidak_aktif'
 *   dan riwayatnya (kamar, tanggal, tagihan) tetap disimpan.
 * - Password akun penghuni TIDAK ada di sini; akun dibuat admin melalui backend
 *   dan password disimpan sebagai hash di database.
 *
 * Struktur satu penghuni:
 *   id              int          ID unik
 *   nama            string       Nama lengkap
 *   no_telepon      string       Nomor telepon
 *   akun            string       Email atau username untuk login
 *   kamar_id        int          ID kamar yang ditempati / pernah ditempati
 *   tanggal_masuk   string       Tanggal mulai sewa (YYYY-MM-DD)
 *   tanggal_keluar  string|null  Tanggal selesai sewa, null jika masih aktif
 *   status          string       'aktif' | 'tidak_aktif'
 */

require_once __DIR__ . '/kamar.php';

function data_penghuni(): array
{
    return [
        ['id' => 1, 'nama' => 'Rizky Pratama', 'no_telepon' => '0800-0000-0001', 'akun' => 'rizky.pratama',
         'kamar_id' => 2, 'tanggal_masuk' => '2026-03-01', 'tanggal_keluar' => null, 'status' => 'aktif'],
        ['id' => 2, 'nama' => 'Siti Aminah', 'no_telepon' => '0800-0000-0002', 'akun' => 'siti.aminah',
         'kamar_id' => 4, 'tanggal_masuk' => '2026-06-15', 'tanggal_keluar' => null, 'status' => 'aktif'],
        ['id' => 3, 'nama' => 'Budi Santoso', 'no_telepon' => '0800-0000-0003', 'akun' => 'budi.santoso',
         'kamar_id' => 6, 'tanggal_masuk' => '2026-09-01', 'tanggal_keluar' => null, 'status' => 'aktif'],
        ['id' => 4, 'nama' => 'Dewi Lestari', 'no_telepon' => '0800-0000-0004', 'akun' => 'dewi.lestari',
         'kamar_id' => 1, 'tanggal_masuk' => '2025-08-01', 'tanggal_keluar' => '2026-07-31', 'status' => 'tidak_aktif'],
    ];
}

/** Semua penghuni (aktif dan tidak aktif). */
function get_semua_penghuni(): array
{
    return data_penghuni();
}

/** Satu penghuni berdasarkan id, atau null. */
function get_penghuni_by_id(int $id): ?array
{
    foreach (data_penghuni() as $penghuni) {
        if ($penghuni['id'] === $id) {
            return $penghuni;
        }
    }
    return null;
}

/**
 * Penghuni AKTIF yang menempati kamar tertentu, atau null jika tidak ada.
 * Penghuni tidak aktif (riwayat) tidak dihitung.
 */
function get_penghuni_by_kamar(int $kamarId): ?array
{
    foreach (data_penghuni() as $penghuni) {
        if ($penghuni['kamar_id'] === $kamarId && $penghuni['status'] === 'aktif') {
            return $penghuni;
        }
    }
    return null;
}

/**
 * Penghuni yang sedang "login" di sisi Penghuni.
 * BELUM ada sistem login sungguhan -- untuk sementara selalu mengembalikan
 * satu penghuni CONTOH. Dipilih id 2 (Siti Aminah) karena datanya mencakup
 * riwayat pembayaran DAN tagihan yang belum lunas, sehingga semua bagian
 * Dashboard Penghuni (info, status tagihan, riwayat, notifikasi) punya isi
 * untuk ditampilkan. Jika penghuni id 2 tidak ditemukan atau sudah tidak
 * aktif, jatuh kembali ke penghuni aktif pertama.
 * Nanti diganti penghuni dari $_SESSION setelah fitur login dibuat.
 */
function get_penghuni_login(): ?array
{
    $dipilih = get_penghuni_by_id(2);
    if ($dipilih !== null && $dipilih['status'] === 'aktif') {
        return $dipilih;
    }

    $aktif = array_values(array_filter(get_semua_penghuni(), fn ($p) => $p['status'] === 'aktif'));
    return $aktif[0] ?? null;
}

/**
 * Apakah kamar sudah ditempati penghuni aktif lain (atau berstatus 'terisi')?
 * Dipakai untuk aturan "satu kamar = satu penyewaan aktif".
 *
 * @param int      $kamarId        Kamar yang diperiksa
 * @param int|null $kecualiPenghuni ID penghuni yang sedang diedit (boleh tetap di kamarnya)
 * @return string|null Alasan kamar tidak bisa dipilih, atau null jika bisa
 */
function alasan_kamar_tidak_tersedia(int $kamarId, ?int $kecualiPenghuni = null): ?string
{
    $penghuni = get_penghuni_by_kamar($kamarId);
    if ($penghuni !== null && $penghuni['id'] !== $kecualiPenghuni) {
        return 'ditempati ' . $penghuni['nama'];
    }

    $kamar = get_kamar_by_id($kamarId);
    if ($kamar !== null && $kamar['status'] === 'terisi' && $penghuni === null) {
        return 'berstatus terisi';
    }
    return null;
}
