<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
zorunlu_rol('admin');

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/hakemler.php'); }
    $ad        = trim($_POST['ad']      ?? '');
    $soyad     = trim($_POST['soyad']   ?? '');
    $tc_kimlik = trim($_POST['tc_kimlik'] ?? '');
    $telefon   = trim($_POST['telefon'] ?? '');
    $email     = trim($_POST['email']   ?? '');
    $seviye    = trim($_POST['seviye']  ?? 'İl');
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $yeni_sifre    = $_POST['yeni_sifre']    ?? '';
    $aktif         = isset($_POST['aktif']) ? 1 : 0;

    if ($ad === '' || $soyad === '') {
        flash_set('hata','Ad ve soyad zorunludur.');
        redirect(BASE_URL.'/admin/hakemler.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }

    $pdo->beginTransaction();
    try {
        if ($_POST['islem'] === 'ekle') {
            if ($kullanici_adi === '' || $yeni_sifre === '') {
                throw new Exception('Kullanıcı adı ve şifre zorunludur.');
            }
            $pdo->prepare("INSERT INTO users (kullanici_adi,email,sifre,rol,ad_soyad,aktif) VALUES (?,?,?,?,?,?)")
                ->execute([$kullanici_adi, $email ?: $kullanici_adi.'@okculukligi.local', sifre_hash($yeni_sifre), 'hakem', $ad.' '.$soyad, $aktif]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO hakemler (user_id,ad,soyad,tc_kimlik,telefon,email,seviye) VALUES (?,?,?,?,?,?,?)")
                ->execute([$uid, $ad, $soyad, $tc_kimlik, $telefon, $email, $seviye]);
            flash_set('basari','Hakem eklendi.');
        } elseif ($_POST['islem'] === 'duzenle') {
            $hkid = (int)$_POST['id'];
            $st = $pdo->prepare("SELECT * FROM hakemler WHERE id=?"); $st->execute([$hkid]); $hk = $st->fetch();
            if (!$hk) throw new Exception('Hakem bulunamadı.');
            $pdo->prepare("UPDATE hakemler SET ad=?, soyad=?, tc_kimlik=?, telefon=?, email=?, seviye=? WHERE id=?")
                ->execute([$ad, $soyad, $tc_kimlik, $telefon, $email, $seviye, $hkid]);
            if ($hk['user_id']) {
                $pdo->prepare("UPDATE users SET ad_soyad=?, email=?, aktif=? WHERE id=?")
                    ->execute([$ad.' '.$soyad, $email, $aktif, (int)$hk['user_id']]);
                if ($yeni_sifre !== '') {
                    $pdo->prepare("UPDATE users SET sifre=? WHERE id=?")
                        ->execute([sifre_hash($yeni_sifre), (int)$hk['user_id']]);
                }
            }
            flash_set('basari','Hakem güncellendi.');
        }
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        flash_set('hata', $ex->getMessage());
    }
    redirect(BASE_URL.'/admin/hakemler.php');
}

if ($islem === 'sil' && $id > 0) {
    $st = $pdo->prepare("SELECT user_id FROM hakemler WHERE id=?"); $st->execute([$id]); $hk = $st->fetch();
    $pdo->prepare("DELETE FROM hakemler WHERE id=?")->execute([$id]);
    if ($hk && $hk['user_id']) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$hk['user_id']]);
    flash_set('basari','Hakem silindi.');
    redirect(BASE_URL.'/admin/hakemler.php');
}

$duzenlenen = null; $duz_user = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM hakemler WHERE id=?"); $st->execute([$id]); $duzenlenen = $st->fetch();
    if ($duzenlenen && $duzenlenen['user_id']) {
        $st = $pdo->prepare("SELECT * FROM users WHERE id=?"); $st->execute([$duzenlenen['user_id']]); $duz_user = $st->fetch();
    }
}

$liste = $pdo->query("
    SELECT h.*, u.kullanici_adi, u.aktif FROM hakemler h
    LEFT JOIN users u ON u.id = h.user_id
    ORDER BY h.ad, h.soyad
")->fetchAll();

ob_start();
?>
<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?><input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>"><?php endif; ?>
    <div class="grid-2">
        <label>Ad *<input type="text" name="ad" required value="<?= e($duzenlenen['ad'] ?? '') ?>"></label>
        <label>Soyad *<input type="text" name="soyad" required value="<?= e($duzenlenen['soyad'] ?? '') ?>"></label>
    </div>
    <div class="grid-2">
        <label>TC Kimlik<input type="text" name="tc_kimlik" maxlength="11" value="<?= e($duzenlenen['tc_kimlik'] ?? '') ?>"></label>
        <label>Seviye
            <select name="seviye">
                <?php foreach (['İl','Bölge','Ulusal','Uluslararası'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($duzenlenen['seviye']??'İl')===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="grid-2">
        <label>Telefon<input type="text" name="telefon" value="<?= e($duzenlenen['telefon'] ?? '') ?>"></label>
        <label>E-posta<input type="email" name="email" value="<?= e($duzenlenen['email'] ?? '') ?>"></label>
    </div>
    <hr>
    <h3>Hesap Bilgileri</h3>
    <div class="grid-2">
        <label>Kullanıcı Adı <?= $islem==='ekle'?'*':'' ?>
            <input type="text" name="kullanici_adi" <?= $islem==='ekle'?'required':'' ?>
                   value="<?= e($duz_user['kullanici_adi'] ?? '') ?>" <?= $islem==='duzenle'?'readonly':'' ?>>
        </label>
        <label>Şifre <?= $islem==='ekle'?'*':'(boş bırakırsanız değişmez)' ?>
            <input type="password" name="yeni_sifre" <?= $islem==='ekle'?'required':'' ?>>
        </label>
    </div>
    <?php if ($islem==='duzenle'): ?>
    <label class="check-line"><input type="checkbox" name="aktif" value="1" <?= ($duz_user['aktif']??1)?'checked':'' ?>> Aktif</label>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/hakemler.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<?php else: ?>
<div class="toolbar"><a href="?islem=ekle" class="btn btn-primary">+ Yeni Hakem</a></div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Ad Soyad</th><th>Seviye</th><th>Telefon</th><th>E-posta</th><th>Kullanıcı</th><th>Durum</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $h): ?>
    <tr>
        <td><?= e($h['ad'].' '.$h['soyad']) ?></td>
        <td><?= e($h['seviye']) ?></td>
        <td><?= e($h['telefon'] ?? '-') ?></td>
        <td><?= e($h['email']   ?? '-') ?></td>
        <td><code><?= e($h['kullanici_adi'] ?? '-') ?></code></td>
        <td><?= ($h['aktif']??1)?'<span class="badge badge-ok">Aktif</span>':'<span class="badge badge-no">Pasif</span>' ?></td>
        <td class="actions">
            <a href="?islem=duzenle&id=<?= (int)$h['id'] ?>" class="btn btn-sm">Düzenle</a>
            <a href="?islem=sil&id=<?= (int)$h['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi? Kullanıcı hesabı da silinir.')">Sil</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Hakemler';
$admin_aktif  = 'hakemler';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
