<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
/**
 * Üye olma sayfası
 * - Herkese açık kayıtlar yalnızca takipçi üye hesabı açar.
 */
$sayfa_baslik = 'Üye Ol';
require_once __DIR__ . '/includes/header.php';

if (giris_yapmis()) {
    redirect(kullanici_bilgi()['rol'] === 'uye' ? BASE_URL . '/favorilerim.php' : BASE_URL . '/admin/');
}

$hata = '';
$rol  = 'uye';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $hata = 'Güvenlik doğrulaması başarısız.';
    } else {
        $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
        $email         = trim($_POST['email']         ?? '');
        $ad_soyad      = trim($_POST['ad_soyad']      ?? '');
        $sifre         = $_POST['sifre']              ?? '';
        $sifre2        = $_POST['sifre_tekrar']       ?? '';
        $rol_post      = 'uye';

        $tc     = trim($_POST['tc_kimlik'] ?? '');
        $tel    = trim($_POST['telefon']   ?? '');

        if ($kullanici_adi === '' || $email === '' || $ad_soyad === '' || $sifre === '') {
            $hata = 'Tüm zorunlu alanları doldurun.';
        } elseif (strlen($sifre) < 6) {
            $hata = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($sifre !== $sifre2) {
            $hata = 'Şifreler eşleşmiyor.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $hata = 'Geçerli bir e-posta girin.';
        } else {
            // benzersizlik kontrolü
            $st = $pdo->prepare("SELECT id FROM users WHERE kullanici_adi = ? OR email = ?");
            $st->execute([$kullanici_adi, $email]);
            if ($st->fetch()) {
                $hata = 'Bu kullanıcı adı veya e-posta zaten kullanılıyor.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("INSERT INTO users (kullanici_adi,email,sifre,rol,ad_soyad,aktif) VALUES (?,?,?,?,?,1)")
                        ->execute([$kullanici_adi, $email, sifre_hash($sifre), $rol_post, $ad_soyad]);
                    $uid = (int)$pdo->lastInsertId();

                    $pdo->commit();
                    flash_set('basari', 'Kayıt başarılı! Giriş yapabilirsiniz.');
                    redirect(BASE_URL . '/login.php');
                } catch (Exception $ex) {
                    $pdo->rollBack();
                    $hata = 'Kayıt sırasında hata oluştu: ' . $ex->getMessage();
                }
            }
        }
    }
}
?>
<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Üye Ol</h1>
        <?php if ($hata): ?><div class="flash flash-hata"><?= e($hata) ?></div><?php endif; ?>

        <p class="muted center">Üye hesabınızla takımları ve sporcuları takip edebilirsiniz.</p>

        <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Ad Soyad *
                <input type="text" name="ad_soyad" required value="<?= e($_POST['ad_soyad'] ?? '') ?>">
            </label>
            <label>Kullanıcı Adı *
                <input type="text" name="kullanici_adi" required value="<?= e($_POST['kullanici_adi'] ?? '') ?>">
            </label>
            <label>E-posta *
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
            </label>
            <label>Şifre * (en az 6 karakter)
                <input type="password" name="sifre" required>
            </label>
            <label>Şifre Tekrar *
                <input type="password" name="sifre_tekrar" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Üye Ol</button>
        </form>
        <p class="muted center">Zaten üye misiniz? <a href="<?= BASE_URL ?>/login.php">Giriş yapın</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
