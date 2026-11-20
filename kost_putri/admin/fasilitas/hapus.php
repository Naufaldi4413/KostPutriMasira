<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$id_fasilitas = (int)($_GET['id'] ?? $_POST['id_fasilitas'] ?? 0);

if ($id_fasilitas <= 0) {
    setFlash('danger', 'ID Fasilitas tidak valid.');
    redirect('index.php');}

// Default sebelum try agar tidak ada undefined variable warning
$totalKamarPakai = 0;
$kamarList       = '';

// Ambil data fasilitas
try {
    $stmt = $pdo->prepare("SELECT * FROM fasilitas WHERE id_fasilitas = :id LIMIT 1");
    $stmt->execute(['id' => $id_fasilitas]);
    $fasilitas = $stmt->fetch();

    if (!$fasilitas) {
        setFlash('danger', 'Data fasilitas tidak ditemukan.');
        redirect('index.php');}

    // Cek penggunaan pada kamar_fasilitas
    $stmtUsage = $pdo->prepare("
        SELECT COUNT(kf.id_kamar) AS total_kamar,
               GROUP_CONCAT(k.nomor_kamar ORDER BY k.nomor_kamar SEPARATOR ', ') AS daftar_nomor_kamar
        FROM kamar_fasilitas kf
        JOIN kamar k ON kf.id_kamar = k.id_kamar
        WHERE kf.id_fasilitas = :id
    ");
    $stmtUsage->execute(['id' => $id_fasilitas]);
    $usage = $stmtUsage->fetch();
    $totalKamarPakai = (int)($usage['total_kamar'] ?? 0);
    $kamarList       = $usage['daftar_nomor_kamar'] ?? '';} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}

// Eksekusi POST hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Sesi form telah kedaluwarsa. Silakan ulangi kembali.');
        redirect('index.php');}

    try {
        $pdo->beginTransaction();

        // Bersihkan relasi di kamar_fasilitas terlebih dahulu
        $stmtDelRel = $pdo->prepare("DELETE FROM kamar_fasilitas WHERE id_fasilitas = :id");
        $stmtDelRel->execute(['id' => $id_fasilitas]);

        // Hapus fasilitas dari tabel master fasilitas
        $stmtDelFas = $pdo->prepare("DELETE FROM fasilitas WHERE id_fasilitas = :id");
        $stmtDelFas->execute(['id' => $id_fasilitas]);

        $pdo->commit();

        setFlash('success', "Fasilitas '" . e($fasilitas['nama_fasilitas']) . "' berhasil dihapus dari sistem.");
        redirect('index.php');} catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();}
        setFlash('danger', "Gagal menghapus fasilitas: " . $e->getMessage());
        redirect('index.php');}
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hapus Fasilitas <?= e($fasilitas['nama_fasilitas']) ?> - Kost Putri Masira</title>
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
        <div class="topbar-title">Konfirmasi Hapus Fasilitas</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-narrow">
        <div class="card-confirm">
            <div class="confirm-ico">
                <i class="ph ph-trash"></i>
            </div>

            <h1 class="confirm-title">Hapus Fasilitas "<?= e($fasilitas['nama_fasilitas']) ?>"?</h1>
            <p class="confirm-desc">
                Apakah Anda yakin ingin menghapus data fasilitas ini?
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Nama Fasilitas:</span>
                    <span class="info-val"><?= e($fasilitas['nama_fasilitas']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kategori / Jenis:</span>
                    <span class="info-val"><?= $fasilitas['jenis'] === 'kamar' ? 'Fasilitas Kamar' : 'Fasilitas Umum' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Keterangan:</span>
                    <span class="info-val"><?= !empty($fasilitas['keterangan']) ? e($fasilitas['keterangan']) : '—' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Digunakan Pada:</span>
                    <span class="info-val"><?= $totalKamarPakai ?> Unit Kamar <?= !empty($kamarList) ? "($kamarList)" : '' ?></span>
                </div>
            </div>

            <?php if ($totalKamarPakai > 0): ?>
            <div class="warning-block">
                <i class="ph ph-warning" style="font-size:1.3rem;flex-shrink:0"></i>
                <div>
                    <strong>Pemberitahuan:</strong><br>
                    Fasilitas ini saat ini terhubung dengan <strong><?= $totalKamarPakai ?> unit kamar</strong> (<?= e($kamarList) ?>). Jika dihapus, fasilitas ini otomatis dilepaskan dari kamar-kamar tersebut.
                </div>
            </div>
            <?php endif; ?>

            <form action="hapus.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="id_fasilitas" value="<?= $id_fasilitas ?>">

                <div class="form-actions">
                    <button type="submit" class="btn-del-confirm">
                        <i class="ph ph-trash"></i> Ya, Hapus Fasilitas
                    </button>
                    <a href="index.php" class="btn-cancel">
                        <i class="ph ph-arrow-left"></i> Batal &amp; Kembali
                    </a>
                </div>
            </form>
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