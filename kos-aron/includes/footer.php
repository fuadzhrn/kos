</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-info">
            <p class="footer-brand">KOS ARON</p>
            <p><?= e(SITE_LOCATION_FULL) ?></p>
            <p class="footer-note">Informasi kamar dan harga dapat berubah. Silakan hubungi pemilik untuk memastikan.</p>
        </div>

        <nav class="footer-nav" aria-label="Navigasi footer">
            <p class="footer-title">Navigasi</p>
            <ul>
                <li><a href="index.php">Beranda</a></li>
                <li><a href="kamar.php">Kamar</a></li>
                <li><a href="tentang.php">Tentang</a></li>
                <li><a href="kontak.php">Kontak</a></li>
            </ul>
        </nav>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Hak cipta dilindungi.</p>
        </div>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
