<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('penghuni', '../login.php');

$user = currentUser();
$id_user = (int)($user['id_user'] ?? $_SESSION['user_id'] ?? 0);

// Get id_penghuni
$id_penghuni = (int)($_SESSION['id_penghuni'] ?? 0);
if ($id_penghuni <= 0) {
    $stmtP = $pdo->prepare("SELECT id_penghuni FROM penghuni WHERE id_user = :id_user LIMIT 1");
    $stmtP->execute(['id_user' => $id_user]);
    $id_penghuni = (int)$stmtP->fetchColumn();
    $_SESSION['id_penghuni'] = $id_penghuni;
}

$flash = getFlash();

$riwayat = [];
$totalLunas = 0;
$totalMenunggu = 0;
$totalDitolak = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            pb.*,
            s.id_sewa, s.periode, s.tanggal_mulai, s.tanggal_selesai, s.total_sewa,
            k.nomor_kamar, k.tipe_kamar
        FROM pembayaran pb
        JOIN sewa s ON pb.id_sewa = s.id_sewa
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id_penghuni
        ORDER BY pb.id_pembayaran DESC
    ");
    $stmt->execute(['id_penghuni' => $id_penghuni]);
    $riwayat = $stmt->fetchAll();

    foreach ($riwayat as $r) {
        if ($r['status_verifikasi'] === 'dikonfirmasi') {
            $totalLunas++;
        } elseif ($r['status_verifikasi'] === 'menunggu') {
            $totalMenunggu++;
        } elseif ($r['status_verifikasi'] === 'ditolak') {
            $totalDitolak++;
        }
    }
} catch (PDOException $e) {
    $riwayat = [];
}

$waPengelola = '6282233053344';
$waPesan = urlencode("Halo Pengelola Kost Putri Masira, saya {$user['nama']} ingin menanyakan perihal riwayat pembayaran sewa.");
$waUrl = "https://wa.me/{$waPengelola}?text={$waPesan}";
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Pembayaran - Kost Putri Masira</title>
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
</style>
</head>
<body>
<div class="layout">

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

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
        <a href="dashboard.php" class="sb-link"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="kamar.php" class="sb-link"><i class="ph ph-door"></i> Kamar Saya</a>
        <a href="sewa.php" class="sb-link"><i class="ph ph-file-text"></i> Sewa Saya</a>
        <a href="pembayaran.php" class="sb-link"><i class="ph ph-credit-card"></i> Pembayaran</a>
        <a href="riwayat_pembayaran.php" class="sb-link active"><i class="ph ph-clock-counter-clockwise"></i> Riwayat Pembayaran</a>
        <div class="sb-label" style="margin-top:.5rem">Lainnya</div>
        <a href="profil.php" class="sb-link"><i class="ph ph-user"></i> Profil</a>
        <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="sb-link"><i class="ph ph-whatsapp-logo"></i> Hubungi Pengelola</a>
        <a href="../index.php" class="sb-link" target="_blank" rel="noopener"><i class="ph ph-globe"></i> Landing Page</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-avatar"><i class="ph ph-user"></i></div>
            <div>
                <div class="sb-uname"><?= e($user['nama'] ?? 'Penghuni') ?></div>
                <div class="sb-urole">Penghuni</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Riwayat Pembayaran</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">
        <div class="pg-header">
            <div>
                <div class="pg-title">Riwayat Pembayaran Sewa</div>
                <p class="pg-sub">Daftar seluruh transaksi pembayaran sewa kos dan status verifikasi dari pengelola.</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats">
            <div class="sc">
                <div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div>
                <div>
                    <div class="sc-label">Dikonfirmasi (Lunas)</div>
                    <div class="sc-val" style="color:var(--green)"><?= $totalLunas ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-a"><i class="ph ph-clock"></i></div>
                <div>
                    <div class="sc-label">Menunggu Verifikasi</div>
                    <div class="sc-val" style="color:#e67e22"><?= $totalMenunggu ?></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-ico ico-r"><i class="ph ph-x-circle"></i></div>
                <div>
                    <div class="sc-label">Ditolak</div>
                    <div class="sc-val" style="color:#dc2626"><?= $totalDitolak ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="ph ph-receipt"></i> Log Transaksi Pembayaran</div>
                <a href="pembayaran.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">
                    <i class="ph ph-plus-circle"></i> Bayar Sewa Baru
                </a>
            </div>
            <?php if (empty($riwayat)): ?>
            <div class="empty">
                <i class="ph ph-receipt"></i> Belum ada riwayat pembayaran yang tercatat.
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Bayar</th>
                            <th>Kamar &amp; Periode</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status Tagihan</th>
                            <th>Status Verifikasi</th>
                            <th>Bukti</th>
                            <th>Keterangan / Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $r): ?>
                        <tr>
                            <td style="white-space:nowrap;font-weight:600">
                                <?= formatTanggal($r['tanggal_bayar']) ?>
                            </td>
                            <td>
                                <strong>Kamar <?= e($r['nomor_kamar']) ?></strong>
                                <span style="font-size:.75rem;color:var(--muted);display:block"><?= e($r['periode'] ?: '1 Semester') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-method">
                                    <?php if ($r['metode_pembayaran'] === 'Cash'): ?>
                                    <i class="ph ph-money"></i> Cash
                                    <?php elseif ($r['metode_pembayaran'] === 'Transfer'): ?>
                                    <i class="ph ph-bank"></i> Transfer
                                    <?php else: ?>
                                    <i class="ph ph-qr-code"></i> QRIS
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:var(--green);white-space:nowrap">
                                <?= formatRupiah($r['jumlah_bayar']) ?>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'lunas'): ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Lunas</span>
                                <?php else: ?>
                                <span class="badge bg-r"><i class="ph ph-clock"></i> Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status_verifikasi'] === 'dikonfirmasi'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Dikonfirmasi</span>
                                <?php elseif ($r['status_verifikasi'] === 'ditolak'): ?>
                                <span class="badge bg-r"><i class="ph ph-x-circle"></i> Ditolak</span>
                                <?php else: ?>
                                <span class="badge bg-a"><i class="ph ph-clock"></i> Menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['metode_pembayaran'] === 'Cash'): ?>
                                <span style="font-size:.78rem;color:var(--muted)">Tunai</span>
                                <?php elseif (!empty($r['bukti_pembayaran'])): ?>
                                <a href="../uploads/bukti/<?= rawurlencode($r['bukti_pembayaran']) ?>" target="_blank" rel="noopener" style="font-size:.78rem;color:var(--green);font-weight:600;display:inline-flex;align-items:center;gap:.2rem;text-decoration:none">
                                    <i class="ph ph-image"></i> Lihat
                                </a>
                                <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                            </td>
                            <td style="font-size:.8125rem;max-width:240px;line-height:1.4">
                                <?= !empty($r['keterangan']) ? nl2br(e($r['keterangan'])) : '<span style="color:var(--muted)">—</span>' ?>
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
