<?php
/**
 * Public Navbar Component
 * Sistem Pengelolaan Kost
 */

$isAuth = function_exists('isLoggedIn') ? isLoggedIn() : (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']));
$user = function_exists('currentUser') ? currentUser() : ($isAuth ? ['nama' => $_SESSION['nama'] ?? 'User', 'role' => $_SESSION['role'] ?? ''] : null);

$dashboardLink = 'login.php';
if ($isAuth && $user) {
    $dashboardLink = ($user['role'] === 'pengelola') ? 'admin/dashboard.php' : 'penghuni/dashboard.php';
}
?>
<header class="site-navbar-wrapper" id="site-navbar">
    <nav class="container nav-container" aria-label="Navigasi Utama">
        <!-- Brand Logo -->
        <a href="#beranda" class="brand-logo" aria-label="Kost Putri Masira">
            <span class="brand-icon">
                <i class="ph ph-house-line"></i>
            </span>
            <div class="brand-text">
                <span class="brand-title">Kost Putri Masira</span>
                <span class="brand-tagline">Kost Khusus Putri</span>
            </div>
        </a>

        <!-- Desktop Action Buttons -->
        <div class="nav-actions-desktop">
            <a href="#kontak" class="btn btn-outline">
                <i class="ph ph-chat-circle-dots"></i>
                <span>Hubungi</span>
            </a>

            <?php if ($isAuth && $user): ?>
                <a href="<?= htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
                    <i class="ph ph-squares-four"></i>
                    <span>Dashboard</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="ph ph-user"></i>
                    <span>Login</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Mobile Menu Toggle Button -->
        <button type="button" class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="navMenu" aria-label="Buka Menu Navigasi">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </nav>
</header>
<!-- Nav drawer OUTSIDE header — fixes backdrop-filter stacking context bug in mobile browsers -->
<div class="nav-menu" id="navMenu" role="navigation" aria-label="Menu Navigasi">
    <ul class="nav-links">
        <li><a href="#beranda" class="nav-link active">Beranda</a></li>
        <li><a href="#tentang" class="nav-link">Tentang</a></li>
        <li><a href="#kamar" class="nav-link">Kamar</a></li>
        <li><a href="#fasilitas" class="nav-link">Fasilitas</a></li>
        <li><a href="#lokasi" class="nav-link">Lokasi</a></li>
        <li><a href="#kontak" class="nav-link">Kontak</a></li>
    </ul>
    <!-- Mobile Actions -->
    <div class="nav-actions-mobile">
        <?php if ($isAuth && $user): ?>
            <a href="<?= htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-block">
                <i class="ph ph-squares-four"></i>
                <span>Dashboard</span>
            </a>
            <a href="logout.php" class="btn btn-outline btn-block">
                <i class="ph ph-sign-out"></i>
                <span>Keluar</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary btn-block">
                <i class="ph ph-sign-in"></i>
                <span>Login Penghuni / Admin</span>
            </a>
        <?php endif; ?>
    </div>
</div>
<div class="nav-backdrop" id="navBackdrop"></div>
