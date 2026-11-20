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
            p.*,
            u.nama, u.username, u.created_at AS user_created_at,
            (
                SELECT k.nomor_kamar 
                FROM sewa s 
                JOIN kamar k ON s.id_kamar = k.id_kamar 
                WHERE s.id_penghuni = p.id_penghuni AND s.status = 'aktif' 
                LIMIT 1
            ) AS nomor_kamar_aktif,
            (
                SELECT COUNT(*) 
                FROM sewa s 
                WHERE s.id_penghuni = p.id_penghuni
            ) AS total_riwayat_sewa
        FROM penghuni p
        JOIN users u ON p.id_user = u.id_user
        ORDER BY p.id_penghuni DESC
    ");
    $daftarPenghuni = $stmt->fetchAll();

    $totalPenghuni   = count($daftarPenghuni);
    $penghuniAktif   = count(array_filter($daftarPenghuni, fn($p) => $p['status'] === 'aktif'));
    $penghuniNonaktif= $totalPenghuni - $penghuniAktif;
    $sedangHuni      = count(array_filter($daftarPenghuni, fn($p) => !empty($p['nomor_kamar_aktif'])));} catch (PDOException $e) {
    $daftarPenghuni = [];
    $totalPenghuni = $penghuniAktif = $penghuniNonaktif = $sedangHuni = 0;}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Penghuni - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
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
        <a href="../fasilitas/index.php" class="sb-link"><i class="ph ph-sparkle"></i> Kelola Fasilitas</a>
        <a href="index.php" class="sb-link active"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
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
        <div class="topbar-title">Pengelolaan Penghuni</div>
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
                <h1 class="pg-title">Daftar Penghuni</h1>
                <p class="pg-sub">Kelola biodata penghuni kost, akun login, dan status keaktifan.</p>
            </div>
            <a href="tambah.php" class="btn-add">
                <i class="ph ph-user-plus"></i> Tambah Penghuni
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-p"><i class="ph ph-users-three"></i></div>
                <div>
                    <div class="sc-label">Total Penghuni Terdaftar</div>
                    <div class="sc-val"><?= $totalPenghuni ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-user-check"></i></div>
                <div>
                    <div class="sc-label">Penghuni Berstatus Aktif</div>
                    <div class="sc-val"><?= $penghuniAktif ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-door"></i></div>
                <div>
                    <div class="sc-label">Sedang Menempati Kamar</div>
                    <div class="sc-val"><?= $sedangHuni ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-user-minus"></i></div>
                <div>
                    <div class="sc-label">Penghuni Nonaktif</div>
                    <div class="sc-val"><?= $penghuniNonaktif ?></div>
                </div>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-list-dashes"></i> Semua Penghuni (<?= count($daftarPenghuni) ?> Orang)
                </div>
            </div>

            <?php if (empty($daftarPenghuni)): ?>
            <div class="empty">
                <i class="ph ph-users"></i>
                <p>Belum ada data penghuni yang didaftarkan.</p>
                <a href="tambah.php" class="btn-add" style="margin-top:.85rem">Tambah Penghuni Baru</a>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px">No</th>
                            <th>Nama Penghuni</th>
                            <th>Username</th>
                            <th>No. WhatsApp / HP</th>
                            <th>NIK</th>
                            <th>Kamar Aktif</th>
                            <th>Tgl Masuk</th>
                            <th>Status Akun</th>
                            <th style="width:190px;text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarPenghuni as $i => $p): ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.8rem"><?= $i + 1 ?></td>
                            <td>
                                <strong style="font-size:.95rem;color:var(--green)">
                                    <?= e($p['nama']) ?>
                                </strong>
                            </td>
                            <td>
                                <code style="font-size:.825rem;background:#f1f3f5;padding:.2rem .4rem;border-radius:4px"><?= e($p['username']) ?></code>
                            </td>
                            <td>
                                <?php if (!empty($p['no_hp'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $p['no_hp'])) ?>" target="_blank" rel="noopener" style="color:var(--green);text-decoration:none;display:inline-flex;align-items:center;gap:.25rem">
                                    <i class="ph ph-whatsapp-logo"></i> <?= e($p['no_hp']) ?>
                                </a>
                                <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.825rem;color:var(--muted)">
                                <?= !empty($p['nik']) ? e($p['nik']) : '—' ?>
                            </td>
                            <td>
                                <?php if (!empty($p['nomor_kamar_aktif'])): ?>
                                <span class="badge bg-room">
                                    <i class="ph ph-door"></i> Kamar <?= e($p['nomor_kamar_aktif']) ?>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-none">Belum sewa</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.825rem;color:var(--muted);white-space:nowrap">
                                <?= formatTanggal($p['tanggal_masuk'], 'pendek') ?>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'aktif'): ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Aktif</span>
                                <?php else: ?>
                                <span class="badge bg-a"><i class="ph ph-x"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;white-space:nowrap">
                                <a href="detail.php?id=<?= $p['id_penghuni'] ?>" class="btn-act btn-view" title="Lihat Detail">
                                    <i class="ph ph-eye"></i> Detail
                                </a>
                                <a href="edit.php?id=<?= $p['id_penghuni'] ?>" class="btn-act btn-edit" title="Edit Biodata">
                                    <i class="ph ph-pencil-simple"></i> Edit
                                </a>
                                <a href="hapus.php?id=<?= $p['id_penghuni'] ?>" class="btn-act btn-del" title="Kelola / Hapus">
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