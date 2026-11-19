<?php
/**
 * Autentikasi, sesi, CSRF, dan proteksi akses berbasis peran (role).
 * ----------------------------------------------------------------
 * Dimuat oleh includes/config.php di SETIAP halaman (publik, admin,
 * penghuni), sehingga sesi PHP selalu aktif sebelum halaman apa pun
 * menghasilkan output.
 *
 * Lingkup tahap ini HANYA: login, sesi, proteksi peran, logout, dan
 * akun admin awal. Konten dashboard (data kamar/penghuni/tagihan yang
 * ditampilkan setelah login) MASIH dari data contoh di data/*.php --
 * BELUM disambungkan ke tabel MySQL (itu adalah pekerjaan tahap CRUD
 * berikutnya, sengaja tidak dikerjakan di sini). Lihat
 * docs/07-login-dan-keamanan.md untuk penjelasan batas ini.
 */

/**
 * Mengaktifkan sesi PHP dengan pengaturan cookie yang aman. Dipanggil
 * sekali oleh includes/config.php sebelum output apa pun dikirim.
 */
function mulai_sesi(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https, // Hanya dikirim lewat HTTPS saat diakses lewat HTTPS.
        'httponly' => true,   // Tidak bisa dibaca JavaScript (mitigasi XSS mencuri sesi).
        'samesite' => 'Lax',  // Cukup untuk navigasi biasa, menahan sebagian besar CSRF cross-site.
    ]);
    session_name('KOSARON_SESSION');
    session_start();
}

// ------------------------------------------------------------------
// CSRF
// ------------------------------------------------------------------

/** Mengambil (atau membuat) token CSRF untuk sesi saat ini. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Elemen <input hidden> siap pakai untuk ditaruh di dalam <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Memeriksa token CSRF yang dikirim form terhadap token di sesi. */
function csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== '' && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ------------------------------------------------------------------
// Identitas pengguna yang sedang login
// ------------------------------------------------------------------

/**
 * Data pengguna yang sedang login dari sesi, atau null jika belum login.
 *
 * @return array{id:int, username:string, role:string, nama:string}|null
 */
function pengguna_login(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Menyelesaikan login: regenerasi session ID, simpan identitas ke sesi. */
function login_berhasil(array $baris): void
{
    // Regenerasi session ID WAJIB setelah login berhasil, supaya session ID
    // yang mungkin sudah diketahui pihak lain (sebelum login) tidak bisa
    // dipakai untuk membajak sesi yang sudah terautentikasi (session fixation).
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'       => (int) $baris['id'],
        'username' => $baris['username'],
        'role'     => $baris['role'],
        'nama'     => $baris['nama'],
    ];

    try {
        $pdo = koneksi_database();
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $baris['id']]);
    } catch (Throwable $e) {
        // Gagal mencatat last_login_at bukan alasan membatalkan login yang
        // sudah sah; detail kegagalan sudah dicatat oleh koneksi_database().
    }
}

/** Menghancurkan sesi (logout). */
function logout_sesi(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameter = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parameter['path'],
            $parameter['domain'],
            $parameter['secure'],
            $parameter['httponly']
        );
    }

    session_destroy();
}

// ------------------------------------------------------------------
// Proteksi akses berbasis peran
// ------------------------------------------------------------------

/**
 * Memastikan pengguna sudah login DAN perannya sesuai yang dibutuhkan.
 * Jika tidak, langsung redirect/menolak dan menghentikan eksekusi (exit) --
 * dipanggil di baris PALING ATAS setiap halaman admin/penghuni (sebelum
 * pemrosesan $_POST apa pun), bukan hanya untuk menyembunyikan menu.
 *
 * @param 'admin'|'penghuni' $peranDibutuhkan
 * @param string             $urlLogin        Path relatif ke login.php dari halaman pemanggil
 * @return array{id:int, username:string, role:string, nama:string} Data pengguna yang login
 */
function require_role(string $peranDibutuhkan, string $urlLogin = 'login.php'): array
{
    $pengguna = pengguna_login();

    if ($pengguna === null) {
        header('Location: ' . $urlLogin . '?alasan=harus-login');
        exit;
    }

    if ($pengguna['role'] !== $peranDibutuhkan) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
            . '<title>Akses Ditolak</title>'
            . '<style>body{font-family:Poppins,Arial,sans-serif;background:#F3F4F6;color:#111827;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            . '.kotak{background:#fff;border:1px solid #E5E7EB;border-radius:8px;padding:32px 36px;'
            . 'max-width:420px;text-align:center}h1{font-size:18px;margin:0 0 8px;color:#B91C1C}'
            . 'p{margin:0;font-size:14px;color:#4B5563}a{color:#2563EB}</style></head><body>'
            . '<div class="kotak"><h1>Akses Ditolak</h1>'
            . '<p>Akun Anda tidak memiliki izin untuk membuka halaman ini.</p>'
            . '<p><a href="' . e($urlLogin) . '">Kembali ke halaman login</a></p>'
            . '</div></body></html>';
        exit;
    }

    if ($peranDibutuhkan === 'penghuni') {
        require_once __DIR__ . '/koneksi.php';
        $stmt = koneksi_database()->prepare("SELECT nama FROM users WHERE id = ? AND role = 'penghuni' AND status = 'aktif'");
        $stmt->execute([$pengguna['id']]);
        $nama = $stmt->fetchColumn();
        if ($nama === false) {
            logout_sesi();
            header('Location: ' . $urlLogin . '?alasan=harus-login');
            exit;
        }
        $_SESSION['user']['nama'] = $pengguna['nama'] = $nama;
    }
    return $pengguna;
}

// ------------------------------------------------------------------
// Login: pencarian akun + pembatasan percobaan sederhana
// ------------------------------------------------------------------

/**
 * Mencari akun berdasarkan USERNAME (tabel users) atau EMAIL (khusus
 * penghuni, dari tabel penghuni) lalu memverifikasi password. Hanya
 * mengembalikan akun jika password benar DAN status akun 'aktif'.
 *
 * Tidak ada perbedaan pesan antara "akun tidak ada", "password salah",
 * atau "akun nonaktif" -- pemanggil (login.php) selalu menampilkan satu
 * pesan generik, supaya keberadaan akun tidak bisa ditebak dari respons.
 */
function cari_dan_verifikasi_login(string $identitas, string $password): ?array
{
    require_once __DIR__ . '/koneksi.php';
    $pdo = koneksi_database();

    // Catatan: dengan ATTR_EMULATE_PREPARES=false, driver MySQL asli TIDAK
    // mendukung nama parameter yang sama dipakai dua kali dalam satu query
    // -- karena itu dipakai dua placeholder (:identitas1/:identitas2)
    // dengan nilai yang sama, bukan satu nama yang diulang.
    $stmt = $pdo->prepare(
        'SELECT u.* FROM users u WHERE u.username = :identitas1
         UNION
         SELECT u.* FROM users u
         INNER JOIN penghuni p ON p.user_id = u.id
         WHERE p.email = :identitas2
         LIMIT 1'
    );
    $stmt->execute(['identitas1' => $identitas, 'identitas2' => $identitas]);
    $baris = $stmt->fetch();

    if ($baris === false) {
        return null;
    }
    if ($baris['status'] !== 'aktif') {
        return null;
    }
    if (!password_verify($password, $baris['password_hash'])) {
        return null;
    }

    return $baris;
}

/** Kunci penyimpanan percobaan login di sesi, dinormalisasi (huruf kecil). */
function kunci_percobaan_login(string $identitas): string
{
    return mb_strtolower(trim($identitas));
}

const BATAS_PERCOBAAN_LOGIN = 5;
const DURASI_KUNCI_LOGIN    = 60; // detik

/** Apakah identitas ini sedang dikunci sementara akibat terlalu banyak percobaan gagal? */
function login_terkunci(string $identitas): bool
{
    $kunci   = kunci_percobaan_login($identitas);
    $catatan = $_SESSION['login_percobaan'][$kunci] ?? null;
    return $catatan !== null && ($catatan['terkunci_hingga'] ?? 0) > time();
}

/** Mencatat satu percobaan login gagal; mengunci sementara jika melebihi batas. */
function catat_percobaan_login_gagal(string $identitas): void
{
    $kunci   = kunci_percobaan_login($identitas);
    $catatan = $_SESSION['login_percobaan'][$kunci] ?? ['jumlah' => 0, 'terkunci_hingga' => 0];
    $catatan['jumlah']++;

    if ($catatan['jumlah'] >= BATAS_PERCOBAAN_LOGIN) {
        $catatan['terkunci_hingga'] = time() + DURASI_KUNCI_LOGIN;
        $catatan['jumlah']          = 0;
    }

    $_SESSION['login_percobaan'][$kunci] = $catatan;
}

/** Mereset hitungan percobaan gagal (dipanggil setelah login berhasil). */
function reset_percobaan_login(string $identitas): void
{
    unset($_SESSION['login_percobaan'][kunci_percobaan_login($identitas)]);
}
