<?php
require_once __DIR__ . '/kamar.php';

// Ambil sewa aktif bila ada, lalu sewa terakhir untuk profil arsip.
const SQL_IDENTITAS_PENGHUNI = "SELECT p.id, p.nama, p.no_telepon, p.user_id, p.email,
    COALESCE(u.username, '') AS akun, s.id AS penyewaan_id, s.kamar_id, k.nomor,
    s.tanggal_masuk, s.tanggal_keluar, s.harga_sewa_disepakati, s.hari_jatuh_tempo,
    IF(s.status = 'aktif', 'aktif', 'tidak_aktif') AS status
    FROM penghuni p LEFT JOIN users u ON u.id = p.user_id";

const SQL_PROFIL_PENGHUNI = SQL_IDENTITAS_PENGHUNI . "
    LEFT JOIN penyewaan s ON s.id = (SELECT r.id FROM penyewaan r WHERE r.penghuni_id = p.id
        ORDER BY (r.status = 'aktif') DESC, r.tanggal_masuk DESC, r.id DESC LIMIT 1)
    LEFT JOIN kamar k ON k.id = s.kamar_id";

function get_semua_penghuni(): array
{
    return koneksi_database()->query(SQL_PROFIL_PENGHUNI . ' ORDER BY p.nama, p.id')->fetchAll();
}

function get_penghuni_by_id(int $id): ?array
{
    $stmt = koneksi_database()->prepare(SQL_PROFIL_PENGHUNI . ' WHERE p.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_penghuni_by_kamar(int $kamarId): ?array
{
    $stmt = koneksi_database()->prepare(SQL_PROFIL_PENGHUNI . " WHERE s.kamar_id = ? AND s.status = 'aktif'");
    $stmt->execute([$kamarId]);
    return $stmt->fetch() ?: null;
}

function get_penghuni_login(): ?array
{
    $user = pengguna_login();
    if (($user['role'] ?? null) !== 'penghuni') {
        return null;
    }
    $stmt = koneksi_database()->prepare(SQL_IDENTITAS_PENGHUNI . "
        LEFT JOIN penyewaan s ON s.penghuni_aktif_unik = p.id
        LEFT JOIN kamar k ON k.id = s.kamar_id
        WHERE p.user_id = ? AND u.role = 'penghuni' AND u.status = 'aktif'");
    $stmt->execute([$user['id']]);
    return $stmt->fetch() ?: null;
}

function riwayat_sewa_penghuni(int $id): array
{
    $stmt = koneksi_database()->prepare('SELECT s.*, k.nomor FROM penyewaan s JOIN kamar k ON k.id = s.kamar_id
        WHERE s.penghuni_id = ? ORDER BY s.tanggal_masuk DESC, s.id DESC');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function validasi_tanggal_sewa(string $tanggal): bool
{
    return format_tanggal($tanggal) !== '-' && $tanggal >= '1000-01-01' && $tanggal <= tanggal_hari_ini();
}

/** Kunci profil lebih dahulu pada setiap operasi yang mengubah sewa/akun penghuni lama. */
function kunci_profil_sewa(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT user_id FROM penghuni WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $profil = $stmt->fetch();
    if (!$profil) {
        throw new DomainException('Profil sudah tidak tersedia. Muat ulang halaman.');
    }
    $stmt = $db->prepare("SELECT * FROM penyewaan WHERE penghuni_id = ? AND status = 'aktif' FOR UPDATE");
    $stmt->execute([$id]);
    return [$profil['user_id'], $stmt->fetch() ?: null];
}

function simpan_penghuni(?int $id, array $nilai, string $password, int $adminId): int
{
    $nama = $nilai['nama'];
    $telepon = $nilai['no_telepon'];
    $akun = $nilai['akun'];
    validasi_identitas_penghuni($nama, $telepon);
    $db = koneksi_database();
    $db->beginTransaction();
    try {
        [$userId, $aktif] = $id === null ? [null, null] : kunci_profil_sewa($db, $id);
        if ((string)($aktif['id'] ?? '') !== $nilai['sewa_aktif']) {
            throw new DomainException('Penyewaan telah berubah. Muat ulang formulir.');
        }
        $mulai = !$aktif && ($id === null || $nilai['mulai_sewa'] === '1');
        $butuhAkun = $userId !== null || $mulai || $akun !== '' || $password !== '' || $aktif !== null;
        if ($butuhAkun) {
            validasi_username_penghuni($akun);
            if (($userId === null || $password !== '') && (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0"))) {
                throw new DomainException('Gunakan password 8–72 byte.');
            }
            $stmt = $db->prepare('SELECT id FROM penghuni WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$akun, $id ?? 0]);
            if ($stmt->fetch()) {
                throw new DomainException('Identitas login sudah dipakai.');
            }
            if ($userId === null) {
                $stmt = $db->prepare("INSERT INTO users (nama, username, password_hash, role, status) VALUES (?, ?, ?, 'penghuni', ?)");
                $stmt->execute([$nama, $akun, password_hash($password, PASSWORD_DEFAULT), ($aktif || $mulai) ? 'aktif' : 'nonaktif']);
                $userId = (int)$db->lastInsertId();
            } else {
                $stmt = $db->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE');
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn() !== 'penghuni') {
                    throw new DomainException('Relasi akun penghuni perlu diperiksa.');
                }
                $stmt = $db->prepare('UPDATE users SET nama = ?, username = ? WHERE id = ?');
                $stmt->execute([$nama, $akun, $userId]);
                if ($password !== '') {
                    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
                }
                if ($mulai) {
                    $db->prepare("UPDATE users SET status = 'aktif' WHERE id = ?")->execute([$userId]);
                }
            }
        }
        if ($id === null) {
            $db->prepare('INSERT INTO penghuni (nama, no_telepon, user_id) VALUES (?, ?, ?)')->execute([$nama, $telepon, $userId]);
            $id = (int)$db->lastInsertId();
        } else {
            $db->prepare('UPDATE penghuni SET nama = ?, no_telepon = ?, user_id = ? WHERE id = ?')->execute([$nama, $telepon, $userId, $id]);
        }
        if ($mulai) {
            $kamarId = filter_var($nilai['kamar_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $tanggal = $nilai['tanggal_masuk'];
            if (!$kamarId || !validasi_tanggal_sewa($tanggal)) {
                throw new DomainException('Pilih kamar dan isi tanggal masuk yang sudah berlangsung.');
            }
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $nilai['harga_sewa_disepakati']) ||
                !filter_var($nilai['hari_jatuh_tempo'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]])) {
                throw new DomainException('Periksa harga sewa dan hari jatuh tempo (1–31).');
            }
            $stmt = $db->prepare('SELECT kondisi FROM kamar WHERE id = ? FOR UPDATE');
            $stmt->execute([$kamarId]);
            if ($stmt->fetchColumn() !== 'aktif') {
                throw new DomainException('Kamar tidak tersedia untuk disewakan.');
            }
            $stmt = $db->prepare("SELECT id FROM penyewaan WHERE (kamar_id = ? OR penghuni_id = ?)
                AND (status = 'aktif' OR tanggal_keluar > ?) LIMIT 1 FOR UPDATE");
            $stmt->execute([$kamarId, $id, $tanggal]);
            if ($stmt->fetch()) {
                throw new DomainException('Kamar terisi atau tanggal mulai bertumpang tindih dengan riwayat sewa.');
            }
            $stmt = $db->prepare('INSERT INTO penyewaan (penghuni_id,kamar_id,tanggal_masuk,harga_sewa_disepakati,hari_jatuh_tempo,dibuat_oleh)
                VALUES (?,?,?,?,?,?)');
            $stmt->execute([$id,$kamarId,$tanggal,$nilai['harga_sewa_disepakati'],$nilai['hari_jatuh_tempo'],$adminId]);
        }
        $db->commit();
        return $id;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) {
            throw new DomainException('Username atau penyewaan aktif sudah dipakai.', 0, $error);
        }
        throw $error;
    }
}

function akhiri_penyewaan(int $id, int $sewaId, string $tanggal): void
{
    $db = koneksi_database();
    $db->beginTransaction();
    try {
        [$userId, $aktif] = kunci_profil_sewa($db, $id);
        if (!$aktif || (int)$aktif['id'] !== $sewaId) {
            throw new DomainException('Penyewaan telah berubah. Muat ulang daftar penghuni.');
        }
        if (!validasi_tanggal_sewa($tanggal) || $tanggal < $aktif['tanggal_masuk']) {
            throw new DomainException('Tanggal keluar harus antara tanggal masuk dan hari ini.');
        }
        $db->prepare("UPDATE penyewaan SET status = 'selesai', tanggal_keluar = ? WHERE id = ?")->execute([$tanggal, $sewaId]);
        $db->prepare("UPDATE users SET status = 'nonaktif' WHERE id = ? AND role = 'penghuni'")->execute([$userId]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function validasi_identitas_penghuni(string $nama, string $telepon): void
{
    if ($nama === '' || mb_strlen($nama) > 100 || !preg_match('/^[0-9+\- ]{8,20}$/D', $telepon)) {
        throw new DomainException('Periksa nama (maksimal 100 karakter) dan nomor telepon (8–20 karakter).');
    }
}

function validasi_username_penghuni(string $akun): void
{
    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/D', $akun) && !(strlen($akun) <= 50 && filter_var($akun, FILTER_VALIDATE_EMAIL))) {
        throw new DomainException('Isi username 3–50 karakter atau email maksimal 50 karakter.');
    }
}

/** Pemilik profil ditentukan oleh akun dalam sesi. */
function ubah_profil_sendiri(string $nama, string $telepon, string $akun): void
{
    validasi_identitas_penghuni($nama, $telepon);
    validasi_username_penghuni($akun);
    $userId = pengguna_login()['id'];
    $db = koneksi_database();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT p.id FROM penghuni p JOIN users u ON u.id = p.user_id
            WHERE u.id = ? AND u.role = 'penghuni' AND u.status = 'aktif' FOR UPDATE");
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new DomainException('Profil penghuni tidak tersedia.');
        }
        $stmt = $db->prepare('SELECT id FROM penghuni WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$akun, $id]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('Identitas login sudah dipakai.');
        }
        $db->prepare('UPDATE penghuni SET nama = ?, no_telepon = ? WHERE user_id = ?')->execute([$nama, $telepon, $userId]);
        $db->prepare('UPDATE users SET nama = ?, username = ? WHERE id = ?')->execute([$nama, $akun, $userId]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new DomainException('Identitas login sudah dipakai.');
        }
        throw $e;
    }
    $_SESSION['user']['nama'] = $nama;
    $_SESSION['user']['username'] = $akun;
}

function ubah_password_penghuni(string $lama, string $baru, string $konfirmasi): void
{
    if (mb_strlen($baru) < 8 || strlen($baru) > 72 || str_contains($baru, "\0") || $baru !== $konfirmasi) {
        throw new DomainException('Gunakan password baru minimal 8 karakter, maksimal 72 byte, dan konfirmasi yang sama.');
    }
    $db = koneksi_database();
    $userId = pengguna_login()['id'];
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'penghuni' AND status = 'aktif'");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || str_contains($lama, "\0") || !password_verify($lama, $hash)) {
        throw new DomainException('Password lama tidak sesuai.');
    }
    if (password_verify($baru, $hash)) {
        throw new DomainException('Pilih password baru yang berbeda dari password saat ini.');
    }
    $hashBaru = password_hash($baru, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND BINARY password_hash = ?
        AND role = 'penghuni' AND status = 'aktif'");
    $stmt->execute([$hashBaru, $userId, $hash]);
    if ($stmt->rowCount() !== 1) {
        throw new DomainException('Data akun berubah. Muat ulang sebelum mengganti password.');
    }
    session_regenerate_id(true);
    $_SESSION['sidik_password'] = hash('sha256', $hashBaru);
    unset($_SESSION['csrf_token']);
}
