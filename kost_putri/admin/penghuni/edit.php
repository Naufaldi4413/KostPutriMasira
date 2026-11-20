<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$error = '';

$id_penghuni = (int)($_GET['id'] ?? $_POST['id_penghuni'] ?? 0);

if ($id_penghuni <= 0) {
    setFlash('danger', 'ID Penghuni tidak valid.');
    redirect('index.php');}

// Ambil data penghuni & user
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
} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}

$nama          = $penghuni['nama'];
$username      = $penghuni['username'];
$nik           = $penghuni['nik'] ?? '';
$no_hp         = $penghuni['no_hp'] ?? '';
$alamat        = $penghuni['alamat'] ?? '';
$tanggal_masuk = $penghuni['tanggal_masuk'] ?: date('Y-m-d');
$status        = $penghuni['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $nama          = trim($_POST['nama'] ?? '');
        $username      = trim($_POST['username'] ?? '');
        $password_baru = trim($_POST['password_baru'] ?? '');
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
            $error = 'Username akun login wajib diisi.';} elseif (strlen($username) < 3) {
            $error = 'Username minimal terdiri dari 3 karakter.';} elseif (!empty($password_baru) && strlen($password_baru) < 8) {
            $error = 'Password baru minimal terdiri dari 8 karakter.';} else {
            try {
                // Cek username unik selain dirinya
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u AND id_user != :uid");
                $stmtCheck->execute(['u' => $username, 'uid' => $penghuni['id_user']]);
                $nikDupe = 0; $sewaAktifCnt = 0;
                if ($nik !== '') {
                    $stNik = $pdo->prepare("SELECT COUNT(*) FROM penghuni WHERE nik = :nik AND id_penghuni != :pid");
                    $stNik->execute(['nik' => $nik, 'pid' => $id_penghuni]);
                    $nikDupe = (int)$stNik->fetchColumn();
                }
                if ($status === 'nonaktif') {
                    $stAk = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_penghuni = :pid AND status = 'aktif'");
                    $stAk->execute(['pid' => $id_penghuni]);
                    $sewaAktifCnt = (int)$stAk->fetchColumn();
                }
                if ($nikDupe > 0) {
                    $error = 'NIK tersebut sudah terdaftar pada penghuni lain.';} elseif ($sewaAktifCnt > 0) {
                    $error = 'Penghuni masih memiliki sewa kamar aktif. Selesaikan sewanya dulu sebelum menonaktifkan akun.';} elseif ($stmtCheck->fetchColumn() > 0) {
                    $error = "Username '$username' sudah digunakan oleh pengguna lain.";} else {
                    $pdo->beginTransaction();

                    // 1. Update tabel users
                    if (!empty($password_baru)) {
                        $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
                        $stmtU = $pdo->prepare("
                            UPDATE users 
                            SET nama = :nama, username = :username, password = :pwd 
                            WHERE id_user = :uid
                        ");
                        $stmtU->execute([
                            'nama'     => $nama,
                            'username' => $username,
                            'pwd'      => $hashed,
                            'uid'      => $penghuni['id_user'],
                        ]);} else {
                        $stmtU = $pdo->prepare("
                            UPDATE users 
                            SET nama = :nama, username = :username 
                            WHERE id_user = :uid
                        ");
                        $stmtU->execute([
                            'nama'     => $nama,
                            'username' => $username,
                            'uid'      => $penghuni['id_user'],
                        ]);}

                    // 2. Update tabel penghuni
                    $stmtP = $pdo->prepare("
                        UPDATE penghuni 
                        SET nik = :nik, no_hp = :no_hp, alamat = :alamat, 
                            tanggal_masuk = :tgl, status = :status
                        WHERE id_penghuni = :pid
                    ");
                    $stmtP->execute([
                        'nik'    => $nik ?: null,
                        'no_hp'  => $no_hp ?: null,
                        'alamat' => $alamat ?: null,
                        'tgl'    => $tanggal_masuk ?: date('Y-m-d'),
                        'status' => $status,
                        'pid'    => $id_penghuni,
                    ]);

                    $pdo->commit();

                    setFlash('success', "Biodata penghuni '$nama' berhasil diperbarui.");
                    redirect("detail.php?id=$id_penghuni");}
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
<title>Edit Penghuni <?= e($penghuni['nama']) ?> - Kost Putri Masira</title>
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
        <div class="topbar-title">Edit Biodata Penghuni</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Edit Penghuni: <?= e($penghuni['nama']) ?></h1>
            <p class="pg-sub">Perbarui informasi kontak, biodata NIK, username, atau ganti password akun login.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="ph ph-warning" style="font-size:1.2rem;flex-shrink:0"></i>
            <div><?= e($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="card-form">
            <form action="edit.php?id=<?= $id_penghuni ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="id_penghuni" value="<?= $id_penghuni ?>">

                <!-- SECTION 1: AKUN LOGIN -->
                <div class="form-section-title">
                    <i class="ph ph-user-circle"></i> 1. Informasi Akun Portal
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
                            value="<?= e($nama) ?>"
                            required
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
                            value="<?= e($username) ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="password_baru">
                            Password Baru (Opsional)
                        </label>
                        <input
                            type="password"
                            id="password_baru"
                            name="password_baru"
                            class="form-control"
                            placeholder="Kosongkan bila tidak ingin mengubah password"
                            minlength="8"
                        >
                        <div class="help-text">Isi minimal 8 karakter hanya bila penghuni meminta reset kata sandi akunnya.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">
                            Status Akun <span class="req">*</span>
                        </label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif (Dapat Mengakses Portal)</option>
                            <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Akses Masuk Dinonaktifkan)</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 2: BIODATA -->
                <div class="form-section-title">
                    <i class="ph ph-identification-card"></i> 2. Biodata &amp; Kontak
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
                            placeholder="Boleh dikosongkan"
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
                        ><?= e($alamat) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Perbarui Penghuni
                    </button>
                    <a href="detail.php?id=<?= $id_penghuni ?>" class="btn-cancel">
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