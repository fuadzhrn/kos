"""Black box pemesanan melalui HTTP. Membaca hasil di MySQL fiktif milik fixture pengujian."""
from datetime import datetime, timedelta, timezone
from pathlib import Path
import re
import runpy
import secrets
import shutil
import tempfile
from urllib.parse import urlencode

suite = runpy.run_path(str(Path(__file__).with_name('uji-tahap-10.py')))
Browser = suite['Browser']
expect = suite['expect']


def uji(base, query):
    tamu, admin, penghuni = [Browser(base) for _ in range(3)]
    path = 'pesan-kamar.php?id=1'
    listing = tamu.request('kamar.php')
    expect('P01', path in listing, 'Daftar kamar memuat tombol pemesanan kamar kosong.')
    detail = tamu.request('detail-kamar.php?id=1')
    expect('P02', path in detail and 'Kamar 01' in detail, 'Detail mengarah ke kamar yang dipilih.')
    token = tamu.token(path)
    today = datetime.now(timezone(timedelta(hours=7))).date().isoformat()
    nama = "Calon O'Neil & Uji"
    catatan = 'Periksa tanda <kamar> & nomor.'
    form = dict(nama_lengkap=nama, nomor_telepon='080000000231', email='calon@example.test',
                catatan_pemesan=catatan, rencana_tanggal_masuk=today, csrf_token=token)
    counts = lambda: query('SELECT (SELECT COUNT(*) FROM calon_penghuni),(SELECT COUNT(*) FROM pemesanan)')
    def check_invalid(fields, pesan, id_uji):
        before = counts()
        html = tamu.request(path, {**form, **fields}, expected=422)
        expect(id_uji, pesan in html and counts() == before, 'HTTP 422 dan jumlah calon/pemesanan tetap.')
    check_invalid({'nama_lengkap':''}, 'Isi nama lengkap', 'P04')
    check_invalid({'nomor_telepon':'abc'}, 'nomor telepon', 'P05')
    receipts = []
    def submit(fields):
        payload = urlencode({**form, **fields}).encode()
        with tamu.opener.open(base + path, data=payload, timeout=10) as response:
            assert response.status == 200
            receipts.append(response.geturl().removeprefix(base))
            html = response.read().decode()
        ref = re.search(r'id="kode-pemesanan">(KA-[A-F0-9]{16})</strong>', html)[1]
        assert 'Menunggu Konfirmasi' in html and 'Hubungi Pemilik' in html and nama not in html and form['email'] not in html
        return ref
    a = submit({})
    expect('P03', counts() == [[1, 1]], 'Satu identitas dan satu pengajuan tersimpan.')
    b = submit({'nama_lengkap':'Pemesan Kedua', 'nomor_telepon':'080000000232', 'email':''})
    c = submit({'nama_lengkap':'Pemesan Ketiga', 'nomor_telepon':'080000000233', 'email':''})
    assert len(set(receipts)) == 3 and a in tamu.request(receipts[0]) and c in tamu.request(receipts[2])
    assert a not in Browser(base).request(receipts[0], expected=404)
    rows = query('SELECT id,kode_pemesanan,status,kamar_id FROM pemesanan ORDER BY id')
    expect('P06', len(rows) == 3 and all(r[3] == 1 for r in rows), 'Tiga calon dapat mengajukan kamar kosong yang sama.')
    tamu.request('pesan-kamar.php?id=4', form, expected=409)
    expect('P07', len(query('SELECT id FROM pemesanan')) == 3, 'Kamar terisi memberi HTTP 409, tanpa pengajuan tambahan.')
    expect('P08', {a,b,c} == {r[1] for r in rows} and len({a,b,c}) == 3, 'Tiga referensi unik sama dengan referensi di database.')
    expect('P09', all(r[2]=='menunggu' for r in rows), 'Semua pengajuan baru menunggu konfirmasi.')
    p1,p2,p3 = [f'admin/pemesanan-detail.php?id={r[0]}' for r in rows]
    guest_page = tamu.request(p1)
    expect('P20', 'name="identitas"' in guest_page and a not in guest_page, 'Tamu diarahkan ke login.')
    penghuni.login('ahmad.fauzi')
    penghuni.request(p1, {'aksi':'setujui','csrf_token':token}, expected=403)
    expect('P21', query('SELECT COUNT(*) FROM pemesanan WHERE status="menunggu"') == [[3]], 'Role penghuni menerima HTTP 403.')
    admin.login('admin')
    daftar = admin.request('admin/pemesanan.php', {'status':'menunggu','cari':a})
    expect('P10', a in daftar and b not in daftar, 'Daftar dan filter kode/status admin sesuai.')
    data = admin.request(p1)
    expect('P11', form['email'] in data and a in data, 'Admin melihat identitas dan rincian pengajuan yang dipilih.')
    expect('P23', '&lt;kamar&gt; &amp; nomor.' in data and '<kamar>' not in data and 'O&#039;Neil &amp; Uji' in data,
           'Tanda kutip, kurung sudut dan ampersand disimpan sebagai teks dan di-escape di HTML.')
    admin.request(p1, {'aksi':'setujui','csrf_token':''}, expected=403)
    tamu.request(path, {**form, 'csrf_token':''}, expected=403)
    expect('P22', counts()==[[3,3]] and query('SELECT COUNT(*) FROM pemesanan WHERE status="menunggu"')==[[3]],
           'CSRF publik dan admin ditolak, jumlah dan status tetap.')
    def act(page, action, code=200, **values):
        return admin.request(page, {'csrf_token':admin.token(page),'aksi':action,**values}, expected=code)
    act(p1,'setujui')
    expect('P12', query('SELECT kode_pemesanan FROM pemesanan WHERE kamar_ditahan_unik=1')==[[a]],
           'Persetujuan menahan kamar untuk pengajuan pertama.')
    act(p2,'setujui',422)
    expect('P14', query('SELECT COUNT(*) FROM pemesanan WHERE status="disetujui"')==[[1]], 'Pengajuan kedua ditolak untuk persetujuan yang bertentangan.')
    act(p3,'tolak',catatan_admin='Rencana berubah')
    expect('P13', query('SELECT status,catatan_admin FROM pemesanan ORDER BY id DESC LIMIT 1')==[['ditolak','Rencana berubah']],
           'Riwayat penolakan menyimpan alasan.')
    expect('P15', query('SELECT COUNT(*) FROM penyewaan WHERE kamar_aktif_unik=1')==[[0]] and path not in tamu.request('detail-kamar.php?id=1'),
           'Kamar yang ditahan belum terisi dan tombol pemesanan disembunyikan.')
    def journal():
        return query('SELECT (SELECT COUNT(*) FROM users),(SELECT COUNT(*) FROM penghuni),(SELECT COUNT(*) FROM penyewaan)')
    initial = journal()[0]
    aktivasi = dict(penghuni_id='0', akun='pemesanan.uji', password=secrets.token_urlsafe(16),
                    tanggal_masuk=today, harga_sewa_disepakati='700000', hari_jatuh_tempo='5')
    act(p1,'aktifkan',422,**{**aktivasi,'hari_jatuh_tempo':'32'})
    assert journal()==[initial]
    act(p1,'aktifkan',**aktivasi)
    saved = journal()[0]
    expect('P16', saved==[n+1 for n in initial], 'Pengaktifan membuat satu akun, profil dan sewa.')
    expect('P17', query('SELECT COUNT(*) FROM penyewaan WHERE kamar_aktif_unik=1')==[[1]] and 'Terisi' in tamu.request('detail-kamar.php?id=1'),
           'Kamar tampil terisi setelah penyewaan aktif.')
    expect('P18', query('SELECT u.username,p.nama,s.hari_jatuh_tempo FROM pemesanan b JOIN penyewaan s ON s.id=b.penyewaan_id JOIN penghuni p ON p.id=s.penghuni_id JOIN users u ON u.id=p.user_id')==[['pemesanan.uji',nama,5]],
           'Tautan pengajuan, penyewaan, profil dan akun benar.')
    # Setelah aktivasi detail tidak lagi memiliki form aksi, gunakan token sesi dari daftar pengajuan menunggu.
    admin.request(p1, {'csrf_token':admin.token(p2),'aksi':'aktifkan',**aktivasi}, expected=422)
    expect('P19', journal()==[saved], 'Kesalahan sesudah pembuatan akun di-rollback; konfirmasi ulang tidak menggandakan data.')


if __name__ == '__main__':
    if not __debug__:
        raise RuntimeError('Jalankan tanpa -O')
    with tempfile.TemporaryDirectory(prefix='kos-aron-pesan-') as work:
        tmp = Path(work)
        app = tmp / 'app'
        shutil.copytree(suite['ROOT'], app, ignore=shutil.ignore_patterns('database.local.php','docs','*.log'))
        with suite['test_database'](app) as query, suite['server'](tmp, app) as base:
            uji(base, query)
