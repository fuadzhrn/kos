<?php
require_once __DIR__ . '/includes/config.php';
$role = (pengguna_login()['role'] ?? '') === 'admin' ? 'admin' : 'penghuni';
$user = require_role($role);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
$id = filter_input(INPUT_GET, 'pengajuan', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$sql = 'SELECT a.nama_bukti,a.mime_bukti,a.bukti_pembayaran FROM pengajuan_pembayaran a
    JOIN tagihan t ON t.id=a.tagihan_id JOIN penyewaan s ON s.id=t.penyewaan_id AND s.penghuni_id=a.penghuni_id
    JOIN penghuni p ON p.id=a.penghuni_id WHERE a.id=?';
$params = [$id ?: 0];
if ($role === 'penghuni') {
    $sql .= ' AND p.user_id=?';
    $params[] = $user['id'];
}
$stmt = koneksi_database()->prepare($sql);
$stmt->execute($params);
$bukti = $stmt->fetch();
if (!$bukti) {
    http_response_code(404);
    exit('Bukti pembayaran tidak ditemukan.');
}
header('Content-Type: ' . $bukti['mime_bukti']);
header('Content-Disposition: inline; filename="' . rawurlencode($bukti['nama_bukti']) . '"');
echo $bukti['bukti_pembayaran'];
