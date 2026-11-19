/* ==========================================================
   KOS ARON - JavaScript admin (tanpa library)
   ========================================================== */
(function () {
    'use strict';

    var BREAKPOINT_DESKTOP = 1024;

    /* ------------------------------------------------------
       Sidebar sebagai menu mobile (di bawah 1024px)
       ------------------------------------------------------ */
    function initSidebar() {
        var sidebar = document.getElementById('sidebar');
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var backdrop = document.querySelector('[data-sidebar-close]');
        if (!sidebar || !toggle) return;

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            if (backdrop) backdrop.hidden = !open;
            document.body.classList.toggle('sidebar-open', open);
        }

        toggle.addEventListener('click', function () {
            var open = !sidebar.classList.contains('is-open');
            setOpen(open);
            if (open) {
                var link = sidebar.querySelector('.is-active') || sidebar.querySelector('a');
                if (link) link.focus();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', function () { setOpen(false); });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= BREAKPOINT_DESKTOP && sidebar.classList.contains('is-open')) {
                setOpen(false);
            }
        });
    }

    /* ------------------------------------------------------
       Menu user di topbar (<details>): tutup saat klik di luar / Escape
       ------------------------------------------------------ */
    function initMenuUser() {
        var menu = document.querySelector('.topbar__user');
        if (!menu) return;

        document.addEventListener('click', function (event) {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary').focus();
            }
        });
    }

    /* ------------------------------------------------------
       Filter tabel tanpa reload.
       <form data-filter-tabel="id-tabel"> berisi (opsional) input "cari" dan
       satu atau lebih <select name="x">. Setiap select dicocokkan dengan
       atribut data-x pada baris tabel (nilai "semua" = tanpa filter).
       Baris tabel ditandai atribut data-baris, dan (jika ada pencarian)
       data-cari untuk pencarian teks.
       ------------------------------------------------------ */
    function initFilterTabel() {
        var forms = document.querySelectorAll('[data-filter-tabel]');

        Array.prototype.forEach.call(forms, function (form) {
            var tabel = document.getElementById(form.getAttribute('data-filter-tabel'));
            if (!tabel) return;

            var baris = tabel.querySelectorAll('tbody tr[data-baris]');
            var barisKosong = tabel.querySelector('[data-filter-kosong]');
            var info = form.querySelector('[data-filter-info]');
            var cari = form.elements.cari; // boleh tidak ada jika halaman tidak menyediakan pencarian
            var pilihan = form.querySelectorAll('select');
            var satuan = form.getAttribute('data-filter-satuan') || 'kamar';

            function terapkan() {
                var kata = cari ? cari.value.toLowerCase().replace(/\s+/g, ' ').trim() : '';
                var tampil = 0;

                Array.prototype.forEach.call(baris, function (tr) {
                    var cocok = kata === '' || (tr.getAttribute('data-cari') || '').indexOf(kata) !== -1;
                    Array.prototype.forEach.call(pilihan, function (sel) {
                        if (sel.value !== 'semua' && tr.getAttribute('data-' + sel.name) !== sel.value) cocok = false;
                    });
                    tr.hidden = !cocok;
                    if (cocok) tampil++;
                });

                if (barisKosong) barisKosong.hidden = tampil !== 0;
                if (info) info.textContent = 'Menampilkan ' + tampil + ' dari ' + baris.length + ' ' + satuan;
            }

            if (cari) cari.addEventListener('input', terapkan);
            Array.prototype.forEach.call(pilihan, function (sel) { sel.addEventListener('change', terapkan); });
            form.addEventListener('submit', function (event) { event.preventDefault(); terapkan(); });
            window.addEventListener('pageshow', terapkan);
            terapkan();
        });
    }

    /* ------------------------------------------------------
       Konfirmasi sebelum mengirim form berbahaya (mis. hapus)
       ------------------------------------------------------ */
    function initKonfirmasi() {
        var forms = document.querySelectorAll('form[data-konfirmasi]');
        Array.prototype.forEach.call(forms, function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-konfirmasi'))) event.preventDefault();
            });
        });
    }

    /* ------------------------------------------------------
       Fallback gambar: <img data-fallback="path-placeholder">
       ------------------------------------------------------ */
    function initFallbackGambar() {
        var gambar = document.querySelectorAll('img[data-fallback]');
        Array.prototype.forEach.call(gambar, function (img) {
            function ganti() {
                var cadangan = img.getAttribute('data-fallback');
                if (cadangan && img.getAttribute('src') !== cadangan) img.src = cadangan;
            }
            img.addEventListener('error', ganti);
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) ganti();
        });
    }

    /* ------------------------------------------------------
       Perlengkapan dasar untuk <dialog> yang dipakai sebagai modal:
       tombol [data-tutup-modal], klik di latar gelap, dan Escape (event
       "cancel" bawaan dialog) semuanya menutup dialog + membersihkan
       ?form=.../?edit=.../?lihat=... dari URL. Dipakai oleh semua modal admin.
       Mengembalikan fungsi tutup() agar pemanggil bisa memakainya juga.
       ------------------------------------------------------ */
    function initDialogDasar(dialog) {
        function tutup() {
            dialog.close();
            if (location.search) history.replaceState(null, '', location.pathname);
        }

        Array.prototype.forEach.call(dialog.querySelectorAll('[data-tutup-modal]'), function (a) {
            a.addEventListener('click', function (e) { e.preventDefault(); tutup(); });
        });
        // Klik di luar kotak modal (pada elemen <dialog>/latar gelap) menutup modal.
        dialog.addEventListener('click', function (e) { if (e.target === dialog) tutup(); });
        dialog.addEventListener('cancel', function (e) { e.preventDefault(); tutup(); });

        return tutup;
    }

    function initModalTagihan() {
        var dialog = document.getElementById('modal-tagihan');
        if (!dialog || typeof dialog.showModal !== 'function') return;
        initDialogDasar(dialog);
        dialog.close();
        dialog.showModal();
    }

    /* ------------------------------------------------------
       Modal Catat Pembayaran (<dialog>).
       Tanpa JavaScript, tautan ?form=baru membuka form lewat server.
       Aturan: nominal dibayar harus SAMA PERSIS dengan nominal tagihan
       (belum ada pembayaran cicilan) -- diperiksa di sini & di server.
       ------------------------------------------------------ */
    function initModalPembayaran() {
        var dialog = document.getElementById('modal-pembayaran');
        if (!dialog || typeof dialog.showModal !== 'function') return;

        var form = dialog.querySelector('[data-form-pembayaran]');
        var el = form.elements;
        var peringatan = form.querySelector('[data-peringatan-nominal]');
        var nominalOtomatis = '';

        function hapusHasilLama() {
            Array.prototype.forEach.call(dialog.querySelectorAll('[data-hasil-form]'), function (n) { n.remove(); });
        }

        // Nama penghuni, nomor kamar, dan nominal tagihan mengikuti tagihan yang dipilih.
        // Nilai "Rp ..." diambil dari data-nominal-tampil (sudah diformat PHP),
        // supaya format Rupiah tidak perlu ditulis ulang di JavaScript.
        function perbaruiTagihan(isiNominal) {
            var opt = el.tagihan_id.selectedOptions[0];
            var ada = opt && opt.value;
            el.nama_penghuni_tampil.value = ada ? opt.getAttribute('data-nama') : '';
            el.nomor_kamar_tampil.value = ada ? 'Kamar ' + opt.getAttribute('data-kamar') : '';
            el.nominal_tagihan_tampil.value = ada ? opt.getAttribute('data-nominal-tampil') : '';
            if (isiNominal && ada && (el.nominal_dibayar.value === '' || el.nominal_dibayar.value === nominalOtomatis)) {
                el.nominal_dibayar.value = opt.getAttribute('data-nominal');
                nominalOtomatis = el.nominal_dibayar.value;
            }
            cekNominal();
        }

        // Aturan: nominal dibayar harus sama persis dengan nominal tagihan.
        function cekNominal() {
            var opt = el.tagihan_id.selectedOptions[0];
            var pesan = '';
            if (opt && opt.value && el.nominal_dibayar.value !== '') {
                if (Number(el.nominal_dibayar.value) !== Number(opt.getAttribute('data-nominal'))) {
                    pesan = 'Nominal dibayar harus sama persis dengan nominal tagihan (' + opt.getAttribute('data-nominal-tampil')
                          + '). Pembayaran cicilan belum didukung.';
                }
            }
            el.nominal_dibayar.setCustomValidity(pesan);
            peringatan.textContent = pesan;
            peringatan.hidden = pesan === '';
        }

        function isi(data) {
            data = data || {};
            el.tagihan_id.value = data.tagihan_id || '';
            el.tanggal_bayar.value = data.tanggal_bayar || '';
            el.nominal_dibayar.value = data.nominal_dibayar || '';
            Array.prototype.forEach.call(el.metode, function (r) { r.checked = r.value === (data.metode || 'tunai'); });
            el.keterangan.value = data.keterangan || '';
            nominalOtomatis = '';
            perbaruiTagihan(false);
        }

        function buka() {
            hapusHasilLama();
            isi({});
            dialog.showModal();
            el.tagihan_id.focus();
        }

        initDialogDasar(dialog);

        var tombolBuat = document.querySelector('[data-buka-pembayaran]');
        if (tombolBuat) {
            tombolBuat.addEventListener('click', function (e) { e.preventDefault(); buka(); });
        }

        el.tagihan_id.addEventListener('change', function () { perbaruiTagihan(true); });
        el.nominal_dibayar.addEventListener('input', cekNominal);

        if (dialog.hasAttribute('data-buka-otomatis')) {
            dialog.close();
            dialog.showModal();
            perbaruiTagihan(false);
        }
    }

    /* ------------------------------------------------------
       Modal Detail Pembayaran (<dialog>, hanya tampilan, tanpa form).
       Tanpa JavaScript, tautan ?lihat=ID membuka detail lewat server.
       Semua teks tampilan (format Rupiah, tanggal, periode) sudah
       diformat oleh PHP di data-lihat-pembayaran, bukan diformat ulang
       di sini, supaya format Rupiah/tanggal cukup punya satu sumber.
       ------------------------------------------------------ */
    function initModalDetailPembayaran() {
        var dialog = document.getElementById('modal-detail-pembayaran');
        if (!dialog || typeof dialog.showModal !== 'function') return;

        initDialogDasar(dialog);

        Array.prototype.forEach.call(document.querySelectorAll('[data-lihat-pembayaran]'), function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var p = JSON.parse(a.getAttribute('data-lihat-pembayaran'));

                // Setiap baris bersifat OPSIONAL: beberapa halaman (mis. penghuni/riwayat.php,
                // sudah jelas milik satu penghuni) sengaja tidak menampilkan baris "Nama Penghuni".
                // querySelector mengembalikan null untuk baris yang memang tidak ada di modal.
                var isi = function (atribut, teks) {
                    var el = dialog.querySelector('[' + atribut + ']');
                    if (el) el.textContent = teks;
                };
                isi('data-detail-nama', p.nama_penghuni);
                isi('data-detail-kamar', 'Kamar ' + p.nomor_kamar);
                isi('data-detail-periode', p.periode_tampil);
                isi('data-detail-nominal-tagihan', p.nominal_tagihan_tampil);
                isi('data-detail-tanggal', p.tanggal_tampil);
                isi('data-detail-nominal-dibayar', p.nominal_dibayar_tampil);
                isi('data-detail-metode', p.metode_tampil);

                var keteranganEl = dialog.querySelector('[data-detail-keterangan-baris]');
                if (keteranganEl) {
                    keteranganEl.hidden = !p.keterangan;
                    if (p.keterangan) keteranganEl.querySelector('dd').textContent = p.keterangan;
                }

                dialog.querySelector('[data-detail-isi]').hidden = false;
                var kosong = dialog.querySelector('[data-detail-kosong]');
                if (kosong) kosong.hidden = true;
                dialog.showModal();
            });
        });

        // Halaman dimuat dengan dialog terbuka (tautan ?lihat=ID tanpa JS): jadikan modal.
        if (dialog.hasAttribute('data-buka-otomatis')) {
            dialog.close();
            dialog.showModal();
        }
    }

    /* ------------------------------------------------------
       Tampilkan / sembunyikan password. Sama seperti assets/js/main.js
       (dipakai login.php), diduplikasi di sini supaya halaman di dalam
       admin/ dan penghuni/ (yang memuat admin.js, bukan main.js) juga
       bisa memakai <div class="input-password"> + [data-toggle-password].
       ------------------------------------------------------ */
    function initTogglePassword() {
        var tombol = document.querySelectorAll('[data-toggle-password]');

        Array.prototype.forEach.call(tombol, function (btn) {
            var input = document.getElementById(btn.getAttribute('aria-controls'));
            if (!input) return;

            btn.addEventListener('click', function () {
                var tampil = input.type === 'password';
                input.type = tampil ? 'text' : 'password';
                btn.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
                input.focus();
            });
        });
    }

    /* ------------------------------------------------------
       Form Ubah Password (penghuni/profil.php): peringatan langsung
       di browser jika Password Baru dan Konfirmasi tidak sama.
       Server tetap memeriksa aturan yang sama.
       ------------------------------------------------------ */
    function initFormPassword() {
        var form = document.querySelector('[data-form-password]');
        if (!form) return;

        var baru = form.elements.password_baru;
        var konfirmasi = form.elements.konfirmasi_password;
        var peringatan = form.querySelector('[data-peringatan-konfirmasi]');

        function cek() {
            var pesan = '';
            if (konfirmasi.value !== '' && baru.value !== konfirmasi.value) {
                pesan = 'Konfirmasi password baru tidak sama dengan password baru.';
            }
            konfirmasi.setCustomValidity(pesan);
            peringatan.textContent = pesan;
            peringatan.hidden = pesan === '';
        }

        baru.addEventListener('input', cek);
        konfirmasi.addEventListener('input', cek);
    }

    /* ------------------------------------------------------
       Tombol "Cetak Laporan" (admin/laporan.php): memakai
       window.print() + CSS @media print, bukan membuat PDF/file baru.
       ------------------------------------------------------ */
    function initCetak() {
        var tombol = document.querySelector('[data-cetak-laporan]');
        if (!tombol) return;
        tombol.addEventListener('click', function () { window.print(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initMenuUser();
        initFilterTabel();
        initKonfirmasi();
        initFallbackGambar();
        initModalTagihan();
        initModalPembayaran();
        initModalDetailPembayaran();
        initTogglePassword();
        initFormPassword();
        initCetak();
    });
})();
