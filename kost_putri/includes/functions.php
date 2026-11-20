<?php
/**
 * Helper Functions & Utilities
 * Sistem Pengelolaan Kost
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Path web ke folder root proyek (mis. "/kost_putri" atau "" jika di root).
 * Dihitung dari lokasi file sebenarnya, jadi tetap benar walau nama folder diganti.
 */
function project_base(): string {
    static $base = null;
    if ($base !== null) { return $base; }
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    if ($doc !== '' && stripos($root, $doc) === 0) {
        $base = rtrim(substr($root, strlen($doc)), '/');
    } else {
        // Fallback: turunkan dari SCRIPT_NAME
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? '')), '/');
        $depth  = substr_count(substr($dir, strlen($root)), '/');
        $base   = rtrim(implode('/', array_slice(explode('/', dirname($script)), 0, max(0, count(explode('/', dirname($script))) - $depth))), '/');
    }
    return $base;
}

function base_url(string $path = ''): string {
    return project_base() . '/' . ltrim($path, '/');
}

/**
 * Redirect. URL relatif (mis. "index.php", "../login.php") diubah menjadi path
 * absolut dari lokasi skrip saat ini, sehingga tidak "Not Found" walau URL
 * diakses tanpa .php atau lewat subfolder.
 */
function redirect(string $url): void {
    if (!preg_match('#^(https?:)?//#i', $url) && ($url === '' || $url[0] !== '/')) {
        $dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $parts = explode('/', $dir . '/' . $url);
        $out   = [];
        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.') { continue; }
            if ($seg === '..') { array_pop($out); continue; }
            $out[] = $seg;
        }
        $url = '/' . implode('/', $out);
    }
    header("Location: " . $url);
    exit;
}

function sanitize(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    if (is_string($data)) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function formatRupiah(float|int|string $angka, bool $denganSimbol = true): string {
    $nominal = (float) $angka;
    $hasil = number_format($nominal, 0, ',', '.');
    return $denganSimbol ? 'Rp ' . $hasil : $hasil;
}

function formatTanggal(?string $tanggal, string $format = 'panjang'): string {
    if (empty($tanggal)) {
        return '-';
    }

    $timestamp = strtotime($tanggal);
    if (!$timestamp) {
        return '-';
    }

    if ($format === 'pendek') {
        return date('d/m/Y', $timestamp);
    }

    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $hari = date('d', $timestamp);
    $bulan = $bulanIndo[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);

    return "{$hari} {$bulan} {$tahun}";
}

function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function hasFlash(): bool {
    return isset($_SESSION['flash_message']);
}

function getFlash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getPenghuniByUserId(PDO $pdo, int $id_user): ?array {
    $stmt = $pdo->prepare("SELECT * FROM penghuni WHERE id_user = :id_user LIMIT 1");
    $stmt->execute(['id_user' => $id_user]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Memperbarui status kamar secara otomatis berdasarkan kapasitas dan jumlah sewa aktif:
 * - 0 penghuni aktif => 'kosong'
 * - jumlah penghuni aktif < kapasitas => 'terisi'
 * - jumlah penghuni aktif >= kapasitas => 'penuh'
 */
if (!function_exists('updateStatusKamar')) {
    function updateStatusKamar(PDO $pdo, int $id_kamar): string {
        $stmt = $pdo->prepare("SELECT kapasitas FROM kamar WHERE id_kamar = :id LIMIT 1");
        $stmt->execute(['id' => $id_kamar]);
        $kamar = $stmt->fetch();
        if (!$kamar) {
            return 'kosong';
        }

        $kapasitas = max(1, (int)$kamar['kapasitas']);

        $stmtSewa = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id AND status = 'aktif'");
        $stmtSewa->execute(['id' => $id_kamar]);
        $aktif = (int)$stmtSewa->fetchColumn();

        if ($aktif === 0) {
            $status = 'kosong';
        } elseif ($aktif < $kapasitas) {
            $status = 'terisi';
        } else {
            $status = 'penuh';
        }

        $stmtUp = $pdo->prepare("UPDATE kamar SET status = :status WHERE id_kamar = :id");
        $stmtUp->execute(['status' => $status, 'id' => $id_kamar]);

        return $status;
    }
}

/**
 * Menghitung jumlah sewa aktif pada suatu unit kamar
 */
if (!function_exists('getJumlahPenghuniAktifKamar')) {
    function getJumlahPenghuniAktifKamar(PDO $pdo, int $id_kamar): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id AND status = 'aktif'");
        $stmt->execute(['id' => $id_kamar]);
        return (int)$stmt->fetchColumn();
    }
}

/**
 * Ubah teks periode ("6 Bulan", "1 Tahun", "12 bulan") menjadi jumlah bulan.
 */
if (!function_exists('parsePeriodeBulan')) {
    function parsePeriodeBulan(?string $periode): int {
        $periode = strtolower(trim((string)$periode));
        if (!preg_match('/(\d+)/', $periode, $m)) {
            return 0;
        }
        $n = (int)$m[1];
        return (str_contains($periode, 'tahun') || str_contains($periode, 'year')) ? $n * 12 : $n;
    }
}

/**
 * Hitung total sewa per penghuni.
 * Aturan kost: sewa sendiri = harga kamar per bulan; berdua = harga dibagi 2 per orang.
 * Total = tarif per bulan x jumlah bulan (dibayar per semester / sesuai periode).
 */
if (!function_exists('hitungTotalSewa')) {
    function hitungTotalSewa(float $hargaBulanan, string $periode, bool $berdua = false): float {
        $bulan = parsePeriodeBulan($periode);
        $tarif = $berdua ? $hargaBulanan / 2 : $hargaBulanan;
        return round($tarif * $bulan, 2);
    }
}

/**
 * Validasi tanggal format Y-m-d.
 */
if (!function_exists('isValidDate')) {
    function isValidDate(?string $tgl): bool {
        if ($tgl === null || $tgl === '') { return false; }
        $d = DateTime::createFromFormat('Y-m-d', $tgl);
        return $d && $d->format('Y-m-d') === $tgl;
    }
}


/**
 * Jumlah pembayaran yang sudah dikonfirmasi (lunas) untuk sebuah sewa.
 */
if (!function_exists('getTotalTerbayar')) {
    function getTotalTerbayar(PDO $pdo, int $id_sewa, int $kecualiIdPembayaran = 0): float {
        $st = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar),0) FROM pembayaran
                             WHERE id_sewa = :s AND status = 'lunas' AND status_verifikasi = 'dikonfirmasi'
                               AND id_pembayaran != :x");
        $st->execute(['s' => $id_sewa, 'x' => $kecualiIdPembayaran]);
        return (float)$st->fetchColumn();
    }
}
