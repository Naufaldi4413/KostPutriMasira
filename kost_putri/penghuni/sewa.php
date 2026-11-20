<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('penghuni', '../login.php');

$user = currentUser();
$id_user = (int)($user['id_user'] ?? $_SESSION['user_id'] ?? 0);

$stmtP = $pdo->prepare("
    SELECT p.*, u.nama, u.username, u.role
    FROM penghuni p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_user = :id_user
    LIMIT 1
");
$stmtP->execute(['id_user' => $id_user]);
$penghuni = $stmtP->fetch();

$id_penghuni = (int)($penghuni['id_penghuni'] ?? 0);
$sewaAktif = null;
$latestBayar = null;

if ($id_penghuni > 0) {
    $stmtSewa = $pdo->prepare("
        SELECT s.*, k.nomor_kamar, k.tipe_kamar, k.harga AS harga_kamar, k.kapasitas
        FROM sewa s
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id_penghuni AND s.status = 'aktif'
        ORDER BY s.id_sewa DESC
        LIMIT 1
    ");
    $stmtSewa->execute(['id_penghuni' => $id_penghuni]);
    $sewaAktif = $stmtSewa->fetch();

    if ($sewaAktif) {
        $stmtLatest = $pdo->prepare("SELECT * FROM pembayaran WHERE id_sewa = :id_sewa ORDER BY id_pembayaran DESC LIMIT 1");
        $stmtLatest->execute(['id_sewa' => $sewaAktif['id_sewa']]);
        $latestBayar = $stmtLatest->fetch();
    }
}

$waPengelola = '6282233053344';
$waPesan = urlencode("Halo Pengelola Kost Putri Masira, saya {$penghuni['nama']} ingin menanyakan perpanjangan / masa sewa kost.");
$waUrl = "https://wa.me/{$waPengelola}?text={$waPesan}";
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sewa Saya - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.info-table{width:100%}
.info-table tr td{padding:.75rem .5rem;border-bottom:1px solid #f3f2ee}
.info-table tr:last-child td{border-bottom:none}
.info-title{width:35%;font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.info-data{font-weight:600;color:#1a1a18}
@media(max-width:768px){
    .pay-grid{grid-template-columns:1fr !important}
}
</style>
</head>
<body>
<div class="layout">

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebarPanel">
    <div class="sb-brand">
        <div class="sb-ico"><i class="ph ph-house-line"></i></div>
        <div>
            <div class="sb-name">Kost Putri Masira</div>
            <span class="sb-sub">Panel Penghuni</span>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-label">Utama</div>
        <a href="dashboard.php" class="sb-link"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="kamar.php" class="sb-link"><i class="ph ph-door"></i> Kamar Saya</a>
        <a href="sewa.php" class="sb-link active"><i class="ph ph-file-text"></i> Sewa Saya</a>
        <a href="pembayaran.php" class="sb-link"><i class="ph ph-credit-card"></i> Pembayaran</a>
        <a href="riwayat_pembayaran.php" class="sb-link"><i class="ph ph-clock-counter-clockwise"></i> Riwayat Pembayaran</a>
        <div class="sb-label" style="margin-top:.5rem">Lainnya</div>
        <a href="profil.php" class="sb-link"><i class="ph ph-user"></i> Profil</a>
        <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="sb-link"><i class="ph ph-whatsapp-logo"></i> Hubungi Pengelola</a>
        <a href="../index.php" class="sb-link" target="_blank" rel="noopener"><i class="ph ph-globe"></i> Landing Page</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-avatar"><i class="ph ph-user"></i></div>
            <div>
                <div class="sb-uname"><?= e($penghuni['nama'] ?? $user['nama'] ?? 'Penghuni') ?></div>
                <div class="sb-urole">Penghuni</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Informasi Sewa Saya</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">
        <div class="pg-header">
            <div>
                <div class="pg-title">Kontrak Sewa Kamar</div>
                <p class="pg-sub">Rincian masa berlaku sewa dan status kontrak hunian aktif Anda.</p>
            </div>
        </div>

        <?php if (!$sewaAktif): ?>
        <div class="panel">
            <div class="panel-body empty">
                <i class="ph ph-file-text"></i>
                <div style="font-size:1.05rem;font-weight:700;color:#1a1a18;margin-bottom:.35rem">Belum Ada Data Sewa</div>
                <p>Saat ini Anda belum memiliki kontrak sewa aktif.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="ph ph-file-text"></i> Detail Kontrak Sewa Aktif</div>
                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Aktif</span>
            </div>
            <div class="panel-body">
                <table class="info-table">
                    <tbody>
                        <tr>
                            <td class="info-title">Unit Kamar</td>
                            <td class="info-data">
                                <span class="badge bg-b" style="font-size:.8rem;padding:.25rem .65rem">Kamar <?= e($sewaAktif['nomor_kamar']) ?></span>
                                <span style="font-size:.85rem;color:var(--muted);margin-left:.35rem">(<?= e($sewaAktif['tipe_kamar'] ?: 'Standar') ?>)</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-title">Periode Sewa</td>
                            <td class="info-data"><?= e($sewaAktif['periode'] ?: '1 Semester') ?></td>
                        </tr>
                        <tr>
                            <td class="info-title">Tanggal Mulai</td>
                            <td class="info-data"><?= formatTanggal($sewaAktif['tanggal_mulai']) ?></td>
                        </tr>
                        <tr>
                            <td class="info-title">Tanggal Selesai</td>
                            <td class="info-data"><?= formatTanggal($sewaAktif['tanggal_selesai']) ?></td>
                        </tr>
                        <tr>
                            <td class="info-title">Total Biaya Sewa</td>
                            <td class="info-data" style="color:var(--green);font-size:1.1rem"><?= formatRupiah($sewaAktif['total_sewa']) ?></td>
                        </tr>
                        <tr>
                            <td class="info-title">Status Pelunasan</td>
                            <td class="info-data">
                                <?php if ($latestBayar && $latestBayar['status'] === 'lunas'): ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Lunas</span>
                                <?php else: ?>
                                <span class="badge bg-r"><i class="ph ph-clock"></i> Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap">
                    <a href="pembayaran.php" style="display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;background:var(--green);color:#fff;border-radius:8px;font-size:.85rem;font-weight:600;text-decoration:none">
                        <i class="ph ph-credit-card"></i> Bayar Sewa Sekarang
                    </a>
                    <a href="<?= $waUrl ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.35rem;padding:.55rem .85rem;background:#25d366;color:#fff;border-radius:8px;font-size:.85rem;font-weight:600;text-decoration:none">
                        <i class="ph ph-whatsapp-logo"></i> Hubungi Pengelola
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const burgerBtn = document.getElementById('burgerBtn');
    const sidebar = document.getElementById('sidebarPanel');
    const backdrop = document.getElementById('sidebarBackdrop');

    const openSidebar = () => {
        sidebar?.classList.add('is-open');
        backdrop?.classList.add('is-open');
        burgerBtn?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    };

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        backdrop?.classList.remove('is-open');
        burgerBtn?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    };

    burgerBtn?.addEventListener('click', () => {
        if (sidebar?.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    backdrop?.addEventListener('click', closeSidebar);

    document.querySelectorAll('.sidebar .sb-link, .sidebar .btn-logout').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar?.classList.contains('is-open')) {
            closeSidebar();
        }
    });
});
</script>
</body>
</html>
