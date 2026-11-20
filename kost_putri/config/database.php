<?php
/**
 * Konfigurasi Database PDO
 * Database: db_pengelolaan_kos
 */

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'db_pengelolaan_kos');
    define('DB_PORT', '3306');
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * Mendapatkan koneksi PDO ke database
 * 
 * @return PDO
 */
function getDbConnection(): PDO {
    static $pdoInstance = null;

    if ($pdoInstance === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdoInstance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }

    return $pdoInstance;
}

// Inisialisasi variabel $pdo global
$pdo = getDbConnection();
