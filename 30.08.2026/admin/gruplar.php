<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';



zorunlu_rol('admin');

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/gruplar.php'); }
    $grup_adi = trim($_POST['grup_adi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    $sezon    = trim($_POST['sezon']    ?? LIG_SEZON);
    $lig_id   = (int)($_POST['lig_id'] ?? 0);
    $bolge_adi = trim($_POST['bolge_adi'] ?? '');
    $kategori_adi = trim($_POST['kategori_adi'] ?? '');
    $ligTurSt=$pdo->prepare('SELECT tur FROM ligler WHERE id=?');$ligTurSt->execute([$lig_id]);$ligTuru=$ligTurSt->fetchColumn();
    if ($ligTuru==='bireysel' && !in_array($kategori_adi,['Minik','Yıldız','Gençlik','Büyük','Veteran'],true)) { flash_set('hata','Kategori, sporcu kayıt ekranındaki kategorilerden biri olmalıdır.'); redirect(BASE_URL.'/admin/gruplar.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0)); }
    if ($ligTuru==='bireysel') {
        if ($bolge_adi==='' || $kategori_adi==='') { flash_set('hata','Bireysel Bölge Ligleri için bölge ve kategori zorunludur.'); redirect(BASE_URL.'/admin/gruplar.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0)); }
        $grup_adi=$bolge_adi.' > '.$kategori_adi;
    } else { $bolge_adi=null; $kategori_adi=null; }

    if ($lig_id === 0 || ($ligTuru !== 'bireysel' && $grup_adi === '')) {
        flash_set('hata','Lig ve grup adı zorunludur.');
        redirect(BASE_URL.'/admin/gruplar.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO gruplar (lig_id,grup_adi,bolge_adi,kategori_adi,aciklama,sezon) VALUES (?,?,?,?,?,?)")
            ->execute([$lig_id, $grup_adi, $bolge_adi, $kategori_adi, $aciklama, $sezon]);
        flash_set('basari','Grup eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        $pdo->prepare("UPDATE gruplar SET lig_id=?, grup_adi=?, bolge_adi=?, kategori_adi=?, aciklama=?, sezon=? WHERE id=?")
            ->execute([$lig_id, $grup_adi, $bolge_adi, $kategori_adi, $aciklama, $sezon, (int)$_POST['id']]);
        flash_set('basari','Grup güncellendi.');
    }
    redirect(BASE_URL.'/admin/gruplar.php');
}

if ($islem === 'sil' && $id > 0) {
    // Önce bağlı maç var mı?
    $say = (int)$pdo->query("SELECT COUNT(*) FROM maclar WHERE grup_id=$id")->fetchColumn();
    if ($say > 0) {
        flash_set('hata','Bu gruba bağlı maçlar var. Önce maçları silin.');
    } else {
        $pdo->prepare("DELETE FROM gruplar WHERE id=?")->execute([$id]);
        flash_set('basari','Grup silindi.');
    }
    redirect(BASE_URL.'/admin/gruplar.php');
}

if ($islem === 'fikstur' && $id > 0) {
    // Round-robin fikstür oluştur
    $takimler = $pdo->prepare("SELECT id FROM takimlar WHERE grup_id=? ORDER BY id");
    $takimler->execute([$id]);
    $ids = array_column($takimler->fetchAll(), 'id');
    if (count($ids) < 2) {
        flash_set('hata','Fikstür için en az 2 takım gerekir.');
        redirect(BASE_URL.'/admin/gruplar.php');
    }
    // Mevcut planlanmamış maçları sil
    $pdo->prepare("DELETE FROM maclar WHERE grup_id=? AND durum='planlandi'")->execute([$id]);
    // Round-robin (tek devre) algoritması
    $n = count($ids);
    // Takım sayısı tek ise "bye" ekle
    $teams = $ids;
    if ($n % 2 == 1) { $teams[] = null; $n++; }
    $rounds = $n - 1;
    $half   = $n / 2;
    $rotate = $teams;
    for ($r = 0; $r < $rounds; $r++) {
        for ($i = 0; $i < $half; $i++) {
            $home = $rotate[$i];
            $away = $rotate[$n - 1 - $i];
            if ($home === null || $away === null) continue;
            $pdo->prepare("INSERT INTO maclar (grup_id, ev_sahibi_id, deplasman_id, hafta, durum) VALUES (?,?,?,?, 'planlandi')")
                ->execute([$id, $home, $away, $r + 1]);
        }
        // rotate: ilk eleman sabit, geri kalanını bir kaydır
        $fixed = $rotate[0];
        $rest  = array_slice($rotate, 1);
        array_unshift($rest, array_pop($rest));
        $rotate = array_merge([$fixed], $rest);
    }
    flash_set('basari', "Fikstür oluşturuldu: $rounds hafta.");
    redirect(BASE_URL.'/admin/gruplar.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM gruplar WHERE id=?");
    $st->execute([$id]);
    $duzenlenen = $st->fetch();
}

$liste = $pdo->query("
    SELECT g.*, l.lig_adi, l.tur, COUNT(t.id) AS takim_sayisi
    FROM gruplar g LEFT JOIN takimlar t ON t.grup_id = g.id
    JOIN ligler l ON l.id=g.lig_id GROUP BY g.id ORDER BY l.lig_adi, g.grup_adi
")->fetchAll();
$ligler = $pdo->query("SELECT id,lig_adi,tur FROM ligler WHERE aktif=1 ORDER BY lig_adi")->fetchAll();

ob_start();
?>
<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?><input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>"><?php endif; ?>
    <div class="grid-2 bireysel-bolge-alanlari"><label>Bölge adı<input type="text" name="bolge_adi" value="<?= e($duzenlenen['bolge_adi'] ?? '') ?>" placeholder="Bursa - Balıkesir Bölgesi"></label><label>Kategori adı<input type="text" name="kategori_adi" value="<?= e($duzenlenen['kategori_adi'] ?? '') ?>" placeholder="Minikler Grubu"></label></div>
    <div class="grid-2"><label>Lig *<select name="lig_id" required><option value="">Seçin</option><?php foreach($ligler as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)($duzenlenen['lig_id']??0)===(int)$l['id']?'selected':'' ?>><?= e($l['lig_adi']) ?> (<?= $l['tur']==='bireysel'?'Bireysel':'Takım' ?>)</option><?php endforeach; ?></select></label><label>Grup Adı *<input type="text" name="grup_adi" required value="<?= e($duzenlenen['grup_adi'] ?? '') ?>"></label></div>
    <label>Açıklama<textarea name="aciklama" rows="3"><?= e($duzenlenen['aciklama'] ?? '') ?></textarea></label>
    <label>Sezon<input type="text" name="sezon" value="<?= e($duzenlenen['sezon'] ?? LIG_SEZON) ?>"></label>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/gruplar.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<script>(()=>{document.querySelector('input[name="grup_adi"]').required=false;const alan=document.querySelector('input[name="kategori_adi"]');if(!alan)return;const sec=document.createElement('select');sec.name='kategori_adi';['Minik','Yıldız','Gençlik','Büyük','Veteran'].forEach(k=>{const o=new Option(k,k,k===alan.value,k===alan.value);sec.add(o)});alan.replaceWith(sec)})();</script>
<script>(()=>{const form=document.querySelector('form.form'),lig=form.querySelector('select[name="lig_id"]'),grup=form.querySelector('input[name="grup_adi"]'),grupEtiket=grup.closest('label'),bolge=form.querySelector('input[name="bolge_adi"]'),kategori=form.querySelector('[name="kategori_adi"]'),bolgeAlan=form.querySelector('.bireysel-bolge-alanlari');const guncelle=()=>{const bireysel=lig.options[lig.selectedIndex]?.text.includes('Bireysel');bolgeAlan.hidden=!bireysel;grupEtiket.hidden=bireysel;grup.disabled=bireysel;grup.required=!bireysel;bolge.disabled=!bireysel;kategori.disabled=!bireysel;bolge.required=bireysel;kategori.required=bireysel};lig.addEventListener('change',guncelle);guncelle()})();</script>
<?php else: ?>
<div class="toolbar"><a href="?islem=ekle" class="btn btn-primary">+ Yeni Grup</a></div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Lig</th><th>Grup</th><th>Açıklama</th><th>Sezon</th><th>Takım</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $g): ?>
    <tr>
        <td><span class="badge"><?= e($g['lig_adi']) ?></span></td>
        <td><strong><?= e($g['grup_adi']) ?></strong></td>
        <td><?= e($g['aciklama'] ?? '-') ?></td>
        <td><?= e($g['sezon']) ?></td>
        <td><?= (int)$g['takim_sayisi'] ?></td>
        <td class="actions">
            <a href="?islem=duzenle&id=<?= (int)$g['id'] ?>" class="btn btn-sm">Düzenle</a>
            <?php if ($g['tur']==='takim'): ?><a href="?islem=fikstur&id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-primary"
               onclick="return confirm('Bu gruptaki PLANLANMIŞ maçlar silinip yeni fikstür oluşturulacak. Devam?')">Fikstür Oluştur</a>
            <?php else: ?><a href="<?= BASE_URL ?>/admin/bireysel-fikstur.php?lig_id=<?= (int)$g['lig_id'] ?>&grup_id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-primary">Bireysel Fikstür</a>
            <?php endif; ?>
            <a href="?islem=sil&id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-danger"
               onclick="return confirm('Grup silinsin mi? (Bağlı takım yoksa silinir)')">Sil</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Gruplar';
$admin_aktif  = 'gruplar';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
