<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

$user = currentUser();
$flash = getFlash();

$id_pembayaran = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id_pembayaran <= 0) {
    setFlash('danger', 'ID Pembayaran tidak valid.');
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            pb.*,
            s.id_sewa, s.tanggal_mulai, s.tanggal_selesai, s.periode, s.total_sewa,
            p.id_penghuni, p.no_hp,
            u.nama AS nama_penghuni, u.username,
            k.nomor_kamar, k.tipe_kamar
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
        redirect('index.php');
}
}
catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');
}

$error = '';

// Hanya pembayaran yang masih 'menunggu' verifikasi yang boleh diproses
if (($bayar['status_verifikasi'] ?? '') !== 'menunggu') {
    setFlash('info', 'Pembayaran ini sudah diproses sebelumnya (status: ' . e($bayar['status_verifikasi']) . ').');
    redirect("detail.php?id={$id_pembayaran}");
}

// Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Token keamanan CSRF tidak valid. Silakan coba kembali.';
} else {
        $alasan = trim($_POST['alasan'] ?? '');
        if (empty($alasan)) {
            $error = 'Alasan penolakan wajib diisi agar penghuni dapat memperbaikinya.';
} else {
            try {
                $stmtUp = $pdo->prepare("
                    UPDATE pembayaran
                    SET 
                        status_verifikasi = 'ditolak',
                        status = 'belum_lunas',
                        tanggal_verifikasi = NOW(),
                        keterangan = :keterangan
                    WHERE id_pembayaran = :id
                ");
                $stmtUp->execute([
                    'keterangan' => $alasan,
                    'id' => $id_pembayaran
                ]);

                setFlash('success', 'Pembayaran sebesar ' . formatRupiah($bayar['jumlah_bayar']) . ' untuk Kamar ' . e($bayar['nomor_kamar']) . ' telah ditolak. Penghuni dapat mengunggah bukti baru.');
                redirect("detail.php?id={$id_pembayaran}");
}
catch (PDOException $e) {
                $error = 'Gagal menolak pembayaran: ' . $e->getMessage();
}
        }
    }
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tolak Pembayaran #<?= $id_pembayaran ?> - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.card-head{padding:1.25rem 1.5rem;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:.75rem}
.card-ico{width:42px;height:42px;border-radius:10px;background:#dc2626;color:#fff;display:grid;place-items:center;font-size:1.4rem;flex-shrink:0}
.card-title{font-size:1.15rem;font-weight:700;color:#991b1b}
.card-sub{font-size:.85rem;color:#b91c1c}
.card-body{padding:1.75rem}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:600px){
    .grid-2{grid-template-columns:1fr}
}
.info-item{display:flex;flex-direction:column;gap:.2rem}
.info-label{font-size:.72rem;font-weight:600;text-transform:uppercase;color:var(--muted);letter-spacing:.04em}
.info-val{font-size:.95rem;font-weight:600;color:#1a1a18}
.chips-label{font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:.5rem;display:block}
.chips-group{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}
.chip{display:inline-block;padding:.35rem .75rem;background:#f1f3f5;border:1px solid #dee2e6;border-radius:999px;font-size:.8125rem;color:#495057;cursor:pointer;transition:all .15s}
.chip:hover{background:#e9ecef;border-color:#ced4da}
.preview-bukti{margin-top:1rem;border-top:1px dashed var(--border);padding-top:1rem}
.thumb-bukti{max-width:180px;max-height:130px;border-radius:8px;border:1px solid var(--border);object-fit:cover}
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
        <div class="topbar-title">Tolak Pembayaran #<?= $id_pembayaran ?></div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="flash danger">
            <i class="ph ph-warning"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-head">
                <div class="card-ico"><i class="ph ph-x-circle"></i></div>
                <div>
                    <h1 class="card-title">Tolak Pembayaran</h1>
                    <div class="card-sub">Berikan alasan yang jelas agar penghuni dapat memperbaikinya.</div>
                </div>
            </div>

            <div class="card-body">
                <div class="info-box">
                    <div class="grid-2">
                        <div class="info-item">
                            <span class="info-label">Penghuni &amp; Kamar</span>
                            <div class="info-val"><?= e($bayar['nama_penghuni']) ?> (Kamar <?= e($bayar['nomor_kamar']) ?>)</div>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nominal Pembayaran</span>
                            <div class="info-val" style="color:#dc2626;font-size:1.15rem"><?= formatRupiah($bayar['jumlah_bayar']) ?></div>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Metode Pembayaran</span>
                            <div class="info-val"><?= e($bayar['metode_pembayaran']) ?></div>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tanggal Bayar</span>
                            <div class="info-val"><?= formatTanggal($bayar['tanggal_bayar']) ?></div>
                        </div>
                    </div>

                    <?php if (!empty($bayar['bukti_pembayaran'])): 
                        $ext = strtolower(pathinfo($bayar['bukti_pembayaran'], PATHINFO_EXTENSION));
                        $fileUrl = '../../uploads/bukti/' . rawurlencode($bayar['bukti_pembayaran']);
                    ?>
                    <div class="preview-bukti">
                        <span class="info-label" style="display:block;margin-bottom:.4rem">Bukti yang Diunggah Penghuni:</span>
                        <div style="display:flex;align-items:center;gap:1rem">
                            <?php if (in_array($ext, ['jpg','jpeg','png','webp','gif'])): ?>
                            <a href="<?= $fileUrl ?>" target="_blank" rel="noopener">
                                <img src="<?= $fileUrl ?>" alt="Bukti" class="thumb-bukti">
                            </a>
                            <?php else: ?>
                            <i class="ph ph-file-pdf" style="font-size:2.5rem;color:#dc2626"></i>
                            <?php endif; ?>
                            <div>
                                <a href="<?= $fileUrl ?>" target="_blank" rel="noopener" style="color:var(--green);font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem">
                                    <i class="ph ph-arrow-square-out"></i> Buka File Bukti Pembayaran
                                </a>
                                <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem"><?= e($bayar['bukti_pembayaran']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <form action="tolak.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id" value="<?= $id_pembayaran ?>">

                    <div class="form-group">
                        <label for="alasan" class="form-label">Alasan Penolakan <span style="color:#dc2626">*</span>:</label>
                        <span class="chips-label">Pilih cepat alasan:</span>
                        <div class="chips-group">
                            <span class="chip" onclick="setAlasan('Bukti transfer buram atau tidak terbaca dengan jelas.')">Bukti Buram</span>
                            <span class="chip" onclick="setAlasan('Nominal transfer tidak sesuai dengan jumlah tagihan sewa.')">Nominal Tidak Sesuai</span>
                            <span class="chip" onclick="setAlasan('Rekening tujuan tidak sesuai dengan rekening resmi kost.')">Rekening Salah</span>
                            <span class="chip" onclick="setAlasan('Transaksi belum masuk pada mutasi rekening pengelola.')">Tidak Ada Mutasi</span>
                            <span class="chip" onclick="setAlasan('File bukti pembayaran tidak valid atau corrupt.')">File Tidak Valid</span>
                        </div>
                        <textarea 
                            id="alasan" 
                            name="alasan" 
                            class="form-input" 
                            required 
                            placeholder="Tuliskan alasan penolakan secara jelas agar penghuni dapat memperbaikinya..."
                        ><?= e($_POST['alasan'] ?? $bayar['keterangan'] ?? '') ?></textarea>
                    </div>

                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;font-size:.825rem;color:#991b1b;display:flex;align-items:center;gap:.5rem">
                        <i class="ph ph-warning-circle" style="font-size:1.1rem;flex-shrink:0"></i>
                        <span>Status pembayaran akan diubah menjadi <strong>Ditolak</strong> dan penghuni akan dapat melihat alasan ini pada halaman portal penghuni.</span>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn-submit-danger">
                            <i class="ph ph-x-circle"></i> Ya, Tolak Pembayaran Ini
                        </button>
                        <a href="detail.php?id=<?= $id_pembayaran ?>" class="btn-cancel">
                            <i class="ph ph-arrow-left"></i> Batal / Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

</div>

<script>
function setAlasan(text) {
    const el = document.getElementById('alasan');
    if (el) {
        el.value = text;
        el.focus()}
}
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