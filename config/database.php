<?php
// ============================================================
// BENGKELIN - Database Configuration
// ============================================================

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

if ($is_local) {
    // === KONFIGURASI DATABASE LOKAL (XAMPP) ===
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'bengkelin');
} else {
    // === KONFIGURASI DATABASE SERVER LIVE (cPanel) ===
    // GANTI BAGIAN INI dengan detail database yang Anda buat di cPanel
    define('DB_HOST', 'localhost'); // Biarkan localhost jika di cPanel
    define('DB_USER', 'user_database_cpanel_anda'); // Ganti dengan username DB cPanel
    define('DB_PASS', 'password_database_anda');    // Ganti dengan password DB cPanel
    define('DB_NAME', 'nama_database_cpanel_anda'); // Ganti dengan nama DB cPanel
}
define('DB_CHARSET', 'utf8mb4');

// Auto-detect protocol and host for BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";

if ($is_local) {
    define('BASE_URL', "$protocol://$host/bengkelin");
} else {
    define('BASE_URL', "$protocol://$host");
}
define('APP_NAME', 'Bengkelin');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
