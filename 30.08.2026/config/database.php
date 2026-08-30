<?php
$DB_HOST = 'localhost';
$DB_NAME = 'okculuk_ligi';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHAR = 'utf8mb4';
$DB_PORT = getenv('OKCULUK_DB_PORT') ?: null;

try {
    $dsn = "mysql:host={$DB_HOST}" . ($DB_PORT ? ";port={$DB_PORT}" : '') . ";dbname={$DB_NAME};charset={$DB_CHAR}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        die('<h2>Veritabanı bağlantı hatası</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>');
    } else { throw $e; }
}
