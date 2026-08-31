<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
zorunlu_rol('admin','yonetici');

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['csrf']??'')){flash_set('hata','Güvenlik doğrulaması başarısız.');redirect(BASE_URL.'/admin/ligler.php');}
    $islem=$_POST['islem']??'';
    try{
        if($islem==='kadro_penceresi'){
            $durum=($_POST['sporcu_kayit_acik']??'0')==='1'?'1':'0';
            $pdo->prepare("INSERT INTO sistem_ayarlari(anahtar,deger) VALUES('sporcu_kayit_acik',?) ON DUPLICATE KEY UPDATE deger=VALUES(deger)")->execute([$durum]);
            flash_set('basari',$durum==='1'?'Takım yetkilileri için sporcu ekleme ve çıkarma dönemi açıldı.':'Takım yetkilileri için sporcu ekleme ve çıkarma dönemi kapatıldı.');
        }elseif($islem==='sezon_olustur'){
            $sezonAdi=trim($_POST['sezon_adi']??'');
            if(!preg_match('/^(20\d{2})-(20\d{2})$/',$sezonAdi,$eslesme)||(int)$eslesme[2]!==((int)$eslesme[1]+1))throw new RuntimeException('Sezon biçimi 2026-2027 şeklinde olmalıdır.');
            $pdo->prepare('INSERT INTO sezonlar(sezon_adi) VALUES(?)')->execute([$sezonAdi]);flash_set('basari',$sezonAdi.' sezonu oluşturuldu.');
        }elseif($islem==='sezon_resmilestir'){
            if(empty($_POST['onay']))throw new RuntimeException('Resmileştirme onayı gereklidir.');
            sezon_sonuclarini_resmilestir($pdo,(int)($_POST['sezon_id']??0));flash_set('basari','Sezon sonuçları donduruldu ve Arşiv’e resmî olarak eklendi.');
        }elseif($islem==='lig_sil'){
            $ligId=(int)($_POST['lig_id']??0);$say=$pdo->prepare('SELECT COUNT(*) FROM gruplar WHERE lig_id=?');$say->execute([$ligId]);
            if((int)$say->fetchColumn()>0)throw new RuntimeException('Bu lige bağlı gruplar var. Önce Gruplar ve Fikstür ekranından grupları kaldırın.');
            $pdo->prepare('DELETE FROM ligler WHERE id=?')->execute([$ligId]);flash_set('basari','Lig silindi.');
        }elseif($islem==='lig_fikstur_sil'){
            $ligId=(int)($_POST['lig_id']??0);$tur=$pdo->prepare('SELECT tur FROM ligler WHERE id=?');$tur->execute([$ligId]);
            if($tur->fetchColumn()==='bireysel')$pdo->prepare('DELETE FROM bireysel_fikstur WHERE lig_id=?')->execute([$ligId]);
            else $pdo->prepare("DELETE m FROM maclar m JOIN gruplar g ON g.id=m.grup_id WHERE g.lig_id=? AND m.durum='planlandi'")->execute([$ligId]);
            flash_set('basari','Planlanan Lig Fikstürü silindi. Tamamlanan karşılaşmalar korunmuştur.');
        }else{
            $ad=trim($_POST['lig_adi']??'');$tur=$_POST['tur']??'takim';$sezonId=(int)($_POST['sezon_id']??0);$aciklama=trim($_POST['aciklama']??'');
            if($ad===''||!in_array($tur,['takim','bireysel'],true)||!$sezonId)throw new RuntimeException('Lig adı, türü ve sezon zorunludur.');
            $s=$pdo->prepare("SELECT * FROM sezonlar WHERE id=? AND durum='aktif'");$s->execute([$sezonId]);$sezon=$s->fetch();
            if(!$sezon)throw new RuntimeException('Yalnızca aktif bir sezona lig eklenebilir veya güncellenebilir.');
            if($islem==='lig_guncelle'){
                $ligId=(int)($_POST['lig_id']??0);$kontrol=$pdo->prepare('SELECT tur FROM ligler WHERE id=?');$kontrol->execute([$ligId]);$mevcutTur=$kontrol->fetchColumn();
                if(!$mevcutTur)throw new RuntimeException('Lig bulunamadı.');
                if($mevcutTur!==$tur)throw new RuntimeException('Mevcut bir ligin türü değiştirilemez.');
                $pdo->prepare('UPDATE ligler SET lig_adi=?,sezon=?,sezon_id=?,aciklama=? WHERE id=?')->execute([$ad,$sezon['sezon_adi'],$sezonId,$aciklama,$ligId]);
                flash_set('basari','Lig bilgileri güncellendi.');
            }else{
                $pdo->prepare('INSERT INTO ligler(lig_adi,tur,sezon,sezon_id,aciklama) VALUES(?,?,?,?,?)')->execute([$ad,$tur,$sezon['sezon_adi'],$sezonId,$aciklama]);
                flash_set('basari','Lig oluşturuldu. Sıradaki adım: Gruplar ve Fikstür ekranından grupları ekleyin.');
            }
        }
    }catch(Throwable $e){flash_set('hata',$e->getMessage());}
    redirect(BASE_URL.'/admin/ligler.php');
}

$sezonlar=$pdo->query('SELECT * FROM sezonlar ORDER BY sezon_adi DESC')->fetchAll();
$aktifSezonlar=array_values(array_filter($sezonlar,fn($s)=>$s['durum']==='aktif'));
$kadroAcik=ayar_al($pdo,'sporcu_kayit_acik','1')==='1';
$ligler=$pdo->query('SELECT l.*,s.durum sezon_durumu,COUNT(g.id) grup_sayisi FROM ligler l LEFT JOIN sezonlar s ON s.id=l.sezon_id LEFT JOIN gruplar g ON g.lig_id=l.id GROUP BY l.id ORDER BY l.created_at DESC')->fetchAll();
$duzenlenenLig=null;$duzenleId=(int)($_GET['duzenle']??0);if($duzenleId){$q=$pdo->prepare('SELECT * FROM ligler WHERE id=?');$q->execute([$duzenleId]);$duzenlenenLig=$q->fetch();if(!$duzenlenenLig){flash_set('hata','Lig bulunamadı.');redirect(BASE_URL.'/admin/ligler.php');}}
ob_start(); ?>
<div class="lig-sayfa-iki-kolon"><div class="lig-yonetim-ust">
    <section class="card">
        <div class="card-head"><h2>Sezon Yönetimi</h2><span class="badge">Kapanışta Arşiv’e kaydedilir</span></div>
        <form method="post" class="form sezon-ekle-form"><?= csrf_field() ?><input type="hidden" name="islem" value="sezon_olustur"><label>Yeni sezon<input name="sezon_adi" required placeholder="2026-2027" pattern="20[0-9]{2}-20[0-9]{2}"></label><button class="btn btn-primary">Sezon Oluştur</button></form>
        <div class="table-wrap"><table class="data-table compact"><thead><tr><th>Sezon</th><th>Durum</th><th>İşlem</th></tr></thead><tbody><?php foreach($sezonlar as $s): ?><tr><td><strong><?= e($s['sezon_adi']) ?></strong><?= $s['resmi_tarih']?'<br><small>'.tr_tarih_saat($s['resmi_tarih']).'</small>':'' ?></td><td><span class="badge <?= $s['durum']==='aktif'?'badge-success':'' ?>"><?= $s['durum']==='aktif'?'Aktif':'Arşivde' ?></span></td><td><?php if($s['durum']==='aktif'): ?><form method="post" onsubmit="return confirm('Bu sezonun sonuçları dondurulacak. Devam edilsin mi?')"><?= csrf_field() ?><input type="hidden" name="islem" value="sezon_resmilestir"><input type="hidden" name="sezon_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="onay" value="1"><button class="btn btn-sm">Resmileştir</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
    <section class="card">
        <div class="card-head"><h2><?= $duzenlenenLig?'Ligi Düzenle':'Yeni Lig Oluştur' ?></h2><span class="badge"><?= $duzenlenenLig?'Düzenleme':'1. adım' ?></span></div>
        <?php if(!$aktifSezonlar): ?><p class="empty-state">Önce aktif bir sezon oluşturun.</p><?php else: ?><p class="muted"><?= $duzenlenenLig?'Lig adı, sezonu ve açıklamasını güncelleyebilirsiniz.':'Grup ve fikstür işlemlerini sonraki adımda Gruplar ve Fikstür ekranından yapın.' ?></p><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="islem" value="<?= $duzenlenenLig?'lig_guncelle':'lig_olustur' ?>"><?php if($duzenlenenLig): ?><input type="hidden" name="lig_id" value="<?= (int)$duzenlenenLig['id'] ?>"><?php endif; ?><div class="grid-2"><label>Lig adı *<input name="lig_adi" required value="<?= e($duzenlenenLig['lig_adi']??'') ?>" placeholder="Bireysel Bölge Ligleri"></label><label>Lig türü *<?php if($duzenlenenLig): ?><input value="<?= $duzenlenenLig['tur']==='takim'?'Takım Ligi':'Bireysel Lig' ?>" disabled><input type="hidden" name="tur" value="<?= e($duzenlenenLig['tur']) ?>"><?php else: ?><select name="tur"><option value="takim">Takım Ligi</option><option value="bireysel">Bireysel Lig</option></select><?php endif; ?></label></div><div class="grid-2"><label>Sezon *<select name="sezon_id" required><?php foreach($aktifSezonlar as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($duzenlenenLig['sezon_id']??0)===(int)$s['id']?'selected':'' ?>><?= e($s['sezon_adi']) ?></option><?php endforeach; ?></select></label><label>Açıklama<textarea name="aciklama" rows="2"><?= e($duzenlenenLig['aciklama']??'') ?></textarea></label></div><div class="form-actions"><button class="btn btn-primary"><?= $duzenlenenLig?'Güncelle':'Ligi Oluştur' ?></button><?php if($duzenlenenLig): ?><a class="btn btn-outline" href="<?= BASE_URL ?>/admin/ligler.php">İptal</a><?php endif; ?></div></form><?php endif; ?>
    </section>
</div><div class="lig-alt-grid">
    <section class="card">
        <div class="card-head"><h2>Ligler</h2><a class="mini-link" href="<?= BASE_URL ?>/admin/gruplar-ve-fikstur.php">Gruplar ve Fikstür →</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Lig</th><th>Tür</th><th>Sezon</th><th>Grup</th><th>İşlem</th></tr></thead><tbody><?php foreach($ligler as $l): ?><tr><td><strong><?= e($l['lig_adi']) ?></strong><?php if($l['aciklama']): ?><br><small><?= e($l['aciklama']) ?></small><?php endif; ?></td><td><?= $l['tur']==='bireysel'?'Bireysel Lig':'Takım Ligi' ?></td><td><?= e($l['sezon']) ?></td><td><?= (int)$l['grup_sayisi'] ?></td><td class="actions"><a class="btn btn-sm" href="?duzenle=<?= (int)$l['id'] ?>">Düzenle</a><form method="post" class="inline-form" onsubmit="return confirm('Lig silinsin mi? Lig önce grup içermemelidir.')"><?= csrf_field() ?><input type="hidden" name="islem" value="lig_sil"><input type="hidden" name="lig_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-danger">Sil</button></form></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
    <aside class="card kadro-donemi-karti">
        <div class="card-head"><h2>Takım Kadro Dönemi</h2><span class="badge <?= $kadroAcik?'badge-success':'' ?>"><?= $kadroAcik?'Açık':'Kapalı' ?></span></div>
        <p class="muted">Takım yetkililerinin kendi kadrolarına sporcu ekleme, düzenleme ve çıkarma yetkisini yönetir.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="kadro_penceresi"><input type="hidden" name="sporcu_kayit_acik" value="<?= $kadroAcik?'0':'1' ?>"><button class="btn <?= $kadroAcik?'btn-danger':'btn-primary' ?>"><?= $kadroAcik?'Dönemi Kapat':'Dönemi Aç' ?></button></form>
    </aside>
</div></div>
<section class="card"><div class="card-head"><h2>Gruplar ve Fikstür</h2><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/gruplar-ve-fikstur.php">Grupları ve Lig Fikstürlerini Yönet →</a></div><p class="muted">Takım ve bireysel lig grupları ile Lig Fikstürleri bu alt sayfadan yönetilir. Turnuva fikstürleri yalnızca Turnuvalar menüsünde yer alır.</p></section>
<?php $admin_baslik='Lig Yönetimi';$admin_aktif='ligler';$admin_geri_url=BASE_URL.'/admin/';$admin_geri_ad='Yönetim Panosu';$admin_icerik=ob_get_clean();require __DIR__.'/partials/layout.php'; ?>
