<?php
require_once __DIR__ . '/penghuni.php';

function kueri_pemesanan(PDO $db, string $sql, array $parameter): PDOStatement
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parameter);
    return $stmt;
}

function transaksi_pemesanan(callable $proses): mixed
{
    $db = koneksi_database();
    $db->beginTransaction();
    try {
        $hasil = $proses($db);
        $db->commit();
        return $hasil;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

// Menyamakan format lokal 08..., +628..., dan 628... untuk pencocokan kontak.
function kontak_pemesanan(string $nomor): string
{
    $angka = preg_replace('/[^0-9]/', '', $nomor);
    return str_starts_with($angka, '0') ? '62' . substr($angka, 1) : $angka;
}

function teks_pemesanan(array $form, string $kunci, int $batas): string
{
    $nilai = $form[$kunci] ?? '';
    if (!is_string($nilai) || !mb_check_encoding($nilai, 'UTF-8') || mb_strlen($nilai) > $batas || str_contains($nilai, "\0")) {
        throw new DomainException('Periksa format dan panjang isian formulir.');
    }
    return trim($nilai);
}

function kunci_kamar_pemesanan(PDO $db, int $kamarId): string
{
    $kondisi = kueri_pemesanan($db, 'SELECT kondisi FROM kamar WHERE id = ? FOR UPDATE', [$kamarId])->fetchColumn();
    if ($kondisi === false) {
        throw new DomainException('Kamar tidak ditemukan.');
    }
    return $kondisi;
}

function pastikan_kamar_bebas(PDO $db, int $kamarId, string $kondisi): void
{
    $terisi = kueri_pemesanan($db, 'SELECT id FROM penyewaan WHERE kamar_aktif_unik = ? FOR UPDATE', [$kamarId])->fetchColumn();
    if ($kondisi !== 'aktif' || $terisi || pemesanan_penahan_kamar($db, $kamarId) !== null) {
        throw new DomainException('Kamar sudah tidak tersedia. Silakan pilih kamar lain.');
    }
}

function ajukan_pemesanan(int $kamarId, array $form): string
{
    $nama = teks_pemesanan($form, 'nama_lengkap', 100);
    $telepon = teks_pemesanan($form, 'nomor_telepon', 20);
    $email = teks_pemesanan($form, 'email', 100);
    $tanggal = teks_pemesanan($form, 'rencana_tanggal_masuk', 10);
    $catatan = teks_pemesanan($form, 'catatan_pemesan', 500);
    $angka = preg_replace('/[ -]/', '', $telepon);
    if ($nama === '' || !preg_match('/^\+?[0-9]{8,15}$/D', $angka)) {
        throw new DomainException('Isi nama lengkap dan nomor telepon yang valid (8–15 digit).');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Alamat email tidak valid.');
    }
    if (format_tanggal($tanggal) === '-' || $tanggal < tanggal_hari_ini()) {
        throw new DomainException('Pilih rencana masuk mulai hari ini.');
    }
    $telepon = kontak_pemesanan($telepon);
    $db = koneksi_database();
    // Mengurutkan pengajuan dari nomor yang sama, termasuk lewat sesi atau kamar berbeda.
    $kunci = hash('sha256', DB_NAME . '/pengajuan/' . $telepon);
    if (!(int)kueri_pemesanan($db, 'SELECT GET_LOCK(?, 3)', [$kunci])->fetchColumn()) {
        throw new DomainException('Pengajuan sedang diproses. Silakan coba lagi.');
    }
    try {
        return transaksi_pemesanan(function (PDO $db) use ($kamarId, $nama, $telepon, $email, $tanggal, $catatan) {
            $kondisi = kunci_kamar_pemesanan($db, $kamarId);
            pastikan_kamar_bebas($db, $kamarId, $kondisi);
            $jumlah = kueri_pemesanan($db, 'SELECT COUNT(*) FROM calon_penghuni
                WHERE nomor_telepon = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL 1 HOUR', [$telepon])->fetchColumn();
            if ((int)$jumlah >= 3) {
                throw new DomainException('Batas pengajuan tercapai. Coba kembali dalam satu jam.');
            }
            kueri_pemesanan($db, 'INSERT INTO calon_penghuni (nama_lengkap,nomor_telepon,email,tanggal_daftar)
                VALUES (?,?,?,?)', [$nama,$telepon,$email ?: null,tanggal_hari_ini()]);
            $calon = (int)$db->lastInsertId();
            $kode = 'KA-' . strtoupper(bin2hex(random_bytes(8)));
            kueri_pemesanan($db, 'INSERT INTO pemesanan (kode_pemesanan,calon_penghuni_id,kamar_id,
                tanggal_pengajuan,rencana_tanggal_masuk,catatan_pemesan) VALUES (?,?,?,?,?,?)',
                [$kode,$calon,$kamarId,date('Y-m-d H:i:s'),$tanggal,$catatan === '' ? null : $catatan]);
            return $kode;
        });
    } finally {
        kueri_pemesanan($db, 'SELECT RELEASE_LOCK(?)', [$kunci]);
    }
}

const SQL_RINCI_PEMESANAN = 'SELECT p.*, c.nama_lengkap, c.nomor_telepon, c.email,
    k.nomor AS nomor_kamar, k.harga AS harga_kamar
    FROM pemesanan p JOIN calon_penghuni c ON c.id=p.calon_penghuni_id JOIN kamar k ON k.id=p.kamar_id';

function daftar_pemesanan(string $status = '', string $cari = ''): array
{
    $sql = SQL_RINCI_PEMESANAN . ' WHERE 1=1';
    $parameter = [];
    if ($status !== '') {
        $sql .= ' AND p.status = ?';
        $parameter[] = $status;
    }
    if ($cari !== '') {
        $sql .= ' AND (LOCATE(?, p.kode_pemesanan) > 0 OR LOCATE(?, c.nama_lengkap) > 0)';
        array_push($parameter, $cari, $cari);
    }
    return kueri_pemesanan(koneksi_database(), $sql . ' ORDER BY p.tanggal_pengajuan DESC,p.id DESC', $parameter)->fetchAll();
}

function detail_pemesanan(int $id): ?array
{
    return kueri_pemesanan(koneksi_database(), SQL_RINCI_PEMESANAN . ' WHERE p.id=?', [$id])->fetch() ?: null;
}

// Kamar menjadi kunci bersama untuk pengajuan publik, keputusan admin, dan penyewaan manual.
function kunci_pemesanan(PDO $db, int $id): array
{
    $kamarId = kueri_pemesanan($db, 'SELECT kamar_id FROM pemesanan WHERE id=?', [$id])->fetchColumn();
    if (!$kamarId) {
        throw new DomainException('Pemesanan tidak ditemukan.');
    }
    $kondisi = kunci_kamar_pemesanan($db, (int)$kamarId);
    $p = kueri_pemesanan($db, SQL_RINCI_PEMESANAN . ' WHERE p.id=? FOR UPDATE', [$id])->fetch();
    if (!$p || $p['penyewaan_id'] !== null) {
        throw new DomainException('Pemesanan sudah selesai atau tidak tersedia.');
    }
    return [$p, $kondisi];
}

function keputusan_pemesanan(int $id, string $aksi, string $catatan, int $adminId): void
{
    $status = ['setujui'=>'disetujui', 'tolak'=>'ditolak', 'batalkan'=>'dibatalkan'][$aksi] ?? null;
    $catatan = teks_pemesanan(['catatan'=>$catatan], 'catatan', 500);
    transaksi_pemesanan(function (PDO $db) use ($id, $adminId, $status, $catatan) {
        [$p, $kondisi] = kunci_pemesanan($db, $id);
        if ($status === null || $p['status'] !== ($status === 'dibatalkan' ? 'disetujui' : 'menunggu')) {
            throw new DomainException('Keputusan tidak sesuai status pemesanan terbaru.');
        }
        if ($status === 'disetujui') {
            pastikan_kamar_bebas($db, (int)$p['kamar_id'], $kondisi);
        }
        kueri_pemesanan($db, 'UPDATE pemesanan SET status=?,catatan_admin=?,diproses_oleh=?,tanggal_diproses=? WHERE id=?',
            [$status,$catatan === '' ? null : $catatan,$adminId,date('Y-m-d H:i:s'),$id]);
    });
}

/** Calon hanya menyampaikan kontak. Admin harus memeriksa identitas sebelum memakai profil lama. */
function profil_dengan_kontak(PDO $db, array $p, bool $kunci = false): array
{
    $internasional = kontak_pemesanan($p['nomor_telepon']);
    $lokal = str_starts_with($internasional, '62') ? '0' . substr($internasional, 2) : $internasional;
    $sql = "SELECT h.id,h.nama AS nama_lengkap,h.no_telepon,h.user_id,u.username
        FROM penghuni h LEFT JOIN users u ON u.id=h.user_id
        WHERE REPLACE(REPLACE(REPLACE(h.no_telepon,'+',''),'-',''),' ','') IN (?,?)
        OR (? IS NOT NULL AND (h.email=? OR u.username=?)) ORDER BY h.id";
    $email = $p['email'];
    return kueri_pemesanan($db, $sql . ($kunci ? ' FOR UPDATE' : ''),
        [$internasional,$lokal,$email,$email,$email])->fetchAll();
}

function pilihan_penghuni_pemesanan(int $id): array
{
    $p = detail_pemesanan($id);
    return $p ? profil_dengan_kontak(koneksi_database(), $p) : [];
}

function aktifkan_pemesanan(int $id, array $form, int $adminId): int
{
    return transaksi_pemesanan(function (PDO $db) use ($id, $form, $adminId) {
        [$p, $kondisi] = kunci_pemesanan($db, $id);
        if ($p['status'] !== 'disetujui' || $kondisi !== 'aktif') {
            throw new DomainException('Aktivasi memerlukan pemesanan disetujui pada kamar yang masih aktif.');
        }
        $pilihan = filter_var($form['penghuni_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        $cocok = array_column(profil_dengan_kontak($db, $p, true), null, 'id');
        $profil = $cocok[$pilihan] ?? null;
        if ($pilihan === false || ($pilihan === 0 && $cocok) ||
            ($pilihan > 0 && (!$profil || ($form['identitas_terverifikasi'] ?? '') !== '1'))) {
            throw new DomainException('Periksa identitas dan pilih profil yang sesuai sebelum melanjutkan.');
        }
        $akunLama = $profil['user_id'] ?? null;
        $nilai = [
            'nama' => $profil['nama_lengkap'] ?? $p['nama_lengkap'],
            'no_telepon' => $profil['no_telepon'] ?? $p['nomor_telepon'],
            'akun' => $akunLama ? $profil['username'] : teks_pemesanan($form, 'akun', 50),
            'sewa_aktif' => '', 'mulai_sewa' => '1', 'kamar_id' => $p['kamar_id'],
            'tanggal_masuk' => teks_pemesanan($form, 'tanggal_masuk', 10),
            'harga_sewa_disepakati' => teks_pemesanan($form, 'harga_sewa_disepakati', 13),
            'hari_jatuh_tempo' => teks_pemesanan($form, 'hari_jatuh_tempo', 2),
        ];
        $password = !$akunLama && is_string($form['password'] ?? null) ? $form['password'] : '';
        $penghuni = simpan_penghuni($pilihan ?: null, $nilai, $password, $adminId, $id);
        if (!$profil && $p['email'] !== null) {
            $konflik = kueri_pemesanan($db, 'SELECT u.id FROM users u JOIN penghuni h ON h.id=?
                WHERE u.username=? AND u.id<>h.user_id FOR UPDATE', [$penghuni,$p['email']])->fetchColumn();
            if ($konflik) {
                throw new DomainException('Email terkait dengan akun lain. Periksa kembali identitas pemesan.');
            }
            kueri_pemesanan($db, 'UPDATE penghuni SET email=? WHERE id=?', [$p['email'],$penghuni]);
        }
        $sewa = kueri_pemesanan($db, 'SELECT id FROM penyewaan WHERE kamar_aktif_unik=? AND penghuni_id=? FOR UPDATE',
            [$p['kamar_id'],$penghuni])->fetchColumn();
        if (!$sewa) {
            throw new LogicException('Relasi penyewaan belum tersimpan.');
        }
        kueri_pemesanan($db, 'UPDATE pemesanan SET penyewaan_id=? WHERE id=?', [$sewa,$id]);
        return (int)$sewa;
    });
}
