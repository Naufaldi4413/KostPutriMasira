<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('pengelola', '../login.php');
$user  = currentUser();
$flash = getFlash();

// 1. Data Kamar & Sewa Aktif
// - Total kamar = jumlah seluruh data tabel kamar.
// - Kamar kosong = kamar dengan 0 sewa aktif.
// - Kamar terisi = jumlah sewa aktif masih di bawah kapasitas.
// - Kamar penuh = jumlah sewa aktif sudah mencapai kapasitas.
$daftarKamar = [];
$totalKamar  = 0;
$kamarKosong = 0;
$kamarTerisi = 0;
$kamarPenuh  = 0;

try {
    $stmtKamar = $pdo->query("
        SELECT 
            k.id_kamar,
            k.nomor_kamar,
            k.tipe_kamar,
            k.kapasitas,
            k.harga,
            k.status AS status_kamar,
            COUNT(s.id_sewa) AS sewa_aktif_count,
            GROUP_CONCAT(u.nama SEPARATOR ', ') AS nama_penghuni
        FROM kamar k
        LEFT JOIN sewa s ON k.id_kamar = s.id_kamar AND s.status = 'aktif'
        LEFT JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        LEFT JOIN users u ON p.id_user = u.id_user
        GROUP BY k.id_kamar, k.nomor_kamar, k.tipe_kamar, k.kapasitas, k.harga, k.status
        ORDER BY k.nomor_kamar ASC
    ");
    $daftarKamar = $stmtKamar->fetchAll();
    $totalKamar  = count($daftarKamar);

    foreach ($daftarKamar as &$km) {
        $sewaAktif = (int)$km['sewa_aktif_count'];
        $kapasitas = max(1, (int)$km['kapasitas']);
        if ($sewaAktif === 0) {
            $km['status_display'] = 'kosong';
            $kamarKosong++;
        } elseif ($sewaAktif < $kapasitas) {
            $km['status_display'] = 'terisi';
            $kamarTerisi++;
        } else {
            $km['status_display'] = 'penuh';
            $kamarPenuh++;
        }
    }
    unset($km);
} catch (PDOException $e) {
    $daftarKamar = [];
    $totalKamar = $kamarKosong = $kamarTerisi = $kamarPenuh = 0;
}

// 2. Total Penghuni Aktif (penghuni.status = 'aktif')
$totalPenghuni = 0;
try {
    $totalPenghuni = (int)$pdo->query("SELECT COUNT(*) FROM penghuni WHERE status = 'aktif'")->fetchColumn();
} catch (PDOException $e) {
    $totalPenghuni = 0;
}

// 3. Pembayaran
// - Pembayaran lunas = pembayaran terverifikasi/dikonfirmasi dan status lunas.
// - Pembayaran menunggu = status_verifikasi = menunggu.
$bayarLunas    = ['total' => 0, 'nominal' => 0.0];
$bayarMenunggu = ['total' => 0, 'nominal' => 0.0];
$totalTransaksiBayar = 0;

try {
    $rowLunas = $pdo->query("
        SELECT COUNT(*) AS total, COALESCE(SUM(jumlah_bayar), 0) AS nominal
        FROM pembayaran
        WHERE (status_verifikasi = 'dikonfirmasi' OR status_verifikasi = 'terverifikasi')
          AND status = 'lunas'
    ")->fetch();
    if ($rowLunas) {
        $bayarLunas = [
            'total'   => (int)$rowLunas['total'],
            'nominal' => (float)$rowLunas['nominal']
        ];
    }

    $rowMenunggu = $pdo->query("
        SELECT COUNT(*) AS total, COALESCE(SUM(jumlah_bayar), 0) AS nominal
        FROM pembayaran
        WHERE status_verifikasi = 'menunggu'
    ")->fetch();
    if ($rowMenunggu) {
        $bayarMenunggu = [
            'total'   => (int)$rowMenunggu['total'],
            'nominal' => (float)$rowMenunggu['nominal']
        ];
    }

    $totalTransaksiBayar = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran")->fetchColumn();
} catch (PDOException $e) {
    $bayarLunas = ['total' => 0, 'nominal' => 0.0];
    $bayarMenunggu = ['total' => 0, 'nominal' => 0.0];
    $totalTransaksiBayar = 0;
}

// 4. Sewa Terbaru dari database db_pengelolaan_kos
$daftarSewaTerbaru = [];
try {
    $stmtSewa = $pdo->query("
        SELECT 
            s.id_sewa,
            s.tanggal_mulai,
            s.tanggal_selesai,
            s.status AS status_sewa,
            u.nama AS nama_penghuni,
            k.nomor_kamar
        FROM sewa s
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        ORDER BY s.id_sewa DESC
        LIMIT 5
    ");
    $daftarSewaTerbaru = $stmtSewa->fetchAll();
} catch (PDOException $e) {
    $daftarSewaTerbaru = [];
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pengelola - Kost Putri Masira</title>
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
.grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.25rem;margin-bottom:1.25rem}
@media(max-width:1200px){
    .grid2{grid-template-columns:minmax(0,1fr)}
}
.pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.pay-item{background:#f6f5f2;border:1px solid var(--border);border-radius:8px;padding:.875rem}
.pay-label{font-size:.7125rem;color:var(--muted);margin-bottom:.2rem;font-weight:500}
.pay-val{font-size:1rem;font-weight:700}
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
            <span class="sb-sub">Panel Pengelola</span>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-label">Utama</div>
        <a href="dashboard.php" class="sb-link active"><i class="ph ph-squares-four"></i> Dashboard</a>
        <a href="kamar/index.php" class="sb-link"><i class="ph ph-door"></i> Kelola Kamar</a>
        <a href="fasilitas/index.php" class="sb-link"><i class="ph ph-sparkle"></i> Kelola Fasilitas</a>
        <a href="penghuni/index.php" class="sb-link"><i class="ph ph-users-three"></i> Kelola Penghuni</a>
        <a href="sewa/index.php" class="sb-link"><i class="ph ph-key"></i> Kelola Sewa</a>
        <a href="pembayaran/index.php" class="sb-link"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        <a href="laporan/index.php" class="sb-link"><i class="ph ph-chart-bar"></i> Laporan</a>
        <div class="sb-label" style="margin-top:.5rem">Lainnya</div>
        <a href="profil.php" class="sb-link"><i class="ph ph-user-circle"></i> Profil Pengelola</a>
        <a href="../index.php" class="sb-link" target="_blank" rel="noopener"><i class="ph ph-globe"></i> Landing Page</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-avatar"><i class="ph ph-user"></i></div>
            <div>
                <div class="sb-uname"><?= e($user['nama'] ?? 'Pengelola') ?></div>
                <div class="sb-urole">Pengelola</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Dashboard Pengelola</div>
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
        <div class="flash <?= in_array($flash['type'],['danger','error'])?'danger':'success' ?>">
            <i class="ph ph-info"></i> <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <div class="pg-title">Selamat Datang, <?= e($user['nama'] ?? 'Pengelola') ?>!</div>
                <p class="pg-sub">Ringkasan kondisi hunian Kost Putri Masira hari ini.</p>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats stats-3">
            <div class="sc"><div class="sc-ico ico-b"><i class="ph ph-door"></i></div><div><div class="sc-label">Total Kamar</div><div class="sc-val"><?= $totalKamar ?></div><div class="sc-sub">Unit terdaftar</div></div></div>
            <div class="sc"><div class="sc-ico ico-g"><i class="ph ph-check-circle"></i></div><div><div class="sc-label">Kamar Kosong</div><div class="sc-val"><?= $kamarKosong ?></div><div class="sc-sub">0 sewa aktif</div></div></div>
            <div class="sc"><div class="sc-ico ico-a"><i class="ph ph-users"></i></div><div><div class="sc-label">Kamar Terisi</div><div class="sc-val"><?= $kamarTerisi ?></div><div class="sc-sub">Di bawah kapasitas</div></div></div>
            <div class="sc"><div class="sc-ico ico-r"><i class="ph ph-lock"></i></div><div><div class="sc-label">Kamar Penuh</div><div class="sc-val"><?= $kamarPenuh ?></div><div class="sc-sub">Mencapai kapasitas</div></div></div>
            <div class="sc"><div class="sc-ico ico-p"><i class="ph ph-users-three"></i></div><div><div class="sc-label">Penghuni Aktif</div><div class="sc-val"><?= $totalPenghuni ?></div><div class="sc-sub">Status aktif</div></div></div>
            <div class="sc"><div class="sc-ico ico-g"><i class="ph ph-money"></i></div><div><div class="sc-label">Pembayaran Lunas</div><div class="sc-val" style="font-size:1.1rem"><?= formatRupiah($bayarLunas['nominal']) ?></div><div class="sc-sub"><?= $bayarLunas['total'] ?> transaksi lunas</div></div></div>
        </div>

        <!-- GRID 2 COL -->
        <div class="grid2">
            <!-- Status Kamar -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="ph ph-door"></i> Status Kamar</div>
                    <a href="kamar/index.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">Kelola Kamar</a>
                </div>
                <?php if (empty($daftarKamar)): ?>
                <div class="empty"><i class="ph ph-info"></i> Belum ada data kamar.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>No Kamar</th><th>Tipe</th><th>Harga</th><th>Status</th><th>Penghuni</th></tr></thead>
                        <tbody>
                        <?php foreach ($daftarKamar as $k): ?>
                        <tr>
                            <td><strong><?= e($k['nomor_kamar']) ?></strong></td>
                            <td><?= e($k['tipe_kamar'] ?? 'Standar') ?></td>
                            <td style="white-space:nowrap"><?= formatRupiah($k['harga']) ?></td>
                            <td>
                                <?php if ($k['status_display']==='penuh'): ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b"><i class="ph ph-prohibit"></i> Penuh</span>
                                <?php elseif ($k['status_display']==='terisi'): ?>
                                <span class="badge bg-a"><i class="ph ph-users"></i> Terisi</span>
                                <?php else: ?>
                                <span class="badge bg-g"><i class="ph ph-check"></i> Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($k['nama_penghuni']) ? e($k['nama_penghuni']) : '<span style="color:var(--muted)">—</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sewa Terbaru -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="ph ph-key"></i> Sewa Terbaru</div>
                    <a href="sewa/index.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">Kelola Sewa</a>
                </div>
                <?php if (empty($daftarSewaTerbaru)): ?>
                <div class="empty"><i class="ph ph-info"></i> Belum ada transaksi sewa.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Penghuni</th>
                                <th>Nomor Kamar</th>
                                <th>Tanggal Mulai</th>
                                <th>Tanggal Selesai</th>
                                <th>Status Sewa</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($daftarSewaTerbaru as $sw): ?>
                        <tr>
                            <td><strong><?= e($sw['nama_penghuni']) ?></strong></td>
                            <td>Kamar <?= e($sw['nomor_kamar']) ?></td>
                            <td style="white-space:nowrap"><?= !empty($sw['tanggal_mulai']) ? formatTanggal($sw['tanggal_mulai'], 'pendek') : '—' ?></td>
                            <td style="white-space:nowrap"><?= !empty($sw['tanggal_selesai']) ? formatTanggal($sw['tanggal_selesai'], 'pendek') : '—' ?></td>
                            <td>
                                <?php if ($sw['status_sewa'] === 'aktif'): ?>
                                <span class="badge bg-g"><i class="ph ph-check-circle"></i> Aktif</span>
                                <?php else: ?>
                                <span class="badge" style="background:#e5e7eb;color:#374151"><i class="ph ph-check"></i> Selesai</span>
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

        <!-- PEMBAYARAN -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="ph ph-money"></i> Ringkasan Pembayaran</div>
                <a href="pembayaran/index.php" style="font-size:.78rem;color:var(--green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:.25rem">Kelola Pembayaran</a>
            </div>
            <div class="panel-body">
                <div class="pay-grid">
                    <div class="pay-item"><div class="pay-label">Total Pemasukan (Lunas)</div><div class="pay-val"><?= formatRupiah($bayarLunas['nominal']) ?></div></div>
                    <div class="pay-item"><div class="pay-label">Total Seluruh Transaksi</div><div class="pay-val"><?= $totalTransaksiBayar ?> transaksi</div></div>
                    <div class="pay-item"><div class="pay-label">Pembayaran Lunas</div><div class="pay-val" style="color:#2d6a4f"><?= $bayarLunas['total'] ?> transaksi</div></div>
                    <div class="pay-item"><div class="pay-label">Pembayaran Menunggu</div><div class="pay-val" style="color:#e67e22"><?= $bayarMenunggu['total'] ?> transaksi</div></div>
                </div>
                <?php if ($totalTransaksiBayar === 0): ?>
                <p style="font-size:.875rem;color:var(--muted);text-align:center;padding:.75rem 0;margin-top:.75rem">
                    <i class="ph ph-info"></i> Belum ada data pembayaran.
                </p>
                <?php endif; ?>
            </div>
        </div>

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