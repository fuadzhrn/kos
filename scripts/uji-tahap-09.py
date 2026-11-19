"""Uji integrasi Tahap 9. Jalankan dari checkout dengan PHP CLI, MySQL dan Chrome."""
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1] / "kos-aron"
DB = "uji_aron_09_" + secrets.token_hex(8)
PASSWORD = secrets.token_urlsafe(20)
ENV = {**os.environ, "ARON_TEST_DB": DB, "ARON_TEST_PASS": PASSWORD}


def php(code, cwd, *args):
    result = subprocess.run(["php", "-r", code, *args], cwd=cwd, env=ENV,
                            capture_output=True, text=True, check=False)
    if result.returncode:
        raise RuntimeError(result.stderr)
    return result.stdout.strip()


def check(ok, label):
    if not ok:
        raise AssertionError(label)
    print("OK", label, flush=True)


class Browser:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, fields=None, photo=None, expected=200):
        data = None
        headers = {}
        if photo is not None:
            boundary = secrets.token_hex(16)
            parts = []
            for key, value in fields.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            parts += [f'--{boundary}\r\nContent-Disposition: form-data; name="foto_kos"; filename="uji.png"\r\nContent-Type: image/png\r\n\r\n'.encode(),
                      photo, f'\r\n--{boundary}--\r\n'.encode()]
            data = b''.join(parts)
            headers["Content-Type"] = "multipart/form-data; boundary=" + boundary
        elif fields is not None:
            data = urllib.parse.urlencode(fields).encode()
        req = urllib.request.Request(self.base + path, data, headers)
        try:
            response = self.opener.open(req, timeout=15)
        except urllib.error.HTTPError as exc:
            response = exc
        with response:
            assert response.status == expected, (path, response.status)
            return response.read().decode()

    def token(self, path):
        html = self.request(path)
        return re.search(r'name="csrf_token" value="([^"]+)"', html)[1]

    def login(self, username):
        html = self.request("login.php", {"csrf_token": self.token("login.php"),
                            "identitas": username, "password": PASSWORD})
        assert 'name="identitas"' not in html, re.findall(r'(?:role="alert">|Warning</b>:)([^<]+)', html)


def run_tests(tmp, app):
    setup = """
        require 'includes/database.local.php';
        $name = getenv('ARON_TEST_DB');
        if (!preg_match('/^uji_aron_09_[0-9a-f]{16}$/D', $name)) exit(2);
        $server = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT, DB_USER, DB_PASS);
    """
    php(setup + """
        $server->exec("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    """, ROOT)
    try:
        php(setup + """
            $settings = ['DB_HOST'=>DB_HOST,'DB_PORT'=>DB_PORT,'DB_USER'=>DB_USER,'DB_PASS'=>DB_PASS,'DB_NAME'=>$name];
            $code = "<?php\\n";
            foreach ($settings as $k=>$v) $code .= 'define('.var_export($k,true).','.var_export($v,true).");\\n";
            file_put_contents($argv[1],$code);
        """, ROOT, str(app / "includes/database.local.php"))
        sql = "require 'includes/koneksi.php'; $db=koneksi_database(); if ($db->query('SELECT DATABASE()')->fetchColumn() !== getenv('ARON_TEST_DB')) exit(2); "
        php(sql + """
            $schema = file_get_contents('database/schema.sql');
            $db->exec(substr($schema,strpos($schema,'CREATE TABLE users (')));
            $db->exec('ALTER TABLE notifikasi DROP INDEX uq_notifikasi_peristiwa,
                DROP COLUMN peristiwa, ADD UNIQUE KEY uq_notifikasi_tagihan_tipe (tagihan_id,tipe)');
            $seed = file_get_contents('database/seed.sql');
            $seed = substr($seed,strpos($seed,'SET FOREIGN_KEY_CHECKS'));
            $seed = str_replace(['2026-', '2025-'], ['2020-', '2019-'], $seed);
            $seed = preg_replace('/UPDATE notifikasi n.*?;/s','',$seed);
            $db->exec($seed);
            $db->exec(file_get_contents('database/migrasi-09-notifikasi.sql'));
            $db->prepare("UPDATE users SET status='aktif', password_hash=?")->execute([password_hash(getenv('ARON_TEST_PASS'), PASSWORD_DEFAULT)]);
            $db->exec("UPDATE kamar SET created_at='2019-01-01'");
        """, app)
        def query(statement):
            return json.loads(php(sql + "echo json_encode($db->query($argv[1])->fetchAll(PDO::FETCH_NUM));",
                                  app, statement))
        check(query("SELECT peristiwa,dibaca FROM notifikasi WHERE tagihan_id=2") == [["bayar-2", 1]],
              "Migrasi mempertahankan pembayaran lama dan tanda baca")
        def generate(day):
            return int(php(sql + "require 'data/notifikasi.php'; echo buat_notifikasi_tagihan($db,$argv[1],5);", app, day))
        check([generate(d) for d in ["2020-11-12", "2020-11-13", "2020-11-13", "2020-11-16",
              "2020-11-17", "2020-11-20", "2020-11-21", "2020-11-22"]] == [0,1,0,0,1,0,1,0],
              "H-7, H-3, batas jatuh tempo, terlambat dan eksekusi ulang")
        cmd = ["php", str(app / "database/buat-notifikasi.php")]
        subprocess.run(cmd, check=True, capture_output=True)
        result = subprocess.run(cmd, check=True, text=True, capture_output=True)
        check("Notifikasi baru: 0" in result.stdout, "CLI pengingat idempoten")
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        base = f"http://127.0.0.1:{port}/"
        with (tmp / "http.log").open("wb") as log:
            server = subprocess.Popen(["php", "-d", 'session.save_path="' + tmp.as_posix() + '"', "-S", f"127.0.0.1:{port}", "-t", str(app)],
                                      stdout=log, stderr=log)
            try:
                for _ in range(100):
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=.1):
                            break
                    except OSError:
                        time.sleep(.05)
                admin, first, second, public = [Browser(base) for _ in range(4)]
                admin.login("admin")
                first.login("ahmad.fauzi")
                second.login("lina.marlina")
                path = "penghuni/notifikasi.php"
                nid = query("SELECT id FROM notifikasi WHERE tagihan_id=5 AND peristiwa='h3'")[0][0]
                check(f'value="{nid}">Tandai dibaca' in first.request(path) and
                      f'value="{nid}">Tandai dibaca' not in second.request(path), "Dua akun melihat notifikasi milik sendiri")
                second.request(path, {"csrf_token": second.token(path), "notifikasi_id": nid}, expected=403)
                first.request(path, {"csrf_token": "tidak-valid", "notifikasi_id": nid}, expected=403)
                assert query(f"SELECT dibaca FROM notifikasi WHERE id={nid}") == [[0]]
                before = re.search(r'Notifikasi \((\d+) belum dibaca\)', first.request("penghuni/index.php"))[1]
                first.request(path, {"csrf_token": first.token(path), "notifikasi_id": nid})
                after = re.search(r'Notifikasi \((\d+) belum dibaca\)', first.request("penghuni/index.php"))[1]
                check(int(before) == int(after) + 1 and query(f"SELECT dibaca,dibaca_pada IS NOT NULL FROM notifikasi WHERE id={nid}") == [[1,1]],
                      "Status baca tersimpan dan jumlah dashboard berubah")
                pay = sql + """require 'includes/functions.php'; require 'data/pembayaran.php';
                    $form=['tagihan_id'=>'5','nominal_dibayar'=>'600000','tanggal_bayar'=>'2020-12-01','metode'=>'tunai','keterangan'=>''];
                    echo proses_pembayaran('simpan',1,$form);"""
                paid_id = int(php(pay, app))
                check("Pembayaran penuh untuk tagihan 2020-11" in first.request(path),
                      "Lunas langsung muncul sesudah pembayaran")
                php(sql + """require 'includes/functions.php'; require 'data/pembayaran.php';
                    proses_pembayaran('batal',1,['tagihan_id'=>'5','pembayaran_id'=>$argv[1],
                    'versi'=>(string)$db->query('SELECT MAX(id) FROM pembayaran_audit')->fetchColumn(),'alasan'=>'Koreksi uji']);""",
                    app, str(paid_id))
                check("Pembayaran penuh untuk tagihan 2020-11" not in first.request(path),
                      "Pembatalan menyembunyikan pemberitahuan lunas lama")
                php(pay, app)
                check(first.request(path).count("Pembayaran penuh untuk tagihan 2020-11") == 1,
                      "Pembayaran pengganti menghasilkan satu informasi lunas yang berlaku")
                report = "admin/laporan.php"
                settings = "admin/pengaturan.php"
                first.request(report, expected=403)
                first.request(settings, expected=403)
                first.request(settings, {"aksi": "simpan_kos"}, expected=403)
                public.request("database/buat-notifikasi.php", expected=403)
                check(True, "Hak akses admin dan CLI")
                monthly = admin.request(report + "?bulan=10&tahun=2020")
                whole = admin.request(report + "?bulan=&tahun=")
                check(all(v in monthly for v in ["Rp 1.700.000","Rp 1.150.000","Rp 550.000","3 kosong"])
                      and 'Yusuf Hidayat' not in monthly, "Rekap Oktober, total sah dan jumlah kamar")
                check(all(v in whole for v in ["Rp 4.000.000","Rp 2.350.000","Rp 1.650.000","Yusuf Hidayat"]),
                      "Semua periode termasuk penghuni lama, pembayaran batal tidak terhitung")
                check("Rp 4.000.000" in admin.request(report+"?tahun=2020") and
                      "Yusuf Hidayat" in admin.request(report+"?bulan=6&tahun=2020&jenis=kamar"),
                      "Filter tahun dan penyewa historis")
                print_report(tmp, app, monthly)
                form = dict(aksi="simpan_kos", nama_kos="Kos Integrasi", deskripsi="Deskripsi contoh",
                            alamat="Jalan Contoh 42", whatsapp="628123456789", maps_link="https://maps.app.goo.gl/Contoh",
                            maps_embed="", csrf_token=admin.token(settings))
                admin.request(settings, {**form, "csrf_token": "tidak-valid"}, expected=403)
                for invalid in [dict(whatsapp="abc"), dict(maps_link="https://example.org/maps")]:
                    assert 'Pengaturan belum disimpan' in admin.request(settings, {**form, **invalid})
                assert 'Pengaturan belum disimpan' in admin.request(settings, form, b'Berkas teks contoh')
                assert query("SELECT nilai FROM pengaturan WHERE kunci='nama_kos'") == [["Kos Aron"]]
                check(True, "CSRF, WhatsApp, Maps dan tipe foto divalidasi sebelum penyimpanan")
                png = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jDVoAAAAASUVORK5CYII=")
                assert "berhasil disimpan" in admin.request(settings, form, png)
                home, about, contact = [public.request(p) for p in ["index.php","tentang.php","kontak.php"]]
                photo = query("SELECT nilai FROM pengaturan WHERE kunci='foto_utama'")[0][0]
                check(all("Kos Integrasi" in h for h in [home,about,contact]) and 'Deskripsi contoh' in about,
                      "Nama dan deskripsi tampil di website publik")
                check(all(k in contact for k in ["Jalan Contoh 42","628123456789","https://maps.app.goo.gl/Contoh"]),
                      "Alamat, WhatsApp dan Google Maps publik")
                check(photo in home and photo in about and urllib.request.urlopen(base+"assets/images/kos/"+photo).read() == png,
                      "Unggahan foto dapat dibuka dari website publik")
                for page in [home, about]:
                    frame = re.search(r'<div class="foto-frame">(.*?)</div>', page, flags=re.S)[1]
                    assert photo in frame and 'Foto ilustrasi' not in frame
                # Uji prioritas berkas unggahan pada salinan aplikasi tanpa menulis record kamar.
                (app / "assets/images/kamar/foto-uji.png").write_bytes(png)
                sources = json.loads(php("""require 'includes/config.php';
                    $k = ['id'=>1,'nomor'=>'01','foto'=>'foto-uji.png','galeri'=>[]];
                    $utama = daftar_foto_kamar($k);
                    $k['foto'] = null;
                    $k['galeri'] = ['foto-uji.png'];
                    $galeri = daftar_foto_kamar($k);
                    echo json_encode([$utama,$galeri,foto_adalah_ilustrasi($utama[0])]);""", app))
                assert sources == [['assets/images/kamar/foto-uji.png'], ['assets/images/kamar/foto-uji.png'], False]
                check(True, "Foto utama dan galeri asli diprioritaskan; label ilustrasi hanya untuk aset ilustrasi")
                admin.request(settings, {**form, "nama_kos":"Kos Integrasi Baru"})
                check(photo in public.request("index.php"), "Menyimpan teks mempertahankan foto")
                other_admin = Browser(base)
                other_admin.login("admin")
                new_password = secrets.token_urlsafe(24)
                change = dict(aksi="ubah_password", password_lama=PASSWORD, password_baru=new_password,
                              konfirmasi_password=new_password, csrf_token=admin.token(settings))
                assert "Password lama tidak sesuai" in admin.request(settings, {**change, "password_lama":"keliru"})
                result = admin.request(settings, change)
                check("Password berhasil diubah" in result and admin.token(settings) != change['csrf_token'],
                      "Perubahan password admin menyimpan hash dan merotasi token")
                check('name="identitas"' in other_admin.request(report), "Sesi admin lain kedaluwarsa")
                fresh = Browser(base)
                for password, should_login in [(PASSWORD, False), (new_password, True)]:
                    response = fresh.request("login.php", {"identitas":"admin", "password":password,
                                             "csrf_token":fresh.token("login.php")})
                    assert ('name="identitas"' not in response) == should_login
                check(True, "Login admin memerlukan password baru")
            except Exception:
                log.flush()
                print((tmp/"http.log").read_text(errors="replace")[-2400:])
                raise
            finally:
                server.terminate()
                server.wait(timeout=10)
            errors = (tmp/"http.log").read_text(errors="replace")
            check(not re.search("PHP (Warning|Fatal|Parse|Notice)", errors), "Log HTTP bersih dari galat PHP")
    finally:
        php(setup + '$server->exec("DROP DATABASE $name");', ROOT)


def print_report(tmp, app, html):
    chrome = os.environ.get("CHROME_BIN") or shutil.which("google-chrome") or shutil.which("chromium") or str(
        Path(os.environ.get("ProgramFiles", "")) / "Google/Chrome/Application/chrome.exe")
    html = re.sub(r'<link[^>]*>|<script\b[^>]*>.*?</script>', '', html, flags=re.S)
    css = ''.join((app/"assets/css"/f).read_text(encoding="utf-8") for f in ["style.css","admin.css"])
    document = tmp/"laporan.html"
    document.write_text(html.replace('</head>', '<style>'+css+'</style></head>'), encoding="utf-8")
    pdf = tmp/"laporan.pdf"
    subprocess.run([chrome,"--headless","--disable-gpu","--disable-extensions","--no-first-run",
                    "--user-data-dir="+str(tmp/"chrome"),"--print-to-pdf="+str(pdf),document.as_uri()],
                   capture_output=True, timeout=45, check=True)
    check(pdf.read_bytes().startswith(b"%PDF") and pdf.stat().st_size > 2000, "Cetak laporan terfilter ke PDF melalui Chrome")


if __name__ == "__main__":
    with tempfile.TemporaryDirectory(prefix="kos-aron-uji09-") as temp:
        tmp = Path(temp)
        app = tmp/"app"
        shutil.copytree(ROOT, app, ignore=shutil.ignore_patterns("database.local.php", "docs", "*.log"))
        run_tests(tmp, app)
