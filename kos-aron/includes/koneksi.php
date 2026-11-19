<?php
/**
 * Koneksi database (PDO) untuk db_kos_aron.
 * ----------------------------------------------------------------
 * Tahap ini HANYA menyediakan fondasi koneksi. Belum ada halaman
 * frontend yang memanggil file ini (lihat docs/04-penyesuaian-frontend.md
 * untuk rencana integrasinya nanti).
 *
 * Kredensial TIDAK ditulis di file ini. Nilai DB_HOST/DB_PORT/DB_NAME/
 * DB_USER/DB_PASS diambil dari includes/database.local.php, yang:
 *   - TIDAK ikut Git (lihat .gitignore pola *.local.php).
 *   - Punya contoh tanpa kredensial asli di database.local.example.php.
 *
 * Jika includes/database.local.php belum ada (misalnya clone baru),
 * koneksi_database() melempar RuntimeException dengan pesan yang
 * menjelaskan cara membuatnya -- bukan detail error database.
 */

if (!defined('DB_HOST')) {
    $berkasKredensial = __DIR__ . '/database.local.php';

    if (!is_file($berkasKredensial)) {
        throw new RuntimeException(
            'Konfigurasi database belum ada. Salin includes/database.local.example.php '
            . 'menjadi includes/database.local.php, lalu isi sesuai environment lokal Anda. '
            . 'Lihat docs/05-panduan-import-database.md.'
        );
    }

    require_once $berkasKredensial;
}

/**
 * Membuka (atau mengambil ulang) satu koneksi PDO ke db_kos_aron.
 *
 * Ketentuan koneksi:
 * - Prepared statement sungguhan, bukan emulasi (ATTR_EMULATE_PREPARES = false).
 * - Mode exception aktif (ATTR_ERRMODE = ERRMODE_EXCEPTION), supaya setiap
 *   kegagalan query terdeteksi, bukan diam-diam gagal.
 * - Detail error database (PDOException::getMessage(), yang bisa memuat
 *   nama host/kolom) TIDAK pernah ditampilkan ke pengunjung: dicatat ke
 *   error_log(), lalu dilempar ulang sebagai RuntimeException berpesan umum.
 *
 * @throws RuntimeException jika koneksi gagal (pesan aman untuk ditampilkan)
 */
function koneksi_database(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[db_kos_aron] Koneksi database gagal: ' . $e->getMessage());
        throw new RuntimeException('Tidak dapat terhubung ke database saat ini. Silakan coba lagi nanti.');
    }

    return $pdo;
}
