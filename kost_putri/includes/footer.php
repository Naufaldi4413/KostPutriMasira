<?php
$currentYear  = date('Y');
$waNumber     = '6282233053344';
$waDefaultMsg = urlencode('Halo Pengelola Kost Masira, saya ingin menanyakan informasi sewa kamar.');
?>
<footer class="site-footer">
    <div class="container footer-container">
        <div class="footer-col footer-col-brand">
            <div class="footer-brand">
                <span class="brand-icon"><i class="ph ph-house-line"></i></span>
                <span class="brand-title">Kost Putri Masira</span>
            </div>
            <p class="footer-desc">Kost khusus putri dengan kamar berisi 2 kasur dan lemari besar, serta fasilitas bersama yang lengkap.</p>
            <div class="footer-badges">
                <span class="badge-tag"><i class="ph ph-gender-female"></i> Khusus Putri</span>
                <span class="badge-tag"><i class="ph ph-calendar-check"></i> Bayar Per Semester</span>
                <span class="badge-tag"><i class="ph ph-wifi-high"></i> WiFi</span>
            </div>
        </div>
        <div class="footer-col">
            <h4 class="footer-heading">Navigasi</h4>
            <ul class="footer-links">
                <li><a href="#beranda"><i class="ph ph-caret-right"></i> Beranda</a></li>
                <li><a href="#tentang"><i class="ph ph-caret-right"></i> Tentang Kost</a></li>
                <li><a href="#kamar"><i class="ph ph-caret-right"></i> Pilihan Kamar</a></li>
                <li><a href="#fasilitas"><i class="ph ph-caret-right"></i> Fasilitas</a></li>
                <li><a href="#lokasi"><i class="ph ph-caret-right"></i> Lokasi</a></li>
                <li><a href="#kontak"><i class="ph ph-caret-right"></i> Kontak</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4 class="footer-heading">Ketentuan Kost</h4>
            <ul class="footer-info-list">
                <li><i class="ph ph-gender-female"></i><span>Khusus Putri</span></li>
                <li><i class="ph ph-users"></i><span>Sewa sendiri atau berdua</span></li>
                <li><i class="ph ph-wallet"></i><span>Rp 500.000 / bulan, dibagi dua jika berdua</span></li>
                <li><i class="ph ph-calendar-check"></i><span>Pembayaran per semester</span></li>
            </ul>
        </div>
        <div class="footer-col footer-col-contact">
            <h4 class="footer-heading">Portal &amp; Akun</h4>
            <p class="footer-contact-text">Akses portal untuk penghuni dan pengelola:</p>
            <a href="login.php" class="btn btn-footer-portal btn-block">
                <i class="ph ph-sign-in"></i>
                <span>Masuk ke Akun</span>
            </a>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container footer-bottom-content">
            <p>&copy; <?= $currentYear ?> Kost Putri Masira. Seluruh hak cipta dilindungi.</p>
            <p class="footer-credit">Kost Putri Masira</p>
        </div>
    </div>
</footer>
<a href="https://wa.me/<?= $waNumber ?>?text=<?= $waDefaultMsg ?>"
   target="_blank" rel="noopener noreferrer"
   class="floating-wa-btn" aria-label="Hubungi via WhatsApp">
    <i class="ph-fill ph-whatsapp-logo"></i>
    <span class="floating-wa-tooltip">Hubungi Pengelola</span>
</a>
<?php
$_callerDepth2 = substr_count(str_replace('\\','/',dirname($_SERVER['SCRIPT_FILENAME'])), '/')
               - substr_count(str_replace('\\','/',rtrim(realpath(__DIR__.'/../'), '/\\')), '/');
$_relBase2 = $_callerDepth2 > 0 ? str_repeat('../', $_callerDepth2) : '';
?>
<script src="<?= $_relBase2 ?>assets/js/app.js"></script>
</body>
</html>