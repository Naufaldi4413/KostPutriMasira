<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

// Filter parameters
$status_bayar = trim($_GET['status_bayar'] ?? '');
$status_verif = trim($_GET['status_verif'] ?? '');
$metode       = trim($_GET['metode'] ?? '');
$search       = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        pb.*,
        s.tanggal_mulai, s.tanggal_selesai, s.periode, s.total_sewa,
        p.id_penghuni, p.no_hp,
        u.nama AS nama_penghuni, u.username,
        k.nomor_kamar, k.tipe_kamar
    FROM pembayaran pb
    JOIN sewa s ON pb.id_sewa = s.id_sewa
    JOIN penghuni p ON s.id_penghuni = p.id_penghuni
    JOIN users u ON p.id_user = u.id_user
    JOIN kamar k ON s.id_kamar = k.id_kamar
    WHERE 1=1
";
$params = [];

if (!empty($status_bayar) && in_array($status_bayar, ['lunas', 'belum_lunas'])) {
    $sql .= " AND pb.status = :status_bayar";
    $params['status_bayar'] = $status_bayar;}
if (!empty($status_verif) && in_array($status_verif, ['menunggu', 'dikonfirmasi', 'ditolak'])) {
    $sql .= " AND pb.status_verifikasi = :status_verif";
    $params['status_verif'] = $status_verif;}
if (!empty($metode) && in_array($metode, ['Cash', 'Transfer', 'QRIS'])) {
    $sql .= " AND pb.metode_pembayaran = :metode";
    $params['metode'] = $metode;}
if (!empty($search)) {
    $sql .= " AND (u.nama LIKE :q OR k.nomor_kamar LIKE :q OR u.username LIKE :q)";
    $params['q'] = "%$search%";}

$sql .= " ORDER BY (pb.status_verifikasi = 'menunggu') DESC, pb.tanggal_bayar DESC, pb.id_pembayaran DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $daftarBayar = $stmt->fetchAll();

    // Summary statistics
    $statAll = $pdo->query("
        SELECT 
            COUNT(*) AS total_transaksi,
            COALESCE(SUM(CASE WHEN status = 'lunas' THEN jumlah_bayar ELSE 0 END), 0) AS total_lunas,
            SUM(status_verifikasi = 'menunggu') AS total_menunggu,
            SUM(status_verifikasi = 'dikonfirmasi') AS total_dikonfirmasi,
            SUM(status_verifikasi = 'ditolak') AS total_ditolak
        FROM pembayaran
    ")->fetch();} catch (PDOException $e) {
    $daftarBayar = [];
    $statAll = [
        'total_transaksi'   => 0,
        'total_lunas'       => 0,
        'total_menunggu'    => 0,
        'total_dikonfirmasi'=> 0,
        'total_ditolak'     => 0,
    ];}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Pembayaran - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.btn-confirm{background:#d8f3dc;color:#1b4332}
.btn-confirm:hover{background:#b7e4c7}
.btn-reject{background:#fee2e2;color:#dc2626}
.btn-reject:hover{background:#fecaca}
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
        <div class="topbar-title">Verifikasi &amp; Pembayaran Sewa</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <h1 class="pg-title">Daftar Pembayaran Sewa</h1>
                <p class="pg-sub">Kelola verifikasi bukti transfer/QRIS per semester dan pencatatan pembayaran tunai (Cash).</p>
            </div>
            <a href="tambah_cash.php" class="btn-add">
                <i class="ph ph-hand-coins"></i> Catat Pembayaran Cash
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-clock"></i></div>
                <div>
                    <div class="sc-label">Menunggu Verifikasi</div>
                    <div class="sc-val" style="color:#d97706"><?= (int)$statAll['total_menunggu'] ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div>
                <div>
                    <div class="sc-label">Dikonfirmasi (Lunas)</div>
                    <div class="sc-val" style="color:#2d6a4f"><?= (int)$statAll['total_dikonfirmasi'] ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-money"></i></div>
                <div>
                    <div class="sc-label">Total Dana Diterima</div>
                    <div class="sc-val" style="font-size:1.15rem"><?= formatRupiah($statAll['total_lunas']) ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-r"><i class="ph ph-x-circle"></i></div>
                <div>
                    <div class="sc-label">Pembayaran Ditolak</div>
                    <div class="sc-val" style="color:#dc2626"><?= (int)$statAll['total_ditolak'] ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card">
            <form action="index.php" method="GET" class="filter-form">
                <input
                    type="text"
                    name="q"
                    class="filter-input"
                    placeholder="Cari penghuni atau no kamar..."
                    value="<?= e($search) ?>"
                    style="min-width:200px"
                >

                <select name="status_verif" class="filter-input">
                    <option value="">-- Semua Status Verifikasi --</option>
                    <option value="menunggu" <?= $status_verif === 'menunggu' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                    <option value="dikonfirmasi" <?= $status_verif === 'dikonfirmasi' ? 'selected' : '' ?>>Dikonfirmasi</option>
                    <option value="ditolak" <?= $status_verif === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                </select>

                <select name="metode" class="filter-input">
                    <option value="">-- Semua Metode --</option>
                    <option value="Transfer" <?= $metode === 'Transfer' ? 'selected' : '' ?>>Transfer Bank</option>
                    <option value="QRIS" <?= $metode === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                    <option value="Cash" <?= $metode === 'Cash' ? 'selected' : '' ?>>Tunai (Cash)</option>
                </select>

                <select name="status_bayar" class="filter-input">
                    <option value="">-- Status Bayar --</option>
                    <option value="lunas" <?= $status_bayar === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                    <option value="belum_lunas" <?= $status_bayar === 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                </select>

                <button type="submit" class="btn-filter">
                    <i class="ph ph-funnel"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($status_verif) || !empty($metode) || !empty($status_bayar)): ?>
                <a href="index.php" class="btn-reset">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Panel -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-receipt"></i> Data Pembayaran Sewa (<?= count($daftarBayar) ?> Catatan)
                </div>
            </div>

            <?php if (empty($daftarBayar)): ?>
            <div class="empty">
                <i class="ph ph-credit-card"></i>
                <p>Belum ada catatan pembayaran yang sesuai dengan filter.</p>
                <a href="tambah_cash.php" class="btn-add" style="margin-top:.85rem">Catat Pembayaran Cash</a>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Tanggal Bayar</th>
                            <th>Penghuni</th>
                            <th>Kamar &amp; Periode</th>
                            <th>Metode</th>
                            <th>Jumlah Bayar</th>
                            <th>Status Bayar</th>
                            <th>Verifikasi</th>
                            <th>Bukti</th>
                            <th style="width:160px;text-align:center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarBayar as $i => $b): ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.8rem"><?= $i + 1 ?></td>
                            <td style="white-space:nowrap;font-size:.825rem">
                                <?= formatTanggal($b['tanggal_bayar'], 'pendek') ?>
                            </td>
                            <td>
                                <strong style="color:var(--green);font-size:.925rem">
                                    <?= e($b['nama_penghuni']) ?>
                                </strong>
                                <div style="font-size:.73rem;color:var(--muted)"><code>@<?= e($b['username']) ?></code></div>
                            </td>
                            <td>
                                <span class="badge bg-room">
                                    <i class="ph ph-door"></i> Kamar <?= e($b['nomor_kamar']) ?>
                                </span>
                                <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem">
                                    <?= e($b['periode'] ?: '1 Semester') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-method">
                                    <?php if ($b['metode_pembayaran'] === 'Cash'): ?>
                                    <i class="ph ph-money"></i> Cash
                                    <?php elseif ($b['metode_pembayaran'] === 'Transfer'): ?>
                                    <i class="ph ph-bank"></i> Transfer
                                    <?php else: ?>
                                    <i class="ph ph-qr-code"></i> QRIS
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="font-weight:700;white-space:nowrap;color:var(--green)">
                                <?= formatRupiah($b['jumlah_bayar']) ?>
                            </td>
                            <td>
                                <?php if ($b['status'] === 'lunas'): ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Lunas</span>
                                <?php else: ?>
                                <span class="badge bg-r"><i class="ph ph-clock"></i> Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($b['status_verifikasi'] === 'dikonfirmasi'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Dikonfirmasi</span>
                                <?php elseif ($b['status_verifikasi'] === 'ditolak'): ?>
                                <span class="badge bg-r"><i class="ph ph-x-circle"></i> Ditolak</span>
                                <?php else: ?>
                                <span class="badge bg-a"><i class="ph ph-clock"></i> Menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['bukti_pembayaran'])): ?>
                                <a href="../../uploads/bukti/<?= rawurlencode($b['bukti_pembayaran']) ?>" target="_blank" rel="noopener" style="color:var(--green);font-size:.85rem;display:inline-flex;align-items:center;gap:.2rem;font-weight:600" title="Lihat Bukti">
                                    <i class="ph ph-image"></i> Ada
                                </a>
                                <?php else: ?>
                                <span style="color:var(--muted);font-size:.8rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;white-space:nowrap">
                                <a href="detail.php?id=<?= $b['id_pembayaran'] ?>" class="btn-act btn-view" title="Detail Pembayaran">
                                    <i class="ph ph-eye"></i> Detail
                                </a>
                                <?php if ($b['status_verifikasi'] === 'menunggu'): ?>
                                <a href="konfirmasi.php?id=<?= $b['id_pembayaran'] ?>" class="btn-act btn-confirm" title="Konfirmasi">
                                    <i class="ph ph-check"></i>
                                </a>
                                <a href="tolak.php?id=<?= $b['id_pembayaran'] ?>" class="btn-act btn-reject" title="Tolak">
                                    <i class="ph ph-x"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
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