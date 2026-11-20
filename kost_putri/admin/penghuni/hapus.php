<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$id_penghuni = (int)($_GET['id'] ?? $_POST['id_penghuni'] ?? 0);

if ($id_penghuni <= 0) {
    setFlash('danger', 'ID Penghuni tidak valid.');
    redirect('index.php');}

// Ambil data penghuni
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama, u.username
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

    // Cek keterikatan foreign key
    $stmtSewa = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_penghuni = :id");
    $stmtSewa->execute(['id' => $id_penghuni]);
    $sewaCount = (int)$stmtSewa->fetchColumn();

    // Catatan: tabel 'keluhan' tidak ada di skema database, jadi tidak dicek.
    $keluhanCount = 0;
    $hasRelations = ($sewaCount > 0 || $keluhanCount > 0);} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}

// POST Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Sesi form telah kedaluwarsa. Silakan ulangi kembali.');
        redirect('index.php');}

    $action = $_POST['action'] ?? 'hapus';

    if ($action === 'toggle_status') {
        // Toggle keaktifan akun
        $newStatus = ($penghuni['status'] === 'aktif') ? 'nonaktif' : 'aktif';
        if ($newStatus === 'nonaktif') {
            $stAk = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_penghuni = :id AND status = 'aktif'");
            $stAk->execute(['id' => $id_penghuni]);
            if ((int)$stAk->fetchColumn() > 0) {
                setFlash('danger', "Penghuni '" . e($penghuni['nama']) . "' masih memiliki sewa kamar aktif. Selesaikan sewanya dulu sebelum menonaktifkan akun.");
                redirect('index.php');
            }
        }
        try {
            $stmtToggle = $pdo->prepare("UPDATE penghuni SET status = :st WHERE id_penghuni = :id");
            $stmtToggle->execute(['st' => $newStatus, 'id' => $id_penghuni]);

            $labelStatus = $newStatus === 'aktif' ? 'diaktifkan kembali' : 'dinonaktifkan';
            setFlash('success', "Status penghuni '" . e($penghuni['nama']) . "' berhasil $labelStatus.");
            redirect('index.php');} catch (PDOException $e) {
            setFlash('danger', 'Gagal memperbarui status: ' . $e->getMessage());
            redirect('index.php');}
    } elseif ($action === 'hapus') {
        if ($hasRelations) {
            setFlash('danger', "Penghuni '" . e($penghuni['nama']) . "' tidak dapat dihapus permanen karena memiliki riwayat sewa ($sewaCount) atau riwayat keluhan ($keluhanCount). Silakan nonaktifkan akunnya.");
            redirect('index.php');}

        try {
            $pdo->beginTransaction();

            // Hapus penghuni
            $stmtDelP = $pdo->prepare("DELETE FROM penghuni WHERE id_penghuni = :id");
            $stmtDelP->execute(['id' => $id_penghuni]);

            // Hapus user
            $stmtDelU = $pdo->prepare("DELETE FROM users WHERE id_user = :uid");
            $stmtDelU->execute(['uid' => $penghuni['id_user']]);

            $pdo->commit();

            setFlash('success', "Penghuni '" . e($penghuni['nama']) . "' dan akun loginnnya berhasil dihapus secara permanen.");
            redirect('index.php');} catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();}
            setFlash('danger', 'Gagal menghapus penghuni: ' . $e->getMessage());
            redirect('index.php');}
    }
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola / Hapus Penghuni - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.confirm-ico{width:54px;height:54px;background:#fee2e2;color:#dc2626;border-radius:50%;display:grid;place-items:center;font-size:1.75rem;margin-bottom:1.25rem}
.confirm-title{font-size:1.25rem;font-weight:700;color:#1a1a18;margin-bottom:.5rem}
.confirm-desc{font-size:.875rem;color:var(--muted);margin-bottom:1.5rem;line-height:1.6}
.info-row{display:flex;justify-content:space-between;padding:.35rem 0;font-size:.875rem}
.info-row:not(:last-child){border-bottom:1px solid #f0efec}
.info-label{color:var(--muted)}
.info-val{font-weight:600;color:#1a1a18}
.btn-toggle{display:inline-flex;align-items:center;gap:.4rem;padding:.65rem 1.3rem;background:#d97706;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s}
.btn-toggle:hover{background:#b45309}
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
        <div class="topbar-title">Kelola / Hapus Penghuni</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-narrow">
        <div class="card-confirm">
            <div class="confirm-ico">
                <i class="ph ph-user-minus"></i>
            </div>

            <h1 class="confirm-title">Kelola Akun: <?= e($penghuni['nama']) ?></h1>
            <p class="confirm-desc">
                Anda dapat menonaktifkan akun penghuni ini agar tidak bisa masuk ke portal, atau menghapusnya jika belum memiliki riwayat sewa atau transaksi.
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Nama Penghuni:</span>
                    <span class="info-val"><?= e($penghuni['nama']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Username Login:</span>
                    <span class="info-val"><code><?= e($penghuni['username']) ?></code></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Akun Saat Ini:</span>
                    <span class="info-val"><?= ucfirst(e($penghuni['status'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Riwayat Sewa:</span>
                    <span class="info-val"><?= $sewaCount ?> Catatan</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Riwayat Keluhan:</span>
                    <span class="info-val"><?= $keluhanCount ?> Catatan</span>
                </div>
            </div>

            <?php if ($hasRelations): ?>
            <div class="warning-block">
                <i class="ph ph-warning" style="font-size:1.3rem;flex-shrink:0"></i>
                <div>
                    <strong>Penghuni Tidak Boleh Dihapus Permanen</strong><br>
                    Penghuni ini memiliki riwayat data sewa atau laporan keluhan. Demi integritas audit pembukuan dan histori, penghuni yang memiliki transaksi tidak boleh dihapus. Anda disarankan untuk <strong>menonaktifkan akun</strong> penghuni ini.
                </div>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <!-- Form Toggle Status (Nonaktifkan / Aktifkan) -->
                <form action="hapus.php" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="id_penghuni" value="<?= $id_penghuni ?>">
                    <input type="hidden" name="action" value="toggle_status">

                    <?php if ($penghuni['status'] === 'aktif'): ?>
                    <button type="submit" class="btn-toggle">
                        <i class="ph ph-prohibit"></i> Nonaktifkan Akun
                    </button>
                    <?php else: ?>
                    <button type="submit" class="btn-toggle" style="background:#2d6a4f">
                        <i class="ph ph-check-circle"></i> Aktifkan Kembali Akun
                    </button>
                    <?php endif; ?>
                </form>

                <!-- Form Hapus Permanen (Hanya bila tidak memiliki relasi) -->
                <?php if (!$hasRelations): ?>
                <form action="hapus.php" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="id_penghuni" value="<?= $id_penghuni ?>">
                    <input type="hidden" name="action" value="hapus">

                    <button type="submit" class="btn-del-confirm" onclick="return confirm('Apakah Anda yakin ingin menghapus data penghuni dan akun ini secara permanen?')">
                        <i class="ph ph-trash"></i> Hapus Permanen
                    </button>
                </form>
                <?php endif; ?>

                <a href="index.php" class="btn-cancel">
                    <i class="ph ph-arrow-left"></i> Batal &amp; Kembali
                </a>
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