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
$jumlahPenghuniAktifKamar = 0;
$roommates = [];
$fasilitasKamar = [];

if ($id_penghuni > 0) {
    $stmtSewa = $pdo->prepare("
        SELECT 
            s.*,
            k.nomor_kamar, k.tipe_kamar, k.harga AS harga_kamar, k.kapasitas, k.keterangan AS keterangan_kamar
        FROM sewa s
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id_penghuni AND s.status = 'aktif'
        ORDER BY s.id_sewa DESC
        LIMIT 1
    ");
    $stmtSewa->execute(['id_penghuni' => $id_penghuni]);
    $sewaAktif = $stmtSewa->fetch();

    if ($sewaAktif) {
        $id_kamar = (int)$sewaAktif['id_kamar'];

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id_kamar AND status = 'aktif'");
        $stmtCount->execute(['id_kamar' => $id_kamar]);
        $jumlahPenghuniAktifKamar = (int)$stmtCount->fetchColumn();

        $stmtRoommates = $pdo->prepare("
            SELECT u.nama
            FROM sewa s
            JOIN penghuni p ON s.id_penghuni = p.id_penghuni
            JOIN users u ON p.id_user = u.id_user
            WHERE s.id_kamar = :id_kamar 
              AND s.status = 'aktif'
              AND s.id_penghuni != :id_penghuni
        ");
        $stmtRoommates->execute(['id_kamar' => $id_kamar, 'id_penghuni' => $id_penghuni]);
        $roommates = $stmtRoommates->fetchAll(PDO::FETCH_COLUMN);

        $stmtFasilitas = $pdo->prepare("
            SELECT f.nama_fasilitas
            FROM kamar_fasilitas kf
            JOIN fasilitas f ON kf.id_fasilitas = f.id_fasilitas
            WHERE kf.id_kamar = :id_kamar
            ORDER BY f.nama_fasilitas ASC
        ");
        $stmtFasilitas->execute(['id_kamar' => $id_kamar]);
        $fasilitasKamar = $stmtFasilitas->fetchAll(PDO::FETCH_COLUMN);
    }
}

$waPengelola = '6282233053344';
$nomorKamarText = $sewaAktif ? "Kamar " . e($sewaAktif['nomor_kamar']) : "Penghuni Kost";
$namaPenghuniText = $penghuni['nama'] ?? $user['nama'] ?? 'Penghuni';
$waPesan = urlencode("Halo Pengelola Kost Putri Masira, saya {$namaPenghuniText} dari {$nomorKamarText}. Saya ingin menanyakan fasilitas kamar...");
$waUrl = "https://wa.me/{$waPengelola}?text={$waPesan}";
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kamar Saya - Kost Putri Masira</title>
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
.chips-wrap{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.35rem}
.chip-f{background:#f8f9fa;border:1px solid var(--border);border-radius:999px;padding:.25rem .75rem;font-size:.78rem;color:#495057;display:inline-flex;align-items:center;gap:.3rem}
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
        <a href="kamar.php" class="sb-link active"><i class="ph ph-door"></i> Kamar Saya</a>
        <a href="sewa.php" class="sb-link"><i class="ph ph-file-text"></i> Sewa Saya</a>
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
        <div class="topbar-title">Informasi Kamar Saya</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">
        <div class="pg-header">
            <div>
                <div class="pg-title">Kamar Hunian Saya</div>
                <p class="pg-sub">Spesifikasi unit kamar, fasilitas, dan rekan sekamar yang Anda tempati.</p>
            </div>
        </div>

        <?php if (!$sewaAktif): ?>
        <div class="panel">
            <div class="panel-body empty">
                <i class="ph ph-door"></i>
                <div style="font-size:1.05rem;font-weight:700;color:#1a1a18;margin-bottom:.35rem">Belum Memiliki Kamar Aktif</div>
                <p>Anda belum terdaftar menempati unit kamar aktif di Kost Putri Masira.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="ph ph-door"></i> Rincian Kamar <?= e($sewaAktif['nomor_kamar']) ?></div>
                <span class="badge bg-b">Kamar <?= e($sewaAktif['nomor_kamar']) ?></span>
            </div>
            <div class="panel-body">
                <table class="info-table">
                    <tbody>
                        <tr>
                            <td class="info-title">Nomor Kamar</td>
                            <td class="info-data">
                                <span class="badge bg-b" style="font-size:.85rem;padding:.25rem .7rem">Kamar <?= e($sewaAktif['nomor_kamar']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-title">Tipe Kamar</td>
                            <td class="info-data"><?= e($sewaAktif['tipe_kamar'] ?: 'Standar') ?></td>
                        </tr>
                        <tr>
                            <td class="info-title">Tarif Sewa</td>
                            <td class="info-data" style="color:var(--green);font-size:1.05rem">
                                <?= formatRupiah($sewaAktif['harga_kamar']) ?> <span style="font-size:.75rem;color:var(--muted);font-weight:400">/ semester</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-title">Kapasitas &amp; Okupansi</td>
                            <td class="info-data"><?= (int)$sewaAktif['kapasitas'] ?> Orang (<?= $jumlahPenghuniAktifKamar ?> Terisi)</td>
                        </tr>
                        <tr>
                            <td class="info-title">Teman Sekamar</td>
                            <td class="info-data">
                                <?php if (!empty($roommates)): ?>
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                        <?php foreach ($roommates as $rm): ?>
                                        <span class="badge bg-b"><i class="ph ph-user"></i> <?= e($rm) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--muted)">Anda menempati kamar ini sendiri (tidak ada rekan sekamar).</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($sewaAktif['keterangan_kamar'])): ?>
                        <tr>
                            <td class="info-title">Deskripsi Kamar</td>
                            <td class="info-data" style="font-weight:400;color:#495057"><?= nl2br(e($sewaAktif['keterangan_kamar'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="info-title">Fasilitas Kamar</td>
                            <td class="info-data">
                                <?php if (!empty($fasilitasKamar)): ?>
                                <div class="chips-wrap">
                                    <?php foreach ($fasilitasKamar as $f): ?>
                                    <span class="chip-f"><i class="ph ph-sparkle" style="color:var(--green)"></i> <?= e($f) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span style="color:var(--muted);font-size:.85rem">Fasilitas standar kost.</span>
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
