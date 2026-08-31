<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';



/** Takımlar yönetimi yalnızca admin içindir; takım yetkilisi sporcu ekranını kullanır. */
zorunlu_rol('admin');
$u = kullanici_bilgi();

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/takimlar.php'); }
    $grup_id     = (int)($_POST['grup_id']     ?? 0);
    $takim_adi   = trim($_POST['takim_adi']   ?? '');
    $kisa_ad     = trim($_POST['kisa_ad']     ?? '');
    $sehir       = trim($_POST['sehir']       ?? '');
    $kurulus_yili= (int)($_POST['kurulus_yili'] ?? 0) ?: null;

    if ($takim_adi === '' || $grup_id === 0) {
        flash_set('hata','Grup ve takım adı zorunludur.');
        redirect(BASE_URL.'/admin/takimlar.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }
    $grupKontrol=$pdo->prepare("SELECT g.id FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE g.id=? AND l.tur='takim'"); $grupKontrol->execute([$grup_id]);
    if (!$grupKontrol->fetch()) { flash_set('hata','Takım yalnızca Takım Ligi grubuna eklenebilir.'); redirect(BASE_URL.'/admin/takimlar.php'); }

    $logo = gorsel_yukle('logo', 'takimlar', $_POST['mevcut_logo'] ?? null);

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO takimlar (grup_id,takim_adi,kisa_ad,sehir,kurulus_yili,logo) VALUES (?,?,?,?,?,?)")
            ->execute([$grup_id, $takim_adi, $kisa_ad, $sehir, $kurulus_yili, $logo]);
        flash_set('basari','Takım eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        $pdo->prepare("UPDATE takimlar SET grup_id=?, takim_adi=?, kisa_ad=?, sehir=?, kurulus_yili=?, logo=? WHERE id=?")
            ->execute([$grup_id, $takim_adi, $kisa_ad, $sehir, $kurulus_yili, $logo, (int)$_POST['id']]);
        flash_set('basari','Takım güncellendi.');
    }
    redirect(BASE_URL.'/admin/takimlar.php');
}

if ($islem === 'sil' && $id > 0) {
    $say = (int)$pdo->query("SELECT COUNT(*) FROM maclar WHERE ev_sahibi_id=$id OR deplasman_id=$id")->fetchColumn();
    if ($say > 0) {
        flash_set('hata','Bu takımın maçları var. Önce maçları silin.');
    } else {
        $pdo->prepare("DELETE FROM takimlar WHERE id=?")->execute([$id]);
        flash_set('basari','Takım silindi.');
    }
    redirect(BASE_URL.'/admin/takimlar.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM takimlar WHERE id=?");
    $st->execute([$id]);
    $duzenlenen = $st->fetch();
}

$gruplar = $pdo->query("SELECT g.*,l.lig_adi FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' ORDER BY l.lig_adi,g.grup_adi")->fetchAll();
$liste = $pdo->query("
    SELECT t.*, g.grup_adi, l.lig_adi FROM takimlar t JOIN gruplar g ON g.id=t.grup_id JOIN ligler l ON l.id=g.lig_id
    WHERE l.tur='takim' ORDER BY l.lig_adi,g.grup_adi, t.takim_adi
")->fetchAll();

ob_start();
?>
<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?><input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>">
        <input type="hidden" name="mevcut_logo" value="<?= e($duzenlenen['logo'] ?? '') ?>"><?php endif; ?>
    <div class="grid-2">
        <label>Grup *
            <select name="grup_id" required>
                <option value="">Seçin</option>
                <?php foreach ($gruplar as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= ($duzenlenen && (int)$duzenlenen['grup_id']===(int)$g['id'])?'selected':'' ?>>
                        <?= e($g['lig_adi'].' / '.$g['grup_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Takım Adı *<input type="text" name="takim_adi" required value="<?= e($duzenlenen['takim_adi'] ?? '') ?>"></label>
    </div>
    <div class="grid-2">
        <label>Kısa Ad<input type="text" name="kisa_ad" maxlength="20" value="<?= e($duzenlenen['kisa_ad'] ?? '') ?>"></label>
        <label>Şehir<input type="text" name="sehir" value="<?= e($duzenlenen['sehir'] ?? '') ?>"></label>
    </div>
    <div class="grid-2">
        <label>Kuruluş Yılı<input type="number" name="kurulus_yili" min="1900" max="<?= date('Y') ?>" value="<?= e($duzenlenen['kurulus_yili'] ?? '') ?>"></label>
        <label>Logo<input type="file" name="logo" accept="image/*"></label>
    </div>
    <?php if ($duzenlenen && $duzenlenen['logo']): ?>
        <p>Mevcut logo: <img src="<?= UPLOAD_URL ?>/<?= e($duzenlenen['logo']) ?>" class="thumb"></p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/takimlar.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<?php else: ?>
<div class="toolbar"><a href="?islem=ekle" class="btn btn-primary">+ Yeni Takım</a></div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Logo</th><th>Takım</th><th>Grup</th><th>Şehir</th><th>Set</th><th>Averaj</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $t): ?>
    <tr>
        <td><?php if ($t['logo']): ?><img src="<?= UPLOAD_URL ?>/<?= e($t['logo']) ?>" class="thumb-sm"><?php else: ?>-<?php endif; ?></td>
        <td><a href="<?= BASE_URL ?>/takim.php?id=<?= (int)$t['id'] ?>"><?= e($t['takim_adi']) ?></a></td>
        <td><?= e($t['grup_adi']) ?></td>
        <td><?= e($t['sehir'] ?? '-') ?></td>
        <td><?= (int)$t['toplam_set'] ?></td>
        <td><?= (int)$t['toplam_puan'] ?></td>
        <td class="actions">
            <a href="?islem=duzenle&id=<?= (int)$t['id'] ?>" class="btn btn-sm">Düzenle</a>
            <a href="?islem=sil&id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Takımlar';
$admin_aktif  = 'takimlar';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
