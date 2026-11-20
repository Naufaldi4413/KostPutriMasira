<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

try {
    $stmt = $pdo->query("
        SELECT 
            f.*,
            COUNT(kf.id_kamar) AS total_kamar
        FROM fasilitas f
        LEFT JOIN kamar_fasilitas kf ON f.id_fasilitas = kf.id_fasilitas
        GROUP BY f.id_fasilitas
        ORDER BY f.jenis DESC, f.nama_fasilitas ASC
    ");
    $daftarFasilitas = $stmt->fetchAll();

    $totalFasilitas = count($daftarFasilitas);
    $fasilitasKamar = count(array_filter($daftarFasilitas, fn($f) => $f['jenis'] === 'kamar'));
    $fasilitasUmum  = count(array_filter($daftarFasilitas, fn($f) => $f['jenis'] === 'umum'));} catch (PDOException $e) {
    $daftarFasilitas = [];
    $totalFasilitas = $fasilitasKamar = $fasilitasUmum = 0;}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Fasilitas - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.badge-kamar{background:#dbeafe;color:#1e40af}
.badge-umum{background:#e0e7ff;color:#4338ca}
@media(max-width:768px){
    .pay-grid{grid-template-columns:1fr !important}
}
</style>
</head>
<body>
<div class="layout">

<!-- Sidebar -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebarPanel">
    <div class="sb-brand">
        <div class="sb-ico"><i class="ph ph-house-line"></i></div>
        <div>
            <div class="sb-name">Kost Putri Masira</div>
            <span class="sb-sub">Panel Pengelola</span>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-label">Utama</div>
        <a href="../dashboard.php" class="sb-link"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="../kamar/index.php" class="sb-link"><i class="ph ph-door"></i> Kelola Kamar</a>
        <a href="index.php" class="sb-link active"><i class="ph ph-sparkle"></i> Kelola Fasilitas</a>
        <a href="../penghuni/index.php" class="sb-link"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
        <a href="../sewa/index.php" class="sb-link"><i class="ph ph-key"></i> Kelola Sewa</a>
        <a href="../pembayaran/index.php" class="sb-link"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        
        <a href="../laporan/index.php" class="sb-link"><i class="ph ph-chart-bar"></i> Laporan</a>
        <div class="sb-label" style="margin-top:.5rem">Lainnya</div>
        <a href="../profil.php" class="sb-link"><i class="ph ph-user-circle"></i> Profil Pengelola</a>
        <a href="../../index.php" class="sb-link" target="_blank" rel="noopener"><i class="ph ph-globe"></i> Landing Page</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-avatar"><i class="ph ph-user"></i></div>
            <div>
                <div class="sb-uname"><?= e($user['nama'] ?? 'Pengelola') ?></div>
                <div class="sb-urole">Pengelola</div>
            </div>
        </div>
        <a href="../../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<!-- Main Content -->
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Pengelolaan Fasilitas</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <h1 class="pg-title">Daftar Fasilitas</h1>
                <p class="pg-sub">Kelola fasilitas kamar maupun fasilitas umum bersama untuk kenyamanan penghuni.</p>
            </div>
            <a href="tambah.php" class="btn-add">
                <i class="ph ph-plus-circle"></i> Tambah Fasilitas
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-p"><i class="ph ph-sparkle"></i></div>
                <div>
                    <div class="sc-label">Total Fasilitas</div>
                    <div class="sc-val"><?= $totalFasilitas ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-bed"></i></div>
                <div>
                    <div class="sc-label">Fasilitas Dalam Kamar</div>
                    <div class="sc-val"><?= $fasilitasKamar ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-users-three"></i></div>
                <div>
                    <div class="sc-label">Fasilitas Umum / Bersama</div>
                    <div class="sc-val"><?= $fasilitasUmum ?></div>
                </div>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-list-dashes"></i> Semua Fasilitas (<?= count($daftarFasilitas) ?> Item)
                </div>
            </div>

            <?php if (empty($daftarFasilitas)): ?>
            <div class="empty">
                <i class="ph ph-sparkle"></i>
                <p>Belum ada data fasilitas terdaftar.</p>
                <a href="tambah.php" class="btn-add" style="margin-top:.85rem">Tambah Fasilitas Sekarang</a>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:70px">No</th>
                            <th>Nama Fasilitas</th>
                            <th>Jenis Fasilitas</th>
                            <th>Keterangan</th>
                            <th style="text-align:center">Digunakan di Kamar</th>
                            <th style="width:140px;text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarFasilitas as $i => $f): ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.8rem"><?= $i + 1 ?></td>
                            <td>
                                <strong style="font-size:.95rem;color:var(--green)">
                                    <?= e($f['nama_fasilitas']) ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($f['jenis'] === 'kamar'): ?>
                                <span class="badge badge-kamar">
                                    <i class="ph ph-bed"></i> Fasilitas Kamar
                                </span>
                                <?php else: ?>
                                <span class="badge badge-umum">
                                    <i class="ph ph-users-three"></i> Fasilitas Umum
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--muted);font-size:.84rem">
                                <?= !empty($f['keterangan']) ? e($f['keterangan']) : '—' ?>
                            </td>
                            <td style="text-align:center">
                                <span style="font-weight:600;color:var(--green)">
                                    <?= (int)$f['total_kamar'] ?>
                                </span> <span style="font-size:.8rem;color:var(--muted)">Unit</span>
                            </td>
                            <td style="text-align:center;white-space:nowrap">
                                <a href="edit.php?id=<?= $f['id_fasilitas'] ?>" class="btn-act btn-edit" title="Edit Fasilitas">
                                    <i class="ph ph-pencil-simple"></i> Edit
                                </a>
                                <a href="hapus.php?id=<?= $f['id_fasilitas'] ?>" class="btn-act btn-del" title="Hapus Fasilitas">
                                    <i class="ph ph-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

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