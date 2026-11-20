<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

$id_penghuni = (int)($_GET['id'] ?? 0);

if ($id_penghuni <= 0) {
    setFlash('danger', 'ID Penghuni tidak valid.');
    redirect('index.php');}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama, u.username, u.role, u.created_at AS akun_created_at
        FROM penghuni p
        JOIN users u ON p.id_user = u.id_user
        WHERE p.id_penghuni = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id_penghuni]);
    $penghuni = $stmt->fetch();

    if (!$penghuni) {
        setFlash('danger', 'Data penghuni tidak ditemukan.');
        redirect('index.php');}

    // Riwayat sewa penghuni
    $stmtSewa = $pdo->prepare("
        SELECT s.*, k.nomor_kamar, k.tipe_kamar, k.harga AS harga_kamar
        FROM sewa s
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id
        ORDER BY s.status ASC, s.tanggal_mulai DESC
    ");
    $stmtSewa->execute(['id' => $id_penghuni]);
    $riwayatSewa = $stmtSewa->fetchAll();

    $sewaAktif = array_filter($riwayatSewa, fn($s) => $s['status'] === 'aktif');
    $kamarAktif = !empty($sewaAktif) ? reset($sewaAktif) : null;} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Penghuni: <?= e($penghuni['nama']) ?> - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.detail-grid{display:grid;grid-template-columns:360px 1fr;gap:1.5rem;margin-bottom:1.75rem}
@media(max-width:960px){
    .detail-grid{grid-template-columns:1fr}
}
.profile-avatar-wrap{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid #f0efec}
.p-avatar{width:56px;height:56px;background:var(--green);color:#fff;border-radius:50%;display:grid;place-items:center;font-size:1.6rem}
.p-name{font-size:1.15rem;font-weight:700;color:#1a1a18}
.p-role{font-size:.75rem;color:var(--muted)}
.info-list{display:flex;flex-direction:column;gap:.75rem}
.info-item{display:flex;flex-direction:column;gap:.15rem}
.info-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.info-val{font-size:.9rem;font-weight:600;color:#1a1a18}
.empty-sub{text-align:center;padding:2.5rem 1rem;color:var(--muted);font-size:.875rem}
.empty-sub i{font-size:2rem;display:block;margin-bottom:.5rem;opacity:.35}
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
        <div class="topbar-title">Profil &amp; Riwayat Penghuni</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-detail">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <h1 class="pg-title"><?= e($penghuni['nama']) ?></h1>
                <p class="pg-sub">Informasi lengkap akun, biodata identitas, dan riwayat penyewaan kamar.</p>
            </div>
            <div class="btn-header-group">
                <a href="edit.php?id=<?= $id_penghuni ?>" class="btn-act btn-edit">
                    <i class="ph ph-pencil-simple"></i> Edit Biodata
                </a>
                <?php if (empty($kamarAktif)): ?>
                <a href="../sewa/tambah.php?id_penghuni=<?= $id_penghuni ?>" class="btn-act btn-new-sewa">
                    <i class="ph ph-key"></i> Buat Sewa Kamar
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn-act btn-back">
                    <i class="ph ph-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <div class="detail-grid">
            <!-- Left: Profil Card -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">
                        <i class="ph ph-user"></i> Profil Penghuni
                    </div>
                    <?php if ($penghuni['status'] === 'aktif'): ?>
                    <span class="badge bg-g"><i class="ph ph-check"></i> Akun Aktif</span>
                    <?php else: ?>
                    <span class="badge bg-a"><i class="ph ph-x"></i> Nonaktif</span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <div class="profile-avatar-wrap">
                        <div class="p-avatar">
                            <i class="ph ph-user"></i>
                        </div>
                        <div>
                            <div class="p-name"><?= e($penghuni['nama']) ?></div>
                            <div class="p-role">Penghuni Kost Putri Masira</div>
                        </div>
                    </div>

                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">Kamar Saat Ini</span>
                            <div class="info-val">
                                <?php if ($kamarAktif): ?>
                                <span class="badge bg-room" style="font-size:.85rem;padding:.3rem .7rem">
                                    <i class="ph ph-door"></i> Kamar <?= e($kamarAktif['nomor_kamar']) ?> (<?= e($kamarAktif['tipe_kamar'] ?: 'Standar') ?>)
                                </span>
                                <?php else: ?>
                                <span class="badge bg-gray">Belum ada sewa aktif</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Username Login</span>
                            <div class="info-val">
                                <code style="font-size:.9rem;background:#f1f3f5;padding:.2rem .4rem;border-radius:4px"><?= e($penghuni['username']) ?></code>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Nomor WhatsApp / HP</span>
                            <div class="info-val">
                                <?php if (!empty($penghuni['no_hp'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $penghuni['no_hp'])) ?>" target="_blank" rel="noopener" style="color:var(--green);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem">
                                    <i class="ph ph-whatsapp-logo" style="font-size:1.1rem"></i> <?= e($penghuni['no_hp']) ?>
                                </a>
                                <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Nomor Induk Kependudukan (NIK)</span>
                            <div class="info-val"><?= !empty($penghuni['nik']) ? e($penghuni['nik']) : '—' ?></div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Tanggal Masuk Kost</span>
                            <div class="info-val"><?= formatTanggal($penghuni['tanggal_masuk']) ?></div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Alamat Asal</span>
                            <div class="info-val" style="font-weight:400;color:var(--muted);line-height:1.4">
                                <?= !empty($penghuni['alamat']) ? nl2br(e($penghuni['alamat'])) : '—' ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Terdaftar Sejak</span>
                            <div class="info-val" style="font-weight:400;color:var(--muted);font-size:.8rem">
                                <?= formatTanggal($penghuni['akun_created_at']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Riwayat Sewa Table -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">
                        <i class="ph ph-clock-counter-clockwise"></i> Riwayat Sewa Kamar (<?= count($riwayatSewa) ?>)
                    </div>
                </div>

                <?php if (empty($riwayatSewa)): ?>
                <div class="empty-sub">
                    <i class="ph ph-calendar-blank"></i>
                    <p>Penghuni ini belum memiliki catatan riwayat sewa.</p>
                    <a href="../sewa/tambah.php?id_penghuni=<?= $id_penghuni ?>" class="btn-act btn-new-sewa" style="margin-top:.85rem">
                        <i class="ph ph-plus-circle"></i> Buat Sewa Sekarang
                    </a>
                </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Kamar</th>
                                <th>Periode</th>
                                <th>Masa Sewa</th>
                                <th>Total Biaya</th>
                                <th>Status</th>
                                <th style="text-align:center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayatSewa as $s): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--green)">Kamar <?= e($s['nomor_kamar']) ?></strong>
                                    <div style="font-size:.75rem;color:var(--muted)"><?= e($s['tipe_kamar'] ?: 'Standar') ?></div>
                                </td>
                                <td><?= e($s['periode'] ?: '—') ?></td>
                                <td style="font-size:.825rem;white-space:nowrap">
                                    <?= formatTanggal($s['tanggal_mulai'], 'pendek') ?> s/d <br>
                                    <?= !empty($s['tanggal_selesai']) ? formatTanggal($s['tanggal_selesai'], 'pendek') : '<span style="color:var(--green)">Sekarang</span>' ?>
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
                                    <a href="../sewa/detail.php?id=<?= $s['id_sewa'] ?>" class="btn-act btn-edit" title="Detail Sewa">
                                        <i class="ph ph-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
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