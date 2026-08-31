<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/** Maç yönetimi (admin + hakem) */
zorunlu_rol('admin','hakem');
$u = kullanici_bilgi();

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/maclar.php'); }
    $grup_id       = (int)($_POST['grup_id']       ?? 0);
    $ev_sahibi_id  = (int)($_POST['ev_sahibi_id']  ?? 0);
    $deplasman_id  = (int)($_POST['deplasman_id']  ?? 0);
    $hafta         = (int)($_POST['hafta']         ?? 1);
    $tarih         = $_POST['tarih']                ?? null;
    $saat          = $_POST['saat']                 ?? null;
    $yer           = trim($_POST['yer']            ?? '');
    $hakem_id      = (int)($_POST['hakem_id']      ?? 0) ?: null;
    $durum         = $_POST['durum']                ?? 'planlandi';

    if (!$grup_id || !$ev_sahibi_id || !$deplasman_id || $ev_sahibi_id === $deplasman_id) {
        flash_set('hata','Geçerli bir grup ve iki farklı takım seçin.');
        redirect(BASE_URL.'/admin/maclar.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }
    $grupKontrol=$pdo->prepare("SELECT g.id FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE g.id=? AND l.tur='takim'");
    $grupKontrol->execute([$grup_id]);
    if (!$grupKontrol->fetch()) { flash_set('hata','Maç yalnızca Takım Ligi grubuna eklenebilir.'); redirect(BASE_URL.'/admin/maclar.php'); }
    $takimKontrol=$pdo->prepare('SELECT COUNT(*) FROM takimlar WHERE grup_id=? AND id IN (?,?)');
    $takimKontrol->execute([$grup_id,$ev_sahibi_id,$deplasman_id]);
    if ((int)$takimKontrol->fetchColumn() !== 2) { flash_set('hata','Seçilen iki takım aynı Takım Ligi grubuna ait olmalıdır.'); redirect(BASE_URL.'/admin/maclar.php'); }

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO maclar (grup_id,ev_sahibi_id,deplasman_id,hafta,tarih,saat,yer,hakem_id,durum) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$grup_id,$ev_sahibi_id,$deplasman_id,$hafta,$tarih?:null,$saat?:null,$yer,$hakem_id,$durum]);
        flash_set('basari','Maç eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        $pdo->prepare("UPDATE maclar SET grup_id=?, ev_sahibi_id=?, deplasman_id=?, hafta=?, tarih=?, saat=?, yer=?, hakem_id=?, durum=? WHERE id=?")
            ->execute([$grup_id,$ev_sahibi_id,$deplasman_id,$hafta,$tarih?:null,$saat?:null,$yer,$hakem_id,$durum,(int)$_POST['id']]);
        flash_set('basari','Maç güncellendi.');
    }
    redirect(BASE_URL.'/admin/maclar.php');
}

if ($islem === 'sil' && $id > 0) {
    $pdo->prepare("DELETE FROM maclar WHERE id=?")->execute([$id]);
    // istatistikler değişti, temizleme: tüm takım/sporcu istatistiklerini resetle
    flash_set('basari','Maç silindi.');
    redirect(BASE_URL.'/admin/maclar.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM maclar WHERE id=?"); $st->execute([$id]); $duzenlenen = $st->fetch();
}

$gruplar  = $pdo->query("SELECT g.*,l.lig_adi FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' ORDER BY l.lig_adi,g.grup_adi")->fetchAll();
$hakemler = $pdo->query("SELECT * FROM hakemler ORDER BY ad, soyad")->fetchAll();
$takimlarr = $pdo->query("SELECT t.*, g.grup_adi FROM takimlar t JOIN gruplar g ON g.id=t.grup_id JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' ORDER BY l.lig_adi,g.grup_adi, t.takim_adi")->fetchAll();
$tMap = [];
foreach ($takimlarr as $tt) $tMap[(int)$tt['id']] = $tt;

$liste = $pdo->query("
    SELECT m.*, t1.takim_adi AS ev, t2.takim_adi AS dep, g.grup_adi, h.ad AS hakem_ad, h.soyad AS hakem_soyad
    FROM maclar m
    JOIN takimlar t1 ON t1.id = m.ev_sahibi_id
    JOIN takimlar t2 ON t2.id = m.deplasman_id
    JOIN gruplar  g  ON g.id  = m.grup_id
    LEFT JOIN hakemler h ON h.id = m.hakem_id
    ORDER BY m.grup_id, m.hafta, m.tarih
")->fetchAll();

ob_start();
?>
<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?><input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>"><?php endif; ?>
    <div class="grid-2">
        <label>Grup *
            <select name="grup_id" id="macGrubu" required>
                <option value="">Seçin</option>
                <?php foreach ($gruplar as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= ($duzenlenen && (int)$duzenlenen['grup_id']===(int)$g['id'])?'selected':'' ?>>
                        <?= e($g['lig_adi'].' / '.$g['grup_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Hafta *<input type="number" name="hafta" min="1" max="50" required value="<?= e($duzenlenen['hafta'] ?? 1) ?>"></label>
    </div>
    <div class="grid-2">
        <label>Ev Sahibi *
            <select name="ev_sahibi_id" id="evTakimi" required>
                <option value="">Seçin</option>
                <?php foreach ($takimlarr as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" data-grup="<?= (int)$t['grup_id'] ?>" <?= ($duzenlenen && (int)$duzenlenen['ev_sahibi_id']===(int)$t['id'])?'selected':'' ?>>
                        <?= e($t['grup_adi'].' / '.$t['takim_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Deplasman *
            <select name="deplasman_id" id="depTakimi" required>
                <option value="">Seçin</option>
                <?php foreach ($takimlarr as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" data-grup="<?= (int)$t['grup_id'] ?>" <?= ($duzenlenen && (int)$duzenlenen['deplasman_id']===(int)$t['id'])?'selected':'' ?>>
                        <?= e($t['grup_adi'].' / '.$t['takim_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="grid-3">
        <label>Tarih<input type="date" name="tarih" value="<?= e($duzenlenen['tarih'] ?? '') ?>"></label>
        <label>Saat<input type="time" name="saat"  value="<?= e($duzenlenen['saat']  ?? '') ?>"></label>
        <label>Hakem
            <select name="hakem_id">
                <option value="">— Atanmadı —</option>
                <?php foreach ($hakemler as $h): ?>
                    <option value="<?= (int)$h['id'] ?>" <?= ($duzenlenen && (int)$duzenlenen['hakem_id']===(int)$h['id'])?'selected':'' ?>>
                        <?= e($h['ad'].' '.$h['soyad']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="grid-2">
        <label>Yer<input type="text" name="yer" value="<?= e($duzenlenen['yer'] ?? '') ?>"></label>
        <label>Durum
            <select name="durum">
                <?php foreach (['planlandi'=>'Planlandı','oynandi'=>'Oynandı','iptal'=>'İptal'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= ($duzenlenen['durum']??'planlandi')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/maclar.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<script>
(function(){
    const grup=document.getElementById('macGrubu'), secimler=[document.getElementById('evTakimi'),document.getElementById('depTakimi')];
    function filtrele(){ const gid=grup.value; secimler.forEach(select=>{ Array.from(select.options).forEach(option=>{ if(!option.value)return; const uygun=!gid||option.dataset.grup===gid; option.hidden=!uygun; option.disabled=!uygun; }); if(select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value=''; }); }
    grup.addEventListener('change',filtrele); filtrele();
})();
</script>
<?php else: ?>
<div class="toolbar">
    <a href="?islem=ekle" class="btn btn-primary">+ Yeni Maç</a>
    <a href="<?= BASE_URL ?>/admin/gruplar.php" class="btn btn-outline">Fikstür Oluştur (Gruplar)</a>
    <a href="<?= BASE_URL ?>/admin/mac-skor.php" class="btn btn-primary">Skor Girişi →</a>
</div>
<div class="table-wrap">
<table class="data-table">
<thead><tr>
    <th>Grup</th><th>Hafta</th><th>Tarih</th><th>Ev</th><th>Skor</th><th>Dep</th>
    <th>Hakem</th><th>Yer</th><th>Durum</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($liste as $m): ?>
    <tr>
        <td><?= e($m['grup_adi']) ?></td>
        <td><?= (int)$m['hafta'] ?></td>
        <td><?= tr_tarih($m['tarih']) ?> <?= tr_saat($m['saat']) ?></td>
        <td><strong><?= e($m['ev']) ?></strong></td>
        <td>
            <?php if ($m['durum']==='oynandi'): ?>
                <span class="score"><?= (int)$m['ev_sahibi_set'] ?> - <?= (int)$m['deplasman_set'] ?></span>
                <small>(<?= (int)$m['ev_sahibi_puan'] ?> - <?= (int)$m['deplasman_puan'] ?>)</small>
            <?php else: ?>
                <span class="muted">vs</span>
            <?php endif; ?>
        </td>
        <td><?= e($m['dep']) ?></td>
        <td><?= e(trim(($m['hakem_ad']??'').' '.($m['hakem_soyad']??'')) ?: '-') ?></td>
        <td><?= e($m['yer'] ?? '-') ?></td>
        <td>
            <?php if ($m['durum']==='oynandi'): ?><span class="badge badge-ok">Oynandı</span>
            <?php elseif ($m['durum']==='iptal'): ?><span class="badge badge-no">İptal</span>
            <?php else: ?><span class="badge">Planlandı</span><?php endif; ?>
        </td>
        <td class="actions">
            <a href="<?= BASE_URL ?>/admin/mac-skor.php?mac_id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-primary">Skor</a>
            <a href="?islem=duzenle&id=<?= (int)$m['id'] ?>" class="btn btn-sm">Düzenle</a>
            <a href="?islem=sil&id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Maçlar';
$admin_aktif  = 'maclar';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
