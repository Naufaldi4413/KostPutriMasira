<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$tab = $_GET['tab'] ?? '';
if ($tab === 'penghuni') {
    redirect('penghuni.php');
} elseif ($tab === 'sewa') {
    redirect('sewa.php');
} elseif ($tab === 'pembayaran') {
    redirect('pembayaran.php');
} else {
    redirect('kamar.php');
}

$user = currentUser();
$flash = getFlash();

// 1. Filter Parameters
$tgl_dari       = trim($_GET['tgl_dari'] ?? '');
$tgl_sampai     = trim($_GET['tgl_sampai'] ?? '');
$filter_periode = trim($_GET['periode'] ?? '');
$filter_status  = trim($_GET['status_bayar'] ?? '');
$filter_metode  = trim($_GET['metode'] ?? '');

$isFiltered = !empty($tgl_dari) || !empty($tgl_sampai) || !empty($filter_periode) || !empty($filter_status) || !empty($filter_metode);

// 2. Rekap Kamar (Perhitungan Real Berdasarkan Kapasitas & Sewa Aktif)
try {
    $stmtKamar = $pdo->query("
        SELECT 
            k.id_kamar, k.nomor_kamar, k.tipe_kamar, k.harga, k.kapasitas,
            COUNT(s.id_sewa) AS penghuni_aktif,
            GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR ', ') AS daftar_penghuni
        FROM kamar k
        LEFT JOIN sewa s ON k.id_kamar = s.id_kamar AND s.status = 'aktif'
        LEFT JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        LEFT JOIN users u ON p.id_user = u.id_user
        GROUP BY k.id_kamar
        ORDER BY k.nomor_kamar ASC
    ");
    $daftarKamar = $stmtKamar->fetchAll();}
 catch (PDOException $e) {
    $daftarKamar = [];}

$totalKamar = count($daftarKamar);
$totalKapasitas = 0;
$totalPenghuniAktifKamar = 0;
$kamarKosong = 0;
$kamarTerisi = 0;
$kamarPenuh  = 0;

foreach ($daftarKamar as &$km) {
    $kap   = max(1, (int)$km['kapasitas']);
    $aktif = (int)$km['penghuni_aktif'];
    $totalKapasitas += $kap;
    $totalPenghuniAktifKamar += $aktif;

    if ($aktif === 0) {
        $km['status_hitung'] = 'kosong';
        $kamarKosong++;} elseif ($aktif < $kap) {
        $km['status_hitung'] = 'terisi';
        $kamarTerisi++;} else {
        $km['status_hitung'] = 'penuh';
        $kamarPenuh++;}
}
unset($km);

// 3. Rekap Penghuni
try {
    $totalPenghuniAktif    = (int)$pdo->query("SELECT COUNT(*) FROM penghuni WHERE status = 'aktif'")->fetchColumn();
    $totalPenghuniNonaktif = (int)$pdo->query("SELECT COUNT(*) FROM penghuni WHERE status = 'nonaktif'")->fetchColumn();

    $stmtPenghuniAktif = $pdo->query("
        SELECT 
            p.id_penghuni, p.nik, p.no_hp, p.alamat, p.tanggal_masuk, p.status AS status_penghuni,
            u.nama, u.username,
            k.nomor_kamar, k.tipe_kamar
        FROM penghuni p
        JOIN users u ON p.id_user = u.id_user
        LEFT JOIN sewa s ON p.id_penghuni = s.id_penghuni AND s.status = 'aktif'
        LEFT JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE p.status = 'aktif'
        ORDER BY u.nama ASC
    ");
    $daftarPenghuniAktif = $stmtPenghuniAktif->fetchAll();}
 catch (PDOException $e) {
    $totalPenghuniAktif = 0;
    $totalPenghuniNonaktif = 0;
    $daftarPenghuniAktif = [];}

// 4. Rekap Kontrak Sewa
try {
    $stmtSewa = $pdo->query("
        SELECT 
            s.*,
            p.no_hp,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar
        FROM sewa s
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        ORDER BY s.status ASC, s.tanggal_mulai DESC
    ");
    $daftarSewa = $stmtSewa->fetchAll();}
 catch (PDOException $e) {
    $daftarSewa = [];}

// 5. Rekap Pembayaran & Keuangan (Mendukung Filter)
$where  = [];
$params = [];

if (!empty($tgl_dari)) {
    $where[] = "pb.tanggal_bayar >= :tgl_dari";
    $params['tgl_dari'] = $tgl_dari;}
if (!empty($tgl_sampai)) {
    $where[] = "pb.tanggal_bayar <= :tgl_sampai";
    $params['tgl_sampai'] = $tgl_sampai;}
if (!empty($filter_periode)) {
    $where[] = "s.periode = :periode";
    $params['periode'] = $filter_periode;}
if (!empty($filter_status)) {
    $where[] = "pb.status = :status_bayar";
    $params['status_bayar'] = $filter_status;}
if (!empty($filter_metode)) {
    $where[] = "pb.metode_pembayaran = :metode";
    $params['metode'] = $filter_metode;}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    $stmtBayar = $pdo->prepare("
        SELECT 
            pb.*,
            s.periode, s.tanggal_mulai, s.tanggal_selesai, s.total_sewa,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar
        FROM pembayaran pb
        JOIN sewa s ON pb.id_sewa = s.id_sewa
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        {$whereSql}
        ORDER BY pb.tanggal_bayar DESC, pb.id_pembayaran DESC
    ");
    $stmtBayar->execute($params);
    $daftarBayar = $stmtBayar->fetchAll();

    $periodeList = $pdo->query("SELECT DISTINCT periode FROM sewa WHERE periode IS NOT NULL AND periode != '' ORDER BY periode ASC")->fetchAll(PDO::FETCH_COLUMN);}
 catch (PDOException $e) {
    $daftarBayar = [];
    $periodeList = [];}

// Perhitungan Rekap Keuangan:
// PENTING:
// - Hanya status_verifikasi = 'dikonfirmasi' yang dihitung ke total pemasukan sah!
// - Pembayaran 'menunggu' dan 'ditolak' TIDAK dihitung sebagai pemasukan.
$totalPemasukanSah  = 0;
$totalDikonfirmasi  = 0;
$totalMenunggu      = 0;
$nominalMenunggu    = 0;
$totalDitolak       = 0;
$nominalDitolak     = 0;
$pemasukanPerMetode = ['Cash' => 0, 'Transfer' => 0, 'QRIS' => 0];
$pemasukanPerPeriode = [];

foreach ($daftarBayar as $b) {
    $metodeB  = $b['metode_pembayaran'] ?? 'Cash';
    $periodeB = $b['periode'] ?: '1 Semester';
    $nominalB = (float)$b['jumlah_bayar'];

    if ($b['status_verifikasi'] === 'dikonfirmasi') {
        $totalDikonfirmasi++;
        $totalPemasukanSah += $nominalB;
        if (isset($pemasukanPerMetode[$metodeB])) {
            $pemasukanPerMetode[$metodeB] += $nominalB;} else {
            $pemasukanPerMetode[$metodeB] = $nominalB;}
        $pemasukanPerPeriode[$periodeB] = ($pemasukanPerPeriode[$periodeB] ?? 0) + $nominalB;} elseif ($b['status_verifikasi'] === 'menunggu') {
        $totalMenunggu++;
        $nominalMenunggu += $nominalB;} elseif ($b['status_verifikasi'] === 'ditolak') {
        $totalDitolak++;
        $nominalDitolak += $nominalB;}
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pengelolaan Kost - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem;margin-bottom:1.5rem}
@media(max-width:1200px){
    .grid2{grid-template-columns:minmax(0,1fr)}
}
.pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.pay-item{background:#fafaf9;border:1px solid var(--border);border-radius:8px;padding:.85rem}
.pay-label{font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;margin-bottom:.2rem}
.pay-val{font-size:1.05rem;font-weight:700;color:#1a1a18}
.print-header{display:none}
.print-signature{display:none}



@media print {
    .sidebar, .topbar, .btn-print, .filter-card, .no-print, .sb-foot {
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
    
    .panel-body {
        padding: 8px 10px !important}
    
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
        font-weight: 700 !important;
        border-top: 2px solid #000 !important}
    
    .badge {
        border: 1px solid #777 !important;
        background: transparent !important;
        color: #000 !important;
        font-size: 7.5pt !important;
        padding: 1px 4px !important}
    
    
    .stats {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
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
    
    .pay-grid {
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 6px !important}
    .pay-item {
        border: 1px solid #777 !important;
        padding: 5px !important;
        background: #fff !important}
    .pay-val {
        font-size: 9.5pt !important;
        color: #000 !important}
    
    
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
@media(max-width:768px){
    .pay-grid{grid-template-columns:1fr !important}
}
</style>
</head>
<body>
<div class="layout">

<!-- Sidebar Admin -->
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
        <a href="../pembayaran/index.php" class="sb-link"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        <a href="index.php" class="sb-link active"><i class="ph ph-file-text"></i> Laporan</a>
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
        <div class="topbar-title">Laporan Pengelolaan Kost</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">

        <!-- Kop Surat Laporan (Khusus Print) -->
        <div class="print-header">
            <div class="print-kop-title">KOST PUTRI MASIRA</div>
            <div class="print-kop-sub">
                Hunian Nyaman, Tenang, dan Bersih Khusus Putri<br>
                Kost Putri Masira | WhatsApp: +62 822-3305-3344
            </div>
            <div class="print-report-title">Laporan Rekapitulasi Operasional &amp; Keuangan</div>
            <div class="print-meta">
                <?php if ($isFiltered): ?>
                    Filter Aktif: 
                    <?= !empty($tgl_dari) ? 'Dari: ' . formatTanggal($tgl_dari) : '' ?>
                    <?= !empty($tgl_sampai) ? ' s/d ' . formatTanggal($tgl_sampai) : '' ?>
                    <?= !empty($filter_periode) ? ' | Periode: ' . e($filter_periode) : '' ?>
                    <?= !empty($filter_status) ? ' | Status: ' . e($filter_status) : '' ?>
                    <?= !empty($filter_metode) ? ' | Metode: ' . e($filter_metode) : '' ?>
                <?php else: ?>
                    Periode Data: Keseluruhan Transaksi Real-time
                <?php endif; ?>
                &bull; Dicetak pada: <?= date('d/m/Y H:i') ?> WIB &bull; Oleh: <?= e($user['nama'] ?? 'Pengelola') ?>
            </div>
        </div>

        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Page Header (Screen) -->
        <div class="pg-header">
            <div>
                <h1 class="pg-title">Laporan Operasional &amp; Keuangan</h1>
                <p class="pg-sub">Rekapitulasi data real kamar, penghuni aktif, kontrak sewa, pembayaran, dan pendapatan kost.</p>
            </div>
            <button type="button" onclick="window.print()" class="btn-print">
                <i class="ph ph-printer" style="font-size:1.1rem"></i> Cetak Laporan
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card">
            <form action="index.php" method="GET" class="filter-form">
                <div class="fg">
                    <label class="fl">Tanggal Bayar Dari</label>
                    <input type="date" name="tgl_dari" class="fi" value="<?= e($tgl_dari) ?>">
                </div>

                <div class="fg">
                    <label class="fl">Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" class="fi" value="<?= e($tgl_sampai) ?>">
                </div>

                <div class="fg">
                    <label class="fl">Periode Sewa</label>
                    <select name="periode" class="fi">
                        <option value="">Semua Periode</option>
                        <?php foreach ($periodeList as $p): ?>
                        <option value="<?= e($p) ?>" <?= $filter_periode === $p ? 'selected' : '' ?>>
                            <?= e($p) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fg">
                    <label class="fl">Status Tagihan</label>
                    <select name="status_bayar" class="fi">
                        <option value="">Semua Status</option>
                        <option value="lunas" <?= $filter_status === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="belum_lunas" <?= $filter_status === 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                    </select>
                </div>

                <div class="fg">
                    <label class="fl">Metode Bayar</label>
                    <select name="metode" class="fi">
                        <option value="">Semua Metode</option>
                        <option value="Cash" <?= $filter_metode === 'Cash' ? 'selected' : '' ?>>Cash (Tunai)</option>
                        <option value="Transfer" <?= $filter_metode === 'Transfer' ? 'selected' : '' ?>>Transfer Bank</option>
                        <option value="QRIS" <?= $filter_metode === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                    </select>
                </div>

                <div class="filter-btns">
                    <button type="submit" class="btn-filter">
                        <i class="ph ph-funnel"></i> Filter
                    </button>
                    <?php if ($isFiltered): ?>
                    <a href="index.php" class="btn-reset" title="Hapus Filter">
                        <i class="ph ph-arrows-counter-clockwise"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- 5. REKAP KEUANGAN -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-chart-pie-slice"></i> Rekapitulasi Keuangan &amp; Pemasukan Sah
                </div>
                <span class="badge bg-g"><i class="ph ph-shield-check"></i> Hanya Status Dikonfirmasi</span>
            </div>
            <div class="panel-body">
                <!-- Statistik Keuangan -->
                <div class="stats" style="margin-bottom:1.25rem">
                    <div class="sc">
                        <div class="sc-ico ico-g"><i class="ph ph-money"></i></div>
                        <div>
                            <div class="sc-label">Total Pemasukan Sah</div>
                            <div class="sc-val" style="color:var(--green);font-size:1.35rem">
                                <?= formatRupiah($totalPemasukanSah) ?>
                            </div>
                            <div class="sc-sub"><?= $totalDikonfirmasi ?> transaksi dikonfirmasi</div>
                        </div>
                    </div>

                    <div class="sc">
                        <div class="sc-ico ico-b"><i class="ph ph-check-circle"></i></div>
                        <div>
                            <div class="sc-label">Pembayaran Terkonfirmasi</div>
                            <div class="sc-val" style="color:#1d4ed8"><?= $totalDikonfirmasi ?></div>
                            <div class="sc-sub">Status lunas sah</div>
                        </div>
                    </div>

                    <div class="sc">
                        <div class="sc-ico ico-a"><i class="ph ph-clock"></i></div>
                        <div>
                            <div class="sc-label">Menunggu Verifikasi</div>
                            <div class="sc-val" style="color:#e67e22"><?= $totalMenunggu ?></div>
                            <div class="sc-sub"><?= formatRupiah($nominalMenunggu) ?> (belum dihitung)</div>
                        </div>
                    </div>

                    <div class="sc">
                        <div class="sc-ico ico-r"><i class="ph ph-x-circle"></i></div>
                        <div>
                            <div class="sc-label">Pembayaran Ditolak</div>
                            <div class="sc-val" style="color:#dc2626"><?= $totalDitolak ?></div>
                            <div class="sc-sub"><?= formatRupiah($nominalDitolak) ?> (dibatalkan)</div>
                        </div>
                    </div>
                </div>

                <!-- Breakdown Metode & Periode -->
                <div class="grid2" style="margin-bottom:0">
                    <div style="background:#fafaf9;border:1px solid var(--border);border-radius:8px;padding:1rem">
                        <strong style="font-size:.85rem;display:block;margin-bottom:.65rem;color:#1a1a18">
                            <i class="ph ph-credit-card"></i> Pemasukan Berdasarkan Metode (Sah)
                        </strong>
                        <div class="pay-grid">
                            <div class="pay-item">
                                <div class="pay-label">Cash (Tunai)</div>
                                <div class="pay-val"><?= formatRupiah($pemasukanPerMetode['Cash'] ?? 0) ?></div>
                            </div>
                            <div class="pay-item">
                                <div class="pay-label">Transfer Bank</div>
                                <div class="pay-val"><?= formatRupiah($pemasukanPerMetode['Transfer'] ?? 0) ?></div>
                            </div>
                            <div class="pay-item">
                                <div class="pay-label">QRIS</div>
                                <div class="pay-val"><?= formatRupiah($pemasukanPerMetode['QRIS'] ?? 0) ?></div>
                            </div>
                            <div class="pay-item">
                                <div class="pay-label">Total Sah</div>
                                <div class="pay-val" style="color:var(--green)"><?= formatRupiah($totalPemasukanSah) ?></div>
                            </div>
                        </div>
                    </div>

                    <div style="background:#fafaf9;border:1px solid var(--border);border-radius:8px;padding:1rem">
                        <strong style="font-size:.85rem;display:block;margin-bottom:.65rem;color:#1a1a18">
                            <i class="ph ph-calendar"></i> Pemasukan Berdasarkan Periode Sewa (Sah)
                        </strong>
                        <?php if (empty($pemasukanPerPeriode)): ?>
                        <div style="font-size:.825rem;color:var(--muted);padding:.5rem 0">Belum ada pemasukan terkonfirmasi pada periode yang dipilih.</div>
                        <?php else: ?>
                        <div class="pay-grid">
                            <?php foreach ($pemasukanPerPeriode as $per => $nom): ?>
                            <div class="pay-item">
                                <div class="pay-label">Periode <?= e($per) ?></div>
                                <div class="pay-val" style="color:var(--green)"><?= formatRupiah($nom) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. REKAP KAMAR -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-door"></i> Rekapitulasi Unit Kamar &amp; Kapasitas
                </div>
                <div style="font-size:.8rem;color:var(--muted)">
                    Total: <strong><?= $totalKamar ?></strong> Kamar &bull; Kapasitas: <strong><?= $totalKapasitas ?></strong> Orang &bull; Terisi: <strong><?= $totalPenghuniAktifKamar ?></strong> Orang
                </div>
            </div>
            <div class="panel-body">
                <div class="stats" style="margin-bottom:1.25rem">
                    <div class="sc">
                        <div class="sc-ico ico-b"><i class="ph ph-door"></i></div>
                        <div>
                            <div class="sc-label">Total Kamar</div>
                            <div class="sc-val"><?= $totalKamar ?></div>
                            <div class="sc-sub">Unit terdaftar</div>
                        </div>
                    </div>
                    <div class="sc">
                        <div class="sc-ico ico-g"><i class="ph ph-check"></i></div>
                        <div>
                            <div class="sc-label">Kamar Kosong</div>
                            <div class="sc-val" style="color:var(--green)"><?= $kamarKosong ?></div>
                            <div class="sc-sub">0 penghuni aktif</div>
                        </div>
                    </div>
                    <div class="sc">
                        <div class="sc-ico ico-a"><i class="ph ph-users"></i></div>
                        <div>
                            <div class="sc-label">Kamar Terisi</div>
                            <div class="sc-val" style="color:#e67e22"><?= $kamarTerisi ?></div>
                            <div class="sc-sub">Sebagian terisi</div>
                        </div>
                    </div>
                    <div class="sc">
                        <div class="sc-ico ico-r"><i class="ph ph-prohibit"></i></div>
                        <div>
                            <div class="sc-label">Kamar Penuh</div>
                            <div class="sc-val" style="color:#dc2626"><?= $kamarPenuh ?></div>
                            <div class="sc-sub">Kapasitas tercapai</div>
                        </div>
                    </div>
                    <div class="sc">
                        <div class="sc-ico ico-p"><i class="ph ph-users-three"></i></div>
                        <div>
                            <div class="sc-label">Penghuni Aktif</div>
                            <div class="sc-val" style="color:#7c3aed"><?= $totalPenghuniAktifKamar ?></div>
                            <div class="sc-sub">Dari <?= $totalKapasitas ?> kapasitas</div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:100px">No Kamar</th>
                                <th>Tipe</th>
                                <th>Tarif / Semester</th>
                                <th>Kapasitas</th>
                                <th>Penghuni Aktif</th>
                                <th>Status Okupansi (Real)</th>
                                <th>Nama Penghuni yang Menempati</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarKamar as $km): ?>
                            <tr>
                                <td><strong>Kamar <?= e($km['nomor_kamar']) ?></strong></td>
                                <td><?= e($km['tipe_kamar'] ?: 'Standar') ?></td>
                                <td style="white-space:nowrap;font-weight:600"><?= formatRupiah($km['harga']) ?></td>
                                <td><?= (int)$km['kapasitas'] ?> Orang</td>
                                <td><?= (int)$km['penghuni_aktif'] ?> Orang</td>
                                <td>
                                    <?php if ($km['status_hitung'] === 'kosong'): ?>
                                    <span class="badge bg-g"><i class="ph ph-check"></i> Kosong</span>
                                    <?php elseif ($km['status_hitung'] === 'terisi'): ?>
                                    <span class="badge bg-a"><i class="ph ph-users"></i> Terisi</span>
                                    <?php else: ?>
                                    <span class="badge bg-r"><i class="ph ph-prohibit"></i> Penuh</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($km['daftar_penghuni']) ? e($km['daftar_penghuni']) : '<span style="color:var(--muted)">—</span>' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. REKAP PENGHUNI -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-users-three"></i> Rekapitulasi Penghuni
                </div>
                <div style="font-size:.8rem;color:var(--muted)">
                    Aktif: <strong><?= $totalPenghuniAktif ?></strong> &bull; Nonaktif: <strong><?= $totalPenghuniNonaktif ?></strong>
                </div>
            </div>
            <div class="panel-body">
                <?php if (empty($daftarPenghuniAktif)): ?>
                <div class="empty">Tidak ada data penghuni aktif saat ini.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px">No</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>No. WhatsApp / HP</th>
                                <th>Kamar Ditempati</th>
                                <th>Tanggal Masuk</th>
                                <th>Status Akun</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $noP = 1; foreach ($daftarPenghuniAktif as $pa): ?>
                            <tr>
                                <td><?= $noP++ ?></td>
                                <td><strong><?= e($pa['nama']) ?></strong></td>
                                <td>@<?= e($pa['username']) ?></td>
                                <td><?= !empty($pa['no_hp']) ? e($pa['no_hp']) : '<span style="color:var(--muted)">—</span>' ?></td>
                                <td>
                                    <?php if (!empty($pa['nomor_kamar'])): ?>
                                    <span class="badge bg-b">Kamar <?= e($pa['nomor_kamar']) ?></span> (<?= e($pa['tipe_kamar'] ?: 'Standar') ?>)
                                    <?php else: ?>
                                    <span style="color:var(--muted);font-size:.8rem">Belum ada sewa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($pa['tanggal_masuk']) ? formatTanggal($pa['tanggal_masuk']) : '—' ?></td>
                                <td><span class="badge bg-g">Aktif</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. REKAP SEWA -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-file-text"></i> Rekapitulasi Kontrak Sewa
                </div>
                <div style="font-size:.8rem;color:var(--muted)">
                    Total Kontrak: <strong><?= count($daftarSewa) ?></strong>
                </div>
            </div>
            <div class="panel-body">
                <?php if (empty($daftarSewa)): ?>
                <div class="empty">Belum ada data kontrak sewa yang tercatat.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px">No</th>
                                <th>Nama Penghuni</th>
                                <th>Kamar</th>
                                <th>Periode</th>
                                <th>Tanggal Mulai</th>
                                <th>Tanggal Selesai</th>
                                <th>Total Biaya Sewa</th>
                                <th>Status Kontrak</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $noS = 1; foreach ($daftarSewa as $sw): ?>
                            <tr>
                                <td><?= $noS++ ?></td>
                                <td>
                                    <strong><?= e($sw['nama_penghuni']) ?></strong>
                                    <div style="font-size:.75rem;color:var(--muted)">@<?= e($sw['username']) ?> <?= !empty($sw['no_hp']) ? '&bull; ' . e($sw['no_hp']) : '' ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-b">Kamar <?= e($sw['nomor_kamar']) ?></span>
                                </td>
                                <td><?= e($sw['periode'] ?: '1 Semester') ?></td>
                                <td><?= formatTanggal($sw['tanggal_mulai']) ?></td>
                                <td><?= formatTanggal($sw['tanggal_selesai']) ?></td>
                                <td style="font-weight:700;color:var(--green);white-space:nowrap"><?= formatRupiah($sw['total_sewa']) ?></td>
                                <td>
                                    <?php if ($sw['status'] === 'aktif'): ?>
                                    <span class="badge bg-g"><i class="ph ph-check-circle"></i> Aktif</span>
                                    <?php else: ?>
                                    <span class="badge" style="background:#f1f3f5;color:#495057">Selesai</span>
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

        <!-- 4. REKAP PEMBAYARAN -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-receipt"></i> Rekapitulasi Riwayat Transaksi Pembayaran
                </div>
                <div style="font-size:.8rem;color:var(--muted)">
                    Total Transaksi: <strong><?= count($daftarBayar) ?></strong> &bull; Sah Diterima: <strong style="color:var(--green)"><?= formatRupiah($totalPemasukanSah) ?></strong>
                </div>
            </div>
            <div class="panel-body">
                <?php if (empty($daftarBayar)): ?>
                <div class="empty">Tidak ada data pembayaran yang sesuai dengan kriteria filter saat ini.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px">No</th>
                                <th>Tanggal Bayar</th>
                                <th>Nama Penghuni</th>
                                <th>Kamar &amp; Periode</th>
                                <th>Metode</th>
                                <th>Jumlah Bayar</th>
                                <th>Status Tagihan</th>
                                <th>Status Verifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $noB = 1; foreach ($daftarBayar as $by): ?>
                            <tr>
                                <td><?= $noB++ ?></td>
                                <td style="white-space:nowrap;font-weight:600"><?= formatTanggal($by['tanggal_bayar']) ?></td>
                                <td>
                                    <strong><?= e($by['nama_penghuni']) ?></strong>
                                    <div style="font-size:.75rem;color:var(--muted)">@<?= e($by['username']) ?></div>
                                </td>
                                <td>
                                    <strong>Kamar <?= e($by['nomor_kamar']) ?></strong>
                                    <div style="font-size:.75rem;color:var(--muted)"><?= e($by['periode'] ?: '1 Semester') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-method">
                                        <?php if ($by['metode_pembayaran'] === 'Cash'): ?>
                                        <i class="ph ph-money"></i> Cash
                                        <?php elseif ($by['metode_pembayaran'] === 'Transfer'): ?>
                                        <i class="ph ph-bank"></i> Transfer
                                        <?php else: ?>
                                        <i class="ph ph-qr-code"></i> QRIS
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td style="font-weight:700;color:var(--green);white-space:nowrap"><?= formatRupiah($by['jumlah_bayar']) ?></td>
                                <td>
                                    <?php if ($by['status'] === 'lunas'): ?>
                                    <span class="badge bg-g"><i class="ph ph-check"></i> Lunas</span>
                                    <?php else: ?>
                                    <span class="badge bg-r"><i class="ph ph-clock"></i> Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($by['status_verifikasi'] === 'dikonfirmasi'): ?>
                                    <span class="badge bg-g"><i class="ph ph-check-circle"></i> Dikonfirmasi (Sah)</span>
                                    <?php elseif ($by['status_verifikasi'] === 'ditolak'): ?>
                                    <span class="badge bg-r"><i class="ph ph-x-circle"></i> Ditolak</span>
                                    <?php else: ?>
                                    <span class="badge bg-a"><i class="ph ph-clock"></i> Menunggu Verifikasi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align:right">TOTAL PEMASUKAN SAH (Dikonfirmasi):</td>
                                <td colspan="3" style="color:var(--green);font-size:1.05rem">
                                    <?= formatRupiah($totalPemasukanSah) ?>
                                    <span style="font-size:.78rem;font-weight:400;color:var(--muted);display:block">
                                        * Hanya menghitung pembayaran yang berstatus Dikonfirmasi (menunggu &amp; ditolak tidak dihitung).
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tanda Tangan Cetak Pengelola (Khusus Print) -->
        <div class="print-signature">
            <div class="sig-box">
                <div><?= formatTanggal(date('Y-m-d')) ?></div>
                <div style="margin-top:2px">Pengelola Kost Putri Masira</div>
                <div class="sig-space"></div>
                <div class="sig-name"><?= e($user['nama'] ?? 'Pengelola') ?></div>
                <div style="font-size:8pt;color:#555">Penanggung Jawab Operasional</div>
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
