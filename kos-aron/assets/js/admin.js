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
       Filter tabel (pencarian + status) tanpa reload.
       <form data-filter-tabel="id-tabel"> berisi input "cari" & select "status".
       Baris tabel memakai data-status dan data-cari.
       ------------------------------------------------------ */
    function initFilterTabel() {
        var forms = document.querySelectorAll('[data-filter-tabel]');

        Array.prototype.forEach.call(forms, function (form) {
            var tabel = document.getElementById(form.getAttribute('data-filter-tabel'));
            if (!tabel) return;

            var baris = tabel.querySelectorAll('tbody tr[data-status]');
            var barisKosong = tabel.querySelector('[data-filter-kosong]');
            var info = form.querySelector('[data-filter-info]');
            var cari = form.elements.cari;
            var status = form.elements.status;
            var satuan = form.getAttribute('data-filter-satuan') || 'kamar';

            function terapkan() {
                var kata = cari.value.toLowerCase().replace(/\s+/g, ' ').trim();
                var st = status.value;
                var tampil = 0;

                Array.prototype.forEach.call(baris, function (tr) {
                    var cocok = (st === 'semua' || tr.getAttribute('data-status') === st) &&
                                (kata === '' || tr.getAttribute('data-cari').indexOf(kata) !== -1);
                    tr.hidden = !cocok;
                    if (cocok) tampil++;
                });

                if (barisKosong) barisKosong.hidden = tampil !== 0;
                if (info) info.textContent = 'Menampilkan ' + tampil + ' dari ' + baris.length + ' ' + satuan;
            }

            cari.addEventListener('input', terapkan);
            status.addEventListener('change', terapkan);
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
       Form penghuni: satu kamar = satu penyewaan aktif.
       Saat status "Aktif", kamar yang sudah terisi (option[data-terisi])
       tidak bisa dipilih. Server tetap memeriksa aturan yang sama.
       ------------------------------------------------------ */
    function initFormPenghuni() {
        var form = document.querySelector('[data-form-penghuni]');
        if (!form) return;

        var status = form.elements.status;
        var kamar = form.elements.kamar_id;

        function sesuaikan() {
            var aktif = status.value === 'aktif';
            Array.prototype.forEach.call(kamar.options, function (opt) {
                if (opt.hasAttribute('data-terisi')) opt.disabled = aktif;
            });
            if (kamar.selectedOptions.length && kamar.selectedOptions[0].disabled) kamar.value = '';
        }

        status.addEventListener('change', sesuaikan);
        sesuaikan();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initMenuUser();
        initFilterTabel();
        initKonfirmasi();
        initFallbackGambar();
        initFormPenghuni();
    });
})();
