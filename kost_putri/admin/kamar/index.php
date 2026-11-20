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
            k.*,
            GROUP_CONCAT(f.nama_fasilitas ORDER BY f.nama_fasilitas SEPARATOR '||') AS fasilitas_list,
            (SELECT COUNT(*) FROM sewa s WHERE s.id_kamar = k.id_kamar AND s.status = 'aktif') AS sewa_aktif
        FROM kamar k
        LEFT JOIN kamar_fasilitas kf ON k.id_kamar = kf.id_kamar
        LEFT JOIN fasilitas f ON kf.id_fasilitas = f.id_fasilitas
        GROUP BY k.id_kamar
        ORDER BY k.nomor_kamar ASC
    ");
    $daftarKamar = $stmt->fetchAll();

    $totalKamar  = count($daftarKamar);
    $kamarKosong = count(array_filter($daftarKamar, fn($k) => $k['status'] === 'kosong'));
    $kamarTerisi = $totalKamar - $kamarKosong;} catch (PDOException $e) {
    $daftarKamar = [];
    $totalKamar = $kamarKosong = $kamarTerisi = 0;}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kamar - Kost Putri Masira</title>
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
        <a href="index.php" class="sb-link active"><i class="ph ph-door"></i> Kelola Kamar</a>
        <a href="../fasilitas/index.php" class="sb-link"><i class="ph ph-sparkle"></i> Kelola Fasilitas</a>
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
        <div class="topbar-title">Pengelolaan Kamar</div>
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
                <h1 class="pg-title">Daftar Kamar</h1>
                <p class="pg-sub">Kelola unit kamar, tipe, tarif sewa, dan fasilitas yang tersedia.</p>
            </div>
            <a href="tambah.php" class="btn-add">
                <i class="ph ph-plus-circle"></i> Tambah Kamar
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-door"></i></div>
                <div>
                    <div class="sc-label">Total Unit Kamar</div>
                    <div class="sc-val"><?= $totalKamar ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div>
                <div>
                    <div class="sc-label">Kamar Kosong (Tersedia)</div>
                    <div class="sc-val"><?= $kamarKosong ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-lock"></i></div>
                <div>
                    <div class="sc-label">Kamar Terisi (Dihuni)</div>
                    <div class="sc-val"><?= $kamarTerisi ?></div>
                </div>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-list-dashes"></i> Semua Data Kamar (<?= count($daftarKamar) ?> Unit)
                </div>
            </div>

            <?php if (empty($daftarKamar)): ?>
            <div class="empty">
                <i class="ph ph-door-open"></i>
                <p>Belum ada data kamar terdaftar.</p>
                <a href="tambah.php" class="btn-add" style="margin-top:.85rem">Tambah Kamar Sekarang</a>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:70px">No</th>
                            <th>No. Kamar</th>
                            <th>Tipe</th>
                            <th>Harga / Bulan</th>
                            <th>Status</th>
                            <th>Fasilitas Kamar</th>
                            <th>Keterangan</th>
                            <th style="width:140px;text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarKamar as $i => $k): 
                            $fasList = !empty($k['fasilitas_list']) ? explode('||', $k['fasilitas_list']) : [];
                        ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.8rem"><?= $i + 1 ?></td>
                            <td>
                                <strong style="font-size:.95rem;color:var(--green)">Kamar <?= e($k['nomor_kamar']) ?></strong>
                            </td>
                            <td><?= e($k['tipe_kamar'] ?: 'Standar') ?><div style='font-size:.73rem;color:var(--muted);margin-top:.2rem'>Kap: <strong><?= (int)$k['kapasitas'] ?></strong> Org (Terisi: <?= (int)$k['sewa_aktif'] ?>)</div></td>
                            <td style="white-space:nowrap;font-weight:600"><?= formatRupiah($k['harga']) ?></td>
                            <td>
                                <?php if ($k['status'] === 'penuh'): ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b"><i class="ph ph-prohibit"></i> Penuh</span>
                                <?php elseif ($k['status'] === 'terisi'): ?>
                                <span class="badge bg-a"><i class="ph ph-users"></i> Terisi</span>
                                <?php else: ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($fasList)): ?>
                                <div class="fasilitas-pills">
                                    <?php foreach ($fasList as $f): ?>
                                    <span class="f-pill"><i class="ph ph-check"></i> <?= e($f) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span style="color:var(--muted);font-size:.8rem">Belum diatur</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.825rem;color:var(--muted);max-width:200px">
                                <?= !empty($k['keterangan']) ? e($k['keterangan']) : '—' ?>
                            </td>
                            <td style="text-align:center;white-space:nowrap">
                                <a href="edit.php?id=<?= $k['id_kamar'] ?>" class="btn-act btn-edit" title="Edit Kamar">
                                    <i class="ph ph-pencil-simple"></i> Edit
                                </a>
                                <a href="hapus.php?id=<?= $k['id_kamar'] ?>" class="btn-act btn-del" title="Hapus Kamar">
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