<?php
/**
 * Mekanisme akun admin awal (CLI, lokal) — TUGAS 5.
 * ----------------------------------------------------------------
 * HANYA bisa dijalankan dari command line (php-cli), BUKAN lewat
 * browser/web server, supaya tidak ada orang lain yang bisa membuat
 * akun admin lewat URL.
 *
 * Password TIDAK PERNAH ditulis di source code: skrip ini meminta
 * username & password secara interaktif (STDIN) lalu menyimpannya
 * sebagai hash (password_hash()) ke tabel users. Password yang Anda
 * ketik TIDAK ditampilkan ulang di output ini maupun di log mana pun.
 *
 * Jalankan:
 *   php database/buat-admin.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya bisa dijalankan lewat command line (CLI), bukan lewat browser.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/koneksi.php';

function baca_baris(string $label): string
{
    echo $label;
    $baris = fgets(STDIN);
    return $baris === false ? '' : trim($baris);
}

/** Password diketik tanpa newline echo tambahan; tidak disembunyikan di semua terminal Windows, lihat catatan di atas. */
function baca_password(string $label): string
{
    echo $label;
    $baris = fgets(STDIN);
    return $baris === false ? '' : trim($baris);
}

echo "=== Setup Akun Admin Awal -- Kos Aron ===\n";
echo "(Password yang Anda ketik tidak akan ditampilkan ulang di output ini.)\n\n";

$pdo = koneksi_database();

$username = '';
while ($username === '') {
    $username = baca_baris('Username admin (huruf/angka/titik/underscore, 3-50 karakter): ');
    if (!preg_match('/^[A-Za-z0-9._]{3,50}$/', $username)) {
        echo "  Username tidak valid. Coba lagi.\n";
        $username = '';
    }
}

$nama = '';
while ($nama === '') {
    $nama = baca_baris('Nama tampilan admin (contoh: Admin Kos Aron): ');
    if (mb_strlen($nama) > 100) {
        echo "  Nama terlalu panjang (maksimal 100 karakter).\n";
        $nama = '';
    }
}

$stmtCek = $pdo->prepare('SELECT id, role FROM users WHERE username = :username');
$stmtCek->execute(['username' => $username]);
$akunLama = $stmtCek->fetch();

if ($akunLama !== false && $akunLama['role'] !== 'admin') {
    fwrite(STDERR, "Username ini sudah dipakai akun dengan role '{$akunLama['role']}'. Gunakan username lain.\n");
    exit(1);
}

if ($akunLama !== false) {
    $jawab = baca_baris("Akun admin '{$username}' sudah ada. Perbarui password & nama? (y/n): ");
    if (mb_strtolower($jawab) !== 'y') {
        echo "Dibatalkan. Tidak ada perubahan.\n";
        exit(0);
    }
}

$password = '';
while (true) {
    $password  = baca_password('Password baru (minimal 8 karakter): ');
    $konfirmasi = baca_password('Ulangi password: ');

    if (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        echo "  Gunakan minimal 8 karakter dan maksimal 72 byte, tanpa karakter NUL.\n";
        continue;
    }
    if ($password !== $konfirmasi) {
        echo "  Konfirmasi tidak sama. Coba lagi.\n";
        continue;
    }
    break;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
unset($password, $konfirmasi); // Jangan biarkan password plain bertahan lebih lama dari yang perlu.

if ($akunLama !== false) {
    $pdo->prepare('UPDATE users SET password_hash = :hash, nama = :nama, status = :status WHERE id = :id')
        ->execute(['hash' => $hash, 'nama' => $nama, 'status' => 'aktif', 'id' => $akunLama['id']]);
    echo "\n[OK] Password & nama akun admin '{$username}' berhasil diperbarui.\n";
} else {
    $pdo->prepare('INSERT INTO users (username, password_hash, role, nama, status) VALUES (:username, :hash, \'admin\', :nama, \'aktif\')')
        ->execute(['username' => $username, 'hash' => $hash, 'nama' => $nama]);
    echo "\n[OK] Akun admin '{$username}' berhasil dibuat.\n";
}

echo "Silakan login di login.php dengan username '{$username}' dan password yang baru Anda ketik tadi.\n";
echo "(Password TIDAK disimpan atau ditampilkan di tempat lain oleh skrip ini.)\n";
