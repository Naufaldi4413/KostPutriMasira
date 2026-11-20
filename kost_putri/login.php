<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

redirectIfLoggedIn();

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if (empty($username) || empty($password)) {
            $error = 'Silakan isi username dan password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch();
                $passwordMatch = false;
                if ($user) {
                    if (password_verify($password, $user['password'])) {
                        $passwordMatch = true;
                    } elseif ($password === $user['password']) {
                        $passwordMatch = true;
                    }
                }
                if ($user && $passwordMatch) {
                    $idPenghuni = null;
                    if ($user['role'] === 'penghuni') {
                        $stmtP = $pdo->prepare("SELECT id_penghuni, status FROM penghuni WHERE id_user = :id_user LIMIT 1");
                        $stmtP->execute(['id_user' => $user['id_user']]);
                        $penghuni = $stmtP->fetch();
                        if ($penghuni) {
                            if ($penghuni['status'] === 'nonaktif') {
                                $error = 'Akun Anda berstatus nonaktif. Hubungi pengelola kost.';
                            } else {
                                $idPenghuni = $penghuni['id_penghuni'];
                            }
                        }
                    }
                    if (empty($error)) {
                        session_regenerate_id(true);
                        $_SESSION['user_id']     = (int)$user['id_user'];
                        $_SESSION['nama']        = $user['nama'];
                        $_SESSION['username']    = $user['username'];
                        $_SESSION['role']        = $user['role'];
                        $_SESSION['id_penghuni'] = $idPenghuni;
                        if ($user['role'] === 'pengelola') {
                            redirect(base_url('admin/dashboard.php'));
                        } elseif ($user['role'] === 'penghuni') {
                            redirect(base_url('penghuni/dashboard.php'));
                        } else {
                            redirect(base_url('index.php'));
                        }
                    }
                } else {
                    $error = 'Username atau password salah.';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

$flash      = getFlash();
$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="assets/css/style.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f6f5f2;min-height:100vh;display:grid;place-items:center;padding:2rem 1rem}
.lcard{background:#fff;border:1px solid #e5e3de;border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:420px;box-shadow:0 4px 24px rgba(0,0,0,.06)}
.lbrand{display:flex;align-items:center;gap:.65rem;justify-content:center;margin-bottom:1.75rem}
.lbrand-ico{width:40px;height:40px;background:#2d6a4f;color:#fff;border-radius:10px;display:grid;place-items:center;font-size:1.2rem}
.lbrand-name{font-weight:700;font-size:1rem;color:#1a1a18;line-height:1.2}
.lbrand-sub{display:block;font-size:.7rem;color:#7a7871;font-weight:400}
.ltitle{font-size:1.35rem;font-weight:700;color:#1a1a18;text-align:center;margin-bottom:.3rem}
.lsub{font-size:.875rem;color:#7a7871;text-align:center;margin-bottom:1.75rem}
.fg{margin-bottom:1.1rem}
.fl{display:block;font-size:.8125rem;font-weight:600;color:#1a1a18;margin-bottom:.4rem}
.fi{width:100%;padding:.65rem .9rem;border:1px solid #e5e3de;border-radius:8px;font-size:.9375rem;font-family:'Plus Jakarta Sans',sans-serif;color:#1a1a18;background:#fafaf9;transition:border-color .15s,box-shadow .15s}
.fi:focus{outline:none;border-color:#2d6a4f;box-shadow:0 0 0 3px rgba(45,106,79,.12);background:#fff}
.lbtn{width:100%;padding:.75rem;background:#2d6a4f;color:#fff;border:none;border-radius:8px;font-size:.9375rem;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;margin-top:.25rem;transition:background .15s}
.lbtn:hover{background:#235540}
.aerror{background:#fdf2f2;border:1px solid #f5c6c6;border-radius:8px;padding:.75rem 1rem;font-size:.875rem;color:#c0392b;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:.5rem}
.aflash{background:#f0faf4;border:1px solid #b7e4c7;border-radius:8px;padding:.75rem 1rem;font-size:.875rem;color:#1b4332;margin-bottom:1.25rem}
.lback{text-align:center;margin-top:1.25rem;font-size:.8125rem;color:#7a7871}
.lback a{color:#2d6a4f;font-weight:600;text-decoration:none}
.lback a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="lcard">
    <div class="lbrand">
        <div class="lbrand-ico"><i class="ph ph-house-line"></i></div>
        <div>
            <div class="lbrand-name">Kost Putri Masira</div>
            <span class="lbrand-sub">Sistem Pengelolaan Kost</span>
        </div>
    </div>
    <h1 class="ltitle">Masuk ke Akun</h1>
    <p class="lsub">Silakan masukkan username dan password Anda.</p>

    <?php if ($flash): ?>
    <div class="aflash"><i class="ph ph-info"></i> <?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="aerror"><i class="ph ph-warning"></i><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form action="login.php" method="POST" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
        <div class="fg">
            <label class="fl" for="username">Username</label>
            <input class="fi" type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username" placeholder="Masukkan username">
        </div>
        <div class="fg">
            <label class="fl" for="password">Password</label>
            <input class="fi" type="password" id="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
        </div>
        <button type="submit" class="lbtn">Masuk</button>
    </form>
    <div class="lback">
        <a href="index.php"> Kembali ke Beranda</a>
    </div>
</div>
</body>
</html>