<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('penghuni', '../login.php');

$user = currentUser();
$id_user = (int)($user['id_user'] ?? $_SESSION['user_id'] ?? 0);

// Ambil data detail penghuni berdasarkan id_user yang sedang login
$stmtP = $pdo->prepare("
    SELECT p.*, u.nama, u.username, u.role
    FROM penghuni p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_user = :id_user
    LIMIT 1
");
$stmtP->execute(['id_user' => $id_user]);
$penghuni = $stmtP->fetch();

$id_penghuni = (int)($penghuni['id_penghuni'] ?? 0);
$_SESSION['id_penghuni'] = $id_penghuni;

$flash = getFlash();

// Ambil sewa aktif milik penghuni ini
$sewaAktif = null;
$jumlahPenghuniAktifKamar = 0;
$roommates = [];
$latestBayar = null;
$recentBayar = [];

if ($id_penghuni > 0) {
    // 1. Data Sewa & Kamar Aktif
    $stmtSewa = $pdo->prepare("
        SELECT 
            s.*,
            k.nomor_kamar, k.tipe_kamar, k.harga AS harga_kamar, k.kapasitas, k.keterangan AS keterangan_kamar
        FROM sewa s
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id_penghuni AND s.status = 'aktif'
        ORDER BY s.id_sewa DESC
        LIMIT 1
    ");
    $stmtSewa->execute(['id_penghuni' => $id_penghuni]);
    $sewaAktif = $stmtSewa->fetch();

    if ($sewaAktif) {
        $id_kamar = (int)$sewaAktif['id_kamar'];

        // 2. Jumlah penghuni aktif di kamar ini
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) FROM sewa WHERE id_kamar = :id_kamar AND status = 'aktif'
        ");
        $stmtCount->execute(['id_kamar' => $id_kamar]);
        $jumlahPenghuniAktifKamar = (int)$stmtCount->fetchColumn();

        // 3. Penghuni lain di kamar yang sama (Roommate)
        $stmtRoommates = $pdo->prepare("
            SELECT u.nama
            FROM sewa s
            JOIN penghuni p ON s.id_penghuni = p.id_penghuni
            JOIN users u ON p.id_user = u.id_user
            WHERE s.id_kamar = :id_kamar 
              AND s.status = 'aktif'
              AND s.id_penghuni != :id_penghuni
        ");
        $stmtRoommates->execute([
            'id_kamar' => $id_kamar,
            'id_penghuni' => $id_penghuni
        ]);
        $roommates = $stmtRoommates->fetchAll(PDO::FETCH_COLUMN);

        // 4. Pembayaran terakhir untuk sewa aktif
        $stmtLatest = $pdo->prepare("
            SELECT * FROM pembayaran
            WHERE id_sewa = :id_sewa
            ORDER BY id_pembayaran DESC
            LIMIT 1
        ");
        $stmtLatest->execute(['id_sewa' => $sewaAktif['id_sewa']]);
        $latestBayar = $stmtLatest->fetch();
    }

    // 5. Pembayaran Terbaru milik penghuni ini (limit 5)
    $stmtRecent = $pdo->prepare("
        SELECT pb.*, k.nomor_kamar, s.periode
        FROM pembayaran pb
        JOIN sewa s ON pb.id_sewa = s.id_sewa
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id_penghuni
        ORDER BY pb.id_pembayaran DESC
        LIMIT 5
    ");
    $stmtRecent->execute(['id_penghuni' => $id_penghuni]);
    $recentBayar = $stmtRecent->fetchAll();
}

$waPengelola = '6282233053344';
$nomorKamarText = $sewaAktif ? "Kamar " . e($sewaAktif['nomor_kamar']) : "Penghuni Kost";
$namaPenghuniText = $penghuni['nama'] ?? $user['nama'] ?? 'Penghuni';
$waPesan = urlencode("Halo Pengelola Kost Putri Masira, saya {$namaPenghuniText} dari {$nomorKamarText}. Saya ingin menanyakan...");
$waUrl = "https://wa.me/{$waPengelola}?text={$waPesan}";
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Penghuni - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
@media(max-width:768px){
    .pay-grid{grid-template-columns:1fr !important}
}
.grid2{display:grid;grid-template-columns:1.15fr .85fr;gap:1.25rem;margin-bottom:1.25rem}
@media(max-width:900px){
    .grid2{grid-template-columns:1fr}
}
.pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.pay-item{background:#f6f5f2;border:1px solid var(--border);border-radius:8px;padding:.875rem}
.pay-label{font-size:.7125rem;color:var(--muted);margin-bottom:.2rem;font-weight:500}
.pay-val{font-size:1rem;font-weight:700}
.info-table{width:100%}
.info-table tr td{padding:.65rem .5rem;border-bottom:1px solid #f3f2ee}
.info-table tr:last-child td{border-bottom:none}
.info-title{width:40%;font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.info-data{font-weight:600;color:#1a1a18}
</style>
</head>
<body>
<div class="layout">

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar Penghuni -->
<aside class="sidebar" id="sidebarPanel">
    <div class="sb-brand">
        <div class="sb-ico"><i class="ph ph-house-line"></i></div>
        <div>
            <div class="sb-name">Kost Putri Masira</div>
            <span class="sb-sub">Panel Penghuni</span>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-label">Utama</div>
        <a href="dashboard.php" class="sb-link active"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="kamar.php" class="sb-link"><i class="ph ph-door"></i> Kamar Saya</a>
        <a href="sewa.php" class="sb-link"><i class="ph ph-file-text"></i> Sewa Saya</a>
        <a href="pembayaran.php" class="sb-link"><i class="ph ph-credit-card"></i> Pembayaran</a>
        <a href="riwayat_pembayaran.php" class="sb-link"><i class="ph ph-clock-counter-clockwise"></i> Riwayat Pembayaran</a>
        <div class="sb-label" style="margin-top:.5rem">Lainnya</div>
        <a href="profil.php" class="sb-link"><i class="ph ph-user"></i> Profil</a>
        <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="sb-link"><i class="ph ph-whatsapp-logo"></i> Hubungi Pengelola</a>
        <a href="../index.php" class="sb-link" target="_blank" rel="noopener"><i class="ph ph-globe"></i> Landing Page</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-avatar"><i class="ph ph-user"></i></div>
            <div>
                <div class="sb-uname"><?= e($penghuni['nama'] ?? $user['nama'] ?? 'Penghuni') ?></div>
                <div class="sb-urole">Penghuni</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<!-- Main Area -->
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Dashboard Penghuni</div>
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

    <main class="content">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'],['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph ph-info"></i> <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <div class="pg-title">Selamat Datang, <?= e($penghuni['nama'] ?? $user['nama'] ?? 'Penghuni') ?>!</div>
                <p class="pg-sub">Informasi status hunian kamar, masa sewa, dan tagihan pembayaran Anda di Kost Putri Masira.</p>
            </div>
        </div>

        <?php if (!$sewaAktif): ?>
        <div class="panel">
            <div class="panel-body empty">
                <i class="ph ph-house-line"></i>
                <div style="font-size:1.05rem;font-weight:700;color:#1a1a18;margin-bottom:.35rem">Belum Ada Kontrak Sewa Aktif</div>
                <p style="max-width:500px;margin:0 auto .75rem;font-size:.875rem">Akun Anda saat ini belum terhubung dengan kamar aktif. Silakan hubungi pengelola kost untuk konfirmasi pendaftaran.</p>
                <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="sb-link" style="display:inline-flex;background:var(--green);color:#fff;border-radius:8px;padding:.5rem 1rem">
                    <i class="ph ph-whatsapp-logo"></i> Hubungi Pengelola via WhatsApp
                </a>
            </div>
        </div>
        <?php else: ?>

        <!-- STATS -->
        <div class="stats">
            <!-- 1. Nomor Kamar -->
            <div class="sc">
                <div class="sc-ico ico-b"><i class="ph ph-door"></i></div>
                <div>
                    <div class="sc-label">Kamar Saya</div>
                    <div class="sc-val">Kamar <?= e($sewaAktif['nomor_kamar']) ?></div>
                    <div class="sc-sub"><?= e($sewaAktif['tipe_kamar'] ?: 'Standar') ?></div>
                </div>
            </div>

            <!-- 2. Kapasitas & Jumlah Penghuni -->
            <div class="sc">
                <div class="sc-ico ico-p"><i class="ph ph-users"></i></div>
                <div>
                    <div class="sc-label">Kapasitas Kamar</div>
                    <div class="sc-val"><?= $jumlahPenghuniAktifKamar ?> / <?= (int)$sewaAktif['kapasitas'] ?></div>
                    <div class="sc-sub"><?= !empty($roommates) ? count($roommates) . ' Teman Sekamar' : 'Kamar Sendiri' ?></div>
                </div>
            </div>

            <!-- 3. Status Sewa -->
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-calendar-check"></i></div>
                <div>
                    <div class="sc-label">Status Sewa</div>
                    <div class="sc-val" style="color:var(--green)">Aktif</div>
                    <div class="sc-sub"><?= e($sewaAktif['periode'] ?: '1 Semester') ?></div>
                </div>
            </div>

            <!-- 4. Total Biaya Sewa -->
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-money"></i></div>
                <div>
                    <div class="sc-label">Total Tagihan</div>
                    <div class="sc-val" style="font-size:1.15rem"><?= formatRupiah($sewaAktif['total_sewa']) ?></div>
                    <div class="sc-sub">Per Semester</div>
                </div>
            </div>

            <!-- 5. Status Pembayaran -->
            <div class="sc">
                <div class="sc-ico <?= ($latestBayar && $latestBayar['status'] === 'lunas') ? 'ico-g' : 'ico-r' ?>">
                    <i class="ph ph-receipt"></i>
                </div>
                <div>
                    <div class="sc-label">Pembayaran</div>
                    <div class="sc-val" style="font-size:1.15rem;color:<?= ($latestBayar && $latestBayar['status'] === 'lunas') ? 'var(--green)' : '#dc2626' ?>">
                        <?= ($latestBayar && $latestBayar['status'] === 'lunas') ? 'Lunas' : 'Belum Lunas' ?>
                    </div>
                    <div class="sc-sub">
                        <?= $latestBayar ? ucfirst($latestBayar['status_verifikasi']) : 'Belum Bayar' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRID 2 COL: Kamar & Sewa (Kiri) vs Pembayaran (Kanan) -->
        <div class="grid2">
            <!-- Data Kamar & Sewa Saya -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="ph ph-door"></i> Data Kamar &amp; Sewa Saya</div>
                    <a href="kamar.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">
                        Detail Kamar
                    </a>
                </div>
                <div class="panel-body">
                    <table class="info-table">
                        <tbody>
                            <tr>
                                <td class="info-title">Nomor Kamar</td>
                                <td class="info-data">
                                    <span class="badge bg-b" style="font-size:.8rem;padding:.25rem .65rem">
                                        Kamar <?= e($sewaAktif['nomor_kamar']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="info-title">Tipe Kamar</td>
                                <td class="info-data"><?= e($sewaAktif['tipe_kamar'] ?: 'Standar') ?></td>
                            </tr>
                            <tr>
                                <td class="info-title">Kapasitas Kamar</td>
                                <td class="info-data"><?= (int)$sewaAktif['kapasitas'] ?> Orang</td>
                            </tr>
                            <tr>
                                <td class="info-title">Penghuni di Kamar</td>
                                <td class="info-data"><?= $jumlahPenghuniAktifKamar ?> Orang Aktif</td>
                            </tr>
                            <tr>
                                <td class="info-title">Teman Sekamar</td>
                                <td class="info-data">
                                    <?php if (!empty($roommates)): ?>
                                        <?= e(implode(', ', $roommates)) ?>
                                    <?php else: ?>
                                        <span style="color:var(--muted)">Tidak ada (Menempati kamar sendiri)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="info-title">Periode Sewa</td>
                                <td class="info-data"><?= e($sewaAktif['periode'] ?: '1 Semester') ?></td>
                            </tr>
                            <tr>
                                <td class="info-title">Tanggal Mulai</td>
                                <td class="info-data"><?= formatTanggal($sewaAktif['tanggal_mulai']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-title">Tanggal Selesai</td>
                                <td class="info-data"><?= formatTanggal($sewaAktif['tanggal_selesai']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-title">Total Sewa</td>
                                <td class="info-data" style="color:var(--green);font-size:1.05rem">
                                    <?= formatRupiah($sewaAktif['total_sewa']) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Status Pembayaran -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="ph ph-credit-card"></i> Status Pembayaran</div>
                    <a href="pembayaran.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">
                        Bayar Sewa
                    </a>
                </div>
                <div class="panel-body">
                    <!-- Pesan Khusus Sesuai Aturan -->
                    <?php if ($latestBayar && $latestBayar['status_verifikasi'] === 'menunggu'): ?>
                    <div class="flash" style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;margin-bottom:1rem">
                        <i class="ph ph-clock" style="font-size:1.15rem"></i>
                        <span>Pembayaran Anda sedang menunggu verifikasi pengelola.</span>
                    </div>
                    <?php elseif ($latestBayar && $latestBayar['status_verifikasi'] === 'ditolak'): ?>
                    <div class="flash danger" style="margin-bottom:1rem">
                        <i class="ph ph-warning-circle" style="font-size:1.15rem"></i>
                        <div>
                            <span>Pembayaran ditolak. Silakan periksa kembali bukti pembayaran atau hubungi pengelola.</span>
                            <?php if (!empty($latestBayar['keterangan'])): ?>
                            <div style="margin-top:.25rem;font-size:.8rem;font-weight:600">Alasan: <?= e($latestBayar['keterangan']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($latestBayar && $latestBayar['status_verifikasi'] === 'dikonfirmasi'): ?>
                    <div class="flash success" style="margin-bottom:1rem">
                        <i class="ph ph-check-circle" style="font-size:1.15rem"></i>
                        <span>Pembayaran semester sudah dikonfirmasi.</span>
                    </div>
                    <?php else: ?>
                    <div class="flash" style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;margin-bottom:1rem">
                        <i class="ph ph-info" style="font-size:1.15rem"></i>
                        <span>Anda belum melakukan pembayaran sewa untuk semester ini.</span>
                    </div>
                    <?php endif; ?>

                    <div class="pay-grid">
                        <div class="pay-item">
                            <div class="pay-label">Jumlah Tagihan</div>
                            <div class="pay-val" style="color:var(--green)"><?= formatRupiah($sewaAktif['total_sewa']) ?></div>
                        </div>
                        <div class="pay-item">
                            <div class="pay-label">Status Tagihan</div>
                            <div class="pay-val">
                                <?php if ($latestBayar && $latestBayar['status'] === 'lunas'): ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Lunas</span>
                                <?php else: ?>
                                <span class="badge bg-r"><i class="ph ph-clock"></i> Belum Lunas</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pay-item">
                            <div class="pay-label">Status Verifikasi</div>
                            <div class="pay-val">
                                <?php if ($latestBayar && $latestBayar['status_verifikasi'] === 'dikonfirmasi'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Dikonfirmasi</span>
                                <?php elseif ($latestBayar && $latestBayar['status_verifikasi'] === 'ditolak'): ?>
                                <span class="badge bg-r"><i class="ph ph-x-circle"></i> Ditolak</span>
                                <?php elseif ($latestBayar && $latestBayar['status_verifikasi'] === 'menunggu'): ?>
                                <span class="badge bg-a"><i class="ph ph-clock"></i> Menunggu</span>
                                <?php else: ?>
                                <span style="font-size:.85rem;color:var(--muted)">Belum Bayar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pay-item">
                            <div class="pay-label">Metode Terakhir</div>
                            <div class="pay-val"><?= e($latestBayar['metode_pembayaran'] ?? '—') ?></div>
                        </div>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                        <a href="pembayaran.php" style="flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.55rem 1rem;background:var(--green);color:#fff;border-radius:8px;font-size:.85rem;font-weight:600;text-decoration:none">
                            <i class="ph ph-credit-card"></i> Bayar Sewa Sekarang
                        </a>
                        <a href="<?= $waUrl ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;gap:.35rem;padding:.55rem .85rem;background:#25d366;color:#fff;border-radius:8px;font-size:.85rem;font-weight:600;text-decoration:none">
                            <i class="ph ph-whatsapp-logo"></i> WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- PEMBAYARAN TERBARU -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="ph ph-receipt"></i> Pembayaran Terbaru</div>
                <a href="riwayat_pembayaran.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">
                    Lihat Riwayat Pembayaran
                </a>
            </div>
            <?php if (empty($recentBayar)): ?>
            <div class="empty">
                <i class="ph ph-receipt"></i> Belum ada transaksi pembayaran.
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kamar &amp; Periode</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status Tagihan</th>
                            <th>Status Verifikasi</th>
                            <th>Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBayar as $rb): ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--muted);font-size:.8125rem">
                                <?= formatTanggal($rb['tanggal_bayar']) ?>
                            </td>
                            <td>
                                <strong>Kamar <?= e($rb['nomor_kamar']) ?></strong>
                                <span style="font-size:.75rem;color:var(--muted);display:block"><?= e($rb['periode'] ?: '1 Semester') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-method">
                                    <?php if ($rb['metode_pembayaran'] === 'Cash'): ?>
                                    <i class="ph ph-money"></i> Cash
                                    <?php elseif ($rb['metode_pembayaran'] === 'Transfer'): ?>
                                    <i class="ph ph-bank"></i> Transfer
                                    <?php else: ?>
                                    <i class="ph ph-qr-code"></i> QRIS
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:var(--green);white-space:nowrap">
                                <?= formatRupiah($rb['jumlah_bayar']) ?>
                            </td>
                            <td>
                                <?php if ($rb['status'] === 'lunas'): ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Lunas</span>
                                <?php else: ?>
                                <span class="badge bg-r"><i class="ph ph-clock"></i> Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rb['status_verifikasi'] === 'dikonfirmasi'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Dikonfirmasi</span>
                                <?php elseif ($rb['status_verifikasi'] === 'ditolak'): ?>
                                <span class="badge bg-r"><i class="ph ph-x-circle"></i> Ditolak</span>
                                <?php else: ?>
                                <span class="badge bg-a"><i class="ph ph-clock"></i> Menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rb['metode_pembayaran'] === 'Cash'): ?>
                                <span style="font-size:.78rem;color:var(--muted)">Tunai</span>
                                <?php elseif (!empty($rb['bukti_pembayaran'])): ?>
                                <a href="../uploads/bukti/<?= rawurlencode($rb['bukti_pembayaran']) ?>" target="_blank" rel="noopener" style="font-size:.78rem;color:var(--green);font-weight:600;display:inline-flex;align-items:center;gap:.2rem;text-decoration:none">
                                    <i class="ph ph-image"></i> Lihat
                                </a>
                                <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

    </main>
</div><!-- /main -->

</div><!-- /layout -->
<script>
(() => {
    function initSidebarDrawer() {
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebar = document.getElementById('sidebarPanel');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!burgerBtn || !sidebar) return;

        const openSidebar = () => {
            sidebar.classList.add('is-open');
            backdrop?.classList.add('is-open');
            burgerBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        };

        const closeSidebar = () => {
            sidebar.classList.remove('is-open');
            backdrop?.classList.remove('is-open');
            burgerBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        };

        burgerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        backdrop?.addEventListener('click', (e) => {
            e.preventDefault();
            closeSidebar();
        });

        sidebar.querySelectorAll('.sb-link, .btn-logout').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                closeSidebar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarDrawer);
    } else {
        initSidebarDrawer();
    }
})();
</script>
</body>
</html>