<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

$id_pembayaran = (int)($_GET['id'] ?? 0);

if ($id_pembayaran <= 0) {
    setFlash('danger', 'ID Pembayaran tidak valid.');
    redirect('index.php');}

try {
    $stmt = $pdo->prepare("
        SELECT 
            pb.*,
            s.tanggal_mulai, s.tanggal_selesai, s.periode, s.total_sewa,
            p.id_penghuni, p.nik, p.no_hp, p.alamat,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar, k.harga AS harga_kamar
        FROM pembayaran pb
        JOIN sewa s ON pb.id_sewa = s.id_sewa
        JOIN penghuni p ON s.id_penghuni = p.id_penghuni
        JOIN users u ON p.id_user = u.id_user
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE pb.id_pembayaran = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id_pembayaran]);
    $bayar = $stmt->fetch();

    if (!$bayar) {
        setFlash('danger', 'Data pembayaran tidak ditemukan.');
        redirect('index.php');}
} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Pembayaran #<?= $id_pembayaran ?> - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem}
@media(max-width:850px){
    .detail-grid{grid-template-columns:1fr}
}
.info-list{display:flex;flex-direction:column;gap:.75rem}
.info-item{display:flex;flex-direction:column;gap:.15rem}
.info-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.info-val{font-size:.9rem;font-weight:600;color:#1a1a18}
.bukti-container{border:1px solid var(--border);border-radius:10px;padding:1rem;background:#fafaf9;text-align:center}
.bukti-img{max-width:100%;max-height:480px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);object-fit:contain}
.bukti-actions{margin-top:.75rem;display:flex;gap:.75rem;justify-content:center}
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
        <div class="topbar-title">Rincian Pembayaran #<?= $id_pembayaran ?></div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-detail">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <h1 class="pg-title">Pembayaran Kamar <?= e($bayar['nomor_kamar']) ?> - <?= e($bayar['nama_penghuni']) ?></h1>
                <p class="pg-sub">Rincian nominal, metode transaksi, status verifikasi, dan bukti pembayaran penghuni.</p>
            </div>
            <div class="btn-header-group">
                <?php if ($bayar['status_verifikasi'] === 'menunggu'): ?>
                <a href="konfirmasi.php?id=<?= $id_pembayaran ?>" class="btn-act btn-confirm">
                    <i class="ph ph-check-circle"></i> Konfirmasi Pembayaran
                </a>
                <a href="tolak.php?id=<?= $id_pembayaran ?>" class="btn-act btn-reject">
                    <i class="ph ph-x-circle"></i> Tolak Pembayaran
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn-act btn-back">
                    <i class="ph ph-arrow-left"></i> Kembali ke Daftar
                </a>
            </div>
        </div>

        <div class="detail-grid">
            <!-- Left: Rincian Pembayaran -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">
                        <i class="ph ph-receipt"></i> Status &amp; Nilai Pembayaran
                    </div>
                    <?php if ($bayar['status_verifikasi'] === 'dikonfirmasi'): ?>
                    <span class="badge bg-g"><i class="ph ph-check-circle"></i> Dikonfirmasi (Lunas)</span>
                    <?php elseif ($bayar['status_verifikasi'] === 'ditolak'): ?>
                    <span class="badge bg-r"><i class="ph ph-x-circle"></i> Ditolak</span>
                    <?php else: ?>
                    <span class="badge bg-a"><i class="ph ph-clock"></i> Menunggu Verifikasi</span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">Jumlah Pembayaran</span>
                            <div class="info-val" style="font-size:1.3rem;color:var(--green)">
                                <?= formatRupiah($bayar['jumlah_bayar']) ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Metode Pembayaran</span>
                            <div class="info-val">
                                <span class="badge bg-method" style="font-size:.85rem;padding:.3rem .7rem">
                                    <?php if ($bayar['metode_pembayaran'] === 'Cash'): ?>
                                    <i class="ph ph-money"></i> Pembayaran Tunai (Cash)
                                    <?php elseif ($bayar['metode_pembayaran'] === 'Transfer'): ?>
                                    <i class="ph ph-bank"></i> Transfer Bank BRI
                                    <?php else: ?>
                                    <i class="ph ph-qr-code"></i> Pembayaran QRIS
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Tanggal Pembayaran</span>
                            <div class="info-val"><?= formatTanggal($bayar['tanggal_bayar']) ?></div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Waktu Verifikasi</span>
                            <div class="info-val" style="font-weight:400;color:var(--muted)">
                                <?= !empty($bayar['tanggal_verifikasi']) ? formatTanggal($bayar['tanggal_verifikasi'], 'lengkap') : 'Belum diverifikasi' ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Keterangan / Catatan</span>
                            <div class="info-val" style="font-weight:400;color:var(--muted);line-height:1.5">
                                <?= !empty($bayar['keterangan']) ? nl2br(e($bayar['keterangan'])) : '—' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Info Penghuni & Kamar -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">
                        <i class="ph ph-user"></i> Data Penghuni &amp; Kamar
                    </div>
                </div>
                <div class="panel-body">
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">Kamar yang Ditempati</span>
                            <div class="info-val">
                                <span class="badge bg-room" style="font-size:.85rem;padding:.3rem .7rem">
                                    Kamar <?= e($bayar['nomor_kamar']) ?>
                                </span>
                                <span style="font-size:.85rem;color:var(--muted);font-weight:400;margin-left:.35rem">
                                    (Tipe: <?= e($bayar['tipe_kamar'] ?: 'Standar') ?>)
                                </span>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Nama Penghuni</span>
                            <div class="info-val">
                                <a href="../penghuni/detail.php?id=<?= $bayar['id_penghuni'] ?>" style="color:var(--green);text-decoration:none">
                                    <?= e($bayar['nama_penghuni']) ?> (@<?= e($bayar['username']) ?>)
                                </a>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Kontak WhatsApp</span>
                            <div class="info-val">
                                <?php if (!empty($bayar['no_hp'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $bayar['no_hp'])) ?>" target="_blank" rel="noopener" style="color:var(--green);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem">
                                    <i class="ph ph-whatsapp-logo" style="font-size:1.1rem"></i> <?= e($bayar['no_hp']) ?>
                                </a>
                                <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Periode Sewa</span>
                            <div class="info-val"><?= e($bayar['periode'] ?: '1 Semester') ?></div>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Total Tagihan Kontrak Sewa</span>
                            <div class="info-val"><?= formatRupiah($bayar['total_sewa']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bukti Pembayaran Section -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="ph ph-image"></i> Bukti Pembayaran
                </div>
            </div>
            <div class="panel-body">
                <?php if ($bayar['metode_pembayaran'] === 'Cash'): ?>
                <div style="text-align:center;padding:2rem 1rem;color:var(--muted)">
                    <i class="ph ph-hand-coins" style="font-size:2.5rem;display:block;margin-bottom:.5rem;color:var(--green)"></i>
                    <strong>Pembayaran Tunai (Cash)</strong><br>
                    Pembayaran diserahkan langsung oleh penghuni kepada pengelola kost. Tidak memerlukan file bukti transfer.
                </div>
                <?php elseif (!empty($bayar['bukti_pembayaran'])): 
                    $fileUrl = '../../uploads/bukti/' . rawurlencode($bayar['bukti_pembayaran']);
                    $ext = strtolower(pathinfo($bayar['bukti_pembayaran'], PATHINFO_EXTENSION));
                ?>
                <div class="bukti-container">
                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])): ?>
                    <img src="<?= $fileUrl ?>" alt="Bukti Pembayaran" class="bukti-img">
                    <?php else: ?>
                    <div style="padding:2rem;text-align:center">
                        <i class="ph ph-file-pdf" style="font-size:3rem;color:#dc2626;display:block;margin-bottom:.5rem"></i>
                        <p>Dokumen Bukti Pembayaran (PDF): <strong><?= e($bayar['bukti_pembayaran']) ?></strong></p>
                    </div>
                    <?php endif; ?>

                    <div class="bukti-actions">
                        <a href="<?= $fileUrl ?>" target="_blank" rel="noopener" class="btn-act btn-back">
                            <i class="ph ph-arrow-square-out"></i> Buka Ukuran Penuh
                        </a>
                        <a href="<?= $fileUrl ?>" download class="btn-act btn-confirm">
                            <i class="ph ph-download-simple"></i> Unduh File Bukti
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:2rem 1rem;color:var(--muted)">
                    <i class="ph ph-warning-circle" style="font-size:2.5rem;display:block;margin-bottom:.5rem;color:#d97706"></i>
                    Belum ada bukti pembayaran yang diunggah untuk transaksi ini.
                </div>
                <?php endif; ?>
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