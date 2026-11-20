<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$error = '';

$preselectedSewa = (int)($_GET['id_sewa'] ?? 0);

// Ambil daftar sewa aktif
try {
    $stmtSewa = $pdo->query("
        SELECT 
            s.id_sewa, s.total_sewa, s.periode, s.tanggal_mulai,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar,
            (SELECT COUNT(*) FROM pembayaran pb WHERE pb.id_sewa = s.id_sewa AND pb.status = 'lunas') AS sudah_lunas
        FROM sewa s
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.status = 'aktif'
        ORDER BY k.nomor_kamar ASC
    ");
    $daftarSewaAktif = $stmtSewa->fetchAll();} catch (PDOException $e) {
    $daftarSewaAktif = [];
    $error = 'Gagal memuat data sewa: ' . $e->getMessage();}

$id_sewa       = $preselectedSewa;
$tanggal_bayar = date('Y-m-d');
$jumlah_bayar  = '';
$keterangan    = 'Pembayaran tunai (Cash) diterima langsung oleh pengelola kost.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $id_sewa          = (int)($_POST['id_sewa'] ?? 0);
        $tanggal_bayar    = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
        $jumlah_bayar_raw = trim($_POST['jumlah_bayar'] ?? '');
        $keterangan       = trim($_POST['keterangan'] ?? '');

        if ($id_sewa <= 0) {
            $error = 'Silakan pilih transaksi sewa kamar.';} elseif (!isValidDate($tanggal_bayar)) {
            $error = 'Tanggal pembayaran tidak valid.';} elseif ($tanggal_bayar > date('Y-m-d')) {
            $error = 'Tanggal pembayaran tidak boleh di masa depan.';} elseif (!is_numeric($jumlah_bayar_raw) || (float)$jumlah_bayar_raw <= 0) {
            $error = 'Jumlah pembayaran harus berupa nominal angka lebih dari 0.';} else {
            $jumlah_bayar = (float)$jumlah_bayar_raw;

            try {
                // Sewa harus ada & aktif, dan nominal tidak boleh melebihi sisa tagihan
                $stS = $pdo->prepare("SELECT total_sewa, status FROM sewa WHERE id_sewa = :id");
                $stS->execute(['id' => $id_sewa]);
                $sw = $stS->fetch();
                if (!$sw || $sw['status'] !== 'aktif') {
                    throw new RuntimeException('Transaksi sewa tidak ditemukan atau sudah selesai.');
                }
                $sisa = (float)$sw['total_sewa'] - getTotalTerbayar($pdo, $id_sewa);
                if ($sisa <= 0) {
                    throw new RuntimeException('Tagihan sewa ini sudah lunas, tidak ada sisa pembayaran.');
                }
                if ($jumlah_bayar > $sisa + 0.01) {
                    throw new RuntimeException('Nominal melebihi sisa tagihan (' . formatRupiah($sisa) . ').');
                }
                // Insert pembayaran Cash langsung berstatus lunas dan dikonfirmasi
                $stmtIns = $pdo->prepare("
                    INSERT INTO pembayaran (
                        id_sewa, tanggal_bayar, jumlah_bayar, metode_pembayaran,
                        status, status_verifikasi, tanggal_verifikasi, keterangan
                    ) VALUES (
                        :id_sewa, :tanggal_bayar, :jumlah_bayar, 'Cash',
                        'lunas', 'dikonfirmasi', NOW(), :keterangan
                    )
                ");
                $stmtIns->execute([
                    'id_sewa'       => $id_sewa,
                    'tanggal_bayar' => $tanggal_bayar,
                    'jumlah_bayar'  => $jumlah_bayar,
                    'keterangan'    => $keterangan ?: 'Pembayaran tunai diterima langsung.',
                ]);

                setFlash('success', 'Pembayaran Cash berhasil dicatat dan otomatis berstatus Lunas.');
                redirect('index.php');} catch (RuntimeException $e) {
                $error = $e->getMessage();} catch (PDOException $e) {
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
<title>Catat Pembayaran Cash - Kost Putri Masira</title>
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
        <a href="../penghuni/index.php" class="sb-link"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
        <a href="../sewa/index.php" class="sb-link"><i class="ph ph-key"></i> Kelola Sewa</a>
        <a href="index.php" class="sb-link active"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        
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
        <div class="topbar-title">Catat Pembayaran Tunai (Cash)</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Pencatatan Pembayaran Cash</h1>
            <p class="pg-sub">Formulir penerimaan uang sewa secara tunai langsung dari penghuni di tempat.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="ph ph-warning" style="font-size:1.2rem;flex-shrink:0"></i>
            <div><?= e($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="info-note">
            <i class="ph ph-info" style="font-size:1.3rem;flex-shrink:0"></i>
            <div>
                <strong>Ketentuan Pembayaran Tunai (Cash):</strong><br>
                Pembayaran yang diterima langsung oleh pengelola tidak memerlukan upload bukti transfer. Status pembayaran otomatis langsung diset <strong>Lunas</strong> dan diverifikasi.
            </div>
        </div>

        <div class="card-form">
            <form action="tambah_cash.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="form-group">
                    <label class="form-label" for="id_sewa">
                        Pilih Transaksi Sewa / Kamar Penghuni <span class="req">*</span>
                    </label>
                    <select id="id_sewa" name="id_sewa" class="form-control" required onchange="handleSewaChange(this)">
                        <option value="">-- Pilih Kamar &amp; Penghuni --</option>
                        <?php foreach ($daftarSewaAktif as $s): ?>
                        <option 
                            value="<?= $s['id_sewa'] ?>"
                            data-tagihan="<?= (float)$s['total_sewa'] ?>"
                            data-penghuni="<?= e($s['nama_penghuni']) ?>"
                            data-kamar="<?= e($s['nomor_kamar']) ?>"
                            <?= $id_sewa === (int)$s['id_sewa'] ? 'selected' : '' ?>
                        >
                            Kamar <?= e($s['nomor_kamar']) ?> - <?= e($s['nama_penghuni']) ?> (Tagihan: Rp <?= number_format($s['total_sewa'], 0, ',', '.') ?>) <?= (int)$s['sudah_lunas'] > 0 ? '[Pernah Bayar]' : '[Belum Bayar]' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="tanggal_bayar">
                            Tanggal Pembayaran <span class="req">*</span>
                        </label>
                        <input
                            type="date"
                            id="tanggal_bayar"
                            name="tanggal_bayar"
                            class="form-control"
                            value="<?= e($tanggal_bayar) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="jumlah_bayar">
                            Jumlah Nominal Diterima (Rp) <span class="req">*</span>
                        </label>
                        <input
                            type="number"
                            id="jumlah_bayar"
                            name="jumlah_bayar"
                            class="form-control"
                            placeholder="Contoh: 3000000"
                            value="<?= e((string)$jumlah_bayar) ?>"
                            min="0"
                            step="1000"
                            required
                        >
                        <div class="help-text">Nominal uang tunai yang diterima dari penghuni.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="keterangan">Keterangan / Catatan Tambahan</label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        class="form-control"
                        rows="3"
                        placeholder="Contoh: Diterima lunas untuk sewa semester 1..."
                    ><?= e($keterangan) ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Simpan Pembayaran Cash
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
function handleSewaChange(select) {
    const opt = select.options[select.selectedIndex];
    const inputJumlah = document.getElementById('jumlah_bayar');
    if (opt && opt.value) {
        const tagihan = opt.getAttribute('data-tagihan');
        if (tagihan) {
            inputJumlah.value = tagihan}
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('id_sewa');
    if (sel && sel.value) {
        handleSewaChange(sel)}
});
</script>

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