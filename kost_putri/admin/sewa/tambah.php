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

$preselectedPenghuni = (int)($_GET['id_penghuni'] ?? 0);

// Ambil daftar penghuni aktif yang belum memiliki sewa aktif
try {
    $stmtP = $pdo->query("
        SELECT p.id_penghuni, u.nama, u.username, p.no_hp
        FROM penghuni p
        JOIN users u ON p.id_user = u.id_user
        WHERE p.status = 'aktif'
          AND p.id_penghuni NOT IN (
              SELECT id_penghuni FROM sewa WHERE status = 'aktif'
          )
        ORDER BY u.nama ASC
    ");
    $daftarPenghuniBebas = $stmtP->fetchAll();

    // Jika preselected penghuni aktif tapi sudah punya sewa, tetap cek
    if ($preselectedPenghuni > 0) {
        $stmtCekPre = $pdo->prepare("
            SELECT p.id_penghuni, u.nama, u.username, p.no_hp,
                   (SELECT COUNT(*) FROM sewa s WHERE s.id_penghuni = p.id_penghuni AND s.status = 'aktif') AS has_sewa
            FROM penghuni p
            JOIN users u ON p.id_user = u.id_user
            WHERE p.id_penghuni = :id
        ");
        $stmtCekPre->execute(['id' => $preselectedPenghuni]);
        $prePenghuniData = $stmtCekPre->fetch();
        if ($prePenghuniData && (int)$prePenghuniData['has_sewa'] > 0) {
            setFlash('danger', "Penghuni " . e($prePenghuniData['nama']) . " sudah memiliki sewa kamar aktif.");
            $preselectedPenghuni = 0;}
    }

    // Ambil daftar kamar beserta kapasitas dan jumlah penghuni aktif saat ini
    $stmtKamar = $pdo->query("
        SELECT 
            k.*,
            (SELECT COUNT(*) FROM sewa s WHERE s.id_kamar = k.id_kamar AND s.status = 'aktif') AS penghuni_aktif
        FROM kamar k
        ORDER BY k.nomor_kamar ASC
    ");
    $daftarKamar = $stmtKamar->fetchAll();} catch (PDOException $e) {
    $daftarPenghuniBebas = [];
    $daftarKamar = [];
    $error = 'Terjadi kesalahan sistem saat memuat data: ' . $e->getMessage();}

$id_penghuni     = $preselectedPenghuni;
$id_kamar        = 0;
$tanggal_mulai   = date('Y-m-d');
$tanggal_selesai = date('Y-m-d', strtotime('+6 months'));
$periode         = '6 Bulan';
$total_sewa      = '';
$hunian          = 'sendiri';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';} else {
        $id_penghuni     = (int)($_POST['id_penghuni'] ?? 0);
        $id_kamar        = (int)($_POST['id_kamar'] ?? 0);
        $tanggal_mulai   = trim($_POST['tanggal_mulai'] ?? date('Y-m-d'));
        $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
        $periode         = trim($_POST['periode'] ?? '6 Bulan');
        $total_sewa_raw  = trim($_POST['total_sewa'] ?? '');
        $berdua          = (($_POST['hunian'] ?? 'sendiri') === 'berdua');
        $hunian          = $berdua ? 'berdua' : 'sendiri';

        // Jika total dikosongkan, hitung otomatis: tarif kamar x jumlah bulan (berdua = dibagi 2)
        if ($total_sewa_raw === '' && $id_kamar > 0) {
            $stH = $pdo->prepare("SELECT harga FROM kamar WHERE id_kamar = :k");
            $stH->execute(['k' => $id_kamar]);
            $hg = $stH->fetchColumn();
            if ($hg !== false) {
                $total_sewa_raw = (string)hitungTotalSewa((float)$hg, $periode, $berdua);
            }
        }

        // 1. Validasi Input Dasar
        if ($id_penghuni <= 0) {
            $error = 'Silakan pilih penghuni kost.';} elseif ($id_kamar <= 0) {
            $error = 'Silakan pilih kamar yang akan disewa.';} elseif (empty($tanggal_mulai)) {
            $error = 'Tanggal mulai sewa wajib diisi.';} elseif (empty($periode)) {
            $error = 'Periode sewa wajib diisi (contoh: 1 Bulan).';} elseif (parsePeriodeBulan($periode) <= 0) {
            $error = 'Periode sewa tidak valid. Pilih durasi, contoh: 6 Bulan.';} elseif (!isValidDate($tanggal_mulai)) {
            $error = 'Format tanggal mulai tidak valid.';} elseif (!empty($tanggal_selesai) && !isValidDate($tanggal_selesai)) {
            $error = 'Format tanggal selesai tidak valid.';} elseif (!is_numeric($total_sewa_raw) || (float)$total_sewa_raw <= 0) {
            $error = 'Total biaya sewa harus berupa angka lebih dari 0.';} elseif (!empty($tanggal_selesai) && $tanggal_selesai < $tanggal_mulai) {
            $error = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai sewa.';} else {
            $total_sewa = (float)$total_sewa_raw;

            try {
                // 2. Validasi: Jangan izinkan sewa aktif ganda untuk penghuni yang sama
                $stmtCekGanda = $pdo->prepare("SELECT COUNT(*) FROM sewa WHERE id_penghuni = :pid AND status = 'aktif'");
                $stmtCekGanda->execute(['pid' => $id_penghuni]);
                if ((int)$stmtCekGanda->fetchColumn() > 0) {
                    $error = 'Penghuni yang dipilih saat ini masih memiliki sewa kamar aktif.';} else {
                    // 3. Validasi Kapasitas Kamar
                    $stmtCekKamar = $pdo->prepare("SELECT nomor_kamar, kapasitas FROM kamar WHERE id_kamar = :kid LIMIT 1");
                    $stmtCekKamar->execute(['kid' => $id_kamar]);
                    $dataKamar = $stmtCekKamar->fetch();

                    if (!$dataKamar) {
                        $error = 'Data kamar tidak ditemukan.';} else {
                        $kapasitasKamar = max(1, (int)$dataKamar['kapasitas']);
                        if ($berdua && $kapasitasKamar < 2) {
                            $error = 'Kamar ' . e($dataKamar['nomor_kamar']) . ' berkapasitas 1 orang, tidak bisa disewa berdua.';
                        }
                        $penghuniAktifKamar = getJumlahPenghuniAktifKamar($pdo, $id_kamar);

                        if (!empty($error)) {
                            // error validasi (mis. kamar tidak bisa berdua) sudah terisi
                        } elseif ($penghuniAktifKamar >= $kapasitasKamar) {
                            $error = "Kamar " . e($dataKamar['nomor_kamar']) . " sudah penuh! Kapasitas maksimum adalah $kapasitasKamar orang dan saat ini sudah dihuni $penghuniAktifKamar orang.";} else {
                            // 4. Proses INSERT Sewa dalam Transaksi
                            $pdo->beginTransaction();

                            // Kunci baris kamar agar dua admin tidak bisa mengisi slot terakhir bersamaan
                            $stmtLock = $pdo->prepare("SELECT kapasitas FROM kamar WHERE id_kamar = :kid FOR UPDATE");
                            $stmtLock->execute(['kid' => $id_kamar]);
                            if (getJumlahPenghuniAktifKamar($pdo, $id_kamar) >= $kapasitasKamar) {
                                $pdo->rollBack();
                                throw new PDOException('Kamar baru saja terisi penuh oleh transaksi lain. Muat ulang halaman.');
                            }

                            $stmtInsertSewa = $pdo->prepare("
                                INSERT INTO sewa (id_penghuni, id_kamar, tanggal_mulai, tanggal_selesai, periode, total_sewa, status)
                                VALUES (:id_penghuni, :id_kamar, :tanggal_mulai, :tanggal_selesai, :periode, :total_sewa, 'aktif')
                            ");
                            $stmtInsertSewa->execute([
                                'id_penghuni'     => $id_penghuni,
                                'id_kamar'        => $id_kamar,
                                'tanggal_mulai'   => $tanggal_mulai,
                                'tanggal_selesai' => !empty($tanggal_selesai) ? $tanggal_selesai : null,
                                'periode'         => $periode,
                                'total_sewa'      => $total_sewa,
                            ]);

                            // 5. Update Status Kamar otomatis berdasarkan kapasitas & sewa aktif
                            updateStatusKamar($pdo, $id_kamar);

                            $pdo->commit();

                            setFlash('success', "Transaksi sewa Kamar " . e($dataKamar['nomor_kamar']) . " berhasil dibuat.");
                            redirect('index.php');}
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
<title>Buat Transaksi Sewa Baru - Kost Putri Masira</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<link rel="stylesheet" href="../../assets/css/app.css">
<style>
/* Gaya khusus halaman ini. Gaya bersama ada di assets/css/app.css */
.room-preview-box{background:#f0faf4;border:1px solid #b7e4c7;border-radius:8px;padding:.75rem 1rem;font-size:.8125rem;color:#1b4332;margin-top:.5rem;display:none}
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
        <div class="topbar-title">Tambah Sewa Kamar</div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="ph ph-calendar"></i> <?= date('l, d F Y') ?></div>
            <button type="button" class="btn-burger" id="burgerBtn" aria-label="Buka Navigasi" aria-expanded="false"><i class="ph ph-list"></i></button>
        </div>
    </header>

    <main class="content content-form">
        <div class="pg-header">
            <h1 class="pg-title">Form Pemesanan / Sewa Kamar</h1>
            <p class="pg-sub">Tetapkan kamar untuk penghuni dengan validasi kapasitas kamar dan tarif sewa.</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-error">
            <i class="ph ph-warning" style="font-size:1.2rem;flex-shrink:0"></i>
            <div><?= e($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="card-form">
            <form action="tambah.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <!-- SECTION 1: PENGHUNI & KAMAR -->
                <div class="form-section-title">
                    <i class="ph ph-user-focus"></i> 1. Pilih Penghuni &amp; Kamar
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="id_penghuni">
                            Pilih Penghuni Kost <span class="req">*</span>
                        </label>
                        <select id="id_penghuni" name="id_penghuni" class="form-control" required>
                            <option value="">-- Pilih Penghuni Terdaftar --</option>
                            <?php foreach ($daftarPenghuniBebas as $p): ?>
                            <option value="<?= $p['id_penghuni'] ?>" <?= $id_penghuni === (int)$p['id_penghuni'] ? 'selected' : '' ?>>
                                <?= e($p['nama']) ?> (@<?= e($p['username']) ?>) <?= !empty($p['no_hp']) ? '- ' . e($p['no_hp']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($daftarPenghuniBebas)): ?>
                        <div class="help-text" style="color:#d97706">
                            Semua penghuni aktif saat ini sudah memiliki sewa kamar aktif, atau belum ada penghuni. <a href="../penghuni/tambah.php" style="color:var(--green);font-weight:600">Daftarkan penghuni baru &rarr;</a>
                        </div>
                        <?php else: ?>
                        <div class="help-text">Hanya menampilkan penghuni aktif yang belum memiliki sewa aktif.</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="id_kamar">
                            Pilih Unit Kamar <span class="req">*</span>
                        </label>
                        <select id="id_kamar" name="id_kamar" class="form-control" required onchange="handleKamarChange(this)">
                            <option value="">-- Pilih Unit Kamar --</option>
                            <?php foreach ($daftarKamar as $k): 
                                $kap = max(1, (int)$k['kapasitas']);
                                $aktif = (int)$k['penghuni_aktif'];
                                $isFull = ($aktif >= $kap);
                            ?>
                            <option 
                                value="<?= $k['id_kamar'] ?>"
                                data-harga="<?= (float)$k['harga'] ?>"
                                data-kapasitas="<?= $kap ?>"
                                data-aktif="<?= $aktif ?>"
                                data-nomor="<?= e($k['nomor_kamar']) ?>"
                                <?= $id_kamar === (int)$k['id_kamar'] ? 'selected' : '' ?>
                                <?= $isFull ? 'disabled' : '' ?>
                            >
                                Kamar <?= e($k['nomor_kamar']) ?> (<?= e($k['tipe_kamar'] ?: 'Standar') ?>) - <?= $isFull ? "[PENUH - $aktif/$kap Org]" : "[TERSEDIA - $aktif/$kap Org]" ?> - Rp <?= number_format($k['harga'], 0, ',', '.') ?>/bln
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="roomPreview" class="room-preview-box"></div>
                        <div class="help-text">Kamar dengan status PENUH dinonaktifkan secara otomatis.</div>
                    </div>
                </div>

                <!-- SECTION 2: PERIODE & BIAYA -->
                <div class="form-section-title">
                    <i class="ph ph-calendar-check"></i> 2. Periode Sewa &amp; Tarif
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
                            <option value="6 Bulan" <?= ($periode === '6 Bulan' || empty($periode)) ? 'selected' : '' ?>>6 Bulan</option>
                            <option value="9 Bulan" <?= $periode === '9 Bulan' ? 'selected' : '' ?>>9 Bulan</option>
                            <option value="12 Bulan" <?= ($periode === '12 Bulan' || $periode === '1 Tahun') ? 'selected' : '' ?>>12 Bulan</option>
                            <?php if (!empty($periode) && !in_array($periode, ['1 Bulan', '2 Bulan', '3 Bulan', '6 Bulan', '9 Bulan', '12 Bulan', '1 Tahun'])): ?>
                            <option value="<?= e($periode) ?>" selected><?= e($periode) ?></option>
                            <?php endif; ?>
                        </select>
                        <div class="help-text">Pilih durasi sewa (default 6 bulan). Tanggal selesai akan otomatis menyesuaikan.</div>
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
                        <div class="help-text">Perkiraan akhir masa sewa. Dapat diperbarui nantinya.</div>
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
                            placeholder="Contoh: 500000"
                            value="<?= e((string)$total_sewa) ?>"
                            min="0"
                            step="1000"
                        >
                        <div class="help-text">Terisi otomatis: tarif kamar &times; jumlah bulan (berdua dibagi 2). Boleh diubah manual; kosongkan untuk dihitung otomatis.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="hunian">Jenis Hunian</label>
                    <select id="hunian" name="hunian" class="form-control">
                        <option value="sendiri" <?= $hunian === 'sendiri' ? 'selected' : '' ?>>Sendiri (tarif penuh per bulan)</option>
                        <option value="berdua" <?= $hunian === 'berdua' ? 'selected' : '' ?>>Berdua (tarif dibagi 2 per orang)</option>
                    </select>
                    <div class="help-text">Berdua hanya untuk kamar berkapasitas 2 orang.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="ph ph-check-circle"></i> Simpan Transaksi Sewa
                    </button>
                    <a href="index.php" class="btn-cancel">
                        <i class="ph ph-x"></i> Batal
                    </a>
                </div>
            </form>
        </div>

    </main>
</div>

</div>

<script>
function handleKamarChange(select) {
    const opt = select.options[select.selectedIndex];
    const preview = document.getElementById('roomPreview');
    const inputTotal = document.getElementById('total_sewa');

    if (!opt || !opt.value) {
        preview.style.display = 'none';
        return}

    const harga = opt.getAttribute('data-harga');
    const nomor = opt.getAttribute('data-nomor');
    const kap = opt.getAttribute('data-kapasitas');
    const aktif = opt.getAttribute('data-aktif');

    updateTotalOtomatis(true);

    preview.innerHTML = `<strong>Kamar ${nomor}:</strong> Kapasitas ${kap} orang, terisi ${aktif} orang. Sisa tempat: ${kap - aktif} orang. Tarif standar: Rp ${Number(harga).toLocaleString('id-ID')}/bln.`;
    preview.style.display = 'block'}

function jumlahBulan(periodeStr) {
    const lower = (periodeStr || '').toLowerCase();
    const m = lower.match(/\d+/);
    if (!m) return 0;
    const n = parseInt(m[0], 10);
    return (lower.includes('tahun') || lower.includes('year')) ? n * 12 : n;
}

let totalManual = false;
function updateTotalOtomatis(force) {
    const sel = document.getElementById('id_kamar');
    const inputTotal = document.getElementById('total_sewa');
    const periode = document.getElementById('periode');
    const hunian = document.getElementById('hunian');
    if (!sel || !inputTotal || !periode) return;
    if (totalManual && !force) return;
    const opt = sel.options[sel.selectedIndex];
    const harga = opt ? parseFloat(opt.getAttribute('data-harga')) : NaN;
    const bulan = jumlahBulan(periode.value);
    if (!harga || !bulan) return;
    const tarif = (hunian && hunian.value === 'berdua') ? harga / 2 : harga;
    inputTotal.value = Math.round(tarif * bulan);
}

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

// Trigger initial preview and bind periode auto calculation
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('id_kamar');
    if (sel && sel.value) {
        handleKamarChange(sel);
    }

    const inputMulai = document.getElementById('tanggal_mulai');
    const selectPeriode = document.getElementById('periode');
    const inputSelesai = document.getElementById('tanggal_selesai');

    selectPeriode?.addEventListener('change', () => { updateTanggalSelesaiOtomatis(); totalManual = false; updateTotalOtomatis(); });
    document.getElementById('hunian')?.addEventListener('change', () => { totalManual = false; updateTotalOtomatis(); });
    document.getElementById('total_sewa')?.addEventListener('input', () => { totalManual = true; });
    inputMulai?.addEventListener('change', updateTanggalSelesaiOtomatis);

    if (inputMulai && selectPeriode && inputSelesai && !inputSelesai.value) {
        updateTanggalSelesaiOtomatis();
    }
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