<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

try {
    $daftarKamar = $pdo->query("
        SELECT k.*, (SELECT COUNT(*) FROM sewa sw WHERE sw.id_kamar = k.id_kamar AND sw.status = 'aktif') AS penghuni_aktif,
               GROUP_CONCAT(f.nama_fasilitas ORDER BY f.id_fasilitas SEPARATOR '||') AS fasilitas_list
        FROM kamar k
        LEFT JOIN kamar_fasilitas kf ON k.id_kamar=kf.id_kamar
        LEFT JOIN fasilitas f ON kf.id_fasilitas=f.id_fasilitas
        GROUP BY k.id_kamar ORDER BY k.nomor_kamar ASC
    ")->fetchAll();
    $fasilitasSemua = $pdo->query("SELECT * FROM fasilitas ORDER BY id_fasilitas")->fetchAll();
} catch (PDOException $ex) {
    $daftarKamar=$fasilitasSemua=[];
}

// Ketentuan harga dari pemilik kost
$hargaPerOrang2 = 2; // tarif berdua dibagi 2
$bulanPerSemester = 6; // pembayaran per semester

$totalKamar  = count($daftarKamar);
$kamarKosong = count(array_filter($daftarKamar, fn($k)=>$k['status']!=='penuh'));
$kamarTerisi = $totalKamar - $kamarKosong;
$waNumber    = '6282233053344';
$waHeroMsg   = urlencode('Halo Pengelola Kost Putri Masira, saya ingin bertanya tentang ketersediaan kamar.');
$roomPhotos  = [
    'assets/images/kamar-a1.jpg',
    'assets/images/kamar-a2.jpg',
    'assets/images/kamar-a3.jpg',
    'assets/images/kamar-default.jpg',
];
$pageTitle = 'Kost Putri Masira - Kost Khusus Putri';
$pageDesc  = 'Kost khusus putri. Sewa sendiri Rp 500.000/bulan atau berdua dibagi dua, dibayar per semester. Dapur, kulkas, parkiran, dan jemuran tersedia.';
$mapEmbedUrl = 'https://maps.google.com/maps?q=-7.1259574,112.7170979&hl=id&z=17&output=embed';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- HERO -->
<section class="hero-section" id="beranda">
    <div class="hero-backdrop-pattern"></div>
    <div class="container hero-container">
        <div class="hero-content">
            <div class="hero-pill-badge">
                <span class="pill-dot"></span>
                <span>Khusus Putri</span>
            </div>
            <h1 class="hero-title">
                Kost Khusus Putri,
                <span class="text-accent-serif">Tenang &amp; Nyaman.</span>
            </h1>
            <p class="hero-subtitle">Kost khusus putri dengan suasana tenang untuk belajar dan beristirahat. Setiap kamar berisi 2 kasur dan lemari besar, dengan dapur, kulkas, dan parkiran yang dipakai bersama.</p>
            <div class="hero-actions">
                <a href="#kamar" class="btn btn-primary btn-lg">
                    <i class="ph ph-door"></i><span>Lihat Pilihan Kamar</span>
                </a>
                <a href="#kontak" class="btn btn-secondary btn-lg">
                    <i class="ph ph-whatsapp-logo"></i><span>Tanya Pengelola</span>
                </a>
            </div>
            <div class="hero-stats-bar">
                <div class="stat-item">
                    <span class="stat-number"><?= $kamarKosong ?></span>
                    <span class="stat-label">Kamar Tersedia</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number"><?= $totalKamar ?></span>
                    <span class="stat-label">Total Unit</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">Khusus Putri</span>
                </div>
            </div>
        </div>

        <!-- FOTO SAMPING HERO -->
        <div class="hero-visual">
            <div class="hero-visual-frame">
                <div class="hero-image-wrapper">
                    <img class="hero-main-img"
                         src="assets/images/hero-kost.jpg"
                         alt="Suasana Hunian Kost Putri Masira"
                         loading="eager">
                    <div class="hero-image-overlay"></div>
                </div>
                <div class="hero-floating-card top-badge">
                    <div class="trust-icon"><i class="ph ph-shield-check"></i></div>
                    <div>
                        <strong style="display:block;font-size:.875rem;color:var(--text-main)">Khusus Putri</strong>
                        <span style="font-size:.8125rem;color:var(--text-muted)">Hanya untuk penghuni putri</span>
                    </div>
                </div>
                <div class="hero-floating-card bottom-badge">
                    <div class="trust-icon">
                        <i class="ph ph-wifi-high"></i>
                    </div>
                    <div>
                        <strong style="display:block;font-size:.875rem;color:var(--text-main)">Fasilitas Bersama</strong>
                        <span style="font-size:.8125rem;color:var(--text-muted)">Kulkas, Kamar Mandi, Dapur &amp; Parkir</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TENTANG KOST -->
<section class="section-wrapper bg-stone" id="tentang">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-tag"><i class="ph ph-sparkle"></i><span>Mengenal Kost Putri Masira</span></div>
            <h2 class="section-title">Mengapa Memilih Kost Kami?</h2>
            <p class="section-lead">Kost khusus putri dengan biaya yang jelas dan fasilitas yang bisa dipakai bersama.</p>
        </div>
        <div class="grid-feature-cards">
            <article class="feature-card">
                <div class="feature-icon-wrap"><i class="ph ph-gender-female"></i></div>
                <h3 class="feature-title">Khusus Putri</h3>
                <p class="feature-desc">Kost ini hanya untuk penghuni putri, sehingga lebih nyaman dan privasi lebih terjaga.</p>
                <ul class="feature-checklist">
                    <li><i class="ph ph-check-circle"></i> Hanya untuk penghuni putri</li>
                    <li><i class="ph ph-check-circle"></i> Bisa disewa sendiri atau berdua</li>
                </ul>
            </article>
            <article class="feature-card">
                <div class="feature-icon-wrap"><i class="ph ph-bed"></i></div>
                <h3 class="feature-title">Kamar Siap Pakai</h3>
                <p class="feature-desc">Setiap kamar sudah berisi kasur dan lemari, jadi Anda tinggal membawa barang pribadi.</p>
                <ul class="feature-checklist">
                    <li><i class="ph ph-check-circle"></i> 2 kasur di setiap kamar</li>
                    <li><i class="ph ph-check-circle"></i> Lemari besar dengan 2 pintu</li>
                </ul>
            </article>
            <article class="feature-card">
                <div class="feature-icon-wrap"><i class="ph ph-cooking-pot"></i></div>
                <h3 class="feature-title">Fasilitas Bersama</h3>
                <p class="feature-desc">Fasilitas yang dipakai bersama oleh seluruh penghuni kost.</p>
                <ul class="feature-checklist">
                    <li><i class="ph ph-check-circle"></i> Dapur bersama &amp; kulkas besar</li>
                    <li><i class="ph ph-check-circle"></i> Kamar mandi luar &amp; tempat jemuran</li>
                    <li><i class="ph ph-check-circle"></i> Parkiran &amp; WiFi</li>
                </ul>
            </article>
            <article class="feature-card">
                <div class="feature-icon-wrap"><i class="ph ph-wallet"></i></div>
                <h3 class="feature-title">Biaya Jelas</h3>
                <p class="feature-desc">Tarif sederhana dan bisa dibagi dua jika kamar dipakai berdua.</p>
                <ul class="feature-checklist">
                    <li><i class="ph ph-check-circle"></i> Sendiri Rp 500.000 / bulan</li>
                    <li><i class="ph ph-check-circle"></i> Berdua Rp 250.000 / orang / bulan</li>
                    <li><i class="ph ph-check-circle"></i> Dibayar per semester</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- KAMAR -->
<section class="section-wrapper" id="kamar">
    <div class="container">
        <div class="section-header-flex">
            <div>
                <div class="section-tag"><i class="ph ph-door"></i><span>Pilihan Kamar</span></div>
                <h2 class="section-title">Daftar Kamar yang Tersedia</h2>
                <p class="section-lead">Pilih unit yang sesuai kebutuhan Anda. Ketersediaan selalu diperbarui.</p>
            </div>
            <div class="availability-status-box">
                <div class="status-summary-item">
                    <span class="status-dot available"></span>
                    <span class="status-text"><strong><?= $kamarKosong ?></strong> Kamar Siap Huni</span>
                </div>
                <div class="status-summary-item">
                    <span class="status-dot occupied"></span>
                    <span class="status-text"><strong><?= $kamarTerisi ?></strong> Kamar Penuh</span>
                </div>
            </div>
        </div>
        <div class="price-info-banner">
            <div class="price-info-item"><i class="ph ph-user"></i><div><strong>Sendiri</strong><span>Rp 500.000 / bulan</span></div></div>
            <div class="price-info-item"><i class="ph ph-users"></i><div><strong>Berdua</strong><span>Rp 500.000 dibagi 2 = Rp 250.000 / orang / bulan</span></div></div>
            <div class="price-info-item"><i class="ph ph-calendar-check"></i><div><strong>Pembayaran</strong><span>Dibayar per semester (<?= $bulanPerSemester ?> bulan)</span></div></div>
        </div>
        <?php if (empty($daftarKamar)): ?>
        <div class="empty-state-card">
            <i class="ph ph-info"></i>
            <h3>Data Kamar Belum Tersedia</h3>
            <p>Silakan hubungi pengelola untuk informasi ketersediaan kamar.</p>
            <a href="#kontak" class="btn btn-primary"><i class="ph ph-chat-circle-dots"></i><span>Hubungi Pengelola</span></a>
        </div>
        <?php else: ?>
        <div class="rooms-carousel" id="roomsCarousel">
        <button type="button" class="rooms-nav rooms-nav-prev" id="roomsPrev" aria-label="Kamar sebelumnya"><i class="ph ph-caret-left"></i></button>
        <button type="button" class="rooms-nav rooms-nav-next" id="roomsNext" aria-label="Kamar berikutnya"><i class="ph ph-caret-right"></i></button>
        <div class="rooms-grid" id="roomsTrack" tabindex="0" aria-label="Daftar kamar, geser ke samping">
            <?php foreach ($daftarKamar as $idx => $kamar):
                $isAvail   = $kamar['status']!=='penuh';
                $photo     = $roomPhotos[$idx % count($roomPhotos)];
                $roomWaMsg = urlencode('Halo Pengelola, saya tertarik Kamar '.$kamar['nomor_kamar'].' (Tipe: '.($kamar['tipe_kamar']??'Standar').'). Apakah masih bisa disurvei?');
            ?>
            <article class="room-card <?= $isAvail?'room-available':'room-occupied' ?>">
                <div class="room-card-media">
                    <img class="room-thumb-img" src="<?= $photo ?>" alt="Kamar <?= e($kamar['nomor_kamar']) ?>" loading="lazy">
                    <div class="room-status-badge <?= $isAvail?'badge-available':'badge-occupied' ?>">
                        <i class="ph <?= $isAvail?'ph-check':'ph-lock' ?>"></i>
                        <span><?= $isAvail ? ((int)$kamar['penghuni_aktif'] > 0 ? 'Sisa ' . max(1, (int)$kamar['kapasitas'] - (int)$kamar['penghuni_aktif']) . ' tempat' : 'Tersedia') : 'Penuh' ?></span>
                    </div>
                </div>
                <div class="room-card-body">
                    <div class="room-card-header">
                        <span class="room-number">Kamar <?= e($kamar['nomor_kamar']) ?></span>
                        <span class="room-type"><?= e($kamar['tipe_kamar']??'Standar') ?></span>
                    </div>
                    <div class="room-pricing">
                        <span class="price-value"><?= formatRupiah($kamar['harga']) ?></span>
                        <span class="price-period">/ bulan <small>(sendiri)</small></span>
                    </div>
                    <p class="room-price-note">Berdua: <strong><?= formatRupiah($kamar['harga'] / $hargaPerOrang2) ?></strong> / orang / bulan<br>Dibayar per semester</p>
                    <?php if (!empty($kamar['keterangan']) && !in_array(strtolower(trim($kamar['keterangan'])), ['kamar tersedia','oke'], true)): ?>
                    <p class="room-description"><?= e($kamar['keterangan']) ?></p>
                    <?php endif; ?>
                    <div class="room-facilities-wrap">
                        <span class="facilities-label">Isi Kamar:</span>
                        <ul class="room-amenities-pills">
                            <li><i class="ph ph-check"></i><span>Kasur 2 buah</span></li>
                            <li><i class="ph ph-check"></i><span>Lemari besar (2 pintu)</span></li>
                        </ul>
                        <p class="text-muted-small">Dapur, kamar mandi, kulkas, parkir &amp; jemuran digunakan bersama.</p>
                    </div>
                    <div class="room-card-footer">
                        <?php if ($isAvail): ?>
                        <a href="https://wa.me/<?= $waNumber ?>?text=<?= $roomWaMsg ?>" target="_blank" rel="noopener" class="btn btn-wa btn-block">
                            <i class="ph ph-whatsapp-logo"></i><span>Tanya Kamar <?= e($kamar['nomor_kamar']) ?></span>
                        </a>
                        <?php else: ?>
                        <button type="button" class="btn btn-disabled btn-block" disabled>
                            <i class="ph ph-lock"></i><span>Sedang Terisi</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        </div>
        <p class="rooms-swipe-hint"><i class="ph ph-hand-swipe-left"></i><span>Geser ke samping untuk melihat kamar lainnya</span></p>
        <?php endif; ?>
    </div>
</section>

<!-- FASILITAS -->
<section class="section-wrapper bg-sand" id="fasilitas">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-tag"><i class="ph ph-squares-four"></i><span>Fasilitas</span></div>
            <h2 class="section-title">Fasilitas Kost</h2>
            <p class="section-lead">Isi kamar dan fasilitas yang dapat digunakan penghuni.</p>
        </div>
        <div class="facilities-category-block">
            <div class="category-header">
                <div class="category-icon"><i class="ph ph-house-line"></i></div>
                <div><h3 class="category-title">Fasilitas Kost</h3><p class="category-desc">Dapat digunakan oleh seluruh penghuni.</p></div>
            </div>
            <div class="grid-amenities-cards">
                <?php
                $fasilitasTampil = !empty($fasilitasSemua) ? $fasilitasSemua : [
                    ['nama_fasilitas'=>'Kasur (2 buah)','keterangan'=>'Dua kasur di setiap kamar.'],
                    ['nama_fasilitas'=>'Lemari Besar','keterangan'=>'Lemari besar dengan 2 pintu.'],
                    ['nama_fasilitas'=>'Dapur Bersama','keterangan'=>'Dapur untuk dipakai bersama penghuni.'],
                    ['nama_fasilitas'=>'Kamar Mandi Luar','keterangan'=>'Kamar mandi di luar kamar, dipakai bersama.'],
                    ['nama_fasilitas'=>'Kulkas Besar','keterangan'=>'Kulkas besar untuk dipakai bersama.'],
                    ['nama_fasilitas'=>'Parkiran','keterangan'=>'Area parkir untuk penghuni.'],
                    ['nama_fasilitas'=>'Tempat Jemuran','keterangan'=>'Tempat menjemur pakaian bersama.'],
                    ['nama_fasilitas'=>'WiFi','keterangan'=>'Internet untuk penghuni.'],
                ];
                foreach ($fasilitasTampil as $fu):
                    $nll = strtolower($fu['nama_fasilitas']);
                    $ico = str_contains($nll,'wifi') ? 'ph-wifi-high'
                         : (str_contains($nll,'kulkas') ? 'ph-snowflake'
                         : (str_contains($nll,'parkir') ? 'ph-motorcycle'
                         : (str_contains($nll,'dapur') ? 'ph-cooking-pot'
                         : (str_contains($nll,'kasur') ? 'ph-bed'
                         : (str_contains($nll,'lemari') ? 'ph-dresser'
                         : (str_contains($nll,'mandi') ? 'ph-shower'
                         : (str_contains($nll,'jemur') ? 'ph-t-shirt' : 'ph-check-circle')))))));
                ?>
                <div class="amenity-item-card">
                    <div class="amenity-icon"><i class="ph <?= $ico ?>"></i></div>
                    <div class="amenity-info">
                        <h4 class="amenity-name"><?= e($fu['nama_fasilitas']) ?></h4>
                        <p class="amenity-detail"><?= e($fu['keterangan'] ?? '') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- LOKASI -->
<section class="section-wrapper" id="lokasi">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-tag"><i class="ph ph-map-pin"></i><span>Lokasi</span></div>
            <h2 class="section-title">Lokasi Kost</h2>
            <p class="section-lead">Lihat titik lokasi kost di peta, lalu hubungi pengelola untuk survei langsung.</p>
        </div>
        <div class="location-layout-grid">
            <div class="location-info-panel">
                <h3 class="panel-heading"><i class="ph ph-navigation-arrow"></i><span>Informasi Lokasi</span></h3>
                <ul class="nearby-list">
                    <li class="nearby-item"><div class="nearby-icon"><i class="ph ph-map-trifold"></i></div><div class="nearby-text"><strong>Titik di Peta</strong><span>Gunakan peta di samping untuk melihat posisi kost dan rute dari lokasi Anda.</span></div></li>
                    <li class="nearby-item"><div class="nearby-icon"><i class="ph ph-motorcycle"></i></div><div class="nearby-text"><strong>Parkiran</strong><span>Tersedia area parkir untuk penghuni.</span></div></li>
                    <li class="nearby-item"><div class="nearby-icon"><i class="ph ph-whatsapp-logo"></i></div><div class="nearby-text"><strong>Survei Langsung</strong><span>Tanyakan alamat lengkap dan jadwal survei melalui WhatsApp pengelola.</span></div></li>
                </ul>
            </div>
            <div class="location-map-panel">
                <div class="map-card-wrapper">
                    <div class="map-card-header">
                        <div class="map-header-title"><i class="ph ph-map-trifold"></i><span>Peta &amp; Lingkungan</span></div>
                        <span class="map-tag">Kost Putri Masira</span>
                    </div>
                    <div class="map-embed-container" style="padding:0;min-height:360px;overflow:hidden">
                        <iframe 
                            src="<?= htmlspecialchars($mapEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" 
                            width="100%" 
                            height="100%" 
                            style="border:0;width:100%;height:100%;min-height:360px;display:block;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Peta Lokasi Kost Putri Masira">
                        </iframe>
                    </div>
                    <div class="map-card-footer"><i class="ph ph-shield-check"></i><span>Titik lokasi sesuai Google Maps.</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- KONTAK & FAQ -->
<section class="section-wrapper bg-stone" id="kontak">
    <div class="container">
        <div class="section-header text-center">
            <div class="section-tag"><i class="ph ph-chat-circle-dots"></i><span>Komunikasi &amp; Informasi</span></div>
            <h2 class="section-title">Hubungi Pengelola Kost</h2>
            <p class="section-lead">Pertanyaan soal kamar, biaya, atau survei? Hubungi kami lewat WhatsApp.</p>
        </div>
        <div class="contact-card-main">
            <div class="contact-left">
                <h3 class="contact-main-heading">Informasi &amp; Reservasi Kamar</h3>
                <p class="contact-main-desc">Hubungi pengelola untuk menanyakan ketersediaan kamar dan menjadwalkan survei.</p>
                <div class="contact-points-list">
                    <div class="contact-point"><div class="point-icon"><i class="ph ph-whatsapp-logo"></i></div><div><span class="point-label">WhatsApp Pengelola</span><span class="point-val">+62 822-3305-3344</span></div></div>
                    <div class="contact-point"><div class="point-icon"><i class="ph ph-calendar-check"></i></div><div><span class="point-label">Sistem Pembayaran</span><span class="point-val">Per semester</span></div></div>
                    <div class="contact-point"><div class="point-icon"><i class="ph ph-gender-female"></i></div><div><span class="point-label">Ketentuan Khusus</span><span class="point-val">Khusus penghuni putri</span></div></div>
                </div>
                <div class="contact-actions-row">
                    <a href="https://wa.me/<?= $waNumber ?>?text=<?= $waHeroMsg ?>" target="_blank" rel="noopener" class="btn btn-wa btn-lg">
                        <i class="ph ph-whatsapp-logo"></i><span>Chat WhatsApp Sekarang</span>
                    </a>
                    <a href="login.php" class="btn btn-outline btn-lg">
                        <i class="ph ph-sign-in"></i><span>Portal Login Penghuni</span>
                    </a>
                </div>
            </div>
            <div class="contact-right">
                <h4 class="faq-box-title"><i class="ph ph-question"></i><span>Pertanyaan Umum (FAQ)</span></h4>
                <div class="faq-accordion">
                    <details class="faq-item">
                        <summary class="faq-question">Bagaimana cara memesan atau survei kamar?</summary>
                        <div class="faq-answer"><p>Hubungi pengelola lewat WhatsApp untuk menanyakan kamar yang tersedia dan menjadwalkan survei.</p></div>
                    </details>
                    <details class="faq-item">
                        <summary class="faq-question">Berapa biaya sewa dan bagaimana cara bayarnya?</summary>
                        <div class="faq-answer"><p>Sewa sendiri Rp 500.000 per bulan. Jika berdua, biayanya dibagi dua (Rp 250.000 per orang per bulan). Pembayaran dilakukan per semester.</p></div>
                    </details>
                    <details class="faq-item">
                        <summary class="faq-question">Apa saja fasilitasnya?</summary>
                        <div class="faq-answer"><p>Setiap kamar berisi 2 kasur dan lemari besar (2 pintu). Fasilitas bersama: dapur, kamar mandi luar, kulkas besar, parkiran, tempat jemuran, dan WiFi.</p></div>
                    </details>
                    <details class="faq-item">
                        <summary class="faq-question">Siapa yang boleh menyewa?</summary>
                        <div class="faq-answer"><p>Kost ini khusus putri. Kamar bisa disewa sendiri atau berdua.</p></div>
                    </details>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>