<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$error = '';

$nama          = '';
$username      = '';
$password      = '';
$nik           = '';
$no_hp         = '';
$alamat        = '';
$tanggal_masuk = date('Y-m-d');
$status        = 'aktif';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $nama          = trim($_POST['nama'] ?? '');
        $username      = trim($_POST['username'] ?? '');
        $password      = trim($_POST['password'] ?? '');
        $nik           = trim($_POST['nik'] ?? '');
        $no_hp         = trim($_POST['no_hp'] ?? '');
        $alamat        = trim($_POST['alamat'] ?? '');
        $tanggal_masuk = trim($_POST['tanggal_masuk'] ?? date('Y-m-d'));
        $status        = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif']) ? $_POST['status'] : 'aktif';

        // Validasi NIK (16 digit) dan nomor HP (08/+62, 9-15 digit)
        $nikDigits = preg_replace('/\D/', '', $nik);
        $hpDigits  = preg_replace('/[\s\-]/', '', $no_hp);
        if ($nik !== '' && !preg_match('/^\d{16}$/', $nik)) {
            $error = 'NIK harus berupa 16 digit angka.';
        } elseif ($no_hp !== '' && !preg_match('/^(\+62|62|0)8\d{7,12}$/', $hpDigits)) {
            $error = 'Nomor HP tidak valid. Contoh: 081234567890.';
        }

        if (!empty($error)) {
            // error validasi NIK / HP di atas
        } elseif (empty($nama)) {
            $error = 'Nama lengkap penghuni wajib diisi.';} elseif (empty($username)) {
            $error = 'Username untuk akun login wajib diisi.';} elseif (strlen($username) < 3) {
            $error = 'Username minimal terdiri dari 3 karakter.';} elseif (empty($password)) {
            $error = 'Password awal wajib diisi.';} elseif (strlen($password) < 8) {
            $error = 'Password minimal terdiri dari 8 karakter.';} else {
            try {
                // Cek username unik di users
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
                $stmtCheck->execute(['u' => $username]);
                $nikDupe = 0;
                if ($nik !== '') {
                    $stNik = $pdo->prepare("SELECT COUNT(*) FROM penghuni WHERE nik = :nik");
                    $stNik->execute(['nik' => $nik]);
                    $nikDupe = (int)$stNik->fetchColumn();
                }
                if ($nikDupe > 0) {
                    $error = 'NIK tersebut sudah terdaftar pada penghuni lain.';} elseif ($stmtCheck->fetchColumn() > 0) {
                    $error = "Username '$username' sudah terdaftar. Silakan pilih username lain.";} else {
                    $pdo->beginTransaction();

                    // 1. Insert ke tabel users dengan role 'penghuni'
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("
                        INSERT INTO users (nama, username, password, role)
                        VALUES (:nama, :username, :password, 'penghuni')
                    ");
                    $stmtUser->execute([
                        'nama'     => $nama,
                        'username' => $username,
                        'password' => $hashedPassword,
                    ]);

                    $newUserId = (int)$pdo->lastInsertId();

                    // 2. Insert ke tabel penghuni
                    $stmtPenghuni = $pdo->prepare("
                        INSERT INTO penghuni (id_user, nik, no_hp, alamat, tanggal_masuk, status)
                        VALUES (:id_user, :nik, :no_hp, :alamat, :tanggal_masuk, :status)
                    ");
                    $stmtPenghuni->execute([
                        'id_user'       => $newUserId,
                        'nik'           => $nik ?: null,
                        'no_hp'         => $no_hp ?: null,
                        'alamat'        => $alamat ?: null,
                        'tanggal_masuk' => $tanggal_masuk ?: date('Y-m-d'),
                        'status'        => $status,
                    ]);

                    $pdo->commit();

                    setFlash('success', "Penghuni '$nama' berhasil ditambahkan beserta akun login.");
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
<title>Tambah Penghuni Baru - Kost Putri Masira</title>
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
        <div class="topbar-title">Tambah Penghuni Baru</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Form Pendaftaran Penghuni</h1>
            <p class="pg-sub">Membuat data biodata penghuni baru sekaligus akun portal login penghuni.</p>
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

                <!-- SECTION 1: AKUN LOGIN -->
                <div class="form-section-title">
                    <i class="ph ph-user-circle"></i> 1. Informasi Akun Login Penghuni
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="nama">
                            Nama Lengkap <span class="req">*</span>
                        </label>
                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            class="form-control"
                            placeholder="Contoh: Siti Rahmawati"
                            value="<?= e($nama) ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="username">
                            Username Akun <span class="req">*</span>
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            placeholder="Contoh: sitirahma"
                            value="<?= e($username) ?>"
                            required
                        >
                        <div class="help-text">Digunakan penghuni untuk masuk ke portal sistem.</div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="password">
                            Password Awal <span class="req">*</span>
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Minimal 8 karakter"
                            value="<?= e($password) ?>"
                            minlength="8"
                            required
                        >
                        <div class="help-text">Akan dienkripsi secara aman dengan password_hash.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">
                            Status Akun <span class="req">*</span>
                        </label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
                            <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Akses Ditutup)</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 2: BIODATA -->
                <div class="form-section-title">
                    <i class="ph ph-identification-card"></i> 2. Biodata &amp; Kontak Penghuni
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="no_hp">
                            No. WhatsApp / HP <span class="req">*</span>
                        </label>
                        <input
                            type="text"
                            id="no_hp"
                            name="no_hp"
                            class="form-control"
                            placeholder="Contoh: 081234567890"
                            value="<?= e($no_hp) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nik">
                            NIK (Nomor Induk Kependudukan) <span style="font-weight:400;color:var(--muted);font-size:.75rem">(Opsional)</span>
                        </label>
                        <input
                            type="text"
                            id="nik"
                            name="nik"
                            class="form-control"
                            placeholder="Boleh dikosongkan jika tidak ada"
                            value="<?= e($nik) ?>"
                            maxlength="20"
                        >
                        <div class="help-text">Data NIK bersifat opsional dan boleh tidak diisi.</div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="tanggal_masuk">
                            Tanggal Masuk Kost <span class="req">*</span>
                        </label>
                        <input
                            type="date"
                            id="tanggal_masuk"
                            name="tanggal_masuk"
                            class="form-control"
                            value="<?= e($tanggal_masuk) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="alamat">Alamat Asal</label>
                        <textarea
                            id="alamat"
                            name="alamat"
                            class="form-control"
                            rows="2"
                            placeholder="Kota asal / alamat rumah..."
                        ><?= e($alamat) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Daftarkan Penghuni
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