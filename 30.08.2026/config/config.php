<?php
if (!defined('OKCULUK_LOADED')) define('OKCULUK_LOADED', true);
$ortamUrl = getenv('OKCULUK_BASE_URL');
if (!$ortamUrl && PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $projeKoku = realpath(dirname(__DIR__));
    $belgeKoku = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($projeKoku && $belgeKoku && str_starts_with(strtolower($projeKoku), strtolower($belgeKoku))) {
        $klasor = str_replace('\\', '/', substr($projeKoku, strlen($belgeKoku)));
    } else {
        // DOCUMENT_ROOT erişilemeyen hostingler için /admin gibi alt uygulama yollarını çıkar.
        $klasor = preg_replace('#/(admin)(/.*)?$#', '', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')));
    }
    $klasor = rtrim($klasor, '/.');
    $ortamUrl = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . ($klasor ? $klasor : '');
}
define('BASE_URL', rtrim($ortamUrl ?: 'http://localhost/okculuk-ligi', '/'));
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL', BASE_URL . '/assets/uploads');
define('LIG_ADI', 'Geleneksel Türk Okçuluğu Bölge Ligleri');
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
    $oturumGuvenli = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $oturumGuvenli,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Arayüz terminolojisini tek noktadan tutarlı biçimde uygula.
if (PHP_SAPI !== 'cli' && !defined('KARSILASMA_TERIMLERI_AKTIF')) {
    define('KARSILASMA_TERIMLERI_AKTIF', true);
    ob_start(function ($cikti) {
        return str_ireplace(
            ['maçları','maçlar','maçın','maça','maçı','maç'],
            ['karşılaşmaları','karşılaşmalar','karşılaşmanın','karşılaşmaya','karşılaşmayı','karşılaşma'],
            $cikti
        );
    });
}
