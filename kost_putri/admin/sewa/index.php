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
            s.*,
            p.nik, p.no_hp,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar, k.kapasitas, k.status AS status_kamar,
            (
                SELECT COUNT(*) 
                FROM sewa sa 
                WHERE sa.id_kamar = s.id_kamar AND sa.status = 'aktif'
            ) AS penghuni_kamar_aktif
        FROM sewa s
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        ORDER BY (s.status = 'aktif') DESC, s.tanggal_mulai DESC
    ");
    $daftarSewa = $stmt->fetchAll();

    $totalSewa    = count($daftarSewa);
    $sewaAktif    = count(array_filter($daftarSewa, fn($s) => $s['status'] === 'aktif'));
    $sewaSelesai  = $totalSewa - $sewaAktif;
    $totalNominal = array_sum(array_map(fn($s) => ($s['status'] === 'aktif' ? (float)$s['total_sewa'] : 0), $daftarSewa));} catch (PDOException $e) {
    $daftarSewa = [];
    $totalSewa = $sewaAktif = $sewaSelesai = $totalNominal = 0;}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Sewa Kamar - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.btn-done{background:#fef3c7;color:#92400e}
.btn-done:hover{background:#fde68a}
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
        <a href="../penghuni/index.php" class="sb-link"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
        <a href="index.php" class="sb-link active"><i class="ph ph-key"></i> Kelola Sewa</a>
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
        <div class="topbar-title">Pengelolaan Sewa Kamar</div>
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
                <h1 class="pg-title">Daftar Transaksi Sewa</h1>
                <p class="pg-sub">Kelola penempatan kamar, masa sewa penghuni, tarif, dan kapasitas kamar.</p>
            </div>
            <a href="tambah.php" class="btn-add">
                <i class="ph ph-plus-circle"></i> Buat Sewa Baru
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-receipt"></i></div>
                <div>
                    <div class="sc-label">Total Transaksi Sewa</div>
                    <div class="sc-val"><?= $totalSewa ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div>
                <div>
                    <div class="sc-label">Sewa Aktif Berjalan</div>
                    <div class="sc-val"><?= $sewaAktif ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-clock-counter-clockwise"></i></div>
                <div>
                    <div class="sc-label">Sewa Selesai</div>
                    <div class="sc-val"><?= $sewaSelesai ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-r"><i class="ph ph-money"></i></div>
                <div>
                    <div class="sc-label">Nilai Sewa Aktif</div>
                    <div class="sc-val" style="font-size:1.15rem"><?= formatRupiah($totalNominal) ?></div>
                </div>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-list-dashes"></i> Semua Data Sewa (<?= count($daftarSewa) ?> Transaksi)
                </div>
            </div>

            <?php if (empty($daftarSewa)): ?>
            <div class="empty">
                <i class="ph ph-key"></i>
                <p>Belum ada data transaksi sewa kamar.</p>
                <a href="tambah.php" class="btn-add" style="margin-top:.85rem">Buat Sewa Baru Sekarang</a>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px">No</th>
                            <th>Nama Penghuni</th>
                            <th>Kamar &amp; Kapasitas</th>
                            <th>Periode Sewa</th>
                            <th>Masa Berlaku</th>
                            <th>Total Sewa</th>
                            <th>Status</th>
                            <th style="width:190px;text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarSewa as $i => $s): 
                            $kap = max(1, (int)$s['kapasitas']);
                            $isi = (int)$s['penghuni_kamar_aktif'];
                        ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.8rem"><?= $i + 1 ?></td>
                            <td>
                                <strong style="font-size:.95rem;color:var(--green)">
                                    <?= e($s['nama_penghuni']) ?>
                                </strong>
                                <div style="font-size:.75rem;color:var(--muted)"><code>@<?= e($s['username']) ?></code></div>
                            </td>
                            <td>
                                <span class="badge bg-room">
                                    <i class="ph ph-door"></i> Kamar <?= e($s['nomor_kamar']) ?>
                                </span>
                                <div style="font-size:.73rem;color:var(--muted);margin-top:.2rem">
                                    Tipe: <?= e($s['tipe_kamar'] ?: 'Standar') ?> &bull; 
                                    Terisi: <strong><?= $isi ?>/<?= $kap ?></strong> Org
                                </div>
                            </td>
                            <td style="font-size:.85rem">
                                <?= e($s['periode'] ?: '—') ?>
                            </td>
                            <td style="font-size:.825rem;white-space:nowrap">
                                <?= formatTanggal($s['tanggal_mulai'], 'pendek') ?> s/d<br>
                                <?= !empty($s['tanggal_selesai']) ? formatTanggal($s['tanggal_selesai'], 'pendek') : '<span style="color:var(--green);font-weight:600">Berjalan</span>' ?>
                            </td>
                            <td style="font-weight:600;white-space:nowrap">
                                <?= formatRupiah($s['total_sewa']) ?>
                            </td>
                            <td>
                                <?php if ($s['status'] === 'aktif'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Aktif</span>
                                <?php else: ?>
                                <span class="badge bg-gray"><i class="ph ph-check"></i> Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;white-space:nowrap">
                                <a href="detail.php?id=<?= $s['id_sewa'] ?>" class="btn-act btn-view" title="Detail Sewa">
                                    <i class="ph ph-eye"></i> Detail
                                </a>
                                <a href="edit.php?id=<?= $s['id_sewa'] ?>" class="btn-act btn-edit" title="Edit Sewa">
                                    <i class="ph ph-pencil-simple"></i> Edit
                                </a>
                                <?php if ($s['status'] === 'aktif'): ?>
                                <a href="selesai.php?id=<?= $s['id_sewa'] ?>" class="btn-act btn-done" title="Selesaikan Sewa">
                                    <i class="ph ph-check-circle"></i> Selesai
                                </a>
                                <?php endif; ?>
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