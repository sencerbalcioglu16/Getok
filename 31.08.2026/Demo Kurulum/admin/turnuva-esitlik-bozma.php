<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
zorunlu_rol('admin','yonetici','hakem');

$id=(int)($_GET['id']??$_POST['id']??0);
$set=max(1,min(SET_SAYISI,(int)($_GET['set']??$_POST['set']??1)));
$q=$pdo->prepare("SELECT tm.*,t.turnuva_adi,t.tur,CASE WHEN t.tur='takim' THEN a.takim_adi ELSE CONCAT(sa.ad,' ',sa.soyad) END ev,CASE WHEN t.tur='takim' THEN b.takim_adi ELSE CONCAT(sb.ad,' ',sb.soyad) END dep FROM turnuva_maclari tm JOIN turnuvalar t ON t.id=tm.turnuva_id LEFT JOIN takimlar a ON t.tur='takim' AND a.id=tm.katilimci1_id LEFT JOIN takimlar b ON t.tur='takim' AND b.id=tm.katilimci2_id LEFT JOIN sporcular sa ON t.tur='bireysel' AND sa.id=tm.katilimci1_id LEFT JOIN sporcular sb ON t.tur='bireysel' AND sb.id=tm.katilimci2_id WHERE tm.id=?");
$q->execute([$id]);$m=$q->fetch();
$normal=$pdo->prepare('SELECT * FROM turnuva_mac_setleri WHERE turnuva_mac_id=? AND set_no=?');$normal->execute([$id,$set]);$normalSet=$normal->fetch();
if(!$m || !$normalSet || (int)$normalSet['puan1']!==(int)$normalSet['puan2'] || $normalSet['kazanan']){flash_set('hata','Bu set için eşitlik bozma gerekmiyor.');redirect(BASE_URL.'/admin/turnuva-skor.php?id='.$id.'&set='.$set);}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['csrf']??'')){flash_set('hata','Güvenlik doğrulaması başarısız.');redirect(BASE_URL.'/admin/turnuva-esitlik-bozma.php?id='.$id.'&set='.$set);}
    $ev=max(0,min(21,(int)($_POST['ev_puan']??0)));$dep=max(0,min(21,(int)($_POST['dep_puan']??0)));
    $pdo->beginTransaction();
    try{
        $say=$pdo->prepare("SELECT COUNT(*) FROM esitlik_bozma_atislari WHERE kaynak='turnuva' AND turnuva_mac_id=? AND set_no=?");$say->execute([$id,$set]);$tur=(int)$say->fetchColumn()+1;
        $pdo->prepare("INSERT INTO esitlik_bozma_atislari(kaynak,turnuva_mac_id,set_no,tur_no,ev_puan,dep_puan) VALUES('turnuva',?,?,?,?,?)")->execute([$id,$set,$tur,$ev,$dep]);
        if($ev===$dep){$pdo->commit();flash_set('hata','Eşitlik devam ediyor; iki isimsiz sporcu yeniden 7 ok atmalıdır.');redirect(BASE_URL.'/admin/turnuva-esitlik-bozma.php?id='.$id.'&set='.$set);}
        $pdo->prepare('UPDATE turnuva_mac_setleri SET kazanan=? WHERE turnuva_mac_id=? AND set_no=?')->execute([$ev>$dep?'ev':'dep',$id,$set]);
        $setler=$pdo->prepare('SELECT kazanan FROM turnuva_mac_setleri WHERE turnuva_mac_id=?');$setler->execute([$id]);$g=[0,0];foreach($setler->fetchAll() as $x){if($x['kazanan']==='ev')$g[0]++;elseif($x['kazanan']==='dep')$g[1]++;}
        $macKazanan=null;if($set===SET_SAYISI && $g[0]!==$g[1])$macKazanan=$g[0]>$g[1]?$m['katilimci1_id']:$m['katilimci2_id'];
        $pdo->prepare('UPDATE turnuva_maclari SET puan1=?,puan2=?,durum=? WHERE id=?')->execute([$g[0],$g[1],$macKazanan?'oynandi':'planlandi',$id]);
        if($macKazanan){
            $e=$pdo->prepare('SELECT * FROM turnuva_eslesmeleri WHERE id=?');$e->execute([$m['eslesme_id']]);$es=$e->fetch();
            $pdo->prepare("UPDATE turnuva_eslesmeleri SET kazanan_id=?,durum='tamamlandi' WHERE id=?")->execute([$macKazanan,$es['id']]);
            $sonraki=$pdo->prepare('SELECT * FROM turnuva_eslesmeleri WHERE turnuva_id=? AND tur_no=? AND eslesme_no=?');$sonraki->execute([$m['turnuva_id'],$es['tur_no']+1,(int)ceil($es['eslesme_no']/2)]);$hedef=$sonraki->fetch();
            if($hedef){$alan=((int)$es['eslesme_no']%2)?'katilimci1_id':'katilimci2_id';$pdo->prepare("UPDATE turnuva_eslesmeleri SET $alan=? WHERE id=?")->execute([$macKazanan,$hedef['id']]);$pdo->prepare("UPDATE turnuva_maclari SET $alan=? WHERE eslesme_id=?")->execute([$macKazanan,$hedef['id']]);}
            else{$pdo->prepare("UPDATE turnuvalar SET durum='tamamlandi' WHERE id=?")->execute([$m['turnuva_id']]);}
        }
        $pdo->commit();
        flash_set('basari',$macKazanan?'Eşitlik bozuldu; kazanan otomatik olarak üst tura yerleştirildi.':'Eşitlik bozuldu; set galibi belirlendi.');
        redirect(BASE_URL.'/admin/turnuva-skor.php?id='.$id.'&set='.min(SET_SAYISI,$set+1));
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash_set('hata',$e->getMessage());redirect(BASE_URL.'/admin/turnuva-esitlik-bozma.php?id='.$id.'&set='.$set);}
}

$q=$pdo->prepare("SELECT * FROM esitlik_bozma_atislari WHERE kaynak='turnuva' AND turnuva_mac_id=? AND set_no=? ORDER BY tur_no");$q->execute([$id,$set]);$turlar=$q->fetchAll();
ob_start(); ?>
<div class="card">
    <div class="card-head"><h2><?= $set ?>. Set — Eşitlik Bozma</h2><span class="badge">Normal set: <?= (int)$normalSet['puan1'] ?> - <?= (int)$normalSet['puan2'] ?></span></div>
    <p class="muted">İsimsiz Eşitlik Bozma Sporcusu, her taraf adına 7 ok atar. Bu ek atışlar turnuva istatistiklerine veya averaja eklenmez.</p>
    <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="set" value="<?= $set ?>">
        <div class="grid-2"><label><?= e($m['ev']) ?> — Eşitlik Bozma Sporcusu<input name="ev_puan" type="number" min="0" max="21" required></label><label><?= e($m['dep']) ?> — Eşitlik Bozma Sporcusu<input name="dep_puan" type="number" min="0" max="21" required></label></div>
        <button class="btn btn-primary">Eşitliği Boz</button>
    </form>
    <?php foreach($turlar as $t): ?><p class="muted">Ek atış <?= (int)$t['tur_no'] ?>: <?= (int)$t['ev_puan'] ?> - <?= (int)$t['dep_puan'] ?><?= $t['ev_puan']==$t['dep_puan']?' · eşitlik sürüyor':'' ?></p><?php endforeach; ?>
</div>
<?php $admin_baslik='Turnuva Eşitlik Bozma';$admin_aktif='skor';$admin_icerik=ob_get_clean();require __DIR__.'/partials/layout.php'; ?>
