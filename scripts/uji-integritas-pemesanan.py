"""Uji transaksi pemesanan yang membutuhkan beberapa koneksi MySQL pada basis data fiktif."""
from concurrent.futures import ThreadPoolExecutor
import json
from pathlib import Path
import runpy
import shutil
import tempfile
import time

fixture = runpy.run_path(str(Path(__file__).with_name("uji-tahap-10.py")))
BOOT = fixture["SELECT_DB"] + """
    require 'includes/functions.php';
    require 'data/pemesanan.php';
    date_default_timezone_set('Asia/Jakarta');
"""


def run_cases(app, query):
    def php(code, *args):
        return fixture["php"](BOOT + code, app, *map(str, args))

    def submit(phone, room=1):
        return php("""
            try {
                echo ajukan_pemesanan((int)$argv[2], ['nama_lengkap'=>'Pemesan Uji',
                    'nomor_telepon'=>$argv[1], 'rencana_tanggal_masuk'=>tanggal_hari_ini()]);
            } catch (DomainException $e) { echo $e->getMessage(); }
        """, phone, room)

    def decide(booking, action):
        return php("""
            try { keputusan_pemesanan((int)$argv[1], $argv[2], 'Uji keputusan', 1); echo 'OK'; }
            catch (DomainException $e) { echo $e->getMessage(); }
        """, booking, action)

    references = [submit(phone) for phone in ("080000000881", "080000000882")]
    assert all(ref.startswith("KA-") for ref in references) and len(set(references)) == 2
    pending = [row[0] for row in query("SELECT id FROM pemesanan WHERE status='menunggu'")]
    assert len(pending) == 2
    # Tahan baris kamar sampai MySQL sendiri menunjukkan kedua keputusan sedang menunggu.
    with ThreadPoolExecutor(max_workers=3) as pool:
        locked = pool.submit(php, """
            $db->beginTransaction();
            $db->query('SELECT id FROM kamar WHERE id=1 FOR UPDATE')->fetchColumn();
            touch('uji-kunci-siap');
            $batas = microtime(true) + 25;
            while (!is_file('uji-buka-kunci') && microtime(true) < $batas) { usleep(20000); }
            $db->rollBack();
        """)
        try:
            deadline = time.monotonic() + 12
            while not (app / "uji-kunci-siap").exists() and time.monotonic() < deadline:
                time.sleep(.025)
            assert (app / "uji-kunci-siap").exists(), "Koneksi pengunci tidak siap"
            decisions = [pool.submit(decide, booking, "setujui") for booking in pending]
            waiting = 0
            while time.monotonic() < deadline:
                waiting = query("""SELECT COUNT(*) FROM information_schema.PROCESSLIST
                    WHERE DB=DATABASE() AND ID <> CONNECTION_ID()
                    AND INFO LIKE 'SELECT kondisi FROM kamar WHERE id%FOR UPDATE%'""")[0][0]
                if waiting == 2:
                    break
                time.sleep(.03)
            assert waiting == 2, "MySQL tidak menunjukkan dua koneksi persetujuan serentak"
        finally:
            (app / "uji-buka-kunci").touch()
        locked.result(timeout=10)
        outcomes = [worker.result(timeout=10) for worker in decisions]
    assert sorted(outcomes) == sorted(["OK", "Kamar sudah tidak tersedia. Silakan pilih kamar lain."])
    winner, = query("SELECT id FROM pemesanan WHERE kamar_ditahan_unik=1")
    loser, = query("SELECT id FROM pemesanan WHERE status='menunggu'")
    assert query("SELECT COUNT(*) FROM penyewaan WHERE kamar_aktif_unik=1") == [[0]]
    print("INT01 Dua koneksi MySQL bersamaan dibuktikan di PROCESSLIST. Satu disetujui, satu ditolak, sewa aktif nol.")

    assert decide(winner[0], "batalkan") == "OK"
    assert php("echo json_encode(get_kamar_by_id(1)['dapat_dipesan']);") == "true"
    assert decide(loser[0], "setujui") == "OK"
    assert decide(loser[0], "batalkan") == "OK"
    assert query("SELECT COUNT(*) FROM pemesanan WHERE status='dibatalkan'") == [[2]]
    print("INT02 Pembatalan melepaskan kamar, pengajuan lain dapat disetujui, kedua riwayat tetap ada.")

    # Arsipkan satu profil seed fiktif dan gunakan format internasional dari kontak lokal.
    php("akhiri_penyewaan(1,2,tanggal_hari_ini());")
    before = query("""SELECT u.id,u.password_hash,h.nama,h.email,h.no_telepon FROM users u
        JOIN penghuni h ON h.user_id=u.id WHERE h.id=1""")
    counts = query("SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM penghuni)")
    assert query("SELECT status FROM users WHERE id=2") == [["nonaktif"]]
    assert submit("+62 811-0000-0101").startswith("KA-")
    booking = query("SELECT MAX(id) FROM pemesanan")[0][0]
    assert decide(booking, "setujui") == "OK"
    matched = json.loads(php("echo json_encode(array_column(pilihan_penghuni_pemesanan((int)$argv[1]), 'id'));", booking))
    assert matched == [1]
    def activate(profile, checked):
        return php("""
            try {
                echo aktifkan_pemesanan((int)$argv[1], ['penghuni_id'=>$argv[2],
                    'identitas_terverifikasi'=>$argv[3], 'tanggal_masuk'=>tanggal_hari_ini(),
                    'harga_sewa_disepakati'=>'500000','hari_jatuh_tempo'=>'17'],1);
            } catch (DomainException $e) { echo $e->getMessage(); }
        """, booking, profile, checked)
    assert "identitas" in activate(0, "") and "identitas" in activate(1, "")
    assert query("SELECT COUNT(*) FROM penyewaan WHERE kamar_aktif_unik=1") == [[0]]
    lease = int(activate(1, "1"))
    after = query("""SELECT u.id,u.password_hash,h.nama,h.email,h.no_telepon FROM users u
        JOIN penghuni h ON h.user_id=u.id WHERE h.id=1""")
    assert before == after and counts == query("SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM penghuni)")
    assert query("SELECT status FROM users WHERE id=2") == [["aktif"]]
    assert query(f"SELECT penghuni_id FROM penyewaan WHERE id={lease} AND kamar_aktif_unik=1") == [[1]]
    assert query(f"SELECT penyewaan_id FROM pemesanan WHERE id={booking}") == [[lease]]
    assert query("SELECT status FROM penyewaan WHERE id=2") == [["selesai"]]
    print("INT03 Profil lama diverifikasi, diaktifkan dengan ID dan hash sandi sama, riwayat serta jumlah akun/profil tetap.")

    phones = ["080000000883", "+62 800-0000-0883", "6280000000883", "0800-0000-0883"]
    attempts = [submit(phone, 2) for phone in phones]
    assert all(result.startswith("KA-") for result in attempts[:3])
    assert "Batas pengajuan" in attempts[3]
    assert query("""SELECT COUNT(*) FROM calon_penghuni c JOIN pemesanan p ON c.id=p.calon_penghuni_id
                    WHERE c.nomor_telepon='6280000000883'""") == [[3]]
    print("INT04 Empat proses PHP terpisah, empat penulisan nomor setara. Tiga pengajuan tersimpan, keempat dibatasi.")


if __name__ == "__main__":
    if not __debug__:
        raise RuntimeError("Jalankan pengujian tanpa Python -O.")
    with tempfile.TemporaryDirectory(prefix="kos-aron-integritas-") as path:
        app = Path(path) / "app"
        shutil.copytree(fixture["ROOT"], app, ignore=shutil.ignore_patterns("database.local.php", "docs", "*.log"))
        with fixture["test_database"](app) as query:
            run_cases(app, query)
    print("Selesai; basis data dan salinan sementara dibersihkan.")
