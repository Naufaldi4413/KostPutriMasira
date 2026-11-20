<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$error = '';

// Ambil semua daftar fasilitas dari DB
try {
    $fasilitasKamar = $pdo->query("SELECT * FROM fasilitas WHERE jenis = 'kamar' ORDER BY nama_fasilitas ASC")->fetchAll();
    $fasilitasUmum  = $pdo->query("SELECT * FROM fasilitas WHERE jenis = 'umum'  ORDER BY nama_fasilitas ASC")->fetchAll();} catch (PDOException $e) {
    $fasilitasKamar = $fasilitasUmum = [];}

$nomor_kamar = '';
$tipe_kamar  = 'Standar';
$harga       = '';
$kapasitas   = 1;
$status      = 'kosong';
$keterangan  = '';
$selectedFasilitas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $nomor_kamar = trim($_POST['nomor_kamar'] ?? '');
        $tipe_kamar  = trim($_POST['tipe_kamar'] ?? 'Standar');
        $harga_raw   = trim($_POST['harga'] ?? '');
        $kapasitas   = min(10, max(1, (int)($_POST['kapasitas'] ?? 1)));
        $status      = 'kosong'; // kamar baru belum punya sewa aktif; status selanjutnya diatur otomatis
        $keterangan  = trim($_POST['keterangan'] ?? '');
        $selectedFasilitas = isset($_POST['fasilitas']) && is_array($_POST['fasilitas']) ? array_map('intval', $_POST['fasilitas']) : [];

        // Validasi
        if (empty($nomor_kamar)) {
            $error = 'Nomor kamar wajib diisi.';} elseif (!is_numeric($harga_raw) || (float)$harga_raw <= 0) {
            $error = 'Harga kamar harus berupa angka lebih dari 0.';} else {
            $harga = (float)$harga_raw;

            try {
                // Cek nomor kamar unik
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM kamar WHERE nomor_kamar = :nomor");
                $stmtCheck->execute(['nomor' => $nomor_kamar]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $error = "Nomor kamar '$nomor_kamar' sudah terdaftar. Gunakan nomor lain.";} else {
                    $pdo->beginTransaction();

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO kamar (nomor_kamar, tipe_kamar, kapasitas, harga, status, keterangan)
                        VALUES (:nomor, :tipe, :kapasitas, :harga, :status, :keterangan)
                    ");
                    $stmtInsert->execute([
                        'nomor'      => $nomor_kamar,
                        'tipe'       => $tipe_kamar ?: 'Standar',
                        'kapasitas'  => $kapasitas,
                        'harga'      => $harga,
                        'status'     => $status,
                        'keterangan' => $keterangan ?: null,
                    ]);

                    $newKamarId = (int)$pdo->lastInsertId();

                    if (!empty($selectedFasilitas)) {
                        $stmtKf = $pdo->prepare("INSERT INTO kamar_fasilitas (id_kamar, id_fasilitas) VALUES (:id_kamar, :id_fasilitas)");
                        foreach ($selectedFasilitas as $idFas) {
                            if ($idFas > 0) {
                                $stmtKf->execute([
                                    'id_kamar'     => $newKamarId,
                                    'id_fasilitas' => $idFas,
                                ]);}
                        }
                    }

                    $pdo->commit();
                    setFlash('success', "Kamar $nomor_kamar berhasil ditambahkan.");
                    redirect('index.php');}
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();}
                $error = 'Terjadi kesalahan basis data: ' . $e->getMessage();}
        }
    }
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Kamar - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.section-divider{font-size:.85rem;font-weight:700;color:var(--green);margin:1.5rem 0 .75rem;padding-bottom:.4rem;border-bottom:1px solid #eee;display:flex;align-items:center;gap:.4rem}
.facility-category-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:1rem 0 .5rem}
.facility-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.6rem;margin-bottom:.75rem}
.facility-box{display:flex;align-items:center;gap:.6rem;background:#fafaf9;border:1px solid var(--border);border-radius:8px;padding:.55rem .75rem;cursor:pointer;transition:border-color .12s,background .12s}
.facility-box:hover{border-color:#b7e4c7;background:#f0faf4}
.facility-box input[type="checkbox"]{accent-color:var(--green);width:16px;height:16px;cursor:pointer}
.facility-info{line-height:1.2}
.facility-name{font-size:.84rem;font-weight:600;color:#1a1a18}
.facility-desc{font-size:.7rem;color:var(--muted)}
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
        <div class="topbar-title">Tambah Kamar Baru</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Form Tambah Kamar</h1>
            <p class="pg-sub">Masukkan informasi nomor kamar, tipe, tarif bulanan, dan pilihan fasilitas.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="ph ph-warning" style="font-size:1.2rem;flex-shrink:0"></i>
            <div><?= e($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="card-form">
            <form action="tambah.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="nomor_kamar">
                            Nomor Kamar <span class="req">*</span>
                        </label>
                        <input
                            type="text"
                            id="nomor_kamar"
                            name="nomor_kamar"
                            class="form-control"
                            placeholder="Contoh: A4, B1, 101"
                            value="<?= e($nomor_kamar) ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="tipe_kamar">Tipe Kamar</label>
                        <input
                            type="text"
                            id="tipe_kamar"
                            name="tipe_kamar"
                            class="form-control"
                            placeholder="Contoh: Standar, Eksklusif, VIP"
                            value="<?= e($tipe_kamar) ?>"
                        >
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="harga">
                            Harga Sewa / Bulan (Rp) <span class="req">*</span>
                        </label>
                        <input
                            type="number"
                            id="harga"
                            name="harga"
                            class="form-control"
                            placeholder="Contoh: 500000"
                            value="<?= e((string)$harga) ?>"
                            min="0"
                            step="1000"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kapasitas">
                            Kapasitas Maksimal (Orang) <span class="req">*</span>
                        </label>
                        <input
                            type="number"
                            id="kapasitas"
                            name="kapasitas"
                            class="form-control"
                            min="1"
                            max="10"
                            value="<?= (int)$kapasitas ?>"
                            required
                        >
                        <div class="help-text">Jumlah penghuni maksimal yang dapat menempati unit ini.</div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="status">
                            Status Awal Kamar <span class="req">*</span>
                        </label>
                        <select id="status" name="status" class="form-control" disabled title="Status diatur otomatis oleh sistem berdasarkan jumlah sewa aktif">
                            <option value="kosong" <?= $status === 'kosong' ? 'selected' : '' ?>>Kosong (Siap Huni)</option>
                            <option value="terisi" <?= $status === 'terisi' ? 'selected' : '' ?>>Terisi (Sebagian Dihuni)</option>
                            <option value="penuh" <?= $status === 'penuh' ? 'selected' : '' ?>>Penuh (Kapasitas Tercapai)</option>
                        </select>
                        <div class="help-text">Diatur otomatis oleh sistem: Kosong (tanpa sewa aktif), Terisi (masih ada tempat), Penuh (kapasitas tercapai).</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="keterangan">Keterangan Tambahan</label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        class="form-control"
                        rows="3"
                        placeholder="Contoh: Jendela menghadap taman samping, ventilasi segar..."
                    ><?= e($keterangan) ?></textarea>
                </div>

                <!-- Pilihan Fasilitas Kamar -->
                <div class="section-divider">
                    <i class="ph ph-sparkle"></i> Pilih Fasilitas Kamar
                </div>
                <p style="font-size:.78rem;color:var(--muted);margin-bottom:.5rem">
                    Centang fasilitas yang tersedia khusus untuk unit kamar ini:
                </p>

                <?php if (!empty($fasilitasKamar)): ?>
                <div class="facility-category-label">Fasilitas Dalam Kamar</div>
                <div class="facility-grid">
                    <?php foreach ($fasilitasKamar as $f): 
                        $isChecked = in_array((int)$f['id_fasilitas'], $selectedFasilitas, true);
                    ?>
                    <label class="facility-box">
                        <input
                            type="checkbox"
                            name="fasilitas[]"
                            value="<?= (int)$f['id_fasilitas'] ?>"
                            <?= $isChecked ? 'checked' : '' ?>
                        >
                        <div class="facility-info">
                            <div class="facility-name"><?= e($f['nama_fasilitas']) ?></div>
                            <?php if (!empty($f['keterangan'])): ?>
                            <div class="facility-desc"><?= e($f['keterangan']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($fasilitasUmum)): ?>
                <div class="facility-category-label">Fasilitas Umum &amp; Tambahan</div>
                <div class="facility-grid">
                    <?php foreach ($fasilitasUmum as $f): 
                        $isChecked = in_array((int)$f['id_fasilitas'], $selectedFasilitas, true);
                    ?>
                    <label class="facility-box">
                        <input
                            type="checkbox"
                            name="fasilitas[]"
                            value="<?= (int)$f['id_fasilitas'] ?>"
                            <?= $isChecked ? 'checked' : '' ?>
                        >
                        <div class="facility-info">
                            <div class="facility-name"><?= e($f['nama_fasilitas']) ?></div>
                            <?php if (!empty($f['keterangan'])): ?>
                            <div class="facility-desc"><?= e($f['keterangan']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Simpan Kamar
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