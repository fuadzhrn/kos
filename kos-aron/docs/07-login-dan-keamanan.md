# 7. Sistem Login, Sesi, dan Hak Akses

Tahap ini mengimplementasikan login, sesi, proteksi peran, logout, dan
mekanisme akun admin awal — **murni PHP Native**, terhubung ke tabel `users`
pada `db_kos_aron`. Sesuai batasan tugas, **CRUD kamar/penghuni/pembayaran
TIDAK dikerjakan** di tahap ini.

## 7.0 Batas penting: apa yang BERUBAH dan apa yang BELUM

- **Berubah (sungguhan, terhubung MySQL):** siapa yang boleh login, peran
  (`admin`/`penghuni`) apa yang dimilikinya, dan halaman mana yang boleh
  dia buka. Ini semua diperiksa terhadap tabel `users` yang sebenarnya.
- **BELUM berubah (masih data contoh seperti sebelumnya):** isi dashboard
  setelah login (kamar, tagihan, riwayat pembayaran) masih dibaca dari
  `data/*.php` (dummy), BUKAN dari tabel `penyewaan`/`tagihan`/`pembayaran`
  MySQL. Contoh konkret: jika Anda login sebagai `lina.marlina` (penghuni),
  topbar akan menyapa "Lina Marlina" (dari sesi yang sungguhan), tapi isi
  halaman "Kamar Saya"/"Tagihan"/dst. tetap menampilkan data contoh milik
  Siti Aminah (penghuni id=2 di `data/penghuni.php`) seperti sebelum ada
  login. Ini **bukan bug** — penyambungan isi dashboard ke database adalah
  pekerjaan tahap CRUD berikutnya yang sengaja tidak dikerjakan di sini
  (lihat instruksi "Jangan mengerjakan CRUD... pada tahap ini").

## 7.1 TUGAS 1 — Login (terhubung database)

- `login.php` terhubung ke tabel `users` lewat `cari_dan_verifikasi_login()`
  (`includes/auth.php`), dipanggil dengan PDO **prepared statement**.
- Login dengan **username ATAU email**: dicari lewat `users.username`
  (berlaku untuk admin & penghuni) atau `penghuni.email` (khusus penghuni,
  lewat JOIN ke `penghuni`) — tanpa menambah kolom email ke `users`.
- Password **tidak pernah** disimpan sebagai teks biasa: `password_hash()`
  (dipakai di `database/buat-admin.php`) saat membuat/mengubah akun, dan
  `password_verify()` saat login.
- `session_regenerate_id(true)` dipanggil tepat setelah kredensial
  terverifikasi benar (`login_berhasil()` di `includes/auth.php`) — mencegah
  *session fixation* (session ID yang mungkin sudah diketahui pihak lain
  sebelum login tidak bisa dipakai setelah login berhasil).

## 7.2 TUGAS 2 — Redirect berbasis peran

Tidak ada dropdown peran di form login. Setelah kredensial benar,
`login.php` membaca `role` dari baris `users` yang ditemukan lalu redirect:
`admin` → `admin/index.php`, `penghuni` → `penghuni/index.php`.

## 7.3 TUGAS 3 — Proteksi halaman (reusable, termasuk endpoint form)

`require_role(string $peranDibutuhkan, string $urlLogin)` di
`includes/auth.php` adalah satu fungsi reusable yang dipanggil di **baris
pertama setelah `require config.php`**, pada **SETIAP** file PHP di folder
`admin/` (10 file) dan `penghuni/` (6 file) — termasuk file yang memproses
`$_POST` di bagian atasnya sendiri (`admin/tagihan.php`, `admin/pembayaran.php`,
dst.), bukan hanya di `includes/header.php`. Ini sengaja diletakkan **sebelum**
blok pemrosesan form di tiap halaman, supaya pengguna yang tidak berhak
tidak bisa memicu logika pemrosesan form sama sekali (bukan sekadar
menyembunyikan tombol/menu).

- Belum login → redirect ke `login.php`.
- Login tapi peran salah → **HTTP 403** + halaman "Akses Ditolak" (bukan
  diam-diam redirect), supaya jelas dibedakan dari "halaman tidak ada".

## 7.4 TUGAS 4 — Keamanan

| Ketentuan | Implementasi |
|---|---|
| Cookie HttpOnly | `session_set_cookie_params(['httponly' => true, ...])` di `mulai_sesi()` |
| SameSite | `'samesite' => 'Lax'` |
| Secure saat HTTPS | `'secure' => $https` (dideteksi dari `$_SERVER['HTTPS']`/`X-Forwarded-Proto`) |
| CSRF token | `csrf_token()`/`csrf_field()`/`csrf_valid()` — dipasang di: form login, form logout, DAN seluruh form perubahan data yang sudah ada (`admin/kamar-form.php`, `admin/penghuni-form.php`, modal Tagihan & Pembayaran, kedua form di `admin/pengaturan.php`, kedua form di `penghuni/profil.php`) |
| Validasi input | Panjang field dibatasi, format diperiksa sebelum query (sudah ada sebagian dari tahap frontend, kini ditambah validasi identitas/panjang di `login.php`) |
| Pesan gagal tidak membocorkan akun | Satu pesan generik yang SAMA untuk "akun tidak ada", "password salah", dan "akun nonaktif": *"Username/email atau password salah."* |
| Pembatasan percobaan login | `login_terkunci()`/`catat_percobaan_login_gagal()` — 5x gagal untuk identitas yang sama → terkunci 60 detik. **Catatan jujur**: ini disimpan di `$_SESSION` (sesuai permintaan "sederhana"), jadi membuka browser/sesi baru akan mereset hitungan. Untuk produksi, sebaiknya dipindah ke penyimpanan yang tidak bergantung sesi (misalnya kolom di `users` atau tabel terpisah) — di luar lingkup tahap ini. |
| Logout via POST | `logout.php` menolak request selain POST, dan memeriksa CSRF token sebelum `session_destroy()` |
| Pengelolaan sesi aman | Nama sesi khusus (`KOSARON_SESSION`), regenerasi ID setelah login, penghancuran sesi penuh (`$_SESSION = []`, hapus cookie, `session_destroy()`) saat logout |

## 7.5 TUGAS 5 — Akun admin awal

`database/buat-admin.php`:
- Hanya bisa dijalankan lewat **CLI** (`php database/buat-admin.php`) —
  menolak dengan HTTP 403 jika diakses lewat web server.
- Meminta username, nama, dan password **interaktif** (STDIN) — password
  **tidak pernah** ditulis di source code maupun ditampilkan ulang di output.
- Password disimpan sebagai `password_hash()`, bukan teks biasa.
- `database/seed.sql` **tidak menyediakan akun admin yang bisa dipakai
  publik**: baris `users` untuk admin (id 1) dibuat dengan `status =
  'nonaktif'` dan `password_hash` berupa teks yang sengaja tidak valid
  (tidak cocok dengan password apa pun) — hanya ada untuk memenuhi relasi
  FK (`dibuat_oleh`/`dicatat_oleh`) pada data uji. Baris ini **harus**
  diganti lewat `buat-admin.php` sebelum admin bisa login sungguhan.
- Hasil di bawah adalah catatan pengujian tahap login. Pada rilis audit akhir,
  akun contoh dalam seed nonaktif. Skrip uji membuat password acak di database sementara.

## 7.6 TUGAS 7 — Hasil Pengujian

Seluruh pengujian dijalankan dengan `php -S 127.0.0.1:8765 -t kos-aron`
(PHP built-in server) + `curl` (cookie jar per skenario), **benar-benar
dieksekusi**, bukan simulasi.

| # | Skenario | Cara uji | Hasil |
|---|---|---|---|
| 1 | Login admin valid | Akun admin dibuat lewat `buat-admin.php`, login dengan kredensial itu | **302 → `admin/index.php`**, akses berikutnya ke `admin/index.php` = 200 |
| 2 | Login penghuni valid | Login `lina.marlina` (username) dan `dedi.kurniawan@contoh.test` (email) | Keduanya **302 → `penghuni/index.php`** |
| 3 | Password salah | Login `admin` dengan password acak | **200**, pesan *"Username/email atau password salah."* |
| 4 | Akun tidak aktif | `UPDATE users SET status='nonaktif'` pada `dedi.kurniawan`, lalu login dengan password yang BENAR | Pesan **sama persis** dengan #3 (tidak membocorkan status); akses `penghuni/index.php` setelahnya tetap **302** (ditolak) |
| 5 | Akses dashboard tanpa login | `GET admin/index.php` dan `GET penghuni/index.php` tanpa cookie sesi | Keduanya **302 → `../login.php?alasan=harus-login`** |
| 6 | Penghuni membuka URL admin | Sesi `lina.marlina` (role penghuni) mengakses `admin/index.php` | **403 Forbidden**, halaman "Akses Ditolak" |
| 7 | Admin mencoba akses endpoint khusus penghuni | Sesi `admin` mengakses `penghuni/index.php` | **403 Forbidden**, halaman "Akses Ditolak" |
| 8 | Logout | (a) `POST logout.php` **tanpa** method POST yang benar/CSRF salah → ditolak, sesi TETAP aktif. (b) `POST logout.php` dengan CSRF token sah → berhasil | (a) redirect ke `login.php`, sesi admin **masih aktif** (akses dashboard tetap 200). (b) **302 → `login.php?keluar=1`** |
| 9 | Akses kembali setelah logout | `GET admin/index.php` dengan cookie sesi yang sudah di-logout | **302 → `../login.php?alasan=harus-login`** (sesi benar-benar hancur) |
| 10 | Sesi tidak valid | Cookie `KOSARON_SESSION` diisi nilai acak yang tidak pernah ada di server | **302 → login** untuk `admin/index.php` maupun `penghuni/index.php` (PHP memperlakukan sebagai sesi baru/kosong, bukan dianggap sah) |

**Pengujian tambahan (di luar 10 wajib, untuk TUGAS 4):**
- 5x percobaan login gagal berturut-turut dengan identitas yang sama →
  percobaan ke-6 ditolak dengan pesan *"Terlalu banyak percobaan gagal.
  Coba lagi dalam 60 detik."* sebelum sempat mengecek password.
- Form login dikirim dengan `csrf_token` yang dipalsukan → ditolak dengan
  pesan *"Sesi formulir sudah tidak valid..."*, TIDAK sempat memeriksa
  kredensial sama sekali.

### Bug nyata yang ditemukan & diperbaiki selama pengujian

`cari_dan_verifikasi_login()` awalnya memakai nama parameter `:identitas`
**dua kali** dalam satu query `UNION` (sekali untuk `users.username`, sekali
untuk `penghuni.email`). Karena `PDO::ATTR_EMULATE_PREPARES` dimatikan
(sesuai ketentuan tahap sebelumnya), driver MySQL native **tidak mendukung**
nama parameter yang diulang dalam satu prepared statement — percobaan login
APA PUN (termasuk kredensial yang benar) langsung gagal dengan pesan "Tidak
dapat memproses login saat ini." Diperbaiki dengan memakai dua placeholder
berbeda (`:identitas1` / `:identitas2`) yang diisi nilai yang sama. Ditemukan
tepat oleh TEST 1 di atas (login admin valid awalnya gagal), diperbaiki, lalu
seluruh 10 skenario diuji ulang dari awal — semuanya lolos.

## 7.7 Berkas yang ditambah/diubah

**Baru:** `includes/auth.php`, `logout.php`, `database/buat-admin.php`,
`docs/07-login-dan-keamanan.md` (dokumen ini).

**Diubah (hanya bagian yang perlu untuk integrasi login/keamanan, desain
navbar/sidebar/warna/font/layout TIDAK dirombak):**
- `includes/config.php` — memuat `auth.php` + memanggil `mulai_sesi()`.
- `login.php` — terhubung database, CSRF, pembatasan percobaan (markup
  form & styling **tidak diubah**, hanya label "Username" → "Username atau
  Email" dan tambahan `<input hidden>` CSRF).
- `admin/includes/header.php`, `penghuni/includes/header.php` — nama di
  topbar dari sesi sungguhan; tautan "Keluar" (`<a href>`) diganti `<form
  method="post">` + tombol (wajib untuk logout aman via POST); tampilan
  tombol dibuat identik dengan tautan sebelumnya lewat CSS tambahan
  (`assets/css/admin.css`), bukan desain baru.
- `assets/css/style.css` — tambahan satu varian `.notice--galat` (warna
  merah, mengikuti pola `.notice--berhasil` yang sudah ada) untuk pesan
  galat login di halaman publik, yang sebelumnya hanya ada di `admin.css`.
- 10 halaman `admin/*.php` + 6 halaman `penghuni/*.php` — tambahan SATU
  baris `require_role(...)` di baris paling atas.
- `admin/kamar-form.php`, `admin/penghuni-form.php`, `admin/tagihan.php`,
  `admin/pembayaran.php`, `admin/pengaturan.php`, `penghuni/profil.php` —
  tambahan `csrf_field()` + pemeriksaan CSRF pada form yang sudah ada,
  TIDAK ada perubahan pada field/validasi/penyimpanan data yang memang
  sengaja belum dikerjakan (masih prototype, sesuai batasan tahap ini).
- `database/seed.sql` — akun admin contoh dinonaktifkan (lihat §7.5).
