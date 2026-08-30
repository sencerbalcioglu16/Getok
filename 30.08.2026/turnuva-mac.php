<?php
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';
$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT tm.*,t.turnuva_adi,t.tur,CASE WHEN t.tur='takim' THEN ev_t.takim_adi ELSE CONCAT(ev_s.ad,' ',ev_s.soyad) END ev,CASE WHEN t.tur='takim' THEN dep_t.takim_adi ELSE CONCAT(dep_s.ad,' ',dep_s.soyad) END dep FROM turnuva_maclari tm JOIN turnuvalar t ON t.id=tm.turnuva_id LEFT JOIN takimlar ev_t ON ev_t.id=tm.katilimci1_id LEFT JOIN takimlar dep_t ON dep_t.id=tm.katilimci2_id LEFT JOIN sporcular ev_s ON ev_s.id=tm.katilimci1_id LEFT JOIN sporcular dep_s ON dep_s.id=tm.katilimci2_id WHERE tm.id=?");
$q->execute([$id]); $m=$q->fetch();
if(!$m) exit('Turnuva karşılaşması bulunamadı.');
$q=$pdo->prepare('SELECT * FROM turnuva_mac_setleri WHERE turnuva_mac_id=? ORDER BY set_no');
$q->execute([$id]); $setler=$q->fetchAll();
$q=$pdo->prepare("SELECT a.*,s.ad,s.soyad FROM turnuva_sporcu_set_atislari a JOIN sporcular s ON s.id=a.sporcu_id WHERE a.turnuva_mac_id=? ORDER BY a.set_no,a.taraf,s.ad");
$q->execute([$id]); $atis=[]; foreach($q as $a) $atis[$a['set_no']][$a['taraf']][]=$a;
$sayfa_baslik=$m['turnuva_adi']; $aktif='turnuvalar'; require __DIR__.'/includes/header.php';
?>
<main class="main-content"><section class="page-heading"><span>TURNUVA KARŞILAŞMASI · <?= $m['tur_no'] ?>. TUR</span><h1><?= e($m['ev'] ?: 'Rakip bekleniyor') ?> <small>—</small> <?= e($m['dep'] ?: 'Rakip bekleniyor') ?></h1><p><?= e($m['turnuva_adi']) ?> · Set skoru: <?= $m['puan1'] ?> - <?= $m['puan2'] ?></p></section><?php if(!$setler): ?><section class="card center muted">Bu karşılaşma için henüz skor girişi yapılmadı.</section><?php endif; ?><?php foreach($setler as $s): ?><section class="card"><h2><?= $s['set_no'] ?>. Set: <?= $s['puan1'] ?> - <?= $s['puan2'] ?></h2><div class="grid-2"><div><strong><?= e($m['ev']) ?></strong><?php foreach($atis[$s['set_no']]['ev']??[] as $a): ?><p><?= e($a['ad'].' '.$a['soyad']) ?>: <b><?= $a['set_toplam'] ?></b></p><?php endforeach; ?></div><div><strong><?= e($m['dep']) ?></strong><?php foreach($atis[$s['set_no']]['dep']??[] as $a): ?><p><?= e($a['ad'].' '.$a['soyad']) ?>: <b><?= $a['set_toplam'] ?></b></p><?php endforeach; ?></div></div></section><?php endforeach; ?></main>
<?php require __DIR__.'/includes/sidebar.php'; require __DIR__.'/includes/footer.php'; ?>
