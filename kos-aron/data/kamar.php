<?php
/**
 * DATA DUMMY KAMAR
 * ----------------------------------------------------------------
 * Semua harga, fasilitas, dan deskripsi di bawah ini hanya CONTOH
 * untuk keperluan pengembangan tampilan, bukan data asli Kos Aron.
 *
 * Nanti, isi fungsi-fungsi di bawah cukup diganti dengan query MySQL
 * (misalnya via PDO) tanpa perlu mengubah halaman yang memanggilnya,
 * selama struktur array yang dikembalikan tetap sama.
 *
 * Struktur satu kamar:
 *   id         int      ID unik
 *   nomor      string   Nomor kamar
 *   harga      int      Harga sewa per bulan (Rupiah)
 *   fasilitas  string[] Daftar fasilitas
 *   deskripsi  string   Deskripsi singkat
 *   foto       string   Nama file di assets/images/kamar/
 *   galeri     string[] Foto tambahan (opsional), juga di assets/images/kamar/.
 *                       Hanya file yang benar-benar ada yang ditampilkan.
 *   status     string   'kosong' | 'terisi'
 */

function data_kamar(): array
{
    return [
        [
            'id'        => 1,
            'nomor'     => '01',
            'harga'     => 500000,
            'fasilitas' => ['Kasur', 'Lemari', 'Meja', 'Kamar mandi luar'],
            'deskripsi' => 'Contoh deskripsi kamar. Isi dengan keterangan kamar yang sebenarnya.',
            'foto'      => 'kamar-01.jpg',
            'galeri'    => ['kamar-01-2.jpg', 'kamar-01-3.jpg'],
            'status'    => 'kosong',
        ],
        [
            'id'        => 2,
            'nomor'     => '02',
            'harga'     => 500000,
            'fasilitas' => ['Kasur', 'Lemari', 'Meja', 'Kamar mandi luar'],
            'deskripsi' => 'Contoh deskripsi kamar. Isi dengan keterangan kamar yang sebenarnya.',
            'foto'      => 'kamar-02.jpg',
            'galeri'    => [],
            'status'    => 'terisi',
        ],
        [
            'id'        => 3,
            'nomor'     => '03',
            'harga'     => 600000,
            'fasilitas' => ['Kasur', 'Lemari', 'Meja', 'Kipas angin', 'Kamar mandi dalam'],
            'deskripsi' => 'Contoh deskripsi kamar. Isi dengan keterangan kamar yang sebenarnya.',
            'foto'      => 'kamar-03.jpg',
            'galeri'    => [],
            'status'    => 'kosong',
        ],
        [
            'id'        => 4,
            'nomor'     => '04',
            'harga'     => 600000,
            'fasilitas' => ['Kasur', 'Lemari', 'Meja', 'Kipas angin', 'Kamar mandi dalam'],
            'deskripsi' => 'Contoh deskripsi kamar. Isi dengan keterangan kamar yang sebenarnya.',
            'foto'      => 'kamar-04.jpg',
            'galeri'    => [],
            'status'    => 'terisi',
        ],
        [
            'id'        => 5,
            'nomor'     => '05',
            'harga'     => 550000,
            'fasilitas' => ['Kasur', 'Lemari', 'Kipas angin', 'Kamar mandi luar'],
            'deskripsi' => 'Contoh deskripsi kamar. Isi dengan keterangan kamar yang sebenarnya.',
            'foto'      => 'kamar-05.jpg',
            'galeri'    => [],
            'status'    => 'kosong',
        ],
        [
            'id'        => 6,
            'nomor'     => '06',
            'harga'     => 550000,
            'fasilitas' => ['Kasur', 'Lemari', 'Kipas angin', 'Kamar mandi luar'],
            'deskripsi' => 'Contoh deskripsi kamar. Isi dengan keterangan kamar yang sebenarnya.',
            'foto'      => 'kamar-06.jpg',
            'galeri'    => [],
            'status'    => 'terisi',
        ],
    ];
}

/** Semua kamar. */
function get_semua_kamar(): array
{
    return data_kamar();
}

/** Satu kamar berdasarkan id, atau null jika tidak ditemukan. */
function get_kamar_by_id(int $id): ?array
{
    foreach (data_kamar() as $kamar) {
        if ($kamar['id'] === $id) {
            return $kamar;
        }
    }
    return null;
}

/**
 * Kamar berstatus kosong (tersedia), dibatasi sejumlah $batas.
 * Nanti setara dengan: SELECT ... WHERE status = 'kosong' LIMIT ?
 */
function get_kamar_tersedia(int $batas): array
{
    $tersedia = array_filter(data_kamar(), fn ($k) => $k['status'] === 'kosong');
    return array_slice(array_values($tersedia), 0, $batas);
}
