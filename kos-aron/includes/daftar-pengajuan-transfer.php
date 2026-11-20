<section class="panel" id="pengajuan">
    <div class="panel__head"><h2><?= $untukAdmin ? 'Pengajuan Pembayaran' : 'Riwayat Pengajuan Transfer' ?></h2></div>
    <?php if ($untukAdmin): ?>
        <form method="get" action="pembayaran.php#pengajuan" class="toolbar" role="search" aria-label="Cari pengajuan pembayaran">
            <div class="filter-field"><label for="status-pengajuan">Status verifikasi</label>
                <select class="form-control" id="status-pengajuan" name="status_pengajuan">
                    <?php foreach ([''=>'Semua','menunggu'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak'] as $nilai => $label): ?>
                        <option value="<?= e($nilai) ?>" <?= $statusPengajuan === $nilai ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="filter-field"><label for="periode-pengajuan">Periode tagihan</label>
                <select id="periode-pengajuan" class="form-control" name="periode_pengajuan">
                    <option value="">Semua periode</option>
                    <?php foreach ($daftarPeriode as $nilai): ?><option value="<?= e($nilai) ?>" <?= $nilai === $periodePengajuan ? 'selected' : '' ?>><?= e(format_periode($nilai)) ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-field"><label for="cari-pengajuan">Nama penghuni, nomor kamar atau tagihan</label>
                <input class="form-control" type="search" id="cari-pengajuan" name="cari_pengajuan" maxlength="100" value="<?= e($cariPengajuan) ?>"></div>
            <button class="btn btn-outline" type="submit">Cari</button>
        </form>
    <?php endif; ?>
    <?php if (!$pengajuanTransfer): ?>
        <p class="panel__kosong">Belum ada pengajuan pembayaran.</p>
    <?php else: ?>
        <div class="tabel-wrap">
            <table class="tabel tabel--tumpuk">
                <thead><tr>
                    <?php if ($untukAdmin): ?><th scope="col">No.</th><th scope="col">Tanggal pengajuan</th><th scope="col">Nama penghuni</th><th scope="col">Kamar</th><?php endif; ?>
                    <th scope="col">Tagihan</th><th scope="col">Tanggal transfer</th><th scope="col">Nominal</th>
                    <?php if ($untukAdmin): ?><th scope="col">Metode</th><?php endif; ?>
                    <th scope="col">Status pengajuan</th><th scope="col">Aksi</th>
                </tr></thead>
                <tbody>
                <?php $terlihat = []; ?>
                <?php foreach ($pengajuanTransfer as $i => $baris): ?>
                    <?php $statusBaris = $baris['status_verifikasi']; $tagihanId = (int)$baris['tagihan_id']; ?>
                    <tr>
                        <?php if ($untukAdmin): ?>
                            <td data-label="No."><?= $i + 1 ?></td><td data-label="Tanggal pengajuan"><?= e($baris['created_at']) ?></td>
                            <td data-label="Nama penghuni"><?= e($baris['nama_penghuni']) ?></td><td data-label="Kamar"><?= e($baris['nomor_kamar']) ?></td>
                        <?php endif; ?>
                        <td data-label="Tagihan">#<?= (int)$baris['tagihan_id'] ?> · <?= e(format_periode($baris['periode'])) ?>
                            <?php if (!$untukAdmin): ?><span class="tabel__sub">Kamar <?= e($baris['nomor_kamar']) ?></span><?php endif; ?></td>
                        <td data-label="Tanggal transfer"><?= e(format_tanggal($baris['tanggal_transfer'])) ?>
                            <?php if ($baris['nama_pengirim'] !== null): ?><span class="tabel__sub">Pengirim: <?= e($baris['nama_pengirim']) ?></span><?php endif; ?></td>
                        <td data-label="Nominal"><?= e(format_rupiah($baris['nominal_diajukan'])) ?></td>
                        <?php if ($untukAdmin): ?><td data-label="Metode"><?= e(label_metode_pembayaran($baris['metode'])) ?></td><?php endif; ?>
                        <td data-label="Status pengajuan">
                            <span class="badge badge--<?= e(['menunggu'=>'belum_bayar','disetujui'=>'lunas','ditolak'=>'terlambat'][$statusBaris]) ?>"><?= e(label_status_pengajuan($statusBaris)) ?></span>
                            <?php if ($statusBaris === 'disetujui' && $baris['pembayaran_id'] === $baris['pembayaran_sah_id'] && $baris['tanggal_bayar']): ?>
                                <span class="tabel__sub">Lunas · <?= e(format_tanggal($baris['tanggal_bayar'])) ?></span>
                            <?php elseif ($statusBaris === 'disetujui'): ?>
                                <span class="tabel__sub">Periksa status transaksi terkini di Tagihan.</span>
                            <?php endif; ?>
                            <?php if ($baris['status_verifikasi'] === 'ditolak' && $baris['catatan_admin'] !== null): ?>
                                <span class="tabel__sub"><?= e($baris['catatan_admin']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Aksi">
                            <?php if ($untukAdmin): ?><a class="btn btn-outline btn-sm" href="verifikasi-pembayaran.php?id=<?= (int)$baris['id'] ?>">Detail</a><?php endif; ?>
                            <?php if (!$untukAdmin && !isset($terlihat[$tagihanId]) && $statusBaris === 'ditolak' && !$baris['pembayaran_sah_id'] && $baris['jumlah'] !== '0.00'): ?>
                                <a class="btn btn-outline btn-sm" href="tagihan.php?ajukan=<?= $tagihanId ?>#pengajuan">Ajukan Ulang Pembayaran</a>
                            <?php endif; ?>
                            <a class="btn btn-outline btn-sm" href="../bukti-transfer.php?pengajuan=<?= (int)$baris['id'] ?>">Lihat bukti</a>
                            <?php if ($baris['keterangan'] !== null): ?><span class="tabel__sub"><?= e($baris['keterangan']) ?></span><?php endif; ?></td>
                    </tr>
                    <?php $terlihat[$tagihanId] = true; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
