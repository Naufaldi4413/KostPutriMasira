<?php
/**
 * Authentication & Role Authorization Check
 * Sistem Informasi Kost Putri Masira
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user saat ini sudah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Mengambil data user yang sedang login dari session
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id_user'     => $_SESSION['user_id'] ?? null,
        'nama'        => $_SESSION['nama'] ?? '',
        'username'    => $_SESSION['username'] ?? '',
        'role'        => $_SESSION['role'] ?? '',
        'id_penghuni' => $_SESSION['id_penghuni'] ?? null,
    ];
}

/**
 * Cek apakah user memiliki role tertentu
 * @param string|array $role (e.g. 'pengelola' atau ['pengelola', 'penghuni'])
 */
function hasRole(string|array $role): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $currentRole = $_SESSION['role'] ?? '';
    
    if (is_array($role)) {
        return in_array($currentRole, $role, true);
    }

    return $currentRole === $role;
}

/**
 * Memastikan user sudah login, jika belum maka diarahkan ke halaman login
 */
function requireLogin(string $redirectUrl = '../login.php'): void {
    if (!isLoggedIn()) {
        if (function_exists('setFlash')) {
            setFlash('danger', 'Silakan masuk terlebih dahulu untuk mengakses halaman ini.');
        }
        redirect($redirectUrl);
    }
}

/**
 * Memastikan user memiliki role yang sesuai
 */
function requireRole(string|array $role, string $redirectUrl = '../login.php'): void {
    requireLogin($redirectUrl);

    if (!hasRole($role)) {
        if (function_exists('setFlash')) {
            setFlash('danger', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        }
        
        // Redirect ke dashboard masing-masing (path absolut dari root proyek)
        $user = currentUser();
        if ($user && $user['role'] === 'pengelola') {
            redirect(base_url('admin/dashboard.php'));
        } elseif ($user && $user['role'] === 'penghuni') {
            redirect(base_url('penghuni/dashboard.php'));
        }
        redirect($redirectUrl);
    }
}

/**
 * Jika user sudah login, redirect ke dashboard role masing-masing.
 * Digunakan di halaman login agar tidak bisa dibuka ulang.
 */
function redirectIfLoggedIn(): void {
    if (!isLoggedIn()) {
        return;
    }
    $role = $_SESSION['role'] ?? '';
    if ($role === 'pengelola') {
        redirect(base_url('admin/dashboard.php'));
    } elseif ($role === 'penghuni') {
        redirect(base_url('penghuni/dashboard.php'));
    }
    redirect(base_url('index.php'));
}
