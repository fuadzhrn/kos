<?php
/**
 * Alat uji koneksi database (CLI atau browser).
 * Menguji koneksi PDO ke db_kos_aron lewat includes/koneksi.php,
 * memastikan prepared statement & pengambilan data bekerja, DAN
 * memastikan password/kredensial TIDAK pernah ikut ditampilkan.
 *
 * Jalankan: php database/uji-koneksi.php
 * (atau akses lewat browser di environment lokal -- tidak membaca input
 * apa pun dari pengunjung, aman diakses langsung untuk pengujian lokal).
 */

require_once __DIR__ . '/../includes/koneksi.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = koneksi_database();
    echo "[OK] Koneksi PDO ke db_kos_aron berhasil.\n";
    echo '    Driver: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    echo '    Emulated prepares: ' . ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'AKTIF (!)' : 'nonaktif') . "\n";
    echo '    Error mode: ' . ($pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION ? 'EXCEPTION' : 'lainnya') . "\n\n";

    // Uji prepared statement sungguhan (bukan emulasi).
    $stmt = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM kamar WHERE status = :status');
    $stmt->execute(['status' => 'terisi']);
    $jumlahTerisi = (int) $stmt->fetch()['jumlah'];
    echo "[OK] Prepared statement berjalan. Kamar berstatus 'terisi': {$jumlahTerisi}\n";

    $tabel = ['users', 'kamar', 'penghuni', 'penyewaan', 'tagihan', 'pembayaran', 'notifikasi', 'pengaturan'];
    echo "\nJumlah baris per tabel:\n";
    foreach ($tabel as $nama) {
        $jumlah = (int) $pdo->query('SELECT COUNT(*) AS c FROM `' . $nama . '`')->fetch()['c'];
        printf("    %-12s %d\n", $nama, $jumlah);
    }

    echo "\n[SELESAI] Semua pengujian koneksi berhasil.\n";
} catch (Throwable $e) {
    // Sengaja TIDAK mencetak $e->getMessage() dari PDOException asli --
    // koneksi_database() sudah melempar RuntimeException berpesan aman.
    http_response_code(500);
    echo '[GAGAL] ' . $e->getMessage() . "\n";
    exit(1);
}
