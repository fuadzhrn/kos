"""Uji HTTP Tahap 10 pada aplikasi salinan dan basis data MySQL baru. Jalankan tanpa -O."""
from contextlib import contextmanager
import json
from pathlib import Path
import re
import runpy
import secrets
import shutil
import socket
import subprocess
import tempfile
import time

stage9 = runpy.run_path(str(Path(__file__).with_name("uji-tahap-09.py")))
Browser = stage9["Browser"]
ROOT = stage9["ROOT"]
php = stage9["php"]
DB = "uji_aron_10_" + secrets.token_hex(8)
stage9["ENV"]["ARON_TEST_DB"] = DB

SERVER = """
require 'includes/database.local.php';
$name = getenv('ARON_TEST_DB');
if (!preg_match('/^uji_aron_10_[0-9a-f]{16}$/D', $name) || DB_NAME === $name) exit(2);
$server = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT, DB_USER, DB_PASS);
"""
SELECT_DB = """
require 'includes/koneksi.php';
$db = koneksi_database();
if ($db->query('SELECT DATABASE()')->fetchColumn() !== getenv('ARON_TEST_DB')) exit(2);
"""


@contextmanager
def test_database(app):
    php(SERVER + '$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");', ROOT)
    try:
        php(SERVER + """
            $values = ['DB_HOST'=>DB_HOST, 'DB_PORT'=>DB_PORT, 'DB_USER'=>DB_USER, 'DB_PASS'=>DB_PASS, 'DB_NAME'=>$name];
            $code = "<?php\n";
            foreach ($values as $k=>$v) $code .= 'define('.var_export($k,true).','.var_export($v,true).");\n";
            file_put_contents($argv[1],$code);
        """, ROOT, str(app / "includes/database.local.php"))
        php(SELECT_DB + r"""
            $schema = file_get_contents('database/schema.sql');
            $seed = file_get_contents('database/seed.sql');
            $start = strpos($schema, 'CREATE TABLE users (');
            $rows = strpos($seed, 'INSERT INTO users (');
            if ($start === false || $rows === false) exit(2);
            $ddl = substr($schema,$start).file_get_contents('database/migrasi-registrasi-pemesanan.sql');
            $dml = substr($seed,$rows);
            if (preg_match('/^\s*(USE|DROP|TRUNCATE|CREATE DATABASE)\b/im',$ddl."\n".$dml)) exit(2);
            $db->exec($ddl);
            $db->exec(str_replace(['2026-', '2025-'], ['2020-', '2019-'], $dml));
            $db->prepare("UPDATE users SET status='aktif', password_hash=?")
               ->execute([password_hash(getenv('ARON_TEST_PASS'), PASSWORD_DEFAULT)]);
        """, app)

        def query(statement):
            code = "echo json_encode($db->query($argv[1])->fetchAll(PDO::FETCH_NUM), JSON_THROW_ON_ERROR);"
            return json.loads(php(SELECT_DB + code, app, statement))

        assert query("SELECT DATABASE()") == [[DB]]
        print("DB sementara terverifikasi", DB, flush=True)
        yield query
    finally:
        php(SERVER + '$server->exec("DROP DATABASE `$name`");', ROOT)


@contextmanager
def server(tmp, app):
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    endpoint = f"127.0.0.1:{port}"
    log_path = tmp / "http.log"
    with log_path.open("wb") as log:
        process = subprocess.Popen(["php", "-d", f'session.save_path="{tmp.as_posix()}"',
                                    "-S", endpoint, "-t", str(app)], stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(("127.0.0.1", port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            else:
                raise RuntimeError("Server HTTP uji tidak merespons")
            yield "http://" + endpoint + "/"
        finally:
            process.terminate()
            process.wait(timeout=10)
    assert not re.search(r"PHP (Warning|Fatal|Parse|Notice)", log_path.read_text(errors="replace"))


def field(html, key):
    match = re.search(fr'name="{key}" value="([^"]+)"', html)
    assert match, key
    return match[1]


def post(browser, path, **fields):
    return browser.request(path, {"csrf_token": browser.token(path), **fields})


def expect(code, passed, detail):
    if not passed:
        raise AssertionError(f"{code} {detail}")
    print(code, detail, flush=True)


def check_scenarios(base, query):
    admin, other, tenant, guest = [Browser(base) for _ in range(4)]
    signin = "login.php"
    home = "admin/index.php"
    login_form = 'name="identitas"'
    invalid = post(guest, signin, identitas="admin", password="ContohTidakCocok")
    admin.login("admin")
    other.login("lina.marlina")
    expect("BB01", "<h1>Dashboard</h1>" in admin.request(home)
           and "<h1>Dashboard Penghuni</h1>" in other.request("penghuni/index.php")
           and "password salah" in invalid and login_form in guest.request(home), "Login dua peran dan sandi keliru")
    other.request(home, expected=403)
    admin.request("penghuni/index.php", expected=403)
    expect("BB02", login_form in guest.request("penghuni/index.php"), "HTTP 403 lintas peran dan tamu ke login")
    room_form = "admin/kamar-form.php"
    rooms = "admin/kamar.php"
    room = dict(nomor="QA10", harga="725000", deskripsi="Kamar uji", kondisi="aktif", fasilitas="Meja")
    created = post(admin, room_form, **room)
    rid = query("SELECT id FROM kamar WHERE nomor='QA10'")[0][0]
    edit = f"{room_form}?id={rid}"
    changed = post(admin, edit, **{**room, "harga":"730000", "fasilitas":"Meja\nLemari"})
    duplicate = post(admin, room_form, **room)
    expect("BB03", "Kamar berhasil disimpan" in created and "Rp 730.000" in changed
           and "Meja, Lemari" in changed and "sudah dipakai" in duplicate, "Tambah, ubah, baca dan tolak nomor kamar duplikat")
    scratch = post(admin, room_form, **{**room, "nomor":"QA-HAPUS"})
    unused = query("SELECT id FROM kamar WHERE nomor='QA-HAPUS'")[0][0]
    deleted = post(admin, rooms, aksi="hapus", id=unused)
    expect("BB04", "QA-HAPUS" in scratch and "Kamar berhasil dihapus" in deleted and "QA-HAPUS" not in deleted,
           "Hapus kamar tanpa penyewaan")
    people = "admin/penghuni-form.php"
    person = dict(nama="Penyewa Contoh", no_telepon="080000001234", akun="qa.penyewa", password=stage9["PASSWORD"],
                  mulai_sewa="1", kamar_id=rid, tanggal_masuk="2021-01-01", harga_sewa_disepakati="715000", hari_jatuh_tempo="31")
    created = post(admin, people, **person)
    pid, sid = query("SELECT p.id,s.id FROM penghuni p JOIN penyewaan s ON s.penghuni_id=p.id WHERE p.nama='Penyewa Contoh'")[0]
    tenant.login("qa.penyewa")
    changed = post(admin, f"{people}?id={pid}", **{**person, "password":"", "nama":"Penyewa Audit", "sewa_aktif":sid})
    expect("BB05", "Data penghuni berhasil disimpan" in created and "Penyewa Audit" in changed
           and "QA10" in tenant.request("penghuni/kamar.php"), "Akun, profil penghuni dan sewa baru tersimpan")
    duplicate = post(admin, people, **{**person, "akun":"qa.kedua", "nama":"Kedua Uji"})
    maintenance = post(admin, people, **{**person, "kamar_id":3, "akun":"qa.kedua", "nama":"Kedua Uji"})
    expect("BB06", 'data-status="terisi" data-cari="kamar qa10' in admin.request(rooms)
           and "Kamar terisi" in duplicate and "tidak tersedia" in maintenance
           and query("SELECT COUNT(*) FROM users WHERE username='qa.kedua'") == [[0]]
           and query("SELECT COUNT(*) FROM penghuni WHERE nama='Kedua Uji'") == [[0]], "Kamar terisi/perawatan ditolak tanpa akun atau profil yatim")
    bills = "admin/tagihan.php"
    def preview(mode, period, **extra):
        return post(admin, bills, aksi="pratinjau", mode=mode, periode=period, **extra)

    def confirm(html):
        return post(admin, bills, aksi="konfirmasi", tiket=field(html, "tiket"))

    peek = preview("baru","2021-02",penyewaan_id=sid)
    before = query("SELECT COUNT(*) FROM tagihan WHERE periode='2021-02'")
    result = confirm(peek)
    bid, price, due = query(f"SELECT id,jumlah,jatuh_tempo FROM tagihan WHERE penyewaan_id={sid} AND periode='2021-02'")[0]
    expect("BB07", "Akan dibuat" in peek and before == [[0]] and "Berhasil 1" in result
           and (price, due) == ("715000.00","2021-02-28"), "Tagihan individual Rp 715.000, 31 disesuaikan ke 28 Februari")
    result = confirm(preview("massal","2021-02"))
    repeat = confirm(preview("massal","2021-02"))
    expect("BB08", "Berhasil 3 · Dilewati 1 · Gagal 0" in result and "Berhasil 0 · Dilewati 4 · Gagal 0" in repeat
           and query("SELECT COUNT(*) FROM tagihan WHERE periode='2021-02'") == [[4]], "Tagihan massal melewati duplikat pada dua konfirmasi")
    future = confirm(preview("baru","2098-01",penyewaan_id=3))
    def bill_status(name, year, state):
        html = admin.request(f"{bills}?tahun={year}&status={state}&cari={name}")
        return re.search(fr'<span class="badge badge--{state}">', html) is not None and "Menampilkan 1 dari" in html

    expect("BB09", "Berhasil 1" in future and bill_status("Lina",2098,"belum_bayar")
           and bill_status("Penyewa",2021,"terlambat"), "Status belum bayar dan terlambat berdasarkan jatuh tempo")
    pays = "admin/pembayaran.php"
    def pay(amount):
        form = admin.request(pays+"?form=baru")
        return admin.request(pays, dict(csrf_token=field(form,"csrf_token"), form_id=field(form,"form_id"),
                             aksi="simpan", tagihan_id=bid, nominal_dibayar=amount, tanggal_bayar="2021-03-01",
                             metode="tunai", keterangan="Bukti Uji QA10"))

    partial = pay("350000")
    before = query(f"SELECT COUNT(*) FROM pembayaran WHERE tagihan_id={bid}")
    paid = pay("715000")
    repeated = pay("715000")
    expect("BB10", "sama persis" in partial and before == [[0]] and "Pembayaran tersimpan" in paid
           and "sudah tercatat" in repeated and bill_status("Penyewa",2021,"lunas"), "Pembayaran penuh diterima satu kali, parsial ditolak dan status lunas")
    trans_id = query(f"SELECT id FROM pembayaran WHERE tagihan_id={bid} AND status='sah'")[0][0]
    detail_path = f"{pays}?lihat={trans_id}"
    def adjust(action, reason):
        detail = admin.request(detail_path)
        return admin.request(detail_path, dict(csrf_token=field(detail, "csrf_token"),
                             aksi=action, pembayaran_id=trans_id, versi=field(detail, "versi"), tagihan_id=bid,
                             tanggal_bayar="2021-03-01", nominal_dibayar="715000", metode="transfer",
                             keterangan="Bukti Koreksi QA10", alasan=reason))

    corrected = adjust("koreksi","Metode transfer sesuai bukti")
    expect("BB10a", "Koreksi pembayaran dan catatan audit tersimpan" in corrected
           and "Bukti Koreksi QA10" in tenant.request("penghuni/riwayat.php")
           and query(f"SELECT metode FROM pembayaran WHERE id={trans_id}") == [["transfer"]],
           "Koreksi metode dan keterangan diperbarui pada riwayat")
    cancelled = adjust("batal","Pembatalan uji")
    expect("BB10b", "Pembayaran dibatalkan" in cancelled and bill_status("Penyewa",2021,"terlambat")
           and "Bukti Koreksi QA10" not in tenant.request("penghuni/riwayat.php")
           and query(f"SELECT aksi FROM pembayaran_audit WHERE pembayaran_id={trans_id} ORDER BY id")
           == [["catat"],["koreksi"],["batal"]], "Pembatalan memulihkan status dan menyimpan tiga aksi audit")
    replacement = pay("715000")
    expect("BB10c", "Pembayaran tersimpan" in replacement and bill_status("Penyewa",2021,"lunas")
           and query(f"SELECT status FROM pembayaran WHERE tagihan_id={bid} ORDER BY id") == [["dibatalkan"],["sah"]],
           "Penerimaan pengganti membuat tepat satu pembayaran sah")
    history = "penghuni/riwayat.php"
    expect("BB11", "Bukti Uji QA10" in tenant.request(history) and "Bukti Uji QA10" not in other.request(history),
           "Riwayat pembayaran hanya muncul untuk pemiliknya")
    notify = "penghuni/notifikasi.php"
    notice = tenant.request(notify)
    nid = query(f"""SELECT n.id FROM notifikasi n JOIN pembayaran p ON p.tagihan_id=n.tagihan_id
                   AND n.peristiwa=CONCAT('bayar-',p.id) WHERE p.tagihan_id={bid} AND p.status='sah'""")[0][0]
    marked = post(tenant,notify,notifikasi_id=nid)
    expect("BB12", "Pembayaran penuh untuk tagihan 2021-02" in notice and f'value="{nid}">Tandai dibaca' not in marked
           and query(f"SELECT dibaca FROM notifikasi WHERE id={nid}") == [[1]], "Notifikasi pembayaran muncul dan status dibaca tersimpan")
    report = admin.request("admin/laporan.php?bulan=02&tahun=2021")
    expect("BB13", all(total in report for total in ["Rp 2.415.000","Rp 715.000","Rp 1.700.000"]),
           "Laporan Februari 2021 sesuai tagihan, penerimaan dan tunggakan")
    ended = post(admin,"admin/penghuni.php",aksi="nonaktifkan",id=pid,sewa_id=sid,tanggal_keluar="2021-03-10")
    details = admin.request(f"admin/penghuni-detail.php?id={pid}")
    protected = post(admin,rooms,aksi="hapus",id=rid)
    expect("BB14", "Penyewaan selesai" in ended and 'data-status="kosong" data-cari="kamar qa10' in protected
           and "Kamar ini memiliki riwayat penyewaan dan tidak dapat dihapus permanen" in protected and "Selesai" in details and "Februari 2021" in details
           and 'badge--lunas' in details and login_form in tenant.request("penghuni/index.php"),
           "Setelah keluar kamar kosong, akses berhenti dan riwayat sewa/tagihan bertahan")
    counts = query(f"""SELECT
        (SELECT COUNT(*) FROM penyewaan WHERE id={sid} AND status='selesai' AND tanggal_keluar='2021-03-10'),
        (SELECT COUNT(*) FROM pembayaran WHERE tagihan_id={bid} AND status='sah' AND nominal_dibayar=715000),
        (SELECT COUNT(*) FROM tagihan WHERE id={bid} AND penyewaan_id={sid}),
        (SELECT COUNT(*) FROM pembayaran_audit a JOIN pembayaran p ON p.id=a.pembayaran_id WHERE p.tagihan_id={bid}),
        (SELECT COUNT(*) FROM users WHERE username='qa.penyewa' AND status='nonaktif')""")
    expect("BB15", counts == [[1,1,1,4,1]], "Rantai penyewaan, akun, tagihan, pembayaran sah dan audit utuh setelah keluar")
    loggedout = admin.request("logout.php", {"csrf_token":admin.token(home)})
    expect("BB16", login_form in loggedout and login_form in admin.request(home), "Sesi admin berakhir setelah logout")


if __name__ == "__main__":
    if not __debug__:
        raise RuntimeError("Python -O menonaktifkan pemeriksaan hasil uji")
    with tempfile.TemporaryDirectory(prefix="kos-aron-uji10-") as path:
        tmp = Path(path)
        app = tmp / "app"
        shutil.copytree(ROOT, app, ignore=shutil.ignore_patterns("database.local.php", "docs", "*.log"))
        with test_database(app) as query, server(tmp, app) as base:
            check_scenarios(base, query)
    print("Selesai, server berhenti dan database uji dihapus.")
