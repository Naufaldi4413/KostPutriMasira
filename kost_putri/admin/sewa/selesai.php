<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

if (!function_exists('updateStatusKamar')) {
    function updateStatusKamar(PDO $pdo, int $id_kamar): string {
        $stmt = $pdo->prepare("SELECT kapasitas FROM kamar WHERE id_kamar = :id LIMIT 1");
        $stmt->execute(['id' => $id_kamar]);
        $kamar = $stmt->fetch();
        if (!$kamar) return 'kosong';
        $kapasitas = max(1, (int)$kamar['kapasitas']);
        $stmtSewa = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id AND status = 'aktif'");
        $stmtSewa->execute(['id' => $id_kamar]);
        $aktif = (int)$stmtSewa->fetchColumn();
        $status = ($aktif === 0) ? 'kosong' : (($aktif < $kapasitas) ? 'terisi' : 'penuh');
        $pdo->prepare("UPDATE kamar SET status = :status WHERE id_kamar = :id")->execute(['status' => $status, 'id' => $id_kamar]);
        return $status;}
}
if (!function_exists('getJumlahPenghuniAktifKamar')) {
    function getJumlahPenghuniAktifKamar(PDO $pdo, int $id_kamar): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id AND status = 'aktif'");
        $stmt->execute(['id' => $id_kamar]);
        return (int)$stmt->fetchColumn();}
}

$user = currentUser();
$id_sewa = (int)($_GET['id'] ?? $_POST['id_sewa'] ?? 0);

if ($id_sewa <= 0) {
    setFlash('danger', 'ID Sewa tidak valid.');
    redirect('index.php');}

// Ambil data sewa
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.nama AS nama_penghuni, u.username, k.nomor_kamar, k.tipe_kamar, k.kapasitas
        FROM sewa s
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_sewa = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id_sewa]);
    $sewa = $stmt->fetch();

    if (!$sewa) {
        setFlash('danger', 'Data transaksi sewa tidak ditemukan.');
        redirect('index.php');}

    if ($sewa['status'] === 'selesai') {
        setFlash('info', 'Transaksi sewa ini sudah berstatus selesai sebelumnya.');
        redirect('index.php');}

} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}

// Handle POST eksekusi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Sesi form telah kedaluwarsa. Silakan ulangi kembali.');
        redirect('index.php');}

    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? date('Y-m-d'));
    if (empty($tanggal_selesai)) {
        $tanggal_selesai = date('Y-m-d');}
    if (!isValidDate($tanggal_selesai)) {
        setFlash('danger', 'Format tanggal selesai tidak valid.');
        redirect("selesai.php?id=$id_sewa");}
    if (!empty($sewa['tanggal_mulai']) && $tanggal_selesai < $sewa['tanggal_mulai']) {
        setFlash('danger', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai sewa.');
        redirect("selesai.php?id=$id_sewa");}

    try {
        $pdo->beginTransaction();

        $stmtUp = $pdo->prepare("
            UPDATE sewa 
            SET status = 'selesai', 
                tanggal_selesai = :ts 
            WHERE id_sewa = :sid
        ");
        $stmtUp->execute([
            'ts'  => $tanggal_selesai,
            'sid' => $id_sewa,
        ]);

        // Perbarui status kamar otomatis berdasarkan sewa aktif yang tersisa
        updateStatusKamar($pdo, (int)$sewa['id_kamar']);

        $pdo->commit();

        setFlash('success', "Masa sewa Kamar " . e($sewa['nomor_kamar']) . " untuk " . e($sewa['nama_penghuni']) . " telah diselesaikan. Status kapasitas kamar berhasil diperbarui.");
        redirect('index.php');} catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();}
        setFlash('danger', 'Gagal menyelesaikan transaksi sewa: ' . $e->getMessage());
        redirect('index.php');}
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Selesaikan Sewa Kamar <?= e($sewa['nomor_kamar']) ?> - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.confirm-ico{width:54px;height:54px;background:#fef3c7;color:#d97706;border-radius:50%;display:grid;place-items:center;font-size:1.75rem;margin-bottom:1.25rem}
.confirm-title{font-size:1.25rem;font-weight:700;color:#1a1a18;margin-bottom:.5rem}
.confirm-desc{font-size:.875rem;color:var(--muted);margin-bottom:1.5rem;line-height:1.6}
.info-row{display:flex;justify-content:space-between;padding:.35rem 0;font-size:.875rem}
.info-row:not(:last-child){border-bottom:1px solid #f0efec}
.info-label{color:var(--muted)}
.info-val{font-weight:600;color:#1a1a18}
.btn-selesai-confirm{display:inline-flex;align-items:center;gap:.4rem;padding:.65rem 1.4rem;background:#d97706;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s}
.btn-selesai-confirm:hover{background:#b45309}
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
        <div class="topbar-title">Konfirmasi Selesai Sewa</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-narrow">
        <div class="card-confirm">
            <div class="confirm-ico">
                <i class="ph ph-calendar-check"></i>
            </div>

            <h1 class="confirm-title">Selesaikan Masa Sewa Kamar <?= e($sewa['nomor_kamar']) ?>?</h1>
            <p class="confirm-desc">
                Apakah masa sewa penghuni ini sudah berakhir atau penghuni telah check-out? Tindakan ini akan menandai status sewa menjadi <strong>Selesai</strong> dan memperbarui status kapasitas kamar secara otomatis.
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Penghuni:</span>
                    <span class="info-val"><?= e($sewa['nama_penghuni']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kamar yang Ditempati:</span>
                    <span class="info-val">Kamar <?= e($sewa['nomor_kamar']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal Mulai Sewa:</span>
                    <span class="info-val"><?= formatTanggal($sewa['tanggal_mulai']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Biaya Sewa:</span>
                    <span class="info-val"><?= formatRupiah($sewa['total_sewa']) ?></span>
                </div>
            </div>

            <form action="selesai.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="id_sewa" value="<?= $id_sewa ?>">

                <div class="form-group">
                    <label class="form-label" for="tanggal_selesai">
                        Tanggal Berakhir / Check-Out
                    </label>
                    <input
                        type="date"
                        id="tanggal_selesai"
                        name="tanggal_selesai"
                        class="form-control"
                        value="<?= !empty($sewa['tanggal_selesai']) ? e($sewa['tanggal_selesai']) : date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-selesai-confirm">
                        <i class="ph ph-check-circle"></i> Ya, Tandai Selesai
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