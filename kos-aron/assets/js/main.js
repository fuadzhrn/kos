/* ==========================================================
   KOS ARON - JavaScript global
   ========================================================== */
(function () {
    'use strict';

    var PLACEHOLDER = 'assets/images/placeholder.svg';
    var BREAKPOINT_MOBILE = 768;

    /* ------------------------------------------------------
       Navbar mobile (toggle menu)
       ------------------------------------------------------ */
    function initNavbar() {
        var toggle = document.querySelector('.nav-toggle');
        var menu = document.getElementById('nav-menu');
        if (!toggle || !menu) return;

        function setOpen(open) {
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            document.body.classList.toggle('nav-open', open);
        }

        toggle.addEventListener('click', function () {
            setOpen(!menu.classList.contains('is-open'));
        });

        // Tutup dengan tombol Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        // Tutup otomatis saat layar dilebarkan ke ukuran tablet/desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth >= BREAKPOINT_MOBILE && menu.classList.contains('is-open')) {
                setOpen(false);
            }
        });
    }

    /* ------------------------------------------------------
       Fallback gambar: jika file foto gagal dimuat,
       ganti dengan gambar placeholder lokal.
       ------------------------------------------------------ */
    function pasangFallback(img) {
        function ganti() {
            if (img.dataset.fallbackApplied) return;
            img.dataset.fallbackApplied = 'true';
            img.src = PLACEHOLDER;
        }

        img.addEventListener('error', ganti);

        // Gambar yang sudah gagal sebelum script dijalankan
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
            ganti();
        }
    }

    function initImageFallback() {
        var images = document.querySelectorAll('img[data-fallback]');
        Array.prototype.forEach.call(images, pasangFallback);
    }

    /* ------------------------------------------------------
       Filter daftar kamar (kamar.php)
       Menyaring kartu yang sudah dirender PHP berdasarkan
       status (data-status) dan kata kunci (data-cari).
       ------------------------------------------------------ */
    function initFilterKamar() {
        var form = document.getElementById('filter-kamar');
        var grid = document.getElementById('grid-kamar');
        if (!form || !grid) return;

        var inputCari = form.elements.cari;
        var selectStatus = form.elements.status;
        var info = document.getElementById('info-hasil');
        var kosong = document.getElementById('kamar-tidak-ditemukan');
        var kartu = grid.querySelectorAll('.card-kamar');
        var total = kartu.length;

        function normalisasi(teks) {
            return teks.toLowerCase().replace(/\s+/g, ' ').trim();
        }

        function terapkan() {
            var kataKunci = normalisasi(inputCari.value);
            var status = selectStatus.value;
            var jumlahTampil = 0;

            Array.prototype.forEach.call(kartu, function (card) {
                var cocokStatus = status === 'semua' || card.dataset.status === status;
                var cocokCari = kataKunci === '' || card.dataset.cari.indexOf(kataKunci) !== -1;
                var tampil = cocokStatus && cocokCari;

                card.hidden = !tampil;
                if (tampil) jumlahTampil++;
            });

            grid.hidden = jumlahTampil === 0;
            if (kosong) kosong.hidden = jumlahTampil !== 0;
            if (info) info.textContent = 'Menampilkan ' + jumlahTampil + ' dari ' + total + ' kamar';
        }

        function reset() {
            form.reset();
            terapkan();
            inputCari.focus();
        }

        inputCari.addEventListener('input', terapkan);
        selectStatus.addEventListener('change', terapkan);

        // Tekan Enter di kolom pencarian tidak me-reload halaman
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            terapkan();
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-reset-filter]'), function (tombol) {
            tombol.addEventListener('click', reset);
        });

        // Terapkan sekali saat halaman dibuka (browser bisa memulihkan isi form saat kembali)
        terapkan();
        window.addEventListener('pageshow', terapkan);
    }

    /* ------------------------------------------------------
       Galeri foto detail kamar
       Klik thumbnail -> ganti foto utama tanpa reload.
       Tanpa JavaScript, thumbnail tetap membuka file fotonya.
       ------------------------------------------------------ */
    function initGaleri() {
        var galeri = document.querySelector('[data-galeri]');
        if (!galeri) return;

        var utama = galeri.querySelector('[data-galeri-utama]');
        var thumbs = galeri.querySelectorAll('[data-galeri-thumb]');
        if (!utama || thumbs.length === 0) return;

        Array.prototype.forEach.call(thumbs, function (thumb) {
            thumb.addEventListener('click', function (event) {
                event.preventDefault();

                delete utama.dataset.fallbackApplied;
                utama.src = thumb.getAttribute('href');

                Array.prototype.forEach.call(thumbs, function (t) {
                    t.classList.remove('is-active');
                    t.removeAttribute('aria-current');
                });
                thumb.classList.add('is-active');
                thumb.setAttribute('aria-current', 'true');
            });
        });
    }

    /* ------------------------------------------------------
       Tampilkan / sembunyikan password (login.php)
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

    document.addEventListener('DOMContentLoaded', function () {
        initNavbar();
        initImageFallback();
        initFilterKamar();
        initGaleri();
        initTogglePassword();
    });
})();
