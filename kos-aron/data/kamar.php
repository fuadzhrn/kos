<?php
/**
 * DATA KAMAR — terhubung ke tabel `kamar` (MySQL) via PDO.
 * ----------------------------------------------------------------
 * Struktur array satu kamar yang dikembalikan (SAMA seperti versi dummy
 * sebelumnya, supaya seluruh halaman yang sudah memanggil fungsi ini
 * TIDAK perlu diubah):
 *   id         int      ID unik
 *   nomor      string   Nomor kamar
 *   harga      int      Harga sewa per bulan (Rupiah)
 *   fasilitas  string[] Daftar fasilitas
 *   deskripsi  string   Deskripsi singkat
 *   foto       string|null  Nama file di assets/images/kamar/
 *   galeri     string[] Foto tambahan (opsional)
 *   kondisi    string   'aktif' | 'perawatan' -- diatur manual oleh admin
 *   status     string   'kosong' | 'terisi' -- SELALU DIHITUNG dari ada/
 *              tidaknya baris `penyewaan` berstatus 'aktif' untuk kamar
 *              ini. TIDAK ADA kolom status yang bisa ditulis manual,
 *              supaya tidak pernah bertentangan dengan data penyewaan
 *              yang sebenarnya (lihat docs/02-rancangan-database.md §2.12).
 *
 * "kondisi" vs "status": kondisi adalah keputusan admin (kamar sedang
 * direnovasi/diperbaiki -> 'perawatan', sehingga tidak boleh disewakan
 * meskipun kosong); status adalah fakta dari data penyewaan yang tidak
 * bisa diatur manual.
 */

require_once __DIR__ . '/../includes/koneksi.php';

/** Klausa SQL yang menghitung status ketersediaan dari tabel penyewaan. */
const SQL_STATUS_KAMAR = "IF(EXISTS(
        SELECT 1 FROM penyewaan s WHERE s.kamar_id = k.id AND s.status = 'aktif'
    ), 'terisi', 'kosong')";

const SQL_KAMAR_DITAHAN = 'EXISTS (SELECT 1 FROM pemesanan b WHERE b.kamar_ditahan_unik = k.id)';

/** Dibaca sesudah baris kamar dikunci oleh transaksi yang mengubah ketersediaan. */
function pemesanan_penahan_kamar(PDO $db, int $kamarId): ?int
{
    $stmt = $db->prepare('SELECT id FROM pemesanan WHERE kamar_ditahan_unik = ? FOR UPDATE');
    $stmt->execute([$kamarId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** Mengubah satu baris hasil query (k.* + alias status_terhitung) menjadi array kamar. */
function baris_kamar_ke_array(array $baris): array
{
    return [
        'id'        => (int) $baris['id'],
        'nomor'     => $baris['nomor'],
        'harga'     => (int) $baris['harga'],
        'deskripsi' => (string) ($baris['deskripsi'] ?? ''),
        'fasilitas' => json_decode($baris['fasilitas'] ?? '[]', true) ?: [],
        'foto'      => $baris['foto_utama'],
        'galeri'    => json_decode($baris['galeri'] ?? '[]', true) ?: [],
        'kondisi'   => $baris['kondisi'],
        'status'    => $baris['status_terhitung'],
        'dapat_dipesan' => $baris['kondisi'] === 'aktif' && $baris['status_terhitung'] === 'kosong' && empty($baris['ditahan']),
    ];
}

/**
 * Semua kamar, terurut berdasarkan nomor.
 *
 * @param bool $sertakanPerawatan true untuk ADMIN (lihat semua kamar apa pun
 *             kondisinya); false (default, untuk halaman PUBLIK) menyembunyikan
 *             kamar berkondisi 'perawatan' -- kamar itu "belum boleh dipublikasikan".
 */
function get_semua_kamar(bool $sertakanPerawatan = false): array
{
    $pdo = koneksi_database();
    $sql = 'SELECT k.*, ' . SQL_STATUS_KAMAR . ' AS status_terhitung, ' . SQL_KAMAR_DITAHAN . ' AS ditahan FROM kamar k';
    if (!$sertakanPerawatan) {
        $sql .= " WHERE k.kondisi = 'aktif'";
    }
    $sql .= ' ORDER BY k.nomor';

    $stmt = $pdo->query($sql);
    return array_map('baris_kamar_ke_array', $stmt->fetchAll());
}

/** Satu kamar berdasarkan id (TANPA memperhatikan kondisi -- untuk admin & lookup internal), atau null. */
function get_kamar_by_id(int $id): ?array
{
    $pdo  = koneksi_database();
    $stmt = $pdo->prepare('SELECT k.*, ' . SQL_STATUS_KAMAR . ' AS status_terhitung, ' . SQL_KAMAR_DITAHAN . ' AS ditahan FROM kamar k WHERE k.id = :id');
    $stmt->execute(['id' => $id]);
    $baris = $stmt->fetch();

    return $baris === false ? null : baris_kamar_ke_array($baris);
}

/**
 * Satu kamar untuk halaman PUBLIK (detail-kamar.php): null juga jika
 * kamar berkondisi 'perawatan' -- kamar itu tidak dipublikasikan,
 * termasuk lewat tautan langsung.
 */
function get_kamar_publik_by_id(int $id): ?array
{
    $kamar = get_kamar_by_id($id);
    return ($kamar !== null && $kamar['kondisi'] === 'aktif') ? $kamar : null;
}

/**
 * Kamar yang BENAR-BENAR bisa disewa sekarang: kondisi 'aktif' DAN
 * tidak ada penyewaan aktif (status kosong), dibatasi sejumlah $batas.
 * Dipakai di beranda publik.
 */
function get_kamar_tersedia(int $batas): array
{
    $pdo  = koneksi_database();
    $stmt = $pdo->prepare(
        "SELECT k.*, 'kosong' AS status_terhitung FROM kamar k
         WHERE k.kondisi = 'aktif'
           AND NOT EXISTS (SELECT 1 FROM penyewaan s WHERE s.kamar_id = k.id AND s.status = 'aktif')
           AND NOT " . SQL_KAMAR_DITAHAN . "
         ORDER BY k.nomor
         LIMIT :batas"
    );
    $stmt->bindValue('batas', $batas, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('baris_kamar_ke_array', $stmt->fetchAll());
}

/**
 * Jumlah kamar per status (untuk admin, mencakup SEMUA kamar apa pun kondisinya).
 *
 * Kamar kosong berkondisi aktif dihitung terpisah dari perawatan. Pemesanan
 * yang disetujui tetap dihitung kosong sampai penyewaan aktif dimulai.
 *
 * @return array{total: int, kosong: int, terisi: int, perawatan: int}
 */
function hitung_kamar_per_status(): array
{
    $pdo   = koneksi_database();
    $baris = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(" . SQL_STATUS_KAMAR . " = 'terisi') AS terisi,
            SUM(kondisi = 'perawatan' AND " . SQL_STATUS_KAMAR . " = 'kosong') AS perawatan
         FROM kamar k"
    )->fetch();

    $total     = (int) $baris['total'];
    $terisi    = (int) $baris['terisi'];
    $perawatan = (int) $baris['perawatan'];

    return ['total' => $total, 'kosong' => $total - $terisi - $perawatan, 'terisi' => $terisi, 'perawatan' => $perawatan];
}

/** Apakah nomor kamar ini sudah dipakai kamar lain? (untuk validasi form) */
function nomor_kamar_terpakai(string $nomor, ?int $kecualiId = null): bool
{
    $pdo = koneksi_database();
    $sql = 'SELECT COUNT(*) FROM kamar WHERE nomor = :nomor';
    if ($kecualiId !== null) {
        $sql .= ' AND id <> :id';
    }
    $stmt   = $pdo->prepare($sql);
    $params = ['nomor' => $nomor];
    if ($kecualiId !== null) {
        $params['id'] = $kecualiId;
    }
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

/** Jumlah baris riwayat penyewaan (aktif maupun selesai) untuk kamar ini. */
function jumlah_riwayat_penyewaan_kamar(int $kamarId): int
{
    $pdo  = koneksi_database();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM penyewaan WHERE kamar_id = :id');
    $stmt->execute(['id' => $kamarId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Menambah kamar baru.
 *
 * @param array{nomor:string, harga:int, deskripsi:string, fasilitas:string[], kondisi:string, foto_utama:?string} $data
 * @return int ID kamar yang baru dibuat
 */
function tambah_kamar(array $data): int
{
    $pdo  = koneksi_database();
    $stmt = $pdo->prepare(
        'INSERT INTO kamar (nomor, harga, deskripsi, fasilitas, foto_utama, galeri, kondisi)
         VALUES (:nomor, :harga, :deskripsi, :fasilitas, :foto_utama, :galeri, :kondisi)'
    );
    $stmt->execute([
        'nomor'      => $data['nomor'],
        'harga'      => $data['harga'],
        'deskripsi'  => $data['deskripsi'],
        'fasilitas'  => json_encode($data['fasilitas'], JSON_UNESCAPED_UNICODE),
        'foto_utama' => $data['foto_utama'],
        'galeri'     => json_encode([]),
        'kondisi'    => $data['kondisi'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Memperbarui data kamar. $data['foto_utama'] HANYA diikutsertakan dalam
 * UPDATE jika key-nya ada di array (lihat pemanggilan di admin/kamar-form.php) --
 * artinya "foto tidak diganti" dibedakan dari "foto dikosongkan".
 */
function perbarui_kamar(int $id, array $data): void
{
    $pdo    = koneksi_database();
    $set    = ['nomor = :nomor', 'harga = :harga', 'deskripsi = :deskripsi', 'fasilitas = :fasilitas', 'kondisi = :kondisi'];
    $params = [
        'nomor'     => $data['nomor'],
        'harga'     => $data['harga'],
        'deskripsi' => $data['deskripsi'],
        'fasilitas' => json_encode($data['fasilitas'], JSON_UNESCAPED_UNICODE),
        'kondisi'   => $data['kondisi'],
        'id'        => $id,
    ];

    if (array_key_exists('foto_utama', $data)) {
        $set[]                = 'foto_utama = :foto_utama';
        $params['foto_utama'] = $data['foto_utama'];
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM kamar WHERE id = ? FOR UPDATE');
        $lock->execute([$id]);
        if (!$lock->fetchColumn()) {
            throw new DomainException('Kamar sudah tidak tersedia.');
        }
        if ($data['kondisi'] === 'perawatan') {
            if (pemesanan_penahan_kamar($pdo, $id) !== null) {
                throw new DomainException('Batalkan pemesanan yang disetujui sebelum mengubah kondisi kamar menjadi perawatan.');
            }
            $sewa = $pdo->prepare("SELECT id FROM penyewaan WHERE kamar_id = ? AND status = 'aktif' FOR UPDATE");
            $sewa->execute([$id]);
            if ($sewa->fetchColumn()) {
                throw new DomainException('Akhiri penyewaan aktif sebelum mengubah kondisi kamar menjadi perawatan.');
            }
        }
        $stmt = $pdo->prepare('UPDATE kamar SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Menghapus kamar PERMANEN. Hanya diizinkan jika kamar ini TIDAK PERNAH
 * punya riwayat penyewaan sama sekali (aktif maupun yang sudah selesai) --
 * lihat TUGAS 2: "kamar dengan riwayat penyewaan tidak boleh dihapus
 * permanen; gunakan penonaktifan (kondisi 'perawatan') bila diperlukan."
 *
 * @return array{berhasil: bool, pesan: ?string}
 */
function hapus_kamar(int $id): array
{
    if (jumlah_riwayat_penyewaan_kamar($id) > 0) {
        return [
            'berhasil' => false,
            'pesan'    => 'Kamar ini memiliki riwayat penyewaan dan tidak dapat dihapus permanen. '
                        . 'Gunakan kondisi "Perawatan" untuk menonaktifkannya dari penyewaan baru.',
        ];
    }

    $pdo  = koneksi_database();
    $stmt = $pdo->prepare('DELETE FROM kamar WHERE id = :id');
    try {
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
            throw $e;
        }
        return ['berhasil' => false, 'pesan' => 'Kamar dengan riwayat pemesanan atau penyewaan harus tetap tersimpan.'];
    }

    return ['berhasil' => true, 'pesan' => null];
}


/** Kamar yang ditempati profil dari sesi penghuni, termasuk fasilitas dan foto. */
function get_kamar_aktif_penghuni(int $penghuniId): ?array
{
    $stmt = koneksi_database()->prepare("SELECT k.*, 'terisi' AS status_terhitung
        FROM penyewaan s JOIN kamar k ON k.id = s.kamar_id WHERE s.penghuni_aktif_unik = ?");
    $stmt->execute([$penghuniId]);
    $baris = $stmt->fetch();
    return $baris ? baris_kamar_ke_array($baris) : null;
}
