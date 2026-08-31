<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/** Yetkili (antrenör / kulüp yöneticisi) yönetimi — sadece admin */
zorunlu_rol('admin');

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/yetkili.php'); }
    $ad        = trim($_POST['ad']      ?? '');
    $soyad     = trim($_POST['soyad']   ?? '');
    $tc_kimlik = trim($_POST['tc_kimlik'] ?? '');
    $telefon   = trim($_POST['telefon'] ?? '');
    $email     = trim($_POST['email']   ?? '');
    $pozisyon  = trim($_POST['pozisyon']?? 'Antrenör');
    $takim_id  = (int)($_POST['takim_id'] ?? 0) ?: null;
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $yeni_sifre    = $_POST['yeni_sifre']    ?? '';
    $aktif         = isset($_POST['aktif']) ? 1 : 0;

    if ($ad === '' || $soyad === '') {
        flash_set('hata','Ad ve soyad zorunludur.');
        redirect(BASE_URL.'/admin/yetkili.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }

    $pdo->beginTransaction();
    try {
        if ($_POST['islem'] === 'ekle') {
            if ($kullanici_adi === '' || $yeni_sifre === '') throw new Exception('Kullanıcı adı ve şifre zorunludur.');
            $pdo->prepare("INSERT INTO users (kullanici_adi,email,sifre,rol,ad_soyad,aktif) VALUES (?,?,?,?,?,?)")
                ->execute([$kullanici_adi, $email ?: $kullanici_adi.'@okculukligi.local', sifre_hash($yeni_sifre), 'yetkili', $ad.' '.$soyad, $aktif]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO yetkili (user_id,takim_id,ad,soyad,tc_kimlik,telefon,email,pozisyon) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$uid, $takim_id, $ad, $soyad, $tc_kimlik, $telefon, $email, $pozisyon]);
            flash_set('basari','Yetkili eklendi.');
        } elseif ($_POST['islem'] === 'duzenle') {
            $ytid = (int)$_POST['id'];
            $st = $pdo->prepare("SELECT * FROM yetkili WHERE id=?"); $st->execute([$ytid]); $yt = $st->fetch();
            if (!$yt) throw new Exception('Yetkili bulunamadı.');
            $pdo->prepare("UPDATE yetkili SET takim_id=?, ad=?, soyad=?, tc_kimlik=?, telefon=?, email=?, pozisyon=? WHERE id=?")
                ->execute([$takim_id, $ad, $soyad, $tc_kimlik, $telefon, $email, $pozisyon, $ytid]);
            if ($yt['user_id']) {
                $pdo->prepare("UPDATE users SET ad_soyad=?, email=?, aktif=? WHERE id=?")
                    ->execute([$ad.' '.$soyad, $email, $aktif, (int)$yt['user_id']]);
                if ($yeni_sifre !== '') {
                    $pdo->prepare("UPDATE users SET sifre=? WHERE id=?")
                        ->execute([sifre_hash($yeni_sifre), (int)$yt['user_id']]);
                }
            }
            flash_set('basari','Yetkili güncellendi.');
        }
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        flash_set('hata', $ex->getMessage());
    }
    redirect(BASE_URL.'/admin/yetkili.php');
}

if ($islem === 'sil' && $id > 0) {
    $st = $pdo->prepare("SELECT user_id FROM yetkili WHERE id=?"); $st->execute([$id]); $yt = $st->fetch();
    $pdo->prepare("DELETE FROM yetkili WHERE id=?")->execute([$id]);
    if ($yt && $yt['user_id']) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$yt['user_id']]);
    flash_set('basari','Yetkili silindi.');
    redirect(BASE_URL.'/admin/yetkili.php');
}

$duzenlenen = null; $duz_user = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM yetkili WHERE id=?"); $st->execute([$id]); $duzenlenen = $st->fetch();
    if ($duzenlenen && $duzenlenen['user_id']) {
        $st = $pdo->prepare("SELECT * FROM users WHERE id=?"); $st->execute([$duzenlenen['user_id']]); $duz_user = $st->fetch();
    }
}

$takimlar = $pdo->query("SELECT t.*, g.grup_adi FROM takimlar t JOIN gruplar g ON g.id=t.grup_id ORDER BY g.grup_adi, t.takim_adi")->fetchAll();
$liste = $pdo->query("
    SELECT y.*, u.kullanici_adi, u.aktif, t.takim_adi, g.grup_adi
    FROM yetkili y
    LEFT JOIN users   u ON u.id = y.user_id
    LEFT JOIN takimlar t ON t.id = y.takim_id
    LEFT JOIN gruplar  g ON g.id = t.grup_id
    ORDER BY y.ad, y.soyad
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
        <label>Pozisyon
            <select name="pozisyon">
                <?php foreach (['Antrenör','Kulüp Başkanı','Yönetici','Asistan Antrenör'] as $p): ?>
                    <option value="<?= $p ?>" <?= ($duzenlenen['pozisyon']??'Antrenör')===$p?'selected':'' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="grid-2">
        <label>Telefon<input type="text" name="telefon" value="<?= e($duzenlenen['telefon'] ?? '') ?>"></label>
        <label>E-posta<input type="email" name="email" value="<?= e($duzenlenen['email'] ?? '') ?>"></label>
    </div>
    <label>Takım
        <select name="takim_id">
            <option value="">— Atanmadı —</option>
            <?php foreach ($takimlar as $tk): ?>
                <option value="<?= (int)$tk['id'] ?>" <?= ((int)($duzenlenen['takim_id'] ?? 0)===(int)$tk['id'])?'selected':'' ?>>
                    <?= e($tk['grup_adi'].' / '.$tk['takim_adi']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <hr><h3>Hesap Bilgileri</h3>
    <div class="grid-2">
        <label>Kullanıcı Adı <?= $islem==='ekle'?'*':'' ?>
            <input type="text" name="kullanici_adi" <?= $islem==='ekle'?'required':'' ?>
                   value="<?= e($duz_user['kullanici_adi'] ?? '') ?>" <?= $islem==='duzenle'?'readonly':'' ?>>
        </label>
        <label>Şifre <?= $islem==='ekle'?'*':'(boşsa değişmez)' ?>
            <input type="password" name="yeni_sifre" <?= $islem==='ekle'?'required':'' ?>>
        </label>
    </div>
    <?php if ($islem==='duzenle'): ?>
    <label class="check-line"><input type="checkbox" name="aktif" value="1" <?= ($duz_user['aktif']??1)?'checked':'' ?>> Aktif</label>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/yetkili.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<?php else: ?>
<div class="toolbar"><a href="?islem=ekle" class="btn btn-primary">+ Yeni Yetkili</a></div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Ad Soyad</th><th>Pozisyon</th><th>Takım</th><th>Telefon</th><th>Kullanıcı</th><th>Durum</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $y): ?>
    <tr>
        <td><?= e($y['ad'].' '.$y['soyad']) ?></td>
        <td><?= e($y['pozisyon']) ?></td>
        <td><?= e(($y['grup_adi']??'').' '.($y['takim_adi']??'')) ?: '-' ?></td>
        <td><?= e($y['telefon'] ?? '-') ?></td>
        <td><code><?= e($y['kullanici_adi'] ?? '-') ?></code></td>
        <td><?= ($y['aktif']??1)?'<span class="badge badge-ok">Aktif</span>':'<span class="badge badge-no">Pasif</span>' ?></td>
        <td class="actions">
            <a href="?islem=duzenle&id=<?= (int)$y['id'] ?>" class="btn btn-sm">Düzenle</a>
            <a href="?islem=sil&id=<?= (int)$y['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Yetkililer';
$admin_aktif  = 'yetkili';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
