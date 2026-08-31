<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

zorunlu_rol('uye','admin','hakem','yetkili','sporcu');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    flash_set('hata', 'Favori işlemi doğrulanamadı.'); redirect(BASE_URL . '/index.php');
}
$tur = $_POST['tur'] ?? '';
$hedef = (int)($_POST['hedef_id'] ?? 0);
$donus = $_POST['donus'] ?? BASE_URL . '/index.php';
if (!in_array($tur, ['takim','sporcu'], true) || $hedef < 1) { flash_set('hata','Geçersiz favori.'); redirect($donus); }
$tablo = $tur === 'takim' ? 'takimlar' : 'sporcular';
$st = $pdo->prepare("SELECT id FROM {$tablo} WHERE id=?"); $st->execute([$hedef]);
if (!$st->fetch()) { flash_set('hata','Kayıt bulunamadı.'); redirect($donus); }
$uid = kullanici_bilgi()['id'];
if (($_POST['islem'] ?? '') === 'kaldir') {
    $pdo->prepare('DELETE FROM favoriler WHERE user_id=? AND tur=? AND hedef_id=?')->execute([$uid,$tur,$hedef]);
    flash_set('basari','Favorilerden kaldırıldı.');
} else {
    $pdo->prepare('INSERT IGNORE INTO favoriler (user_id,tur,hedef_id) VALUES (?,?,?)')->execute([$uid,$tur,$hedef]);
    flash_set('basari','Favorilere eklendi.');
}
redirect($donus);
?>
