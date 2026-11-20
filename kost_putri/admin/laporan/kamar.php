<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

// 1. Filter Parameters
$q_kamar      = trim($_GET['q_kamar'] ?? '');
$status_kamar = trim($_GET['status_kamar'] ?? '');
$tipe_kamar   = trim($_GET['tipe_kamar'] ?? '');

$isFiltered = !empty($q_kamar) || !empty($status_kamar) || !empty($tipe_kamar);

// 2. Ambil Pilihan Tipe Kamar
try {
    $tipeList = $pdo->query("SELECT DISTINCT tipe_kamar FROM kamar WHERE tipe_kamar IS NOT NULL AND tipe_kamar != '' ORDER BY tipe_kamar ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tipeList = [];
}

// 3. Query Kamar & Penghuni Aktif
$where = [];
$params = [];

if (!empty($q_kamar)) {
    $where[] = "k.nomor_kamar LIKE :q_kamar";
    $params['q_kamar'] = '%' . $q_kamar . '%';
}
if (!empty($tipe_kamar)) {
    $where[] = "k.tipe_kamar = :tipe_kamar";
    $params['tipe_kamar'] = $tipe_kamar;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    $stmtKamar = $pdo->prepare("
        SELECT 
            k.id_kamar, k.nomor_kamar, k.tipe_kamar, k.harga, k.kapasitas,
            COUNT(s.id_sewa) AS jumlah_penghuni,
            GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR ', ') AS daftar_penghuni
        FROM kamar k
        LEFT JOIN sewa s ON k.id_kamar = s.id_kamar AND s.status = 'aktif'
        LEFT JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        LEFT JOIN users u ON p.id_user = u.id_user
        {$whereSql}
        GROUP BY k.id_kamar, k.nomor_kamar, k.tipe_kamar, k.harga, k.kapasitas
        ORDER BY k.nomor_kamar ASC
    ");
    $stmtKamar->execute($params);
    $rawKamar = $stmtKamar->fetchAll();
} catch (PDOException $e) {
    $rawKamar = [];
}

// 4. Hitung Status Real & Filter Status
$daftarKamar = [];
$totalKamar = 0;
$totalKapasitas = 0;
$totalPenghuni = 0;
$kamarKosong = 0;
$kamarTerisi = 0;
$kamarPenuh  = 0;

foreach ($rawKamar as $km) {
    $kap = max(1, (int)$km['kapasitas']);
    $jml = (int)$km['jumlah_penghuni'];

    if ($jml === 0) {
        $st = 'kosong';
    } elseif ($jml < $kap) {
        $st = 'terisi';
    } else {
        $st = 'penuh';
    }

    $km['status_hitung'] = $st;

    // Filter status jika dipilih
    if (!empty($status_kamar) && $status_kamar !== $st) {
        continue;
    }

    $daftarKamar[] = $km;
    $totalKamar++;
    $totalKapasitas += $kap;
    $totalPenghuni += $jml;

    if ($st === 'kosong') $kamarKosong++;
    elseif ($st === 'terisi') $kamarTerisi++;
    else $kamarPenuh++;
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Rekap Kamar - Kost Putri Masira</title>
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
            <div class="print-report-title">LAPORAN REKAP DATA KAMAR</div>
            <div class="print-meta">
                Dicetak: <?= date('d/m/Y H:i') ?> WIB &bull; Oleh: <?= e($user['nama'] ?? 'Pengelola') ?>
                <?php if ($isFiltered): ?> &bull; <em>(Hasil Filter Diterapkan)</em><?php endif; ?>
            </div>
        </div>

        <!-- REPORT TABS -->
        <div class="report-nav-tabs no-print">
            <a href="kamar.php" class="report-nav-item active"><i class="ph ph-door"></i> Rekap Kamar</a>
            <a href="penghuni.php" class="report-nav-item"><i class="ph ph-users-three"></i> Rekap Penghuni</a>
            <a href="sewa.php" class="report-nav-item"><i class="ph ph-key"></i> Rekap Sewa</a>
            <a href="pembayaran.php" class="report-nav-item"><i class="ph ph-credit-card"></i> Rekap Pembayaran</a>
        </div>

        <!-- PAGE HEADER -->
        <div class="pg-header">
            <div>
                <h1 class="pg-title">Laporan Rekap Kamar</h1>
                <p class="pg-sub">Rekapitulasi kondisi kamar, kapasitas, jumlah penghuni aktif, dan ketersediaan.</p>
            </div>
            <div>
                <button type="button" onclick="window.print()" class="btn-print">
                    <i class="ph ph-printer"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="filter-card no-print">
            <form action="kamar.php" method="GET" class="filter-form">
                <div class="fg">
                    <label class="fl" for="q_kamar">Cari No Kamar</label>
                    <input type="text" id="q_kamar" name="q_kamar" class="fi" placeholder="Contoh: A1, 102..." value="<?= e($q_kamar) ?>">
                </div>

                <div class="fg">
                    <label class="fl" for="status_kamar">Status Kamar</label>
                    <select id="status_kamar" name="status_kamar" class="fi">
                        <option value="">Semua Status</option>
                        <option value="kosong" <?= $status_kamar === 'kosong' ? 'selected' : '' ?>>Kosong (0 Penghuni)</option>
                        <option value="terisi" <?= $status_kamar === 'terisi' ? 'selected' : '' ?>>Terisi (Ada Ruang)</option>
                        <option value="penuh" <?= $status_kamar === 'penuh' ? 'selected' : '' ?>>Penuh (Kapasitas Maks)</option>
                    </select>
                </div>

                <div class="fg">
                    <label class="fl" for="tipe_kamar">Tipe Kamar</label>
                    <select id="tipe_kamar" name="tipe_kamar" class="fi">
                        <option value="">Semua Tipe</option>
                        <?php foreach ($tipeList as $t): ?>
                        <option value="<?= e($t) ?>" <?= $tipe_kamar === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-btns">
                    <button type="submit" class="btn-filter"><i class="ph ph-funnel"></i> Filter</button>
                    <?php if ($isFiltered): ?>
                    <a href="kamar.php" class="btn-reset"><i class="ph ph-arrow-counter-clockwise"></i> Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- STATS CARDS -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-door"></i></div>
                <div>
                    <div class="sc-label">Total Kamar</div>
                    <div class="sc-val"><?= $totalKamar ?></div>
                    <div class="sc-sub">Unit terdata</div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div>
                <div>
                    <div class="sc-label">Kamar Kosong</div>
                    <div class="sc-val"><?= $kamarKosong ?></div>
                    <div class="sc-sub">Siap disewa</div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-users"></i></div>
                <div>
                    <div class="sc-label">Kamar Terisi</div>
                    <div class="sc-val"><?= $kamarTerisi ?></div>
                    <div class="sc-sub">Sebagian terisi</div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-r"><i class="ph ph-lock"></i></div>
                <div>
                    <div class="sc-label">Kamar Penuh</div>
                    <div class="sc-val"><?= $kamarPenuh ?></div>
                    <div class="sc-sub">Kapasitas penuh</div>
                </div>
            </div>
        </div>

        <!-- TABLE PANEL -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-door"></i> Rekapitulasi Data Kamar
                </div>
                <div style="font-size:.8125rem;color:var(--muted)">
                    Menampilkan <strong><?= count($daftarKamar) ?></strong> data
                </div>
            </div>
            <div class="table-wrap">
                <?php if (empty($daftarKamar)): ?>
                <div class="empty">
                    <i class="ph ph-magnifying-glass" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.5"></i>
                    Tidak ada data kamar yang sesuai dengan kriteria filter.
                </div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>No Kamar</th>
                            <th>Tipe</th>
                            <th>Harga</th>
                            <th>Kapasitas</th>
                            <th>Jumlah Penghuni</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarKamar as $idx => $k): ?>
                        <tr>
                            <td style="color:var(--muted)"><?= $idx + 1 ?></td>
                            <td><strong>Kamar <?= e($k['nomor_kamar']) ?></strong></td>
                            <td><?= e($k['tipe_kamar'] ?: 'Standar') ?></td>
                            <td style="white-space:nowrap"><?= formatRupiah($k['harga']) ?></td>
                            <td><?= (int)$k['kapasitas'] ?> Orang</td>
                            <td>
                                <strong><?= (int)$k['jumlah_penghuni'] ?> Orang</strong>
                                <?php if (!empty($k['daftar_penghuni'])): ?>
                                <div style="font-size:.72rem;color:var(--muted);margin-top:.15rem">
                                    (<?= e($k['daftar_penghuni']) ?>)
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($k['status_hitung'] === 'penuh'): ?>
                                <span class="badge bg-r"><i class="ph ph-lock"></i> Penuh</span>
                                <?php elseif ($k['status_hitung'] === 'terisi'): ?>
                                <span class="badge bg-a"><i class="ph ph-users"></i> Terisi</span>
                                <?php else: ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Kosong</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
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
