<?php
/**
 * config.php — Konfigurasi koneksi database menggunakan PDO
 * Membaca kredensial dari environment variables (Docker Compose)
 */

define('DB_HOST',     getenv('DB_HOST')     ?: 'mariadb_db');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'uas_db');
define('DB_USER',     getenv('DB_USER')     ?: 'uas_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'uas_password_secure'); // <-- Sudah diperbaiki agar sinkron
define('APP_NAME',    'Sistem Manajemen Mahasiswa');
define('APP_VERSION', '1.0.0');

/**
 * Membuat koneksi PDO dengan error handling yang proper.
 * Menggunakan Singleton pattern agar hanya ada satu koneksi.
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    } catch (PDOException $e) {
        // Di production: jangan tampilkan pesan error mentah ke user
        error_log('[DB ERROR] ' . $e->getMessage());
        die(json_encode([
            'error' => 'Koneksi database gagal. Silakan coba beberapa saat lagi.',
            'code'  => $e->getCode()
        ]));
    }

    return $pdo;
}

/** Helper: sanitasi output untuk mencegah XSS */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Helper: redirect dengan pesan flash */
function redirect(string $url, string $message = '', string $type = 'success'): never {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit;
}