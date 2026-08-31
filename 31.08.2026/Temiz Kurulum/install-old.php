<?php
/**
 * KURULUM SİHİRBAZI
 * ----------------------------------------------------------------
 *  1) Veritabanı bilgilerini al
 *  2) Veritabanını oluştur + sql/install.sql içeriğini yükle
 *  3) Admin hesabı oluştur
 *  4) install.lock dosyası yaz
 *  5) Ana sayfaya yönlendir
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__ . '/config/install.lock') && empty($_GET['force'])) {
    // Kurulum zaten yapılmış; install.php'yi kilitli göster
    $kilitli = true;
} else {
    $kilitli = false;
}

$adim = (int)($_GET['adim'] ?? 1);
$hata = ''; $mesaj = '';

// -------- ADIM 1: Veritabanı bilgileri --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['adim'] ?? 1) == 1) {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? 'okculuk_ligi');
    $user = trim($_POST['db_user'] ?? 'root');
    $pass = $_POST['db_pass'] ?? '';
    $base = rtrim($_POST['base_url'] ?? '', '/');

    if ($base === '') $base = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

    // Önce server'a bağlan, db yoksa oluştur
    try {
        $dsn0 = "mysql:host={$host};charset=utf8mb4";
        $pdo0 = new PDO($dsn0, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo0->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo0->exec("USE `{$name}`");
    } catch (PDOException $e) {
        $hata = 'MySQL bağlantı hatası: ' . $e->getMessage();
    }
    if (!$hata) {
        // SQL'i yükle
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        if (!$sql) { $hata = 'install.sql okunamadı.'; }
        else {
            try {
                // Yorum satırlarını temizle
                $sql_no_comments = preg_replace('/^\s*--.*$/m', '', $sql);
                // ; ile biten statement'ları ayır
                $statements = preg_split('/;\s*[\r\n]+/', $sql_no_comments);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') continue;
                    $pdo0->exec($stmt);
                }
            } catch (PDOException $e) {
                $hata = 'SQL yükleme hatası: ' . $e->getMessage();
            }
        }
    }
    if (!$hata) {
        // config dosyalarını yaz
        $cfg_db = "<?php\n\$DB_HOST = " . var_export($host, true) . ";\n"
                . "\$DB_NAME = " . var_export($name, true) . ";\n"
                . "\$DB_USER = " . var_export($user, true) . ";\n"
                . "\$DB_PASS = " . var_export($pass, true) . ";\n"
                . "\$DB_CHAR = 'utf8mb4';\n\n"
                . "try {\n"
                . "    \$dsn = \"mysql:host={\$DB_HOST};dbname={\$DB_NAME};charset={\$DB_CHAR}\";\n"
                . "    \$pdo = new PDO(\$dsn, \$DB_USER, \$DB_PASS, [\n"
                . "        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
                . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                . "        PDO::ATTR_EMULATE_PREPARES   => false,\n"
                . "    ]);\n"
                . "    \$pdo->exec(\"SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci\");\n"
                . "} catch (PDOException \$e) {\n"
                . "    if (basename(\$_SERVER['SCRIPT_NAME']) !== 'install.php') {\n"
                . "        die('<h2>Veritabanı bağlantı hatası</h2><p>' . htmlspecialchars(\$e->getMessage()) . '</p>');\n"
                . "    } else { throw \$e; }\n"
                . "}\n";
        file_put_contents(__DIR__ . '/config/database.php', $cfg_db);

        $cfg_main = "<?php\nif (!defined('OKCULUK_LOADED')) define('OKCULUK_LOADED', true);\n"
                  . "define('BASE_URL', " . var_export($base, true) . ");\n"
                  . "define('BASE_PATH', " . var_export(__DIR__, true) . ");\n"
                  . "define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');\n"
                  . "define('UPLOAD_URL', BASE_URL . '/assets/uploads');\n"
                  . "define('LIG_ADI', 'Okçuluk Amatör Ligi');\n"
                  . "define('LIG_SEZON', " . var_export($_POST['sezon'] ?? '2025-2026', true) . ");\n"
                  . "define('TAKIM_BASINA_SPORCU', 5);\n"
                  . "define('OK_SAYISI', 7);\n"
                  . "define('SET_SAYISI', 5);\n"
                  . "define('GRUP_TAKIM_SAYISI', 6);\n"
                  . "define('MAKS_OK_PUAN', 10);\n"
                  . "define('UYGULAMA_ADI', 'Okçuluk Amatör Ligi Yönetim Sistemi');\n"
                  . "define('SURUM', '1.0.0');\n"
                  . "date_default_timezone_set('Europe/Istanbul');\n"
                  . "if (session_status() === PHP_SESSION_NONE) {\n"
                  . "    ini_set('session.cookie_httponly', 1);\n"
                  . "    ini_set('session.use_strict_mode', 1);\n"
                  . "    session_start();\n"
                  . "}\n";
        file_put_contents(__DIR__ . '/config/config.php', $cfg_main);

        // Admin oluştur
        $admin_user = trim($_POST['admin_user'] ?? 'admin');
        $admin_email = trim($_POST['admin_email'] ?? 'admin@okculukligi.local');
        $admin_pass = $_POST['admin_pass'] ?? 'admin123';
        $admin_name = trim($_POST['admin_name'] ?? 'Site Yöneticisi');

        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass,
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET kullanici_adi=?, email=?, sifre=?, ad_soyad=? WHERE id=1")
            ->execute([$admin_user, $admin_email, $hash, $admin_name]);
        // Diğer örnek kullanıcıların şifrelerini de ayarla
        $hash_h = password_hash('hakem123', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET sifre=? WHERE id=2")->execute([$hash_h]);
        $pdo->prepare("UPDATE users SET sifre=? WHERE id=3")->execute([$hash_h]);
        $hash_y = password_hash('yetkili123', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET sifre=? WHERE id=4")->execute([$hash_y]);
        $pdo->prepare("UPDATE users SET sifre=? WHERE id=5")->execute([$hash_y]);
        $hash_s = password_hash('sporcu123', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET sifre=? WHERE id=6")->execute([$hash_s]);

        // install.lock oluştur
        file_put_contents(__DIR__ . '/config/install.lock',
            "Kurulum: " . date('Y-m-d H:i:s') . "\nAdmin: {$admin_user}\n");

        $mesaj = 'Kurulum tamamlandı!';
        header('Refresh: 2; url=' . $base . '/index.php');
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>Kurulum Sihirbazı — <?= htmlspecialchars('Okçuluk Amatör Ligi') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="install-body">
<div class="install-wrap">
    <div class="card install-card">
        <h1>🎯 Okçuluk Amatör Ligi — Kurulum</h1>
        <p class="muted">Sistemi kullanmaya başlamadan önce veritabanı bağlantısını ve yönetici hesabını oluşturun.</p>

        <?php if ($kilitli && empty($_GET['force'])): ?>
            <div class="flash flash-hata">
                Kurulum zaten tamamlanmış. Tekrar kurmak için
                <a href="?force=1">buraya tıklayın</a> (mevcut veriler silinir!).
            </div>
        <?php else: ?>

            <?php if ($hata): ?><div class="flash flash-hata"><?= htmlspecialchars($hata) ?></div><?php endif; ?>
            <?php if ($mesaj): ?>
                <div class="flash flash-basari">
                    <?= htmlspecialchars($mesaj) ?><br>
                    Yönlendiriliyor... <a href="index.php">Ana sayfa</a>
                </div>
            <?php endif; ?>

            <form method="post" class="form">
                <input type="hidden" name="adim" value="1">

                <h3>1. Veritabanı Bilgileri (MySQL)</h3>
                <div class="grid-2">
                    <label>Sunucu
                        <input type="text" name="db_host" required value="localhost">
                    </label>
                    <label>Veritabanı Adı
                        <input type="text" name="db_name" required value="okculuk_ligi">
                    </label>
                </div>
                <div class="grid-2">
                    <label>Kullanıcı
                        <input type="text" name="db_user" required value="root">
                    </label>
                    <label>Parola
                        <input type="password" name="db_pass" value="">
                    </label>
                </div>
                <div class="grid-2">
                    <label>Site Kök URL
                        <input type="text" name="base_url" required value="<?= 'http://' . htmlspecialchars($_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'])) ?>">
                    </label>
                    <label>Sezon
                        <input type="text" name="sezon" value="2025-2026">
                    </label>
                </div>

                <h3>2. Yönetici (Admin) Hesabı</h3>
                <div class="grid-2">
                    <label>Ad Soyad
                        <input type="text" name="admin_name" required value="Site Yöneticisi">
                    </label>
                    <label>Kullanıcı Adı
                        <input type="text" name="admin_user" required value="admin">
                    </label>
                </div>
                <div class="grid-2">
                    <label>E-posta
                        <input type="email" name="admin_email" required value="admin@okculukligi.local">
                    </label>
                    <label>Şifre
                        <input type="text" name="admin_pass" required value="admin123">
                    </label>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary btn-lg">Kurulumu Başlat</button>
                </div>
            </form>

            <div class="hint-box">
                <strong>Örnek Veriler Yüklenecek</strong>
                <ul>
                    <li>2 grup (A, B)</li>
                    <li>12 takım (her grupta 6)</li>
                    <li>60 sporcu (her takımda 5)</li>
                    <li>2 hakem, 2 yetkili</li>
                    <li>2 haber, 3 duyuru, 2 yönetmelik</li>
                    <li>30 maç (A+B grubu, 5 hafta round-robin), 12 tanesi skorlanmış</li>
                </ul>
                <p><strong>Örnek girişler (kurulum sonrası):</strong></p>
                <ul>
                    <li>Yönetici: <code>admin</code> / <code>admin123</code></li>
                    <li>Hakem: <code>hakem1</code> / <code>hakem123</code></li>
                    <li>Yetkili: <code>yetkili1</code> / <code>yetkili123</code></li>
                    <li>Sporcu: <code>sporcu1</code> / <code>sporcu123</code></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
