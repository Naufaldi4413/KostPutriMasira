<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

// 1. Filter Parameters
$status_sewa  = trim($_GET['status_sewa'] ?? '');
$filter_kamar = trim($_GET['kamar'] ?? '');
$tgl_dari     = trim($_GET['tgl_dari'] ?? '');
$tgl_sampai   = trim($_GET['tgl_sampai'] ?? '');

$isFiltered = !empty($status_sewa) || !empty($filter_kamar) || !empty($tgl_dari) || !empty($tgl_sampai);

// 2. Ambil Daftar Kamar untuk Dropdown Filter
try {
    $daftarKamarOpt = $pdo->query("SELECT id_kamar, nomor_kamar FROM kamar ORDER BY nomor_kamar ASC")->fetchAll();
} catch (PDOException $e) {
    $daftarKamarOpt = [];
}

// 3. Query Sewa
$where = [];
$params = [];

if (!empty($status_sewa)) {
    $where[] = "s.status = :status_sewa";
    $params['status_sewa'] = $status_sewa;
}
if (!empty($filter_kamar)) {
    $where[] = "s.id_kamar = :kamar";
    $params['kamar'] = (int)$filter_kamar;
}
if (!empty($tgl_dari)) {
    $where[] = "s.tanggal_mulai >= :tgl_dari";
    $params['tgl_dari'] = $tgl_dari;
}
if (!empty($tgl_sampai)) {
    $where[] = "s.tanggal_mulai <= :tgl_sampai";
    $params['tgl_sampai'] = $tgl_sampai;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    $stmtSewa = $pdo->prepare("
        SELECT 
            s.*,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar
        FROM sewa s
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        {$whereSql}
        ORDER BY s.id_sewa DESC
    ");
    $stmtSewa->execute($params);
    $daftarSewa = $stmtSewa->fetchAll();
} catch (PDOException $e) {
    $daftarSewa = [];
}

// Stats
$totalSewa    = count($daftarSewa);
$totalAktif   = count(array_filter($daftarSewa, fn($s) => $s['status'] === 'aktif'));
$totalSelesai = $totalSewa - $totalAktif;
$totalNominal = array_sum(array_map(fn($s) => (float)$s['total_sewa'], $daftarSewa));
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Rekap Sewa - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.report-nav-tabs{display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:2px solid var(--border);padding-bottom:.25rem;overflow-x:auto}
.report-nav-item{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem 1.15rem;border-radius:8px 8px 0 0;font-size:.875rem;font-weight:600;color:var(--muted);text-decoration:none;transition:all .15s ease;white-space:nowrap;border-bottom:3px solid transparent;margin-bottom:-4px}
.report-nav-item:hover{color:var(--green);background:rgba(45,106,79,.05)}
.report-nav-item.active{color:var(--green);border-bottom-color:var(--green);background:#fff;box-shadow:0 -2px 6px rgba(0,0,0,.03)}
.print-header{display:none}
.print-signature{display:none}



@media print {
    .sidebar, .topbar, .btn-print, .filter-card, .report-nav-tabs, .no-print, .sb-foot, .btn-burger, .sidebar-backdrop {
        display: none !important}
    body {
        background: #fff !important;
        color: #000 !important;
        font-size: 10pt !important;
        padding: 0 !important;
        margin: 0 !important}
    .layout {
        display: block !important;
        min-height: auto !important}
    .main {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important}
    .content {
        padding: 0 !important;
        max-width: 100% !important}
    .pg-header {
        display: none !important}
    .print-header {
        display: block !important;
        text-align: center !important;
        margin-bottom: 20px !important;
        border-bottom: 3px double #000 !important;
        padding-bottom: 12px !important}
    .print-kop-title {
        font-size: 16pt !important;
        font-weight: 800 !important;
        letter-spacing: .05em !important;
        margin-bottom: 2px !important}
    .print-kop-sub {
        font-size: 9pt !important;
        color: #333 !important;
        line-height: 1.3 !important}
    .print-report-title {
        font-size: 13pt !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        margin-top: 14px !important;
        text-decoration: underline !important}
    .print-meta {
        font-size: 8.5pt !important;
        color: #444 !important;
        margin-top: 4px !important}
    .panel {
        border: 1px solid #777 !important;
        box-shadow: none !important;
        margin-bottom: 18px !important;
        page-break-inside: avoid !important}
    .panel-head {
        background: #f2f2f2 !important;
        border-bottom: 1px solid #777 !important;
        padding: 6px 10px !important}
    .panel-title {
        font-size: 10pt !important;
        font-weight: 700 !important;
        color: #000 !important}
    table {
        width: 100% !important;
        border-collapse: collapse !important}
    th, td {
        border: 1px solid #777 !important;
        padding: 5px 7px !important;
        font-size: 8.5pt !important;
        color: #000 !important}
    th {
        background: #e9ecef !important;
        font-weight: 700 !important}
    tfoot td {
        background: #f2f2f2 !important;
        font-weight: 700 !important}
    .badge {
        border: 1px solid #777 !important;
        background: transparent !important;
        color: #000 !important;
        font-size: 7.5pt !important;
        padding: 1px 4px !important}
    .stats {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 8px !important;
        margin-bottom: 14px !important;
        page-break-inside: avoid !important}
    .sc {
        border: 1px solid #777 !important;
        padding: 6px 8px !important;
        background: #fff !important}
    .sc-ico {
        display: none !important}
    .sc-val {
        font-size: 11pt !important;
        color: #000 !important}
    .sc-label {
        font-size: 7pt !important}
    .print-signature {
        display: flex !important;
        justify-content: flex-end !important;
        margin-top: 30px !important;
        page-break-inside: avoid !important}
    .sig-box {
        text-align: center !important;
        width: 240px !important;
        font-size: 9.5pt !important}
    .sig-space {
        height: 65px !important}
    .sig-name {
        font-weight: 700 !important;
        text-decoration: underline !important}
}
</style>
</head>
<body>
<div class="layout">

<!-- Sidebar Backdrop -->
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
        <a href="../dashboard.php" class="sb-link"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="../kamar/index.php" class="sb-link"><i class="ph ph-door"></i> Kelola Kamar</a>
        <a href="../fasilitas/index.php" class="sb-link"><i class="ph ph-sparkle"></i> Kelola Fasilitas</a>
        <a href="../penghuni/index.php" class="sb-link"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
        <a href="../sewa/index.php" class="sb-link"><i class="ph ph-key"></i> Kelola Sewa</a>
        <a href="../pembayaran/index.php" class="sb-link"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        <a href="index.php" class="sb-link active"><i class="ph ph-chart-bar"></i> Laporan</a>
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

<!-- Main -->
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Laporan Pengelolaan Kost</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">

        <!-- KOP CETAK (Hanya Muncul Saat Print) -->
        <div class="print-header">
            <div class="print-kop-title">KOST PUTRI MASIRA</div>
            <div class="print-kop-sub">
                Hunian Kost Khusus Putri<br>
                Kost Putri Masira &bull; WhatsApp: 0822-3305-3344
            </div>
            <div class="print-report-title">LAPORAN REKAP TRANSAKSI SEWA</div>
            <div class="print-meta">
                Dicetak: <?= date('d/m/Y H:i') ?> WIB &bull; Oleh: <?= e($user['nama'] ?? 'Pengelola') ?>
                <?php if ($isFiltered): ?> &bull; <em>(Hasil Filter Diterapkan)</em><?php endif; ?>
            </div>
        </div>

        <!-- REPORT TABS -->
        <div class="report-nav-tabs no-print">
            <a href="kamar.php" class="report-nav-item"><i class="ph ph-door"></i> Rekap Kamar</a>
            <a href="penghuni.php" class="report-nav-item"><i class="ph ph-users-three"></i> Rekap Penghuni</a>
            <a href="sewa.php" class="report-nav-item active"><i class="ph ph-key"></i> Rekap Sewa</a>
            <a href="pembayaran.php" class="report-nav-item"><i class="ph ph-credit-card"></i> Rekap Pembayaran</a>
        </div>

        <!-- PAGE HEADER -->
        <div class="pg-header">
            <div>
                <h1 class="pg-title">Laporan Rekap Sewa</h1>
                <p class="pg-sub">Rekapitulasi riwayat dan transaksi sewa kamar, durasi kontrak, total biaya, dan status sewa.</p>
            </div>
            <div>
                <button type="button" onclick="window.print()" class="btn-print">
                    <i class="ph ph-printer"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="filter-card no-print">
            <form action="sewa.php" method="GET" class="filter-form">
                <div class="fg">
                    <label class="fl" for="status_sewa">Status Sewa</label>
                    <select id="status_sewa" name="status_sewa" class="fi">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?= $status_sewa === 'aktif' ? 'selected' : '' ?>>Aktif (Berjalan)</option>
                        <option value="selesai" <?= $status_sewa === 'selesai' ? 'selected' : '' ?>>Selesai (Berakhir)</option>
                    </select>
                </div>

                <div class="fg">
                    <label class="fl" for="kamar">Unit Kamar</label>
                    <select id="kamar" name="kamar" class="fi">
                        <option value="">Semua Kamar</option>
                        <?php foreach ($daftarKamarOpt as $km): ?>
                        <option value="<?= $km['id_kamar'] ?>" <?= $filter_kamar == $km['id_kamar'] ? 'selected' : '' ?>>
                            Kamar <?= e($km['nomor_kamar']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fg">
                    <label class="fl" for="tgl_dari">Mulai Dari</label>
                    <input type="date" id="tgl_dari" name="tgl_dari" class="fi" value="<?= e($tgl_dari) ?>">
                </div>

                <div class="fg">
                    <label class="fl" for="tgl_sampai">Sampai Tanggal</label>
                    <input type="date" id="tgl_sampai" name="tgl_sampai" class="fi" value="<?= e($tgl_sampai) ?>">
                </div>

                <div class="filter-btns">
                    <button type="submit" class="btn-filter"><i class="ph ph-funnel"></i> Filter</button>
                    <?php if ($isFiltered): ?>
                    <a href="sewa.php" class="btn-reset"><i class="ph ph-arrow-counter-clockwise"></i> Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- STATS CARDS -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-key"></i></div>
                <div>
                    <div class="sc-label">Total Transaksi</div>
                    <div class="sc-val"><?= $totalSewa ?></div>
                    <div class="sc-sub">Kontrak sewa</div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div>
                <div>
                    <div class="sc-label">Sewa Aktif</div>
                    <div class="sc-val"><?= $totalAktif ?></div>
                    <div class="sc-sub">Sedang menghuni</div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-clock"></i></div>
                <div>
                    <div class="sc-label">Sewa Selesai</div>
                    <div class="sc-val"><?= $totalSelesai ?></div>
                    <div class="sc-sub">Masa sewa usai</div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-p"><i class="ph ph-coins"></i></div>
                <div>
                    <div class="sc-label">Total Nilai Sewa</div>
                    <div class="sc-val" style="font-size:1.15rem"><?= formatRupiah($totalNominal) ?></div>
                    <div class="sc-sub">Akumulasi tarif</div>
                </div>
            </div>
        </div>

        <!-- TABLE PANEL -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-key"></i> Rekapitulasi Data Sewa
                </div>
                <div style="font-size:.8125rem;color:var(--muted)">
                    Menampilkan <strong><?= count($daftarSewa) ?></strong> data
                </div>
            </div>
            <div class="table-wrap">
                <?php if (empty($daftarSewa)): ?>
                <div class="empty">
                    <i class="ph ph-magnifying-glass" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.5"></i>
                    Tidak ada transaksi sewa yang sesuai dengan kriteria filter.
                </div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Nama Penghuni</th>
                            <th>Kamar</th>
                            <th>Periode</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Total Sewa</th>
                            <th>Status Sewa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarSewa as $idx => $sw): ?>
                        <tr>
                            <td style="color:var(--muted)"><?= $idx + 1 ?></td>
                            <td>
                                <strong><?= e($sw['nama_penghuni']) ?></strong>
                                <div style="font-size:.72rem;color:var(--muted)">@<?= e($sw['username']) ?></div>
                            </td>
                            <td>
                                <strong>Kamar <?= e($sw['nomor_kamar']) ?></strong>
                                <div style="font-size:.72rem;color:var(--muted)"><?= e($sw['tipe_kamar'] ?: 'Standar') ?></div>
                            </td>
                            <td><?= e($sw['periode'] ?: '—') ?></td>
                            <td style="white-space:nowrap">
                                <?= !empty($sw['tanggal_mulai']) ? formatTanggal($sw['tanggal_mulai'], 'pendek') : '—' ?>
                            </td>
                            <td style="white-space:nowrap">
                                <?= !empty($sw['tanggal_selesai']) ? formatTanggal($sw['tanggal_selesai'], 'pendek') : '—' ?>
                            </td>
                            <td style="font-weight:600;white-space:nowrap">
                                <?= formatRupiah($sw['total_sewa']) ?>
                            </td>
                            <td>
                                <?php if ($sw['status'] === 'aktif'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Aktif</span>
                                <?php else: ?>
                                <span class="badge" style="background:#e5e7eb;color:#374151"><i class="ph ph-check"></i> Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" style="text-align:right">TOTAL NILAI KONTRAK SEWA:</td>
                            <td colspan="2" style="color:var(--green);font-size:.95rem">
                                <?= formatRupiah($totalNominal) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TANDA TANGAN (Hanya Muncul Saat Print) -->
        <div class="print-signature">
            <div class="sig-box">
                <div>Mengetahui,</div>
                <div style="font-weight:600;margin-top:2px">Pengelola Kost Putri Masira</div>
                <div class="sig-space"></div>
                <div class="sig-name">( <?= e($user['nama'] ?? 'Pengelola') ?> )</div>
            </div>
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
