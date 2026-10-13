<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/database.php'; require_once __DIR__.'/../includes/functions.php'; zorunlu_rol('admin','yonetici','hakem');
$id=(int)($_GET['id']??$_POST['id']??0);
$q=$pdo->prepare("SELECT tm.*,t.turnuva_adi,t.tur FROM turnuva_maclari tm JOIN turnuvalar t ON t.id=tm.turnuva_id WHERE tm.id=?"); $q->execute([$id]); $m=$q->fetch();
if(!$m || !$m['katilimci1_id'] || !$m['katilimci2_id']) { flash_set('hata','Bu eşleşmede iki rakip henüz belirlenmedi.'); redirect(BASE_URL.'/admin/mac-skor.php?kaynak=turnuva'); }
$bekleyenEsitlik=$pdo->prepare("SELECT set_no FROM turnuva_mac_setleri WHERE turnuva_mac_id=? AND puan1=puan2 AND kazanan IS NULL ORDER BY set_no LIMIT 1");$bekleyenEsitlik->execute([$id]);if($_SERVER['REQUEST_METHOD']==='GET' && ($esitlikSet=$bekleyenEsitlik->fetchColumn())) redirect(BASE_URL.'/admin/turnuva-esitlik-bozma.php?id='.$id.'&set='.(int)$esitlikSet);
$set=max(1,min(SET_SAYISI,(int)($_GET['set']??$_POST['set']??1)));
function turnuva_taraf_sporcular($pdo,$tur,$hedef){ if($tur==='takim'){$s=$pdo->prepare('SELECT id,ad,soyad FROM sporcular WHERE takim_id=? ORDER BY ad,soyad');$s->execute([$hedef]);return $s->fetchAll();}$s=$pdo->prepare('SELECT id,ad,soyad FROM sporcular WHERE id=?');$s->execute([$hedef]);return $s->fetchAll(); }
$ev=turnuva_taraf_sporcular($pdo,$m['tur'],$m['katilimci1_id']); $dep=turnuva_taraf_sporcular($pdo,$m['tur'],$m['katilimci2_id']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['csrf']??'')){flash_set('hata','Güvenlik doğrulaması başarısız.');redirect(BASE_URL.'/admin/turnuva-skor.php?id='.$id);}
 $esitlikBozma=false;$pdo->beginTransaction(); try{
  $pdo->prepare('DELETE FROM turnuva_sporcu_set_atislari WHERE turnuva_mac_id=? AND set_no=?')->execute([$id,$set]);$pdo->prepare('DELETE FROM turnuva_mac_setleri WHERE turnuva_mac_id=? AND set_no=?')->execute([$id,$set]);
  $in=$pdo->prepare('INSERT INTO turnuva_sporcu_set_atislari(turnuva_mac_id,set_no,taraf,sporcu_id,ok1,ok2,ok3,ok4,ok5,ok6,ok7,set_toplam) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$top=['ev'=>0,'dep'=>0];
  foreach(['ev'=>$ev,'dep'=>$dep] as $taraf=>$liste)foreach($liste as $sp){$p=max(0,min(21,(int)($_POST[$taraf][$sp['id']]??0)));$in->execute([$id,$set,$taraf,$sp['id'],$p,0,0,0,0,0,0,$p]);$top[$taraf]+=$p;}
  $esitlikBozma=$top['ev']===$top['dep'];
  $kazanan=$top['ev']===$top['dep']?null:($top['ev']>$top['dep']?'ev':'dep');$pdo->prepare('INSERT INTO turnuva_mac_setleri(turnuva_mac_id,set_no,puan1,puan2,tamamlandi,kazanan) VALUES(?,?,?,?,1,?)')->execute([$id,$set,$top['ev'],$top['dep'],$kazanan]);
  $s=$pdo->prepare('SELECT puan1,puan2,kazanan FROM turnuva_mac_setleri WHERE turnuva_mac_id=?');$s->execute([$id]);$g=[0,0];foreach($s->fetchAll() as $x){if($x['kazanan']==='ev')$g[0]++;elseif($x['kazanan']==='dep')$g[1]++;}
  $macKazanan=null;if($set===SET_SAYISI && $g[0]!==$g[1])$macKazanan=$g[0]>$g[1]?$m['katilimci1_id']:$m['katilimci2_id'];
  $pdo->prepare('UPDATE turnuva_maclari SET puan1=?,puan2=?,durum=? WHERE id=?')->execute([$g[0],$g[1],$macKazanan?'oynandi':'planlandi',$id]);
  if($macKazanan){
   $e=$pdo->prepare('SELECT * FROM turnuva_eslesmeleri WHERE id=?');$e->execute([$m['eslesme_id']]);$es=$e->fetch();$pdo->prepare("UPDATE turnuva_eslesmeleri SET kazanan_id=?,durum='tamamlandi' WHERE id=?")->execute([$macKazanan,$es['id']]);
   $sonraki=$pdo->prepare('SELECT * FROM turnuva_eslesmeleri WHERE turnuva_id=? AND tur_no=? AND eslesme_no=?');$sonraki->execute([$m['turnuva_id'],$es['tur_no']+1,(int)ceil($es['eslesme_no']/2)]);$hedef=$sonraki->fetch();
   if($hedef){$alan=((int)$es['eslesme_no']%2)?'katilimci1_id':'katilimci2_id';$pdo->prepare("UPDATE turnuva_eslesmeleri SET $alan=? WHERE id=?")->execute([$macKazanan,$hedef['id']]);$pdo->prepare("UPDATE turnuva_maclari SET $alan=? WHERE eslesme_id=?")->execute([$macKazanan,$hedef['id']]);}else{$pdo->prepare("UPDATE turnuvalar SET durum='tamamlandi' WHERE id=?")->execute([$m['turnuva_id']]);}
  }
  $pdo->commit();flash_set($esitlikBozma?'hata':'basari',$esitlikBozma?$set.'. set eşit bitti. Eşitlik bozma atışı gereklidir.':($macKazanan?'Kazanan otomatik olarak üst tura yerleştirildi.':$set.'. set kaydedildi.'));
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash_set('hata',$e->getMessage());}
 if($esitlikBozma) redirect(BASE_URL.'/admin/turnuva-esitlik-bozma.php?id='.$id.'&set='.$set);
 redirect(BASE_URL.'/admin/turnuva-skor.php?id='.$id.'&set='.min(SET_SAYISI,$set+1));
}
$mevcut=[];$s=$pdo->prepare('SELECT * FROM turnuva_sporcu_set_atislari WHERE turnuva_mac_id=? AND set_no=?');$s->execute([$id,$set]);foreach($s->fetchAll() as $x)$mevcut[$x['taraf']][$x['sporcu_id']]=$x['set_toplam'];$s=$pdo->prepare('SELECT * FROM turnuva_mac_setleri WHERE turnuva_mac_id=? ORDER BY set_no');$s->execute([$id]);$sets=$s->fetchAll();
ob_start(); ?>
<form method="post" class="form"><div class="card"><div class="card-head"><h2>🏆 <?= e($m['turnuva_adi']) ?></h2><span class="badge"><?= $set ?>. Set / <?= SET_SAYISI ?></span></div><p class="muted">Her sporcu için 7 okun toplam puanını tek alana girin. Bir sporcunun set puanı en fazla 21 olabilir. Eşit setlerde eşitlik bozma atışı istenir.</p><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="set" value="<?= $set ?>"><div class="grid-2"><?php foreach(['ev'=>$ev,'dep'=>$dep] as $taraf=>$liste): ?><section class="card"><h3><?= $taraf==='ev'?'Ev Sahibi':'Misafir' ?></h3><?php foreach($liste as $sp): ?><label class="grid-2"><span><?= e($sp['ad'].' '.$sp['soyad']) ?></span><input type="number" min="0" max="21" name="<?= $taraf ?>[<?= $sp['id'] ?>]" value="<?= (int)($mevcut[$taraf][$sp['id']]??0) ?>" placeholder="7 ok toplamı"></label><?php endforeach; ?></section><?php endforeach; ?></div><button class="btn btn-primary"><?= $set<SET_SAYISI?'Seti Kaydet ve Sonraki Sete Geç':'Son Seti Kaydet ve Maçı Tamamla' ?></button></div></form><section class="card"><h2>Set Durumu</h2><?php foreach($sets as $x): ?><p><?= $x['set_no'] ?>. Set: <?= $x['puan1'] ?> - <?= $x['puan2'] ?></p><?php endforeach; ?></section>
<?php $admin_baslik='Turnuva Skor Girişi';$admin_aktif='skor';$admin_icerik=ob_get_clean();require __DIR__.'/partials/layout.php'; ?>
