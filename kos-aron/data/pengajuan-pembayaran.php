<?php
require_once __DIR__ . '/tagihan.php';
require_once __DIR__ . '/../includes/upload.php';

const BATAS_BUKTI_TRANSFER = 3 * 1024 * 1024;

function label_status_pengajuan(string $status): string
{
    return match ($status) {
        'menunggu' => 'Menunggu Verifikasi Admin',
        'disetujui' => 'Pembayaran Disetujui',
        'ditolak' => 'Pembayaran Ditolak',
    };
}

// Daftar hanya memuat metadata. Isi bukti dibaca terpisah setelah pemeriksaan hak akses.
const SQL_PENGAJUAN_TRANSFER = 'SELECT a.id,a.tagihan_id,a.penghuni_id,a.tanggal_transfer,a.nominal_diajukan,a.nama_pengirim,
    a.status_verifikasi,a.keterangan,a.catatan_admin,a.created_at,a.metode,a.diverifikasi_oleh,a.diverifikasi_pada,
    a.pembayaran_id,t.periode,t.jumlah,t.jatuh_tempo,k.nomor AS nomor_kamar,p.nama AS nama_penghuni,
    s.tanggal_masuk,s.tanggal_keluar,u.nama AS nama_admin,pb.id AS pembayaran_sah_id,
    CASE WHEN pb.nominal_dibayar=t.jumlah THEN pb.tanggal_bayar END AS tanggal_bayar
    FROM pengajuan_pembayaran a JOIN tagihan t ON t.id=a.tagihan_id
    JOIN penyewaan s ON s.id=t.penyewaan_id AND s.penghuni_id=a.penghuni_id
    JOIN penghuni p ON p.id=s.penghuni_id JOIN kamar k ON k.id=s.kamar_id
    LEFT JOIN users u ON u.id=a.diverifikasi_oleh LEFT JOIN pembayaran pb ON pb.tagihan_aktif_unik=t.id';

function daftar_pengajuan_transfer(?int $penghuniId, string $status = '', string $cari = '', ?string $periode = null): array
{
    $filter = [];
    $params = [];
    if ($penghuniId !== null) {
        $filter[] = 'a.penghuni_id=?';
        $params[] = $penghuniId;
    }
    if ($status !== '') {
        $filter[] = 'a.status_verifikasi=?';
        $params[] = $status;
    }
    if ($periode !== null) {
        $filter[] = 't.periode=?';
        $params[] = $periode;
    }
    if ($cari !== '') {
        $filter[] = '(LOCATE(?,p.nama)>0 OR LOCATE(?,k.nomor)>0 OR a.tagihan_id=?)';
        $nomor = ltrim($cari, '#');
        array_push($params, $cari, $cari, ctype_digit($nomor) ? $nomor : 0);
    }
    $where = $filter ? ' WHERE ' . implode(' AND ', $filter) : '';
    $stmt = koneksi_database()->prepare(SQL_PENGAJUAN_TRANSFER . $where . ' ORDER BY a.id DESC');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function detail_pengajuan_transfer(int $id): ?array
{
    $stmt = koneksi_database()->prepare(SQL_PENGAJUAN_TRANSFER . ' WHERE a.id=?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function simpan_pengajuan_transfer(int $penghuniId, int $tagihanId, array $form, array $file): void
{
    foreach (['tanggal_transfer','nama_pengirim','keterangan'] as $key) {
        if (!is_string($form[$key] ?? '')) {
            throw new DomainException('Format isian formulir tidak valid.');
        }
    }
    $tanggal = trim($form['tanggal_transfer'] ?? '');
    $nama = trim($form['nama_pengirim'] ?? '');
    $catatan = trim($form['keterangan'] ?? '');
    if (!validasi_tanggal_sewa($tanggal)) {
        throw new DomainException('Isi tanggal transfer yang valid, paling lambat hari ini.');
    }
    if (!mb_check_encoding($nama . $catatan, 'UTF-8') || mb_strlen($nama) > 100 || mb_strlen($catatan) > 255) {
        throw new DomainException('Nama pengirim maksimal 100 karakter dan keterangan maksimal 255 karakter.');
    }
    $galat = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($galat !== UPLOAD_ERR_OK) {
        throw new DomainException(match ($galat) {
            UPLOAD_ERR_NO_FILE => 'Pilih gambar bukti transfer.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Bukti transfer melebihi batas ukuran unggahan (maksimal 3 MB).',
            default => 'Unggahan belum lengkap. Pilih kembali bukti transfer.',
        });
    }
    $tmp = $file['tmp_name'] ?? null;
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        throw new DomainException('Bukti harus berasal dari unggahan formulir.');
    }
    $ukuran = filesize($tmp);
    if (!$ukuran || $ukuran > BATAS_BUKTI_TRANSFER) {
        throw new DomainException('Bukti transfer harus berukuran lebih dari nol dan maksimal 3 MB.');
    }
    $mime = mime_gambar_unggahan($tmp);
    $ekstensi = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!$mime || ($ekstensi === 'jpeg' ? 'jpg' : $ekstensi) !== MIME_FOTO_DIIZINKAN[$mime]) {
        throw new DomainException('Gunakan gambar JPG, JPEG, PNG, atau WEBP dengan ekstensi yang sesuai.');
    }
    $isi = file_get_contents($tmp);
    if ($isi === false || strlen($isi) !== $ukuran) {
        throw new DomainException('Bukti transfer gagal dibaca. Pilih ulang file.');
    }
    $db = koneksi_database();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT t.jumlah FROM tagihan t JOIN penyewaan s ON s.id=t.penyewaan_id
            WHERE t.id=? AND s.penghuni_id=? FOR UPDATE');
        $stmt->execute([$tagihanId,$penghuniId]);
        $nominal = $stmt->fetchColumn();
        if ($nominal === false || $nominal === '0.00') {
            throw new DomainException('Tagihan tidak tersedia untuk pengajuan pembayaran.');
        }
        $stmt = $db->prepare('SELECT id FROM pembayaran WHERE tagihan_aktif_unik=? FOR UPDATE');
        $stmt->execute([$tagihanId]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('Pembayaran untuk tagihan ini sudah tercatat.');
        }
        $stmt = $db->prepare('INSERT INTO pengajuan_pembayaran
            (tagihan_id,penghuni_id,tanggal_transfer,nominal_diajukan,nama_pengirim,nama_bukti,mime_bukti,bukti_pembayaran,keterangan)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$tagihanId,$penghuniId,$tanggal,$nominal,$nama === '' ? null : $nama,
            bin2hex(random_bytes(16)) . '.' . MIME_FOTO_DIIZINKAN[$mime],$mime,$isi,$catatan === '' ? null : $catatan]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new DomainException('Sudah ada pengajuan yang menunggu verifikasi Admin.');
        }
        throw $e;
    }
}

/** Pemeriksaan ulang dan keputusan menggunakan satu transaksi. Urutan kunci dimulai dari tagihan. */
function verifikasi_pengajuan_transfer(int $id, int $adminId, string $aksi, string $catatan): bool
{
    $catatan = trim($catatan);
    if (!in_array($aksi, ['terima','tolak'], true)) {
        throw new DomainException('Pilih tindakan verifikasi yang valid.');
    }
    if (($aksi === 'tolak' && $catatan === '') || mb_strlen($catatan) > 255 || !mb_check_encoding($catatan,'UTF-8')) {
        throw new DomainException('Alasan penolakan wajib diisi. Catatan maksimal 255 karakter.');
    }
    $status = $aksi === 'terima' ? 'disetujui' : 'ditolak';
    $db = koneksi_database();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE id=? AND role='admin' AND status='aktif' FOR SHARE");
        $stmt->execute([$adminId]);
        if (!$stmt->fetchColumn()) {
            throw new DomainException('Hanya Admin aktif yang dapat memverifikasi pembayaran.');
        }
        $stmt = $db->prepare('SELECT tagihan_id FROM pengajuan_pembayaran WHERE id=?');
        $stmt->execute([$id]);
        $tagihanId = $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT t.jumlah,t.periode,s.penghuni_id FROM tagihan t JOIN penyewaan s ON s.id=t.penyewaan_id
            WHERE t.id=? FOR UPDATE');
        $stmt->execute([$tagihanId ?: 0]);
        $tagihan = $stmt->fetch();
        $stmt = $db->prepare('SELECT tagihan_id,penghuni_id,status_verifikasi,diverifikasi_oleh,diverifikasi_pada,pembayaran_id,
            nominal_diajukan,tanggal_transfer,metode,keterangan FROM pengajuan_pembayaran WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$tagihan || !$p || $p['tagihan_id'] !== $tagihanId || $p['penghuni_id'] !== $tagihan['penghuni_id']) {
            throw new DomainException('Pengajuan tidak ditemukan atau tidak sesuai dengan pemilik tagihan.');
        }
        if ($p['status_verifikasi'] === $status) {
            $db->commit();
            return false;
        }
        if ($p['status_verifikasi'] !== 'menunggu' || $p['diverifikasi_oleh'] !== null || $p['diverifikasi_pada'] !== null || $p['pembayaran_id'] !== null) {
            throw new DomainException('Pengajuan sudah diproses. Periksa keputusan terbaru.');
        }
        $pembayaranId = null;
        if ($aksi === 'terima') {
            $stmt = $db->prepare('SELECT id FROM pembayaran WHERE tagihan_aktif_unik=? FOR UPDATE');
            $stmt->execute([$tagihanId]);
            if ($stmt->fetchColumn()) {
                throw new DomainException('Sudah ada pembayaran sah untuk tagihan ini. Periksa transaksi sebelum menyelesaikan pengajuan.');
            }
            if (nominal_pembayaran($p['nominal_diajukan']) !== $tagihan['jumlah']) {
                throw new DomainException('Nominal pengajuan berbeda dari nominal penuh tagihan.');
            }
            if ($p['metode'] !== 'transfer' || !validasi_tanggal_sewa($p['tanggal_transfer'])) {
                throw new DomainException('Periksa metode dan tanggal transfer pengajuan.');
            }
            $pembayaranId = catat_pembayaran_baru($db, (int)$tagihanId, $adminId, [
                'tanggal_bayar'=>$p['tanggal_transfer'], 'nominal_dibayar'=>$tagihan['jumlah'],
                'metode'=>'transfer', 'keterangan'=>$p['keterangan'],
            ], $id);
        } else {
            $stmt = $db->prepare("INSERT INTO pembayaran_audit (pengajuan_id,aksi,admin_id,alasan,sebelum,sesudah)
                VALUES (?,'tolak',?,?,?,?)");
            $stmt->execute([$id,$adminId,$catatan,json_encode(['status_verifikasi'=>'menunggu'], JSON_THROW_ON_ERROR),
                json_encode(['status_verifikasi'=>'ditolak','tagihan_id'=>$tagihanId], JSON_THROW_ON_ERROR)]);
        }
        $stmt = $db->prepare('UPDATE pengajuan_pembayaran SET status_verifikasi=?,catatan_admin=?,diverifikasi_oleh=?,
            diverifikasi_pada=NOW(),pembayaran_id=? WHERE id=?');
        $stmt->execute([$status,$catatan === '' ? null : $catatan,$adminId,$pembayaranId,$id]);
        $diterima = $pembayaranId !== null;
        $stmt = $db->prepare('INSERT INTO notifikasi (penghuni_id,tagihan_id,tipe,peristiwa,judul,pesan,tanggal)
            VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$p['penghuni_id'],$tagihanId,$diterima ? 'lunas' : 'verifikasi',
            $diterima ? 'bayar-' . $pembayaranId : 'tolak-' . $id,
            $diterima ? 'Pembayaran disetujui' : 'Pembayaran ditolak',
            'Pembayaran tagihan ' . format_periode($tagihan['periode']) . ($diterima ? ' telah disetujui.' : ' ditolak. Periksa alasan di Riwayat Pembayaran.'),
            tanggal_hari_ini()]);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
