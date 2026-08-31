<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
zorunlu_rol('admin');

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/haberler.php'); }
    $baslik   = trim($_POST['baslik'] ?? '');
    $ozet     = trim($_POST['ozet']   ?? '');
    $icerik   = $_POST['icerik']      ?? '';
    $yayinda  = isset($_POST['yayinda']) ? 1 : 0;

    $gorsel = gorsel_yukle('gorsel', 'haberler', $_POST['mevcut_gorsel'] ?? null);

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO haberler (baslik,ozet,icerik,gorsel,yayinda,yazar_id) VALUES (?,?,?,?,?,?)")
            ->execute([$baslik, $ozet, guvenli_html($icerik), $gorsel, $yayinda, kullanici_bilgi()['id']]);
        flash_set('basari','Haber eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        $pdo->prepare("UPDATE haberler SET baslik=?, ozet=?, icerik=?, gorsel=?, yayinda=? WHERE id=?")
            ->execute([$baslik, $ozet, guvenli_html($icerik), $gorsel, $yayinda, (int)$_POST['id']]);
        flash_set('basari','Haber güncellendi.');
    }
    redirect(BASE_URL.'/admin/haberler.php');
}
if ($islem === 'sil' && $id > 0) {
    $pdo->prepare("DELETE FROM haberler WHERE id=?")->execute([$id]);
    flash_set('basari','Haber silindi.');
    redirect(BASE_URL.'/admin/haberler.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM haberler WHERE id=?");
    $st->execute([$id]);
    $duzenlenen = $st->fetch();
}

$liste = $pdo->query("SELECT * FROM haberler ORDER BY created_at DESC")->fetchAll();

ob_start();
?>
<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?><input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>">
        <input type="hidden" name="mevcut_gorsel" value="<?= e($duzenlenen['gorsel'] ?? '') ?>"><?php endif; ?>
    <label>Başlık *<input type="text" name="baslik" required value="<?= e($duzenlenen['baslik'] ?? '') ?>"></label>
    <label>Özet<input type="text" name="ozet" value="<?= e($duzenlenen['ozet'] ?? '') ?>"></label>
    <label>İçerik *</label>
    <?= html_editor_alani('icerik', 'haberEditor', $duzenlenen['icerik'] ?? '') ?>
    <div class="grid-2">
        <label>Görsel<input type="file" name="gorsel" accept="image/*"></label>
        <label class="check-line"><input type="checkbox" name="yayinda" value="1" <?= (!$duzenlenen || $duzenlenen['yayinda'])?'checked':'' ?>> Yayında</label>
    </div>
    <?php if ($duzenlenen && $duzenlenen['gorsel']): ?>
        <p>Mevcut: <img src="<?= UPLOAD_URL ?>/<?= e($duzenlenen['gorsel']) ?>" class="thumb"></p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/haberler.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<?php else: ?>
<div class="toolbar"><a href="?islem=ekle" class="btn btn-primary">+ Yeni Haber</a></div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Başlık</th><th>Görsel</th><th>Yayın</th><th>Tarih</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $h): ?>
    <tr>
        <td><?= e($h['baslik']) ?></td>
        <td><?php if ($h['gorsel']): ?><img src="<?= UPLOAD_URL ?>/<?= e($h['gorsel']) ?>" class="thumb-sm"><?php else: ?>-<?php endif; ?></td>
        <td><?= $h['yayinda']?'<span class="badge badge-ok">Yayında</span>':'<span class="badge">Taslak</span>' ?></td>
        <td><?= tr_tarih_saat($h['created_at']) ?></td>
        <td class="actions">
            <a href="?islem=duzenle&id=<?= (int)$h['id'] ?>" class="btn btn-sm">Düzenle</a>
            <a href="?islem=sil&id=<?= (int)$h['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Haberler';
$admin_aktif  = 'haberler';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
