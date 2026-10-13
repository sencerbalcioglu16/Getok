<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/**
 * Kullanıcı kendi profilini düzenler (özellikle sporcu için)
 */
$u = kullanici_bilgi();
$user_id = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/profil.php'); }
    $ad_soyad  = trim($_POST['ad_soyad'] ?? '');
    $email     = trim($_POST['email']    ?? '');
    $yeni_sifre = $_POST['yeni_sifre']   ?? '';
    $mevcut_sifre = $_POST['mevcut_sifre'] ?? '';

    $hata = '';
    if ($ad_soyad === '' || $email === '') $hata = 'Ad soyad ve e-posta zorunludur.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $hata = 'Geçerli bir e-posta girin.';

    // email benzersiz mi?
    if (!$hata) {
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $st->execute([$email, $user_id]);
        if ($st->fetch()) $hata = 'Bu e-posta zaten kullanımda.';
    }

    // şifre değişikliği
    $sifre_degisecek = false;
    if (!$hata && $yeni_sifre !== '') {
        if (strlen($yeni_sifre) < 6) {
            $hata = 'Yeni şifre en az 6 karakter olmalı.';
        } else {
            $st = $pdo->prepare("SELECT sifre FROM users WHERE id = ?"); $st->execute([$user_id]);
            $cur_hash = $st->fetchColumn();
            if (!sifre_dogrula($mevcut_sifre, $cur_hash)) {
                $hata = 'Mevcut şifre hatalı.';
            } else {
                $sifre_degisecek = true;
            }
        }
    }

    if ($hata) {
        flash_set('hata', $hata);
        redirect(BASE_URL.'/admin/profil.php');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET ad_soyad=?, email=? WHERE id=?")
            ->execute([$ad_soyad, $email, $user_id]);
        if ($sifre_degisecek) {
            $pdo->prepare("UPDATE users SET sifre=? WHERE id=?")
                ->execute([sifre_hash($yeni_sifre), $user_id]);
        }

        // Sporcu ise sporcular tablosunu da güncelle
        if ($u['rol'] === 'sporcu') {
            $ad = explode(' ', $ad_soyad, 2)[0] ?? $ad_soyad;
            $soyad = explode(' ', $ad_soyad, 2)[1] ?? '';
            $pdo->prepare("UPDATE sporcular SET ad=?, soyad=?, email=? WHERE user_id=?")
                ->execute([$ad, $soyad, $email, $user_id]);
        } elseif ($u['rol'] === 'hakem') {
            $ad = explode(' ', $ad_soyad, 2)[0] ?? $ad_soyad;
            $soyad = explode(' ', $ad_soyad, 2)[1] ?? '';
            $pdo->prepare("UPDATE hakemler SET ad=?, soyad=?, email=? WHERE user_id=?")
                ->execute([$ad, $soyad, $email, $user_id]);
        } elseif ($u['rol'] === 'yetkili') {
            $ad = explode(' ', $ad_soyad, 2)[0] ?? $ad_soyad;
            $soyad = explode(' ', $ad_soyad, 2)[1] ?? '';
            $pdo->prepare("UPDATE yetkili SET ad=?, soyad=?, email=? WHERE user_id=?")
                ->execute([$ad, $soyad, $email, $user_id]);
        }
        $pdo->commit();
        // session'ı güncelle
        $_SESSION['kullanici']['ad_soyad'] = $ad_soyad;
        $_SESSION['kullanici']['email']    = $email;
        flash_set('basari','Profil güncellendi.');
    } catch (Exception $ex) {
        $pdo->rollBack();
        flash_set('hata', 'Hata: ' . $ex->getMessage());
    }
    redirect(BASE_URL.'/admin/profil.php');
}

$st = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $st->execute([$user_id]); $me = $st->fetch();
$detay = null;
if ($me['rol'] === 'sporcu') {
    $x = $pdo->prepare("SELECT * FROM sporcular WHERE user_id = ?"); $x->execute([$user_id]); $detay = $x->fetch();
} elseif ($me['rol'] === 'hakem') {
    $x = $pdo->prepare("SELECT * FROM hakemler WHERE user_id = ?"); $x->execute([$user_id]); $detay = $x->fetch();
} elseif ($me['rol'] === 'yetkili') {
    $x = $pdo->prepare("SELECT * FROM yetkili WHERE user_id = ?"); $x->execute([$user_id]); $detay = $x->fetch();
}

ob_start();
?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <div class="grid-2">
        <label>Ad Soyad *<input type="text" name="ad_soyad" required value="<?= e($me['ad_soyad']) ?>"></label>
        <label>E-posta *<input type="email" name="email" required value="<?= e($me['email']) ?>"></label>
    </div>
    <div class="grid-2">
        <label>Kullanıcı Adı (değiştirilemez)<input type="text" disabled value="<?= e($me['kullanici_adi']) ?>"></label>
        <label>Rol
            <input type="text" disabled value="<?= e($me['rol']) ?>">
        </label>
    </div>
    <hr><h3>Şifre Değiştir (opsiyonel)</h3>
    <div class="grid-2">
        <label>Mevcut Şifre<input type="password" name="mevcut_sifre"></label>
        <label>Yeni Şifre (en az 6 karakter)<input type="password" name="yeni_sifre"></label>
    </div>
    <?php if ($detay): ?>
    <hr><h3>Detay Bilgileri</h3>
    <div class="grid-2">
        <label>Telefon<input type="text" name="telefon" form="noop" disabled value="<?= e($detay['telefon'] ?? '') ?>"></label>
        <label>TC Kimlik<input type="text" disabled value="<?= e($detay['tc_kimlik'] ?? '-') ?>"></label>
    </div>
    <p class="muted">Detaylı bilgilerinizi değiştirmek için yönetici ile iletişime geçin.</p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary">Kaydet</button>
    </div>
</form>

<?php
$admin_baslik = 'Profilim';
$admin_aktif  = 'profil';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
