<?php
if (!defined('OKCULUK_LOADED')) define('OKCULUK_LOADED', true);
define('BASE_URL', 'http://localhost/okculuk-ligi');
define('BASE_PATH', 'C:\\xampp\\htdocs\\okculuk-ligi');
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL', BASE_URL . '/assets/uploads');
define('LIG_ADI', 'Okçuluk Amatör Ligi');
define('LIG_SEZON', '2025-2026');
define('TAKIM_BASINA_SPORCU', 5);
define('OK_SAYISI', 7);
define('SET_SAYISI', 5);
define('GRUP_TAKIM_SAYISI', 6);
define('MAKS_OK_PUAN', 10);
define('UYGULAMA_ADI', 'Okçuluk Amatör Ligi Yönetim Sistemi');
define('SURUM', '1.0.0');
date_default_timezone_set('Europe/Istanbul');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}
