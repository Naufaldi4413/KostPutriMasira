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
    $stmtP = $pdo->prepare("SELECT id_penghuni, nama FROM penghuni p JOIN users u ON p.id_user = u.id_user WHERE p.id_user = :id_user LIMIT 1");
    $stmtP->execute(['id_user' => $id_user]);
    $rowP = $stmtP->fetch();
    $id_penghuni = (int)($rowP['id_penghuni'] ?? 0);
    $_SESSION['id_penghuni'] = $id_penghuni;
}

$flash = getFlash();
$error = '';

$sewaAktif = null;
$latestBayar = null;

try {
    $stmtSewa = $pdo->prepare("
        SELECT s.*, k.nomor_kamar, k.tipe_kamar, k.harga, k.keterangan AS keterangan_kamar
        FROM sewa s
        JOIN kamar k ON s.id_kamar = k.id_kamar
        WHERE s.id_penghuni = :id_penghuni AND s.status = 'aktif'
        ORDER BY s.id_sewa DESC
        LIMIT 1
    ");
    $stmtSewa->execute(['id_penghuni' => $id_penghuni]);
    $sewaAktif = $stmtSewa->fetch();

    if ($sewaAktif) {
        $stmtBayar = $pdo->prepare("
            SELECT * FROM pembayaran
            WHERE id_sewa = :id_sewa
            ORDER BY id_pembayaran DESC
            LIMIT 1
        ");
        $stmtBayar->execute(['id_sewa' => $sewaAktif['id_sewa']]);
        $latestBayar = $stmtBayar->fetch();
    }
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_bayar'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';
    } elseif (!$sewaAktif) {
        $error = 'Anda tidak memiliki kontrak sewa aktif untuk dibayar.';
    } else {
        $metode = trim($_POST['metode_pembayaran'] ?? '');
        $tanggal_bayar = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
        $keterangan = trim($_POST['keterangan'] ?? '');

        // Sisa tagihan = total sewa - pembayaran yang sudah dikonfirmasi
        $sisaTagihan = (float)$sewaAktif['total_sewa'] - getTotalTerbayar($pdo, (int)$sewaAktif['id_sewa']);
        $stMenunggu = $pdo->prepare("SELECT COUNT(*) FROM pembayaran WHERE id_sewa = :s AND status_verifikasi = 'menunggu'");
        $stMenunggu->execute(['s' => $sewaAktif['id_sewa']]);
        if ($sisaTagihan <= 0) {
            $error = 'Tagihan sewa Anda sudah lunas. Tidak ada pembayaran yang perlu diunggah.';
        } elseif ((int)$stMenunggu->fetchColumn() > 0) {
            $error = 'Anda masih memiliki bukti pembayaran yang menunggu verifikasi pengelola. Mohon tunggu hasilnya.';
        } elseif (!isValidDate($tanggal_bayar) || $tanggal_bayar > date('Y-m-d')) {
            $error = 'Tanggal pembayaran tidak valid.';
        } elseif (!in_array($metode, ['Transfer', 'QRIS'])) {
            $error = 'Metode pembayaran tidak valid.';
        } elseif (empty($_FILES['bukti_pembayaran']['name'])) {
            $error = 'Silakan pilih file bukti pembayaran (JPG, PNG, atau PDF) terlebih dahulu.';
        } else {
            $file = $_FILES['bukti_pembayaran'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Gagal mengunggah file bukti. Kode error: ' . $file['error'];
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Ukuran file melebihi batas maksimal 2MB. Harap perkecil file Anda.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/pjpeg', 'application/pdf'];

                if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) {
                    $error = 'Format file tidak diizinkan. Harap unggah foto bukti dalam format JPG, PNG, atau PDF.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/bukti/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $safeName = 'bukti_' . $sewaAktif['id_sewa'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetPath = $uploadDir . $safeName;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        try {
                            $stmtIns = $pdo->prepare("
                                INSERT INTO pembayaran (
                                    id_sewa,
                                    tanggal_bayar,
                                    jumlah_bayar,
                                    metode_pembayaran,
                                    status,
                                    status_verifikasi,
                                    bukti_pembayaran,
                                    keterangan
                                ) VALUES (
                                    :id_sewa,
                                    :tanggal_bayar,
                                    :jumlah_bayar,
                                    :metode_pembayaran,
                                    'belum_lunas',
                                    'menunggu',
                                    :bukti_pembayaran,
                                    :keterangan
                                )
                            ");
                            $stmtIns->execute([
                                'id_sewa' => $sewaAktif['id_sewa'],
                                'tanggal_bayar' => $tanggal_bayar,
                                'jumlah_bayar' => $sisaTagihan,
                                'metode_pembayaran' => $metode,
                                'bukti_pembayaran' => $safeName,
                                'keterangan' => $keterangan
                            ]);

                            setFlash('success', 'Bukti pembayaran berhasil diunggah! Pengelola sedang memverifikasi pembayaran Anda.');
                            redirect('pembayaran.php');
                        } catch (PDOException $e) {
                            $error = 'Gagal menyimpan transaksi pembayaran: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'Gagal memindahkan file ke server.';
                    }
                }
            }
        }
    }
}

$csrf_token = generateCsrfToken();
$waPengelola = '6282233053344';
$waPesan = urlencode("Halo Pengelola Kost Putri Masira, saya {$user['nama']} ingin menanyakan perihal pembayaran sewa.");
$waUrl = "https://wa.me/{$waPengelola}?text={$waPesan}";
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran Sewa - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.methods-nav{display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap}
.method-tab{flex:1;min-width:180px;background:#fff;border:2px solid var(--border);border-radius:10px;padding:.85rem;text-align:center;cursor:pointer;transition:all .15s}
.method-tab:hover{border-color:#b7e4c7;background:#fafaf9}
.method-tab.active{border-color:var(--green);background:#f0faf4}
.method-tab-ico{font-size:1.5rem;color:var(--green);display:block;margin-bottom:.25rem}
.method-tab-title{font-size:.85rem;font-weight:700;display:block}
.method-tab-desc{font-size:.72rem;color:var(--muted);display:block}
.method-content{display:none}
.method-content.active{display:block}
.demo-tag{display:inline-flex;align-items:center;gap:.25rem;background:#fee2e2;color:#dc2626;padding:.2rem .55rem;border-radius:999px;font-size:.6875rem;font-weight:700;letter-spacing:.04em}
.bank-card{background:#fafaf9;border:1px solid var(--border);border-radius:8px;padding:1.15rem;margin-bottom:1.25rem}
.rek-number{font-size:1.35rem;font-weight:800;letter-spacing:.05em;color:#1a1a18;font-family:monospace}
.btn-copy{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .8rem;background:#fff;border:1px solid var(--border);border-radius:7px;font-size:.8rem;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;color:var(--green);cursor:pointer}
.btn-copy:hover{background:#f0faf4;border-color:var(--green)}
.qris-card{text-align:center;padding:1.25rem;background:#fafaf9;border:1px solid var(--border);border-radius:8px;margin-bottom:1.25rem}
.qris-img{max-width:230px;width:100%;height:auto;border-radius:8px;border:1px solid var(--border);margin:.5rem 0}
.instructions{background:#f8f9fa;border-left:4px solid var(--green);padding:.85rem 1.15rem;border-radius:0 8px 8px 0;margin-bottom:1.25rem;font-size:.825rem;color:#495057}
.instructions ol{margin-left:1.25rem;margin-top:.4rem;display:flex;flex-direction:column;gap:.25rem}
.btn-submit-pay{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.75rem 1.25rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s}
.btn-submit-pay:hover{background:var(--green-dark)}
.toast-copy{display:none;align-items:center;gap:.25rem;color:var(--green);font-size:.78rem;font-weight:600;margin-top:.25rem}
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
        <a href="pembayaran.php" class="sb-link active"><i class="ph ph-credit-card"></i> Pembayaran</a>
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
                <div class="sb-uname"><?= e($user['nama'] ?? 'Penghuni') ?></div>
                <div class="sb-urole">Penghuni</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="ph ph-sign-out"></i> Keluar</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Pembayaran Sewa Kamar</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content">
        <?php if ($flash): ?>
        <div class="flash <?= in_array($flash['type'], ['danger','error']) ? 'danger' : 'success' ?>">
            <i class="ph <?= in_array($flash['type'], ['danger','error']) ? 'ph-warning' : 'ph-check-circle' ?>"></i>
            <span><?= e($flash['message']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="flash danger">
            <i class="ph ph-warning"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <div class="pg-header">
            <div>
                <div class="pg-title">Pembayaran Sewa Kos</div>
                <p class="pg-sub">Selesaikan pembayaran sewa kamar per semester dengan metode Cash, Transfer Bank, atau QRIS.</p>
            </div>
        </div>

        <?php if (!$sewaAktif): ?>
        <div class="panel">
            <div class="panel-body empty">
                <i class="ph ph-receipt"></i>
                <div style="font-weight:700;font-size:1.05rem;margin-bottom:.25rem">Belum Ada Sewa Aktif</div>
                <p>Anda belum memiliki kontrak sewa kamar aktif saat ini.</p>
            </div>
        </div>
        <?php else: ?>

        <!-- Panel Rincian Tagihan -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="ph ph-receipt"></i> Rincian Tagihan Sewa Semester</div>
                <span class="badge" style="background:#e0f2fe;color:#0284c7;font-weight:700">Kamar <?= e($sewaAktif['nomor_kamar']) ?></span>
            </div>
            <div class="panel-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem">
                    <div>
                        <div style="font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase">Kamar Ditempati</div>
                        <div style="font-size:1.1rem;font-weight:700;color:var(--green)">Kamar <?= e($sewaAktif['nomor_kamar']) ?> (<?= e($sewaAktif['tipe_kamar'] ?: 'Standar') ?>)</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase">Periode Sewa</div>
                        <div style="font-size:1rem;font-weight:700"><?= e($sewaAktif['periode'] ?: '1 Semester') ?></div>
                        <div style="font-size:.78rem;color:var(--muted)"><?= formatTanggal($sewaAktif['tanggal_mulai']) ?> s/d <?= formatTanggal($sewaAktif['tanggal_selesai']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase">Jumlah Tagihan</div>
                        <div style="font-size:1.35rem;font-weight:800;color:var(--green)"><?= formatRupiah($sewaAktif['total_sewa']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($latestBayar && $latestBayar['status_verifikasi'] === 'dikonfirmasi'): ?>
        <div class="flash success">
            <i class="ph ph-check-circle" style="font-size:1.3rem"></i>
            <div>
                <strong>Pembayaran semester sudah dikonfirmasi (Lunas).</strong><br>
                <span>Pembayaran sewa sebesar <?= formatRupiah($latestBayar['jumlah_bayar']) ?> via <?= e($latestBayar['metode_pembayaran']) ?> telah diverifikasi oleh pengelola.</span>
            </div>
        </div>

        <?php elseif ($latestBayar && $latestBayar['status_verifikasi'] === 'menunggu'): ?>
        <div class="flash" style="background:#fff3cd;border:1px solid #ffeeba;color:#856404">
            <i class="ph ph-clock" style="font-size:1.3rem"></i>
            <div>
                <strong>Pembayaran Anda sedang menunggu verifikasi pengelola.</strong><br>
                <span>Pengelola kost sedang memverifikasi bukti pembayaran yang Anda kirimkan.</span>
            </div>
        </div>

        <?php else: ?>

            <?php if ($latestBayar && $latestBayar['status_verifikasi'] === 'ditolak'): ?>
            <div class="flash danger">
                <i class="ph ph-warning-circle" style="font-size:1.3rem"></i>
                <div>
                    <strong>Pembayaran ditolak. Silakan periksa kembali bukti pembayaran atau hubungi pengelola.</strong>
                    <?php if (!empty($latestBayar['keterangan'])): ?>
                    <br><strong>Alasan:</strong> <?= e($latestBayar['keterangan']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="ph ph-wallet"></i> Pilih Metode Pembayaran</div>
                </div>
                <div class="panel-body">
                    <div class="methods-nav">
                        <div class="method-tab active" onclick="switchMethod('transfer')">
                            <i class="ph ph-bank method-tab-ico"></i>
                            <span class="method-tab-title">Transfer Bank</span>
                            <span class="method-tab-desc">Bank BRI (Demo)</span>
                        </div>
                        <div class="method-tab" onclick="switchMethod('qris')">
                            <i class="ph ph-qr-code method-tab-ico"></i>
                            <span class="method-tab-title">QRIS</span>
                            <span class="method-tab-desc">E-Wallet &amp; Mobile Banking</span>
                        </div>
                        <div class="method-tab" onclick="switchMethod('cash')">
                            <i class="ph ph-hand-coins method-tab-ico"></i>
                            <span class="method-tab-title">Tunai (Cash)</span>
                            <span class="method-tab-desc">Bayar di Kantor Pengelola</span>
                        </div>
                    </div>

                    <!-- 1. TRANSFER -->
                    <div id="method-transfer" class="method-content active">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap">
                            <strong style="font-size:.95rem">Transfer Rekening BRI</strong>
                            <span class="demo-tag">DATA SEMENTARA / DEMO</span>
                        </div>

                        <div class="bank-card">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                                <div>
                                    <div style="font-weight:700;color:var(--green)">Bank BRI</div>
                                    <div class="rek-number" id="rekBRI">1234567890</div>
                                    <div style="font-size:.825rem;color:#495057">Atas Nama: Pengelola Kost</div>
                                </div>
                                <div>
                                    <button type="button" class="btn-copy" onclick="copyRekening('1234567890')">
                                        <i class="ph ph-copy"></i> Salin Nomor Rekening
                                    </button>
                                    <div id="copyToast" class="toast-copy"><i class="ph ph-check"></i> Nomor rekening disalin!</div>
                                </div>
                            </div>
                        </div>

                        <div class="instructions">
                            <strong>Petunjuk:</strong>
                            <ol>
                                <li>Transfer tepat <strong><?= formatRupiah($sewaAktif['total_sewa']) ?></strong> ke rekening di atas.</li>
                                <li>Simpan bukti transfer foto atau tangkapan layar.</li>
                                <li>Unggah bukti pada formulir di bawah ini lalu klik <strong>"Saya Sudah Bayar"</strong>.</li>
                            </ol>
                        </div>

                        <form action="pembayaran.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action_bayar" value="1">
                            <input type="hidden" name="metode_pembayaran" value="Transfer">

                            <div class="form-group">
                                <label class="form-label">Tanggal Transfer <span style="color:#dc2626">*</span></label>
                                <input type="date" name="tanggal_bayar" class="form-input" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Unggah Bukti Transfer <span style="color:#dc2626">*</span></label>
                                <input type="file" name="bukti_pembayaran" class="form-input" accept=".jpg,.jpeg,.png,.pdf" required>
                                <span style="font-size:.75rem;color:var(--muted)">Format: JPG, PNG, atau PDF. Maks. 2MB.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Catatan Tambahan (Opsional)</label>
                                <input type="text" name="keterangan" class="form-input" placeholder="Contoh: Transfer dari rekening BRI a.n Siti Fatimah">
                            </div>

                            <button type="submit" class="btn-submit-pay">
                                <i class="ph ph-paper-plane-tilt"></i> Saya Sudah Bayar
                            </button>
                        </form>
                    </div>

                    <!-- 2. QRIS -->
                    <div id="method-qris" class="method-content">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap">
                            <strong style="font-size:.95rem">Pembayaran QRIS</strong>
                            <span class="demo-tag">QRIS SEMENTARA / DEMO</span>
                        </div>

                        <div class="qris-card">
                            <div style="font-size:.825rem;color:var(--muted)">Pindai barcode QRIS melalui m-banking atau e-wallet:</div>
                            <img src="../assets/images/qris-placeholder.png" alt="QRIS Demo Kost Putri Masira" class="qris-img">
                            <div style="font-size:.9rem;font-weight:700;color:var(--green)">KOST PUTRI MASIRA</div>
                            <div style="font-size:.78rem;color:var(--muted)">NMID: ID102030405060 (DEMO)</div>
                        </div>

                        <div class="instructions">
                            <strong>Petunjuk:</strong>
                            <ol>
                                <li>Scan kode QRIS di atas melalui mobile banking atau e-wallet (BCA, BRI, GoPay, OVO, Dana, dll).</li>
                                <li>Ketikkan nominal tepat <strong><?= formatRupiah($sewaAktif['total_sewa']) ?></strong>.</li>
                                <li>Selesaikan pembayaran, simpan screenshot bukti, dan unggah di formulir bawah ini.</li>
                            </ol>
                        </div>

                        <form action="pembayaran.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action_bayar" value="1">
                            <input type="hidden" name="metode_pembayaran" value="QRIS">

                            <div class="form-group">
                                <label class="form-label">Tanggal Pembayaran <span style="color:#dc2626">*</span></label>
                                <input type="date" name="tanggal_bayar" class="form-input" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Unggah Bukti Transaksi QRIS <span style="color:#dc2626">*</span></label>
                                <input type="file" name="bukti_pembayaran" class="form-input" accept=".jpg,.jpeg,.png,.pdf" required>
                                <span style="font-size:.75rem;color:var(--muted)">Format: JPG, PNG, atau PDF. Maks. 2MB.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Catatan Tambahan (Opsional)</label>
                                <input type="text" name="keterangan" class="form-input" placeholder="Contoh: Bayar via GoPay / OVO a.n Siti">
                            </div>

                            <button type="submit" class="btn-submit-pay">
                                <i class="ph ph-paper-plane-tilt"></i> Saya Sudah Bayar
                            </button>
                        </form>
                    </div>

                    <!-- 3. CASH -->
                    <div id="method-cash" class="method-content">
                        <div style="text-align:center;padding:1.5rem;background:#fafaf9;border:1px solid var(--border);border-radius:8px;margin-bottom:1.25rem">
                            <i class="ph ph-hand-coins" style="font-size:2.8rem;color:var(--green);display:block;margin-bottom:.5rem"></i>
                            <div style="font-size:1.1rem;font-weight:700;margin-bottom:.35rem">Bayar Tunai di Kantor Pengelola</div>
                            <p style="font-size:.85rem;color:var(--muted);max-width:500px;margin:0 auto">
                                Silakan temui pengelola kost secara langsung di kantor Kost Putri Masira.
                            </p>
                            <div style="margin-top:.75rem;font-size:1.25rem;font-weight:800;color:var(--green)">
                                Jumlah: <?= formatRupiah($sewaAktif['total_sewa']) ?>
                            </div>
                        </div>

                        <div class="instructions">
                            <strong>Alur Pembayaran Tunai:</strong>
                            <ol>
                                <li>Siapkan uang pas sejumlah <strong><?= formatRupiah($sewaAktif['total_sewa']) ?></strong>.</li>
                                <li>Datangi kantor pengelola kost pada jam operasional.</li>
                                <li>Serahkan uang tunai, dan pengelola akan langsung mengonfirmasi status pelunasan sewa di sistem.</li>
                                <li><strong>Tidak perlu mengunggah bukti untuk metode Cash.</strong></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

</div>

<script>
function switchMethod(method) {
    document.querySelectorAll('.method-tab').forEach((tab) => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.method-content').forEach(content => {
        content.classList.remove('active');
    });

    if (method === 'transfer') {
        document.querySelectorAll('.method-tab')[0].classList.add('active');
        document.getElementById('method-transfer').classList.add('active');
    } else if (method === 'qris') {
        document.querySelectorAll('.method-tab')[1].classList.add('active');
        document.getElementById('method-qris').classList.add('active');
    } else if (method === 'cash') {
        document.querySelectorAll('.method-tab')[2].classList.add('active');
        document.getElementById('method-cash').classList.add('active');
    }
}

function copyRekening(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast();
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast();
}

function showToast() {
    const toast = document.getElementById('copyToast');
    if (toast) {
        toast.style.display = 'inline-flex';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 2500);
    }
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
