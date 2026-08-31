<?php
// Hata raporlamayı aç (geçici)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1) KONFİGÜRASYON VE FONKSİYONLARI YÜKLE
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

/**
 * Giriş sayfası (admin / hakem / sporcu / yetkili)
 */
$sayfa_baslik = 'Giriş';
require_once __DIR__ . '/includes/header.php';

// Eğer zaten giriş yapmışsa admin paneline yönlendir
if (giris_yapmis()) {
    redirect(BASE_URL . '/admin/');
}

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $hata = 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $kullanici = trim($_POST['kullanici_adi'] ?? '');
        $sifre     = $_POST['sifre'] ?? '';
        if ($kullanici === '' || $sifre === '') {
            $hata = 'Kullanıcı adı ve şifre zorunludur.';
        } else {
            $st = $pdo->prepare("SELECT * FROM users WHERE kullanici_adi = ? OR email = ? LIMIT 1");
            $st->execute([$kullanici, $kullanici]);
            $u = $st->fetch();
            if (!$u || !sifre_dogrula($sifre, $u['sifre']) || !$u['aktif']) {
                $hata = 'Hatalı kullanıcı adı veya şifre.';
            } else {
                $pdo->prepare("UPDATE users SET son_giris = NOW() WHERE id = ?")->execute([$u['id']]);
                $_SESSION['kullanici'] = [
                    'id'           => (int)$u['id'],
                    'kullanici_adi'=> $u['kullanici_adi'],
                    'email'        => $u['email'],
                    'rol'          => $u['rol'],
                    'ad_soyad'     => $u['ad_soyad'],
                ];
                flash_set('basari', 'Hoş geldiniz, ' . ($u['ad_soyad'] ?: $u['kullanici_adi']) . '.');
                redirect($u['rol'] === 'uye' ? BASE_URL . '/favorilerim.php' : BASE_URL . '/admin/');
            }
        }
    }
}
?>
<main class="main-content">
<div class="auth-wrap">
    <div class="card auth-card">

        <h1>Giriş Yap</h1>
        <?php if ($hata): ?><div class="flash flash-hata"><?= e($hata) ?></div><?php endif; ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Kullanıcı Adı veya E-posta
                <input type="text" name="kullanici_adi" required autofocus>
            </label>
            <label>Şifre
                <input type="password" name="sifre" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>
        <p class="muted center">Hesabınız yok mu? <a href="<?= BASE_URL ?>/register.php">Üye olun</a></p>

        <div class="hint-box">
            <strong>Örnek Hesaplar</strong>
            <ul>
                <li>Yönetici: <code>admin</code> / <code>admin123</code></li>
                <li>Hakem: <code>hakem1</code> / <code>hakem123</code></li>
                <li>Yetkili: <code>yetkili1</code> / <code>yetkili123</code></li>
                <li>Sporcu: <code>sporcu1</code> / <code>sporcu123</code></li>
            </ul>
        </div>
    </div>
</div>
</main>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
