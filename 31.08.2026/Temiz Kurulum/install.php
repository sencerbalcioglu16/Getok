<?php
/**
 * KURULUM SİHİRBAZI
 * ----------------------------------------------------------------
 *  1) Veritabanı bilgilerini al
 *  2) Önceden oluşturulmuş veritabanına bağlan + sql/install.sql içeriğini yükle
 *  3) Admin hesabı oluştur
 *  4) install.lock dosyası yaz
 *  5) Ana sayfaya yönlendir
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ============================================================
// 1. Gerekli dizinleri oluştur
// ============================================================
$dirs = ['config', 'assets', 'assets/css', 'assets/js', 'assets/uploads', 'includes', 'sql'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

// ============================================================
// 2. SQL dosyasını kontrol et
// ============================================================
$sql_source = __DIR__ . '/install.sql';
$sql_target = __DIR__ . '/sql/install.sql';

if (file_exists($sql_source) && !file_exists($sql_target)) {
    copy($sql_source, $sql_target);
}

if (!file_exists($sql_source) && !file_exists($sql_target)) {
    die('<h2>Hata: install.sql dosyası bulunamadı!</h2><p>Lütfen install.sql dosyasını proje klasörüne kopyalayın.</p>');
}

// ============================================================
// 3. Minimal CSS oluştur (style.css yoksa)
// ============================================================
$css_path = __DIR__ . '/assets/css/style.css';
if (!file_exists($css_path)) {
    $css = <<<'CSS'
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:system-ui,-apple-system,sans-serif; background:#f5f7fa; color:#1a202c; line-height:1.6; }
.install-body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; background:linear-gradient(135deg,#1a365d 0%,#2d3748 100%); }
.install-wrap { width:100%; max-width:820px; }
.card { background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.3); overflow:hidden; }
.install-card { padding:40px; }
h1 { font-size:28px; font-weight:700; color:#1a202c; margin-bottom:8px; }
h3 { font-size:18px; font-weight:600; color:#2d3748; margin:24px 0 12px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
.muted { color:#718096; font-size:14px; }
.flash { padding:14px 18px; border-radius:8px; margin:16px 0; font-weight:500; }
.flash-hata { background:#fed7d7; color:#9b2c2c; border-left:4px solid #e53e3e; }
.flash-basari { background:#c6f6d5; color:#22543d; border-left:4px solid #38a169; }
.form label { display:block; font-weight:500; font-size:14px; color:#4a5568; margin-bottom:6px; }
.form input[type="text"], .form input[type="password"], .form input[type="email"] { 
    width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:8px; font-size:15px; transition:border-color .2s; 
}
.form input:focus { border-color:#4299e1; outline:none; box-shadow:0 0 0 3px rgba(66,153,225,0.15); }
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:8px; }
.btn { display:inline-block; padding:12px 32px; border:none; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-primary { background:#2b6cb0; color:#fff; }
.btn-primary:hover { background:#2c5282; transform:translateY(-1px); box-shadow:0 4px 12px rgba(43,108,176,0.4); }
.btn-lg { padding:14px 48px; font-size:18px; }
.form-actions { margin-top:28px; text-align:center; }
.hint-box { margin-top:24px; padding:20px; background:#ebf8ff; border-radius:10px; border:1px solid #bee3f8; font-size:14px; }
.hint-box ul { margin:8px 0 8px 20px; }
.hint-box code { background:#edf2f7; padding:2px 8px; border-radius:4px; font-size:13px; }
@media (max-width:640px){ .grid-2 { grid-template-columns:1fr; } .install-card { padding:24px; } }
CSS;
    file_put_contents($css_path, $css);
}

// ============================================================
// 4. Header/Footer oluştur (yoksa)
// ============================================================
$header_path = __DIR__ . '/includes/header.php';
if (!file_exists($header_path)) {
    file_put_contents($header_path, '<?php // Header dosyası ?>');
}
$footer_path = __DIR__ . '/includes/footer.php';
if (!file_exists($footer_path)) {
    file_put_contents($footer_path, '');
}

// ============================================================
// 5. Kurulum kilidini kontrol et
// ============================================================
$kilitli = file_exists(__DIR__ . '/config/install.lock');

$hata = '';
$mesaj = '';

// ============================================================
// 6. POST işlemi
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adim']) && $_POST['adim'] == 1) {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $port = trim($_POST['db_port'] ?? '');
    $name = trim($_POST['db_name'] ?? 'okculuk_ligi');
    $user = trim($_POST['db_user'] ?? 'root');
    $pass = $_POST['db_pass'] ?? '';
    $base = rtrim($_POST['base_url'] ?? '', '/');
    $sezon = trim($_POST['sezon'] ?? '2025-2026');
    $admin_user = trim($_POST['admin_user'] ?? 'admin');
    $admin_email = trim($_POST['admin_email'] ?? 'admin@okculukligi.local');
    $admin_pass = $_POST['admin_pass'] ?? 'admin123';
    $admin_name = trim($_POST['admin_name'] ?? 'Site Yöneticisi');

    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        $hata = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi içerebilir.';
    }
    if ($port !== '' && !ctype_digit($port)) $hata = 'Veritabanı portu yalnızca rakamlardan oluşmalıdır.';
    if ($kilitli && empty($_POST['force'])) {
        $hata = 'Kurulum kilitli. Yeniden kurulum için ?force=1 adresini kullanın.';
    }

    if ($base === '') {
        $base = 'http://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    }

    // --- 6a. Veritabanına bağlan ---
    try {
        if ($hata) throw new RuntimeException($hata);
        // Kurulum yalnızca kullanıcıya atanmış, önceden oluşturulmuş veritabanına bağlanır.
        $dsn0 = "mysql:host={$host}" . ($port !== '' ? ";port={$port}" : '') . ";dbname={$name};charset=utf8mb4";
        $pdo0 = new PDO($dsn0, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable $e) {
        $hata = 'MySQL bağlantı hatası: ' . $e->getMessage();
    }

    // --- 6b. SQL yükle ---
    if (!$hata) {
        $sql_file = file_exists($sql_target) ? $sql_target : $sql_source;
        $sql = file_get_contents($sql_file);
        if ($sql === false) {
            $hata = 'install.sql okunamadı. Dosya: ' . $sql_file;
        } else {
            try {
                // Yorum satırlarını temizle
                $lines = explode("\n", $sql);
                $clean_lines = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    // -- ile başlayan satırları atla
                    if (strpos($line, '--') === 0) continue;
                    // SET FOREIGN_KEY_CHECKS satırlarını koru
                    if (stripos($line, 'SET FOREIGN_KEY_CHECKS') === 0) {
                        $clean_lines[] = $line;
                        continue;
                    }
                    // Satır içi -- yorumlarını temizle
                    $line = preg_replace('/\s*--.*$/', '', $line);
                    if (trim($line) !== '') {
                        $clean_lines[] = $line;
                    }
                }
                $sql_clean = implode("\n", $clean_lines);
                
                // Statement'ları ayır
                $statements = preg_split('/;\s*[\r\n]+/', $sql_clean);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') continue;
                    try {
                        $pdo0->exec($stmt);
                    } catch (PDOException $e) {
                        // 'Duplicate entry' veya 'already exists' hatasını yoksay
                        if (strpos($e->getMessage(), 'Duplicate entry') === false && 
                            strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
            } catch (PDOException $e) {
                $hata = 'SQL yükleme hatası: ' . $e->getMessage();
            }
        }
    }

    // Temiz kurulum paketinde eski temel şemadan gelen örnek verileri kaldır.
    // Demo paketi, işaret dosyası sayesinde bu aşamayı atlar ve kendi örnek
    // organizasyonlarını kurulumun sonraki adımında oluşturur.
    if (!$hata && !file_exists(__DIR__ . '/tools/demo-kurulum.modu')) {
        try {
            $pdo0->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ([
                'sporcu_set_atislari', 'mac_setleri', 'maclar', 'yetkili', 'hakemler',
                'sporcular', 'takimlar', 'gruplar', 'duyurular', 'haberler', 'yonetmelikler'
            ] as $tablo) {
                $pdo0->exec("DELETE FROM `{$tablo}`");
            }
            // İlk satır, kurulum ekranında güncellenen ana yönetici hesabıdır.
            $pdo0->exec('DELETE FROM users WHERE id <> 1');
            $pdo0->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (PDOException $e) {
            try { $pdo0->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (PDOException $ignore) { }
            $hata = 'Temiz kurulum verileri hazırlanamadı: ' . $e->getMessage();
        }
    }

    // --- 6c. Config dosyalarını yaz ---
    if (!$hata) {
        // database.php
        $cfg_db = "<?php\n\$DB_HOST = " . var_export($host, true) . ";\n"
                . "\$DB_NAME = " . var_export($name, true) . ";\n"
                . "\$DB_USER = " . var_export($user, true) . ";\n"
                . "\$DB_PASS = " . var_export($pass, true) . ";\n"
                . "\$DB_CHAR = 'utf8mb4';\n\n"
                . "\$DB_PORT = " . var_export($port !== '' ? $port : null, true) . ";\n\n"
                . "try {\n"
                . "    \$dsn = \"mysql:host={\$DB_HOST}\" . (\$DB_PORT ? \";port={\$DB_PORT}\" : '') . \";dbname={\$DB_NAME};charset={\$DB_CHAR}\";\n"
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

        // config.php
        $cfg_main = "<?php\nif (!defined('OKCULUK_LOADED')) define('OKCULUK_LOADED', true);\n"
                  . "\$ortamUrl = getenv('OKCULUK_BASE_URL');\n"
                  . "if (!\$ortamUrl && PHP_SAPI !== 'cli' && !empty(\$_SERVER['HTTP_HOST'])) {\n"
                  . "    \$https = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') || ((\$_SERVER['SERVER_PORT'] ?? '') == 443);\n"
                  . "    \$projeKoku = realpath(dirname(__DIR__)); \$belgeKoku = !empty(\$_SERVER['DOCUMENT_ROOT']) ? realpath(\$_SERVER['DOCUMENT_ROOT']) : false;\n"
                  . "    if (\$projeKoku && \$belgeKoku && str_starts_with(strtolower(\$projeKoku), strtolower(\$belgeKoku))) \$klasor = str_replace('\\\\', '/', substr(\$projeKoku, strlen(\$belgeKoku)));\n"
                  . "    else \$klasor = preg_replace('#/(admin)(/.*)?$#', '', str_replace('\\\\', '/', dirname(\$_SERVER['SCRIPT_NAME'] ?? '/')));\n"
                  . "    \$ortamUrl = (\$https ? 'https' : 'http') . '://' . \$_SERVER['HTTP_HOST'] . rtrim(\$klasor, '/.');\n"
                  . "}\n"
                  . "define('BASE_URL', rtrim(\$ortamUrl ?: " . var_export($base, true) . ", '/'));\n"
                  . "define('BASE_PATH', dirname(__DIR__));\n"
                  . "define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');\n"
                  . "define('UPLOAD_URL', BASE_URL . '/assets/uploads');\n"
                  . "define('LIG_ADI', 'Okçuluk Amatör Ligi');\n"
                  . "define('LIG_SEZON', " . var_export($sezon, true) . ");\n"
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
    }

    // --- 6d. Admin ve kullanıcı şifrelerini güncelle ---
    if (!$hata) {
        try {
            // Kurulumla birlikte güncel şema eklerini de oluştur: ligler,
            // sezon/arşiv, favoriler, bireysel fikstür ve canlı set alanları.
            if (!file_exists(__DIR__ . '/tools/demo-kurulum.modu') && !defined('TEMIZ_KURULUM')) {
                define('TEMIZ_KURULUM', true);
            }
            require_once __DIR__ . '/config/config.php';
            require_once __DIR__ . '/config/database.php';
            require_once __DIR__ . '/includes/functions.php';

            $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET kullanici_adi=?, email=?, sifre=?, ad_soyad=? WHERE id=1");
            $stmt->execute([$admin_user, $admin_email, $hash, $admin_name]);

            // Diğer kullanıcılar
            $hash_h = password_hash('hakem123', PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET sifre=? WHERE id=2")->execute([$hash_h]);
            $pdo->prepare("UPDATE users SET sifre=? WHERE id=3")->execute([$hash_h]);

            $hash_y = password_hash('yetkili123', PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET sifre=? WHERE id=4")->execute([$hash_y]);
            $pdo->prepare("UPDATE users SET sifre=? WHERE id=5")->execute([$hash_y]);

            $hash_s = password_hash('sporcu123', PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET sifre=? WHERE id=6")->execute([$hash_s]);

            // Demo paketinde bu işaret dosyası bulunur; kurulum sonrasında
            // örnek sezon, görseller ve simüle edilmiş karşılaşmalar oluşturulur.
            if (file_exists(__DIR__ . '/tools/demo-kurulum.modu')) {
                define('DEMO_KURULUM_AGENT', true);
                require __DIR__ . '/tools/demo_organizasyon_agent.php';
            }

        } catch (Throwable $e) {
            $hata = 'Kullanıcı güncelleme hatası: ' . $e->getMessage();
        }
    }

    // --- 6e. Kilid oluştur ve yönlendir ---
    if (!$hata) {
        file_put_contents(__DIR__ . '/config/install.lock',
            "Kurulum: " . date('Y-m-d H:i:s') . "\nAdmin: {$admin_user}\n");
        $mesaj = 'Kurulum tamamlandı!';
        header('Refresh: 2; url=' . $base . '/index.php');
        exit;
    }
}

// ============================================================
// 7. Sayfayı göster
// ============================================================
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum Sihirbazı — Okçuluk Amatör Ligi</title>
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
                <a href="?force=1" class="link-more">buraya tıklayın</a> (mevcut veriler silinir!).
            </div>
        <?php else: ?>

            <?php if ($hata): ?>
                <div class="flash flash-hata"><strong>Hata:</strong> <?= htmlspecialchars($hata) ?></div>
            <?php endif; ?>

            <?php if ($mesaj): ?>
                <div class="flash flash-basari">
                    <?= htmlspecialchars($mesaj) ?><br>
                    Yönlendiriliyor... <a href="index.php" class="link-more">Ana sayfa</a>
                </div>
            <?php endif; ?>

    <form method="post" class="form">
                <input type="hidden" name="adim" value="1">
                <?php if (!empty($_GET['force'])): ?><input type="hidden" name="force" value="1"><?php endif; ?>

                <h3>1. Veritabanı Bilgileri (MySQL)</h3>
                <div class="hint-box" style="margin-top:0">
                    <strong>Veritabanı bilgilerinizi girin:</strong> Kurulumdan önce hosting panelinizden boş bir MySQL veritabanı ve bu veritabanına yetkili bir kullanıcı oluşturun. Sistem yeni veritabanı oluşturmaya çalışmaz.
                </div>
                <div class="grid-2">
                    <label>MySQL Sunucusu
                        <input type="text" name="db_host" required value="localhost">
                    </label>
                    <label>MySQL Portu <small class="muted">(genellikle 3306)</small>
                        <input type="text" name="db_port" value="">
                    </label>
                </div>
                <div class="grid-2">
                    <label>Veritabanı Adı
                        <input type="text" name="db_name" required value="okculuk_ligi">
                    </label>
                    <label>Kullanıcı
                        <input type="text" name="db_user" required value="root">
                    </label>
                </div>
                <div class="grid-2">
                    <label>Veritabanı Parolası
                        <input type="password" name="db_pass" value="">
                    </label>
                </div>
                <div class="grid-2">
                    <label>Site Kök URL
                        <input type="text" name="base_url" required value="<?= 'http://' . htmlspecialchars($_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')) ?>">
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
                <strong>📋 Temiz Kurulum</strong>
                <ul>
                    <li>Seçilen veritabanı temizlenir ve sistem tabloları yeniden oluşturulur.</li>
                    <li>2 grup (A, B)</li>
                    <li>12 takım (her grupta 6)</li>
                    <li>60 sporcu (her takımda 5)</li>
                    <li>2 hakem, 2 yetkili</li>
                    <li>2 haber, 3 duyuru, 2 yönetmelik</li>
                    <li>30 maç (A+B grubu, 5 hafta round-robin), 12 tanesi skorlanmış</li>
                </ul>
                <p><strong>🔑 Örnek girişler (kurulum sonrası):</strong></p>
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
