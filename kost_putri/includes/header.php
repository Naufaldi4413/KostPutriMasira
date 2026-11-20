<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = $pageTitle ?? 'Kost Putri Masira - Kost Khusus Putri';
$pageDesc  = $pageDesc  ?? 'Kost khusus putri. Sewa sendiri atau berdua, dibayar per semester.';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web" defer></script>
<?php
// Hitung kedalaman relatif dari halaman yang memanggil include ini ke root project
$_callerDepth = substr_count(str_replace('\\','/',dirname($_SERVER['SCRIPT_FILENAME'])), '/') 
              - substr_count(str_replace('\\','/',rtrim(realpath(__DIR__.'/../'), '/\\')), '/');
$_relBase = $_callerDepth > 0 ? str_repeat('../', $_callerDepth) : '';
?>
<link rel="stylesheet" href="<?= $_relBase ?>assets/css/style.css">
</head>
<body>