<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1) KONFİGÜRASYON, VERİTABANI VE FONKSİYONLARI YÜKLE
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// 2) YETKİ KONTROLÜ
zorunlu_rol('admin');

// 3) İŞLEMLER
$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        flash_set('hata', 'CSRF hatası');
        redirect(BASE_URL . '/admin/duyurular.php');
    }
    $baslik   = trim($_POST['baslik'] ?? '');
    $icerik   = $_POST['icerik'] ?? '';
    $medya_url = trim($_POST['medya_url'] ?? '');
    $yayinda  = isset($_POST['yayinda']) ? 1 : 0;

    if ($baslik === '' || trim(strip_tags($icerik)) === '' || ($medya_url !== '' && !filter_var($medya_url, FILTER_VALIDATE_URL))) {
        flash_set('hata', 'Başlık ve içerik zorunludur.');
        redirect(BASE_URL . '/admin/duyurular.php?islem=' . $_POST['islem'] . '&id=' . (int)($_POST['id'] ?? 0));
    }

    $gorsel = gorsel_yukle('gorsel', 'duyurular', $_POST['mevcut_gorsel'] ?? null);

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO duyurular (baslik, icerik, gorsel, medya_url, yayinda, yazar_id) VALUES (?,?,?,?,?,?)")
            ->execute([$baslik, guvenli_html($icerik), $gorsel, $medya_url ?: null, $yayinda, kullanici_bilgi()['id']]);
        flash_set('basari', 'Duyuru eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        $pdo->prepare("UPDATE duyurular SET baslik=?, icerik=?, gorsel=?, medya_url=?, yayinda=? WHERE id=?")
            ->execute([$baslik, guvenli_html($icerik), $gorsel, $medya_url ?: null, $yayinda, (int)$_POST['id']]);
        flash_set('basari', 'Duyuru güncellendi.');
    }
    redirect(BASE_URL . '/admin/duyurular.php');
}

if ($islem === 'sil' && $id > 0) {
    $pdo->prepare("DELETE FROM duyurular WHERE id = ?")->execute([$id]);
    flash_set('basari', 'Duyuru silindi.');
    redirect(BASE_URL . '/admin/duyurular.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM duyurular WHERE id = ?");
    $st->execute([$id]);
    $duzenlenen = $st->fetch();
    if (!$duzenlenen) {
        flash_set('hata', 'Duyuru bulunamadı.');
        redirect(BASE_URL . '/admin/duyurular.php');
    }
}

$liste = $pdo->query("SELECT * FROM duyurular ORDER BY created_at DESC")->fetchAll();

// 4) ÇIKTI TAMPONLAMASINI BAŞLAT
ob_start();
?>

<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?>
        <input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>">
        <input type="hidden" name="mevcut_gorsel" value="<?= e($duzenlenen['gorsel'] ?? '') ?>">
    <?php endif; ?>
    <label>Başlık *<input type="text" name="baslik" required value="<?= e($duzenlenen['baslik'] ?? '') ?>"></label>
    <label>İçerik * (HTML / metin editörü)
        <textarea name="icerik" id="ed-duyuru" rows="12" class="editor"><?= e($duzenlenen['icerik'] ?? '') ?></textarea>
    </label>
    <label>YouTube / video bağlantısı (isteğe bağlı)
        <input type="url" name="medya_url" placeholder="https://www.youtube.com/watch?v=..." value="<?= e($duzenlenen['medya_url'] ?? '') ?>">
    </label>
    <div class="grid-2">
        <label>Görsel (jpg, png, webp, svg - max 5MB)
            <input type="file" name="gorsel" accept="image/*">
        </label>
        <label class="check-line">
            <input type="checkbox" name="yayinda" value="1" <?= (!$duzenlenen || $duzenlenen['yayinda']) ? 'checked' : '' ?>> Yayında
        </label>
    </div>
    <?php if ($duzenlenen && $duzenlenen['gorsel']): ?>
        <p>Mevcut görsel: <img src="<?= UPLOAD_URL ?>/<?= e($duzenlenen['gorsel']) ?>" class="thumb"></p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem === 'ekle' ? 'Ekle' : 'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/duyurular.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<?php else: ?>
<div class="toolbar">
    <a href="?islem=ekle" class="btn btn-primary">+ Yeni Duyuru</a>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Başlık</th><th>Görsel</th><th>Yayın</th><th>Tarih</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($liste)): ?>
            <tr><td colspan="5" class="muted">Henüz duyuru yok.</td></tr>
        <?php else: foreach ($liste as $d): ?>
            <tr>
                <td><?= e($d['baslik']) ?></td>
                <td><?php if ($d['gorsel']): ?><img src="<?= UPLOAD_URL ?>/<?= e($d['gorsel']) ?>" class="thumb-sm"><?php else: ?>-<?php endif; ?></td>
                <td><?= $d['yayinda'] ? '<span class="badge badge-ok">Yayında</span>' : '<span class="badge">Taslak</span>' ?></td>
                <td><?= tr_tarih_saat($d['created_at']) ?></td>
                <td class="actions">
                    <a href="?islem=duzenle&id=<?= (int)$d['id'] ?>" class="btn btn-sm">Düzenle</a>
                    <a href="?islem=sil&id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Bu duyuruyu silmek istediğinize emin misiniz?')">Sil</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>


<?php endif; ?>



<?php

// 5) TAMPONLANAN ÇIKTIYI YAKALA
$admin_icerik = ob_get_clean();

// 6) LAYOUT DEĞİŞKENLERİ
$admin_baslik = 'Duyurular';
$admin_aktif  = 'duyurular';

// 7) LAYOUT'U DAHİL ET
require __DIR__ . '/partials/layout.php';
