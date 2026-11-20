<?php
require_once __DIR__ . '/tagihan.php';
require_once __DIR__ . '/notifikasi.php';

const SQL_RIWAYAT_PEMBAYARAN = 'SELECT pb.*, s.penghuni_id, p.nama AS nama_penghuni, k.nomor AS nomor_kamar,
    u.nama AS admin_pencatat, t.periode AS periode_tagihan, t.jatuh_tempo, t.jumlah AS jumlah_tagihan FROM pembayaran pb
    JOIN users u ON u.id = pb.dicatat_oleh
    JOIN tagihan t ON t.id = pb.tagihan_id JOIN penyewaan s ON s.id = t.penyewaan_id
    JOIN penghuni p ON p.id = s.penghuni_id JOIN kamar k ON k.id = s.kamar_id';

function daftar_metode_pembayaran(): array
{
    return ['tunai' => 'Tunai', 'transfer' => 'Transfer'];
}

function get_semua_pembayaran(bool $termasukBatal = false): array
{
    $filter = $termasukBatal ? '' : " WHERE pb.status = 'sah'";
    return koneksi_database()->query(SQL_RIWAYAT_PEMBAYARAN . $filter . ' ORDER BY pb.tanggal_bayar DESC, pb.id DESC')->fetchAll();
}

function get_pembayaran_by_id(int $id, bool $termasukBatal = false): ?array
{
    $filter = $termasukBatal ? '' : " AND pb.status = 'sah'";
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_PEMBAYARAN . ' WHERE pb.id = ?' . $filter);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_detail_pembayaran_penghuni(int $id, int $penghuniId): ?array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_PEMBAYARAN . " WHERE pb.id = ? AND s.penghuni_id = ? AND pb.status = 'sah'");
    $stmt->execute([$id, $penghuniId]);
    return $stmt->fetch() ?: null;
}

function get_pembayaran_by_penghuni(int $penghuniId, int $batas = PHP_INT_MAX): array
{
    $stmt = koneksi_database()->prepare(SQL_RIWAYAT_PEMBAYARAN . " WHERE s.penghuni_id = ? AND pb.status = 'sah'
        ORDER BY pb.tanggal_bayar DESC, pb.id DESC LIMIT ?");
    $stmt->bindValue(1, $penghuniId, PDO::PARAM_INT);
    $stmt->bindValue(2, $batas, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Ringkasan pembayaran untuk tagihan pada satu PERIODE TAGIHAN tertentu
 * (dipakai di Laporan Admin, selaras dengan filter periode yang sama dengan
 * ringkasan_tagihan_periode()). $periode = null berarti semua periode.
 * Beda dengan ringkasan_pembayaran_bulan_ini(): fungsi itu berdasarkan
 * TANGGAL PEMBAYARAN diterima (kas bulan berjalan), bukan periode tagihannya.
 *
 * @return array{jumlah: int, total: int}
 */
function ringkasan_pembayaran_periode(?string $periode = null): array
{
    $hasil = ['jumlah' => 0, 'total' => 0];
    foreach (get_semua_pembayaran() as $pembayaran) {
        if ($periode !== null && $pembayaran['periode_tagihan'] !== $periode) {
            continue;
        }
        $hasil['jumlah']++;
        $hasil['total'] += $pembayaran['nominal_dibayar'];
    }
    return $hasil;
}

/**
 * Ringkasan pembayaran pada satu bulan kalender (default: bulan berjalan).
 *
 * @param string|null $bulan Format YYYY-MM, default bulan dari tanggal_hari_ini()
 * @return array{jumlah: int, total: int, bulan: string}
 */
function ringkasan_pembayaran_bulan_ini(?string $bulan = null): array
{
    $bulan ??= substr(tanggal_hari_ini(), 0, 7);
    $hasil = ['jumlah' => 0, 'total' => 0, 'bulan' => $bulan];

    foreach (get_semua_pembayaran() as $pembayaran) {
        if (substr($pembayaran['tanggal_bayar'], 0, 7) === $bulan) {
            $hasil['jumlah']++;
            $hasil['total'] += $pembayaran['nominal_dibayar'];
        }
    }
    return $hasil;
}

function audit_pembayaran(int $id): array
{
    $stmt = koneksi_database()->prepare('SELECT a.*, u.nama AS admin FROM pembayaran_audit a
        JOIN users u ON u.id = a.admin_id WHERE a.pembayaran_id = ? ORDER BY a.id DESC');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

/** Bandingkan DECIMAL(12,2) tanpa pembulatan floating point. */
function nominal_pembayaran(string $nilai): string
{
    if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', $nilai, $bagian)) {
        throw new DomainException('Isi nominal positif dengan maksimal dua angka desimal.');
    }
    $nominal = (ltrim($bagian[1], '0') ?: '0') . '.' . str_pad($bagian[2] ?? '', 2, '0');
    if ($nominal === '0.00') {
        throw new DomainException('Nominal dibayar harus lebih dari nol.');
    }
    return $nominal;
}

/** Dipanggil dalam transaksi yang sudah mengunci tagihan dan memeriksa pembayaran aktif. */
function catat_pembayaran_baru(PDO $db, int $tagihanId, int $adminId, array $nilai, ?int $pengajuanId = null): int
{
    $stmt = $db->prepare('INSERT INTO pembayaran
        (tanggal_bayar,nominal_dibayar,metode,keterangan,tagihan_id,dicatat_oleh) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$nilai['tanggal_bayar'],$nilai['nominal_dibayar'],$nilai['metode'],$nilai['keterangan'],$tagihanId,$adminId]);
    $id = (int)$db->lastInsertId();
    $sesudah = $nilai + ['id'=>$id,'tagihan_id'=>$tagihanId,'dicatat_oleh'=>$adminId,'status'=>'sah'];
    $stmt = $db->prepare('INSERT INTO pembayaran_audit (pembayaran_id,pengajuan_id,aksi,admin_id,sesudah) VALUES (?,?,?,?,?)');
    $stmt->execute([$id,$pengajuanId,$pengajuanId === null ? 'catat' : 'terima',$adminId,json_encode($sesudah, JSON_THROW_ON_ERROR)]);
    if ($pengajuanId === null) {
        buat_notifikasi_tagihan($db, tanggal_hari_ini(), $tagihanId);
    }
    return $id;
}

/** Faktur dikunci dahulu untuk semua tindakan. Pembayaran dan jurnal audit ditulis dalam satu transaksi. */
function proses_pembayaran(string $aksi, int $adminId, array $form): int
{
    if (!in_array($aksi, ['simpan', 'koreksi', 'batal'], true)) {
        throw new DomainException('Tindakan pembayaran tidak valid.');
    }
    $tagihanId = filter_var($form['tagihan_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$tagihanId) {
        throw new DomainException('Pilih tagihan yang akan dibayar.');
    }
    $alasan = $form['alasan'] ?? '';
    if ($aksi !== 'simpan' && ($alasan === '' || mb_strlen($alasan) > 255)) {
        throw new DomainException('Alasan koreksi atau pembatalan wajib diisi, maksimal 255 karakter.');
    }
    if ($aksi !== 'batal') {
        $nominal = nominal_pembayaran($form['nominal_dibayar']);
        if (format_tanggal($form['tanggal_bayar']) === '-' || $form['tanggal_bayar'] < '1000-01-01' || $form['tanggal_bayar'] > tanggal_hari_ini()) {
            throw new DomainException('Isi tanggal pembayaran yang valid sampai hari ini.');
        }
        if (!isset(daftar_metode_pembayaran()[$form['metode']]) || mb_strlen($form['keterangan']) > 255) {
            throw new DomainException('Pilih tunai atau transfer dan batasi keterangan hingga 255 karakter.');
        }
    }

    $db = koneksi_database();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND status = 'aktif' FOR SHARE");
        $stmt->execute([$adminId]);
        if (!$stmt->fetchColumn()) {
            throw new DomainException('Pembayaran hanya dapat diproses oleh admin aktif.');
        }
        $stmt = $db->prepare('SELECT jumlah FROM tagihan WHERE id = ? FOR UPDATE');
        $stmt->execute([$tagihanId]);
        $jumlah = $stmt->fetchColumn();
        if ($jumlah === false) {
            throw new DomainException('Tagihan tidak ditemukan.');
        }
        if ($aksi !== 'batal' && $nominal !== $jumlah) {
            throw new DomainException('Nominal dibayar harus sama persis dengan tagihan ' . format_rupiah($jumlah) . '.');
        }

        $stmt = $db->prepare("SELECT id, tagihan_id, tanggal_bayar, nominal_dibayar, metode, keterangan, dicatat_oleh, status
            FROM pembayaran WHERE tagihan_aktif_unik = ? FOR UPDATE");
        $stmt->execute([$tagihanId]);
        $lama = $stmt->fetch() ?: null;
        if ($aksi === 'simpan' && $lama) {
            throw new DomainException('Pembayaran untuk tagihan ini sudah tercatat. Periksa detail transaksi.');
        }
        if ($aksi === 'simpan') {
            $stmt = $db->prepare('SELECT id FROM pengajuan_pembayaran WHERE tagihan_menunggu_unik=? FOR UPDATE');
            $stmt->execute([$tagihanId]);
            if ($stmt->fetchColumn()) {
                throw new DomainException('Ada bukti transfer yang menunggu verifikasi untuk tagihan ini. Periksa pengajuan tersebut sebelum mencatat pembayaran langsung.');
            }
            $id = catat_pembayaran_baru($db, $tagihanId, $adminId, [
                'tanggal_bayar'=>$form['tanggal_bayar'], 'nominal_dibayar'=>$nominal,
                'metode'=>$form['metode'], 'keterangan'=>$form['keterangan'] === '' ? null : $form['keterangan'],
            ]);
            $db->commit();
            return $id;
        }
        if (!$lama || (string)$lama['id'] !== $form['pembayaran_id']) {
            throw new DomainException('Pembayaran sudah berubah. Muat ulang detail transaksi.');
        }
        $stmt = $db->prepare('SELECT id FROM pembayaran_audit WHERE pembayaran_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$lama['id']]);
        if ((string)($stmt->fetchColumn() ?: 0) !== $form['versi']) {
            throw new DomainException('Detail sudah berubah. Muat ulang sebelum melakukan koreksi.');
        }
        $baru = $lama;
        if ($aksi === 'batal') {
            $baru['status'] = 'dibatalkan';
            $id = (int)$lama['id'];
            $db->prepare("UPDATE pembayaran SET status = 'dibatalkan' WHERE id = ?")->execute([$id]);
        } else {
            $baru['tanggal_bayar'] = $form['tanggal_bayar'];
            $baru['nominal_dibayar'] = $nominal;
            $baru['metode'] = $form['metode'];
            $baru['keterangan'] = $form['keterangan'] === '' ? null : $form['keterangan'];
            $nilai = [$baru['tanggal_bayar'], $nominal, $baru['metode'], $baru['keterangan']];
            if ($baru === $lama) {
                throw new DomainException('Belum ada nilai yang berubah.');
            }
            $id = (int)$lama['id'];
            $db->prepare('UPDATE pembayaran SET tanggal_bayar = ?, nominal_dibayar = ?, metode = ?, keterangan = ? WHERE id = ?')
                ->execute([...$nilai, $id]);
        }

        $stmt = $db->prepare('INSERT INTO pembayaran_audit (pembayaran_id, aksi, admin_id, alasan, sebelum, sesudah)
            VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $aksi, $adminId, $alasan,
            json_encode($lama, JSON_THROW_ON_ERROR), json_encode($baru, JSON_THROW_ON_ERROR)]);
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new DomainException('Pembayaran untuk tagihan ini sudah tercatat.');
        }
        throw $e;
    }
}
