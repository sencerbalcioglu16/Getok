<?php
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';

$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT tm.*,t.turnuva_adi,t.tur,CASE WHEN t.tur='takim' THEN ev_t.takim_adi ELSE CONCAT(ev_s.ad,' ',ev_s.soyad) END ev,CASE WHEN t.tur='takim' THEN dep_t.takim_adi ELSE CONCAT(dep_s.ad,' ',dep_s.soyad) END dep FROM turnuva_maclari tm JOIN turnuvalar t ON t.id=tm.turnuva_id LEFT JOIN takimlar ev_t ON t.tur='takim' AND ev_t.id=tm.katilimci1_id LEFT JOIN takimlar dep_t ON t.tur='takim' AND dep_t.id=tm.katilimci2_id LEFT JOIN sporcular ev_s ON t.tur='bireysel' AND ev_s.id=tm.katilimci1_id LEFT JOIN sporcular dep_s ON t.tur='bireysel' AND dep_s.id=tm.katilimci2_id WHERE tm.id=?");
$q->execute([$id]);$m=$q->fetch();
if(!$m)exit('Turnuva karşılaşması bulunamadı.');

$q=$pdo->prepare('SELECT * FROM turnuva_mac_setleri WHERE turnuva_mac_id=? ORDER BY set_no');$q->execute([$id]);$setler=$q->fetchAll();
$q=$pdo->prepare("SELECT a.*,s.ad,s.soyad FROM turnuva_sporcu_set_atislari a JOIN sporcular s ON s.id=a.sporcu_id WHERE a.turnuva_mac_id=? ORDER BY a.set_no,a.taraf,s.ad");$q->execute([$id]);$atis=[];foreach($q as $a)$atis[$a['set_no']][$a['taraf']][]=$a;
$q=$pdo->prepare("SELECT * FROM esitlik_bozma_atislari WHERE kaynak='turnuva' AND turnuva_mac_id=? ORDER BY set_no,tur_no");$q->execute([$id]);$esitlikler=[];foreach($q as $x)$esitlikler[$x['set_no']][]=$x;
$evLink=$m['tur']==='takim'?BASE_URL.'/takim.php?id='.(int)$m['katilimci1_id']:BASE_URL.'/sporcu.php?id='.(int)$m['katilimci1_id'];
$depLink=$m['tur']==='takim'?BASE_URL.'/takim.php?id='.(int)$m['katilimci2_id']:BASE_URL.'/sporcu.php?id='.(int)$m['katilimci2_id'];
$sayfa_baslik=$m['turnuva_adi'];$aktif='turnuvalar';require __DIR__.'/includes/header.php';
?>
<main class="main-content">
    <section class="page-heading mac-ozet">
        <span>TURNUVA KARŞILAŞMASI · <?= (int)$m['tur_no'] ?>. TUR</span>
        <h1>
            <?php if($m['ev']): ?><a href="<?= $evLink ?>"><?= e($m['ev']) ?></a><?php else: ?>Rakip bekleniyor<?php endif; ?>
            <b><?= (int)$m['puan1'] ?> - <?= (int)$m['puan2'] ?></b>
            <?php if($m['dep']): ?><a href="<?= $depLink ?>"><?= e($m['dep']) ?></a><?php else: ?>Rakip bekleniyor<?php endif; ?>
        </h1>
        <p><?= e($m['turnuva_adi']) ?> · <?= $m['tur']==='takim'?'Takım Turnuvası':'Bireysel Turnuva' ?></p>
    </section>

    <section class="mac-skor-foyu">
        <?php if(!$setler): ?><div class="card muted">Bu karşılaşma için henüz set skoru girilmedi.</div><?php endif; ?>
        <?php foreach($setler as $st): ?>
            <?php $evAtis=$atis[$st['set_no']]['ev']??[];$depAtis=$atis[$st['set_no']]['dep']??[];$satirSayisi=max(count($evAtis),count($depAtis)); ?>
            <div class="set-foyu">
                <div class="set-foyu-baslik"><span><?= (int)$st['set_no'] ?>. Set</span><strong><?= (int)$st['puan1'] ?> - <?= (int)$st['puan2'] ?></strong></div>
                <div class="table-wrap">
                    <table class="set-skor-tablosu">
                        <thead><tr><th><?= e($m['ev']?:'Rakip 1') ?></th><th>Puan</th><th><?= e($m['dep']?:'Rakip 2') ?></th><th>Puan</th></tr></thead>
                        <tbody>
                        <?php for($i=0;$i<$satirSayisi;$i++): ?><?php $ev=$evAtis[$i]??null;$dep=$depAtis[$i]??null; ?>
                            <tr>
                                <td><?php if($ev): ?><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$ev['sporcu_id'] ?>"><?= e($ev['ad'].' '.$ev['soyad']) ?></a><?php else: ?>—<?php endif; ?></td>
                                <td class="set-puan"><?= $ev?(int)$ev['set_toplam']:'—' ?></td>
                                <td><?php if($dep): ?><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$dep['sporcu_id'] ?>"><?= e($dep['ad'].' '.$dep['soyad']) ?></a><?php else: ?>—<?php endif; ?></td>
                                <td class="set-puan"><?= $dep?(int)$dep['set_toplam']:'—' ?></td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach($esitlikler[$st['set_no']]??[] as $eb): ?><div class="esitlik-ozet"><strong>Eşitlik bozma</strong><span><?= (int)$eb['tur_no'] ?>. tur: <b><?= (int)$eb['ev_puan'] ?> - <?= (int)$eb['dep_puan'] ?></b><?= $eb['ev_puan']==$eb['dep_puan']?' · eşitlik sürüyor':'' ?></span></div><?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>
<?php require __DIR__.'/includes/sidebar.php';require __DIR__.'/includes/footer.php'; ?>
