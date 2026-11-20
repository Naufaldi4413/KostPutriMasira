<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole('pengelola', '../../login.php');

if (!function_exists('updateStatusKamar')) {
    function updateStatusKamar(PDO $pdo, int $id_kamar): string {
        $stmt = $pdo->prepare("SELECT kapasitas FROM kamar WHERE id_kamar = :id LIMIT 1");
        $stmt->execute(['id' => $id_kamar]);
        $kamar = $stmt->fetch();
        if (!$kamar) return 'kosong';
        $kapasitas = max(1, (int)$kamar['kapasitas']);
        $stmtSewa = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id AND status = 'aktif'");
        $stmtSewa->execute(['id' => $id_kamar]);
        $aktif = (int)$stmtSewa->fetchColumn();
        $status = ($aktif === 0) ? 'kosong' : (($aktif < $kapasitas) ? 'terisi' : 'penuh');
        $pdo->prepare("UPDATE kamar SET status = :status WHERE id_kamar = :id")->execute(['status' => $status, 'id' => $id_kamar]);
        return $status;}
}
if (!function_exists('getJumlahPenghuniAktifKamar')) {
    function getJumlahPenghuniAktifKamar(PDO $pdo, int $id_kamar): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :id AND status = 'aktif'");
        $stmt->execute(['id' => $id_kamar]);
        return (int)$stmt->fetchColumn();}
}

$user = currentUser();
$error = '';

$id_sewa = (int)($_GET['id'] ?? $_POST['id_sewa'] ?? 0);

if ($id_sewa <= 0) {
    setFlash('danger', 'ID Sewa tidak valid.');
    redirect('index.php');}

// Ambil data sewa saat ini
try {
    $stmt = $pdo->prepare("SELECT * FROM sewa WHERE id_sewa = :id LIMIT 1");
    $stmt->execute(['id' => $id_sewa]);
    $sewa = $stmt->fetch();

    if (!$sewa) {
        setFlash('danger', 'Data sewa tidak ditemukan.');
        redirect('index.php');}

    // Ambil daftar penghuni yang diizinkan (penghuni saat ini + penghuni lain yang belum punya sewa aktif)
    $stmtP = $pdo->prepare("
        SELECT p.id_penghuni, u.nama, u.username, p.no_hp
        FROM penghuni p
        JOIN users u ON p.id_user = u.id_user
        WHERE p.id_penghuni = :cur_p
           OR (
               p.status = 'aktif'
               AND p.id_penghuni NOT IN (
                   SELECT id_penghuni FROM sewa WHERE status = 'aktif' AND id_sewa != :cur_s
               )
           )
        ORDER BY u.nama ASC
    ");
    $stmtP->execute(['cur_p' => $sewa['id_penghuni'], 'cur_s' => $id_sewa]);
    $daftarPenghuni = $stmtP->fetchAll();

    // Ambil daftar kamar beserta jumlah penghuni aktif lain (di luar transaksi sewa ini)
    $stmtK = $pdo->prepare("
        SELECT 
            k.*,
            (
                SELECT COUNT(*) 
                FROM sewa s 
                WHERE s.id_kamar = k.id_kamar 
                  AND s.status = 'aktif' 
                  AND s.id_sewa != :cur_s
            ) AS penghuni_aktif_lain
        FROM kamar k
        ORDER BY k.nomor_kamar ASC
    ");
    $stmtK->execute(['cur_s' => $id_sewa]);
    $daftarKamar = $stmtK->fetchAll();} catch (PDOException $e) {
    setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    redirect('index.php');}

$id_penghuni     = (int)$sewa['id_penghuni'];
$id_kamar        = (int)$sewa['id_kamar'];
$tanggal_mulai   = $sewa['tanggal_mulai'];
$tanggal_selesai = $sewa['tanggal_selesai'] ?? '';
$periode         = $sewa['periode'] ?? '';
$total_sewa      = (float)$sewa['total_sewa'];
$status          = $sewa['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $id_penghuni     = (int)($_POST['id_penghuni'] ?? 0);
        $id_kamar        = (int)($_POST['id_kamar'] ?? 0);
        $tanggal_mulai   = trim($_POST['tanggal_mulai'] ?? '');
        $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
        $periode         = trim($_POST['periode'] ?? '');
        $total_sewa_raw  = trim($_POST['total_sewa'] ?? '');
        $status          = in_array($_POST['status'] ?? '', ['aktif', 'selesai']) ? $_POST['status'] : 'aktif';

        if ($id_penghuni <= 0) {
            $error = 'Silakan pilih penghuni kost.';} elseif ($id_kamar <= 0) {
            $error = 'Silakan pilih unit kamar.';} elseif (empty($tanggal_mulai)) {
            $error = 'Tanggal mulai sewa wajib diisi.';} elseif (empty($periode)) {
            $error = 'Periode sewa wajib diisi.';} elseif (parsePeriodeBulan($periode) <= 0) {
            $error = 'Periode sewa tidak valid. Contoh: 6 Bulan.';} elseif (!isValidDate($tanggal_mulai)) {
            $error = 'Format tanggal mulai tidak valid.';} elseif (!empty($tanggal_selesai) && !isValidDate($tanggal_selesai)) {
            $error = 'Format tanggal selesai tidak valid.';} elseif (!is_numeric($total_sewa_raw) || (float)$total_sewa_raw <= 0) {
            $error = 'Total biaya sewa harus berupa angka lebih dari 0.';} elseif (!empty($tanggal_selesai) && $tanggal_selesai < $tanggal_mulai) {
            $error = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.';} else {
            $total_sewa = (float)$total_sewa_raw;

            try {
                // Jika diset 'aktif', validasi tidak boleh dobel sewa aktif untuk penghuni
                if ($status === 'aktif') {
                    $stmtCekDobel = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM sewa 
                        WHERE id_penghuni = :pid AND status = 'aktif' AND id_sewa != :sid
                    ");
                    $stmtCekDobel->execute(['pid' => $id_penghuni, 'sid' => $id_sewa]);
                    if ((int)$stmtCekDobel->fetchColumn() > 0) {
                        $error = 'Penghuni yang dipilih masih memiliki transaksi sewa kamar lain yang berstatus aktif.';}
                }

                // Jika masih tidak ada error, cek kapasitas kamar tujuan
                if (empty($error)) {
                    $stmtKamarInfo = $pdo->prepare("SELECT nomor_kamar, kapasitas FROM kamar WHERE id_kamar = :kid LIMIT 1");
                    $stmtKamarInfo->execute(['kid' => $id_kamar]);
                    $infoKamar = $stmtKamarInfo->fetch();

                    if (!$infoKamar) {
                        $error = 'Data unit kamar tidak ditemukan.';} else {
                        $kapasitas = max(1, (int)$infoKamar['kapasitas']);

                        // Hitung penghuni aktif lain di kamar tujuan
                        $stmtAktifLain = $pdo->prepare("
                            SELECT COUNT(*) 
                            FROM sewa 
                            WHERE id_kamar = :kid AND status = 'aktif' AND id_sewa != :sid
                        ");
                        $stmtAktifLain->execute(['kid' => $id_kamar, 'sid' => $id_sewa]);
                        $aktifLain = (int)$stmtAktifLain->fetchColumn();

                        // Jika transaksi ini diset 'aktif', pastikan kamar muat
                        if ($status === 'aktif' && $aktifLain >= $kapasitas) {
                            $error = "Kamar " . e($infoKamar['nomor_kamar']) . " sudah penuh ($aktifLain/$kapasitas orang). Tidak dapat menambahkan penghuni lagi ke kamar ini.";} else {
                            $old_id_kamar = (int)$sewa['id_kamar'];

                            $pdo->beginTransaction();

                            // Kunci baris kamar tujuan lalu hitung ulang kapasitas (cegah dua admin mengisi slot terakhir bersamaan)
                            $pdo->prepare("SELECT id_kamar FROM kamar WHERE id_kamar = :kid FOR UPDATE")->execute(['kid' => $id_kamar]);
                            $stmtRe = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_kamar = :kid AND status = 'aktif' AND id_sewa != :sid");
                            $stmtRe->execute(['kid' => $id_kamar, 'sid' => $id_sewa]);
                            if ($status === 'aktif' && (int)$stmtRe->fetchColumn() >= $kapasitas) {
                                $pdo->rollBack();
                                throw new PDOException('Kamar baru saja terisi penuh oleh transaksi lain. Muat ulang halaman.');
                            }

                            $stmtUpdate = $pdo->prepare("
                                UPDATE sewa 
                                SET id_penghuni     = :pid,
                                    id_kamar        = :kid,
                                    tanggal_mulai   = :tm,
                                    tanggal_selesai = :ts,
                                    periode         = :per,
                                    total_sewa      = :tot,
                                    status          = :st
                                WHERE id_sewa = :sid
                            ");
                            $stmtUpdate->execute([
                                'pid' => $id_penghuni,
                                'kid' => $id_kamar,
                                'tm'  => $tanggal_mulai,
                                'ts'  => !empty($tanggal_selesai) ? $tanggal_selesai : null,
                                'per' => $periode,
                                'tot' => $total_sewa,
                                'st'  => $status,
                                'sid' => $id_sewa,
                            ]);

                            // Perbarui status kedua kamar (kamar lama & kamar baru bila berbeda)
                            updateStatusKamar($pdo, $old_id_kamar);
                            if ($old_id_kamar !== $id_kamar) {
                                updateStatusKamar($pdo, $id_kamar);}

                            $pdo->commit();

                            setFlash('success', 'Data transaksi sewa berhasil diperbarui.');
                            redirect("detail.php?id=$id_sewa");}
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();}
                $error = 'Terjadi kesalahan basis data: ' . $e->getMessage();}
        }
    }
}

$csrf_token = generateCsrfToken();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Sewa Kamar - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
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
        <a href="index.php" class="sb-link active"><i class="ph ph-key"></i> Kelola Sewa</a>
        <a href="../pembayaran/index.php" class="sb-link"><i class="ph ph-credit-card"></i> Kelola Pembayaran</a>
        
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
        <div class="topbar-title">Edit Transaksi Sewa</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Edit Transaksi Sewa #<?= $id_sewa ?></h1>
            <p class="pg-sub">Perbarui informasi kamar, penghuni, masa berlaku, atau status sewa.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="ph ph-warning" style="font-size:1.2rem;flex-shrink:0"></i>
            <div><?= e($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="card-form">
            <form action="edit.php?id=<?= $id_sewa ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="id_sewa" value="<?= $id_sewa ?>">

                <!-- SECTION 1: PENGHUNI & KAMAR -->
                <div class="form-section-title">
                    <i class="ph ph-user-focus"></i> 1. Penempatan Penghuni &amp; Kamar
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="id_penghuni">
                            Penghuni Kost <span class="req">*</span>
                        </label>
                        <select id="id_penghuni" name="id_penghuni" class="form-control" required>
                            <?php foreach ($daftarPenghuni as $p): ?>
                            <option value="<?= $p['id_penghuni'] ?>" <?= $id_penghuni === (int)$p['id_penghuni'] ? 'selected' : '' ?>>
                                <?= e($p['nama']) ?> (@<?= e($p['username']) ?>) <?= !empty($p['no_hp']) ? '- ' . e($p['no_hp']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="id_kamar">
                            Pilih Unit Kamar <span class="req">*</span>
                        </label>
                        <select id="id_kamar" name="id_kamar" class="form-control" required>
                            <?php foreach ($daftarKamar as $k): 
                                $kap = max(1, (int)$k['kapasitas']);
                                $aktifLain = (int)$k['penghuni_aktif_lain'];
                                $isFull = ($aktifLain >= $kap);
                                $isSelected = ($id_kamar === (int)$k['id_kamar']);
                            ?>
                            <option 
                                value="<?= $k['id_kamar'] ?>"
                                <?= $isSelected ? 'selected' : '' ?>
                                <?= ($isFull && !$isSelected) ? 'disabled' : '' ?>
                            >
                                Kamar <?= e($k['nomor_kamar']) ?> (<?= e($k['tipe_kamar'] ?: 'Standar') ?>) - <?= ($isFull && !$isSelected) ? "[PENUH - $aktifLain/$kap Org]" : "[$aktifLain/$kap Org]" ?> - Rp <?= number_format($k['harga'], 0, ',', '.') ?>/bln
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Kamar tujuan yang kapasitasnya sudah penuh dinonaktifkan.</div>
                    </div>
                </div>

                <!-- SECTION 2: PERIODE & BIAYA -->
                <div class="form-section-title">
                    <i class="ph ph-calendar-check"></i> 2. Periode Sewa &amp; Status
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="tanggal_mulai">
                            Tanggal Mulai Sewa <span class="req">*</span>
                        </label>
                        <input
                            type="date"
                            id="tanggal_mulai"
                            name="tanggal_mulai"
                            class="form-control"
                            value="<?= e($tanggal_mulai) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="periode">
                            Durasi / Periode Sewa <span class="req">*</span>
                        </label>
                        <select id="periode" name="periode" class="form-control" required>
                            <option value="1 Bulan" <?= $periode === '1 Bulan' ? 'selected' : '' ?>>1 Bulan</option>
                            <option value="2 Bulan" <?= $periode === '2 Bulan' ? 'selected' : '' ?>>2 Bulan</option>
                            <option value="3 Bulan" <?= $periode === '3 Bulan' ? 'selected' : '' ?>>3 Bulan</option>
                            <option value="6 Bulan" <?= $periode === '6 Bulan' ? 'selected' : '' ?>>6 Bulan</option>
                            <option value="9 Bulan" <?= $periode === '9 Bulan' ? 'selected' : '' ?>>9 Bulan</option>
                            <option value="12 Bulan" <?= ($periode === '12 Bulan' || $periode === '1 Tahun') ? 'selected' : '' ?>>12 Bulan</option>
                            <?php if (!empty($periode) && !in_array($periode, ['1 Bulan', '2 Bulan', '3 Bulan', '6 Bulan', '9 Bulan', '12 Bulan', '1 Tahun'])): ?>
                            <option value="<?= e($periode) ?>" selected><?= e($periode) ?></option>
                            <?php endif; ?>
                        </select>
                        <div class="help-text">Pilih durasi sewa. Tanggal selesai akan otomatis menyesuaikan saat diubah.</div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="tanggal_selesai">
                            Tanggal Selesai (Opsional)
                        </label>
                        <input
                            type="date"
                            id="tanggal_selesai"
                            name="tanggal_selesai"
                            class="form-control"
                            value="<?= e($tanggal_selesai) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="total_sewa">
                            Total Tarif Sewa (Rp) <span class="req">*</span>
                        </label>
                        <input
                            type="number"
                            id="total_sewa"
                            name="total_sewa"
                            class="form-control"
                            value="<?= e((string)$total_sewa) ?>"
                            min="0"
                            step="1000"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">
                        Status Transaksi Sewa <span class="req">*</span>
                    </label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif (Penghuni Sedang Menempati Kamar)</option>
                        <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai (Masa Sewa Berakhir / Keluar)</option>
                    </select>
                    <div class="help-text">Jika diubah menjadi 'Selesai', kapasitas kamar akan langsung berkurang dan status kamar diperbarui otomatis.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Perbarui Sewa
                    </button>
                    <a href="detail.php?id=<?= $id_sewa ?>" class="btn-cancel">
                        <i class="ph ph-x"></i> Batal
                    </a>
                </div>
            </form>
        </div>

    </main>
</div>

</div>

<script>
function hitungTanggalSelesai(tglMulaiStr, periodeStr) {
    if (!tglMulaiStr || !periodeStr) return '';
    let jumlahBulan = 0;
    const lower = periodeStr.toLowerCase();
    if (lower.includes('tahun') || lower.includes('year')) {
        const m = lower.match(/\d+/);
        jumlahBulan = (m ? parseInt(m[0], 10) : 1) * 12;
    } else {
        const m = lower.match(/\d+/);
        if (m) {
            jumlahBulan = parseInt(m[0], 10);
        }
    }
    if (jumlahBulan <= 0) return '';

    const parts = tglMulaiStr.split('-');
    if (parts.length !== 3) return '';

    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    const d = parseInt(parts[2], 10);

    let targetMonth = m + jumlahBulan;
    let targetYear = y + Math.floor((targetMonth - 1) / 12);
    targetMonth = ((targetMonth - 1) % 12) + 1;

    const maxDays = new Date(targetYear, targetMonth, 0).getDate();
    const targetDay = Math.min(d, maxDays);

    const pad = (n) => String(n).padStart(2, '0');
    return `${targetYear}-${pad(targetMonth)}-${pad(targetDay)}`;
}

function updateTanggalSelesaiOtomatis() {
    const inputMulai = document.getElementById('tanggal_mulai');
    const selectPeriode = document.getElementById('periode');
    const inputSelesai = document.getElementById('tanggal_selesai');

    if (!inputMulai || !selectPeriode || !inputSelesai) return;
    const hasil = hitungTanggalSelesai(inputMulai.value, selectPeriode.value);
    if (hasil) {
        inputSelesai.value = hasil;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const inputMulai = document.getElementById('tanggal_mulai');
    const selectPeriode = document.getElementById('periode');

    selectPeriode?.addEventListener('change', updateTanggalSelesaiOtomatis);
    inputMulai?.addEventListener('change', updateTanggalSelesaiOtomatis);
});
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