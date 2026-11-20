<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$error = '';

$id_fasilitas = (int)($_GET['id'] ?? $_POST['id_fasilitas'] ?? 0);

if ($id_fasilitas <= 0) {
    setFlash('danger', 'ID Fasilitas tidak valid.');
    redirect('index.php');}

// Ambil data fasilitas
try {
    $stmt = $pdo->prepare("SELECT * FROM fasilitas WHERE id_fasilitas = :id LIMIT 1");
    $stmt->execute(['id' => $id_fasilitas]);
    $fasilitas = $stmt->fetch();

    if (!$fasilitas) {
        setFlash('danger', 'Data fasilitas tidak ditemukan.');
        redirect('index.php');}
} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}

$nama_fasilitas = $fasilitas['nama_fasilitas'];
$jenis          = $fasilitas['jenis'];
$keterangan     = $fasilitas['keterangan'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $nama_fasilitas = trim($_POST['nama_fasilitas'] ?? '');
        $jenis          = in_array($_POST['jenis'] ?? '', ['kamar', 'umum']) ? $_POST['jenis'] : 'kamar';
        $keterangan     = trim($_POST['keterangan'] ?? '');

        if (empty($nama_fasilitas)) {
            $error = 'Nama fasilitas wajib diisi.';} else {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE fasilitas 
                    SET nama_fasilitas = :nama,
                        jenis          = :jenis,
                        keterangan     = :keterangan
                    WHERE id_fasilitas = :id
                ");
                $stmtUpdate->execute([
                    'nama'       => $nama_fasilitas,
                    'jenis'      => $jenis,
                    'keterangan' => $keterangan ?: null,
                    'id'         => $id_fasilitas,
                ]);

                setFlash('success', "Fasilitas '$nama_fasilitas' berhasil diperbarui.");
                redirect('index.php');} catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();}
        }
    }
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Fasilitas <?= e($fasilitas['nama_fasilitas']) ?> - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.radio-group{display:flex;gap:1.5rem;margin-top:.35rem}
.radio-label{display:flex;align-items:center;gap:.45rem;font-size:.875rem;cursor:pointer}
.radio-label input[type="radio"]{accent-color:var(--green);width:16px;height:16px}
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
        <div class="topbar-title">Edit Fasilitas <?= e($fasilitas['nama_fasilitas']) ?></div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Edit Fasilitas <?= e($fasilitas['nama_fasilitas']) ?></h1>
            <p class="pg-sub">Perbarui nama, jenis penempatan, atau keterangan dari fasilitas ini.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="ph ph-warning" style="font-size:1.2rem;flex-shrink:0"></i>
            <div><?= e($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="card-form">
            <form action="edit.php?id=<?= $id_fasilitas ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="id_fasilitas" value="<?= $id_fasilitas ?>">

                <div class="form-group">
                    <label class="form-label" for="nama_fasilitas">
                        Nama Fasilitas <span class="req">*</span>
                    </label>
                    <input
                        type="text"
                        id="nama_fasilitas"
                        name="nama_fasilitas"
                        class="form-control"
                        placeholder="Contoh: AC, WiFi, Lemari"
                        value="<?= e($nama_fasilitas) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Kategori / Jenis Fasilitas <span class="req">*</span>
                    </label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input
                                type="radio"
                                name="jenis"
                                value="kamar"
                                <?= $jenis === 'kamar' ? 'checked' : '' ?>
                            >
                            <span><strong>Fasilitas Kamar</strong> (dalam kamar tidur)</span>
                        </label>
                        <label class="radio-label">
                            <input
                                type="radio"
                                name="jenis"
                                value="umum"
                                <?= $jenis === 'umum' ? 'checked' : '' ?>
                            >
                            <span><strong>Fasilitas Umum</strong> (area bersama)</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="keterangan">Keterangan Fasilitas</label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        class="form-control"
                        rows="3"
                        placeholder="Penjelasan ringkas mengenai fasilitas ini..."
                    ><?= e($keterangan) ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Perbarui Fasilitas
                    </button>
                    <a href="index.php" class="btn-cancel">
                        <i class="ph ph-x"></i> Batal
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