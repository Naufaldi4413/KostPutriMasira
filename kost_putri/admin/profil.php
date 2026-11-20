<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('pengelola', '../login.php');

$currentUser = currentUser();
$id_user = (int)($currentUser['id_user'] ?? $_SESSION['user_id'] ?? 0);

// Ambil data user pengelola saat ini langsung dari tabel users database db_pengelolaan_kos
try {
    $stmtUser = $pdo->prepare("SELECT id_user, nama, username, password, role, created_at FROM users WHERE id_user = :id_user LIMIT 1");
    $stmtUser->execute(['id_user' => $id_user]);
    $userData = $stmtUser->fetch();
} catch (PDOException $e) {
    $userData = null;
}

if (!$userData) {
    setFlash('danger', 'Data akun pengelola tidak ditemukan di database.');
    redirect('dashboard.php');
}

// Tangani submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('danger', 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
        redirect('profil.php');
    }

    $action = $_POST['action'] ?? '';

    // ==========================================
    // 1. UPDATE INFORMASI PROFIL (NAMA & USERNAME)
    // ==========================================
    if ($action === 'update_profil') {
        $nama     = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($nama) || empty($username)) {
            setFlash('danger', 'Nama lengkap dan username tidak boleh kosong.');
            redirect('profil.php');
        }

        if (strlen($nama) < 3) {
            setFlash('danger', 'Nama lengkap minimal 3 karakter.');
            redirect('profil.php');
        }

        if (strlen($username) < 3) {
            setFlash('danger', 'Username minimal 3 karakter.');
            redirect('profil.php');
        }

        if (preg_match('/\s/', $username)) {
            setFlash('danger', 'Username tidak boleh mengandung spasi.');
            redirect('profil.php');
        }

        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
            setFlash('danger', 'Username hanya boleh terdiri dari huruf, angka, garis bawah (_), dan titik (.).');
            redirect('profil.php');
        }

        try {
            // Cek keunikan username (tidak boleh digunakan oleh user lain)
            $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id_user != :id_user");
            $stmtCek->execute([
                'username' => $username,
                'id_user'  => $id_user
            ]);

            if ($stmtCek->fetchColumn() > 0) {
                setFlash('danger', "Username \"@{$username}\" sudah digunakan oleh akun lain. Silakan pilih username yang berbeda.");
                redirect('profil.php');
            }

            // Update tabel users
            $stmtUpd = $pdo->prepare("UPDATE users SET nama = :nama, username = :username WHERE id_user = :id_user");
            $stmtUpd->execute([
                'nama'     => $nama,
                'username' => $username,
                'id_user'  => $id_user
            ]);

            // Sinkronkan session login agar pengelola tetap login dengan data yang diperbarui
            $_SESSION['nama']     = $nama;
            $_SESSION['username'] = $username;

            setFlash('success', 'Profil berhasil diperbarui.');
            redirect('profil.php');
        } catch (PDOException $e) {
            setFlash('danger', 'Terjadi kesalahan basis data saat memperbarui profil: ' . $e->getMessage());
            redirect('profil.php');
        }
    }

    // ==========================================
    // 2. GANTI PASSWORD
    // ==========================================
    elseif ($action === 'ganti_password') {
        $password_lama       = (string)($_POST['password_lama'] ?? '');
        $password_baru       = (string)($_POST['password_baru'] ?? '');
        $konfirmasi_password = (string)($_POST['konfirmasi_password'] ?? '');

        if (empty($password_lama)) {
            setFlash('danger', 'Password lama wajib dimasukkan untuk verifikasi.');
            redirect('profil.php');
        }

        if (empty($password_baru)) {
            setFlash('danger', 'Password baru wajib diisi.');
            redirect('profil.php');
        }

        if (strlen($password_baru) < 8) {
            setFlash('danger', 'Password baru minimal harus 8 karakter.');
            redirect('profil.php');
        }

        if ($password_baru !== $konfirmasi_password) {
            setFlash('danger', 'Password baru dan konfirmasi password tidak cocok.');
            redirect('profil.php');
        }

        // Verifikasi password lama terhadap data di database
        $isOldMatch = false;
        if (password_verify($password_lama, $userData['password']) || $password_lama === $userData['password']) {
            $isOldMatch = true;
        }

        if (!$isOldMatch) {
            setFlash('danger', 'Password lama yang Anda masukkan tidak sesuai.');
            redirect('profil.php');
        }

        if ($password_lama === $password_baru) {
            setFlash('danger', 'Password baru tidak boleh sama dengan password saat ini.');
            redirect('profil.php');
        }

        try {
            // Hash password baru menggunakan password_hash()
            $hashedPassword = password_hash($password_baru, PASSWORD_DEFAULT);

            // Simpan password hash ke tabel users
            $stmtUpdPass = $pdo->prepare("UPDATE users SET password = :password WHERE id_user = :id_user");
            $stmtUpdPass->execute([
                'password' => $hashedPassword,
                'id_user'  => $id_user
            ]);

            setFlash('success', 'Password berhasil diperbarui dengan aman. Sesi login Anda tetap aktif.');
            redirect('profil.php');
        } catch (PDOException $e) {
            setFlash('danger', 'Terjadi kesalahan basis data saat mengganti password: ' . $e->getMessage());
            redirect('profil.php');
        }
    } else {
        setFlash('danger', 'Aksi yang dipilih tidak valid.');
        redirect('profil.php');
    }
}

$flash = getFlash();
$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Pengelola - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.profile-layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:1.5rem;align-items:start}
@media(max-width:900px){
    .profile-layout{grid-template-columns:minmax(0,1fr)}
}
.profile-card{text-align:center;padding:1.5rem 1.25rem 1rem}
.profile-avatar{width:76px;height:76px;border-radius:50%;background:#e8f5e9;border:3px solid #b7e4c7;color:var(--green);font-size:2.2rem;font-weight:700;display:grid;place-items:center;margin:0 auto .85rem}
.profile-name{font-size:1.1rem;font-weight:700;color:#1a1a18;margin-bottom:.2rem;word-break:break-word}
.profile-user-handle{font-size:.84rem;color:var(--muted);margin-bottom:.65rem;font-weight:500}
.meta-list{margin-top:1.25rem;border-top:1px solid #f0efec;padding-top:1rem;text-align:left}
.meta-item{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px dashed #f0efec;font-size:.825rem}
.meta-item:last-child{border-bottom:none}
.meta-label{color:var(--muted)}
.meta-val{font-weight:600;color:#1a1a18}
.security-note{background:#fbfbf9;border:1px solid var(--border);border-radius:10px;padding:1rem;margin-top:1rem;font-size:.8rem;color:var(--muted);line-height:1.5}
.security-note-title{font-weight:700;color:#1a1a18;margin-bottom:.4rem;display:flex;align-items:center;gap:.4rem}
.security-note ul{padding-left:1.15rem;margin-top:.35rem}
.security-note li{margin-bottom:.3rem}
.input-wrap{position:relative}
.input-with-icon{padding-left:2.4rem}
.input-icon-left{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:1.1rem;pointer-events:none}
.btn-toggle-eye{position:absolute;right:.65rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:.25rem;font-size:1.15rem;display:flex;align-items:center;justify-content:center;transition:color .12s}
.btn-toggle-eye:hover{color:#1a1a18}
</style>
</head>
<body>
<div class="layout">

<!-- Mobile Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar -->
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
        <a href="dashboard.php" class="sb-link"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="kamar/index.php" class="sb-link"><i class="ph ph-door"></i> Kelola Kamar</a>
        <a href="fasilitas/index.php" class="sb-link"><i class="ph ph-sparkle"></i> Kelola Fasilitas</a>
        <a href="penghuni/index.php" class="sb-link"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
        <a href="sewa/index.php" class="sb-link"><i class="ph ph-key"></i> Kelola Sewa</a>
        <a href="pembayaran/index.php" class="sb-link"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        <a href="laporan/index.php" class="sb-link"><i class="ph ph-chart-bar"></i> Laporan</a>
        <div class="sb-label" style="margin-top:.5rem">Lainnya</div>
        <a href="profil.php" class="sb-link active"><i class="ph ph-user-circle"></i> Profil Pengelola</a>
        <a href="../index.php" class="sb-link" target="_blank" rel="noopener"><i class="ph ph-globe"></i> Landing Page</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-avatar"><i class="ph ph-user"></i></div>
            <div>
                <div class="sb-uname"><?= e($userData['nama'] ?? 'Pengelola') ?></div>
                <div class="sb-urole">Pengelola</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<!-- Main Content Area -->
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Profil Pengelola</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="6" x2="20" y2="6"></line>
                    <line x1="4" y1="12" x2="20" y2="12"></line>
                    <line x1="4" y1="18" x2="20" y2="18"></line>
                </svg>
            </button>
        </div>
    </header>

    <main class="content content-form">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger', 'error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger', 'error']) ? 'ph-warning-circle' : 'ph-check-circle' ?>"></i>
            <div><?= e($flash['message']) ?></div>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <div class="pg-title">Profil Pengelola</div>
                <p class="pg-sub">Kelola informasi identitas akun pengelola dan keamanan kata sandi Anda.</p>
            </div>
        </div>

        <div class="profile-layout">
            <!-- Kolom Kiri: Ringkasan Akun -->
            <div>
                <div class="panel">
                    <div class="profile-card">
                        <div class="profile-avatar">
                            <i class="ph ph-user"></i>
                        </div>
                        <h3 class="profile-name"><?= e($userData['nama']) ?></h3>
                        <div class="profile-user-handle">@<?= e($userData['username']) ?></div>
                        <div>
                            <span class="badge bg-g">
                                <i class="ph ph-shield-check"></i> Pengelola (Admin)
                            </span>
                        </div>

                        <div class="meta-list">
                            <div class="meta-item">
                                <span class="meta-label">ID Akun</span>
                                <span class="meta-val">#<?= (int)$userData['id_user'] ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Peran</span>
                                <span class="meta-val">Pengelola Kos</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Terdaftar Sejak</span>
                                <span class="meta-val"><?= formatTanggal($userData['created_at']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Status Akun</span>
                                <span class="meta-val" style="color:var(--green)">Aktif</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="security-note">
                    <div class="security-note-title">
                        <i class="ph ph-shield-warning" style="color:var(--green);font-size:1.1rem"></i>
                        Catatan Keamanan
                    </div>
                    <ul>
                        <li>Gunakan kombinasi password yang kuat (huruf, angka, simbol).</li>
                        <li>Jangan berikan akses username dan password akun pengelola kepada pihak lain.</li>
                        <li>Perbarui password secara berkala untuk menjaga keamanan data kost.</li>
                    </ul>
                </div>
            </div>

            <!-- Kolom Kanan: Form Edit Profil & Form Ganti Password -->
            <div>
                <!-- Form 1: Ubah Informasi Profil (Nama & Username) -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="ph ph-user-circle"></i> Edit Informasi Akun</div>
                    </div>
                    <div class="panel-body">
                        <form action="profil.php" method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                            <input type="hidden" name="action" value="update_profil">

                            <div class="form-group">
                                <label class="form-label" for="nama">Nama Lengkap <span class="req">*</span></label>
                                <div class="input-wrap">
                                    <span class="input-icon-left"><i class="ph ph-user"></i></span>
                                    <input type="text" id="nama" name="nama" class="form-control input-with-icon" value="<?= e($userData['nama']) ?>" required maxlength="100" placeholder="Masukkan nama lengkap">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="username">Username Akun <span class="req">*</span></label>
                                <div class="input-wrap">
                                    <span class="input-icon-left"><i class="ph ph-at"></i></span>
                                    <input type="text" id="username" name="username" class="form-control input-with-icon" value="<?= e($userData['username']) ?>" required maxlength="50" placeholder="Masukkan username">
                                </div>
                                <div class="form-hint">
                                    <i class="ph ph-info"></i> Username digunakan saat masuk (login) ke dashboard pengelola. Tanpa spasi.
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-submit">
                                    <i class="ph ph-floppy-disk"></i> Simpan Perubahan Profil
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Form 2: Ganti Password -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="ph ph-lock-key"></i> Ganti Password</div>
                    </div>
                    <div class="panel-body">
                        <form action="profil.php" method="POST" autocomplete="off" id="formGantiPassword">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                            <input type="hidden" name="action" value="ganti_password">

                            <div class="form-group">
                                <label class="form-label" for="password_lama">Password Saat Ini (Lama) <span class="req">*</span></label>
                                <div class="input-wrap">
                                    <span class="input-icon-left"><i class="ph ph-key"></i></span>
                                    <input type="password" id="password_lama" name="password_lama" class="form-control input-with-icon" required placeholder="Masukkan password saat ini" style="padding-right:2.5rem">
                                    <button type="button" class="btn-toggle-eye" data-target="password_lama" aria-label="Lihat password">
                                        <i class="ph ph-eye"></i>
                                    </button>
                                </div>
                                <div class="form-hint">
                                    <i class="ph ph-shield-check"></i> Wajib memasukkan password lama untuk verifikasi kepemilikan akun.
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="password_baru">Password Baru <span class="req">*</span></label>
                                    <div class="input-wrap">
                                        <span class="input-icon-left"><i class="ph ph-lock"></i></span>
                                        <input type="password" id="password_baru" name="password_baru" class="form-control input-with-icon" required minlength="8" placeholder="Minimal 8 karakter" style="padding-right:2.5rem">
                                        <button type="button" class="btn-toggle-eye" data-target="password_baru" aria-label="Lihat password">
                                            <i class="ph ph-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="konfirmasi_password">Konfirmasi Password Baru <span class="req">*</span></label>
                                    <div class="input-wrap">
                                        <span class="input-icon-left"><i class="ph ph-check-square"></i></span>
                                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control input-with-icon" required minlength="8" placeholder="Ulangi password baru" style="padding-right:2.5rem">
                                        <button type="button" class="btn-toggle-eye" data-target="konfirmasi_password" aria-label="Lihat password">
                                            <i class="ph ph-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-submit">
                                    <i class="ph ph-shield-check"></i> Perbarui Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Drawer Logic
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

    // 2. Toggle Password Visibility
    document.querySelectorAll('.btn-toggle-eye').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon?.classList.remove('ph-eye');
                icon?.classList.add('ph-eye-slash');
            } else {
                input.type = 'password';
                icon?.classList.remove('ph-eye-slash');
                icon?.classList.add('ph-eye');
            }
        });
    });

    // 3. Client Validation for Password Match & Length
    const formGantiPassword = document.getElementById('formGantiPassword');
    const passBaru = document.getElementById('password_baru');
    const passKonf = document.getElementById('konfirmasi_password');

    formGantiPassword?.addEventListener('submit', (e) => {
        if (passBaru.value.length < 8) {
            e.preventDefault();
            alert('Password baru minimal harus 8 karakter!');
            passBaru.focus();
            return;
        }
        if (passBaru.value !== passKonf.value) {
            e.preventDefault();
            alert('Password baru dan konfirmasi password tidak cocok! Silakan periksa kembali.');
            passKonf.focus();
        }
    });
});
</script>
</body>
</html>
