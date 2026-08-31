<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
zorunlu_rol('admin','yonetici');

$ligId=(int)($_GET['lig_id']??$_POST['lig_id']??0);
$q=$pdo->prepare("SELECT * FROM ligler WHERE id=? AND tur='bireysel'");$q->execute([$ligId]);$lig=$q->fetch();
if(!$lig){flash_set('hata','Bireysel lig bulunamadı.');redirect(BASE_URL.'/admin/gruplar-ve-fikstur.php');}
$q=$pdo->prepare('SELECT id,grup_adi,bolge_adi,kategori_adi,atis_alani FROM gruplar WHERE lig_id=? ORDER BY bolge_adi,kategori_adi,grup_adi');$q->execute([$ligId]);$gruplar=$q->fetchAll();
$grupHaritasi=[];foreach($gruplar as $g)$grupHaritasi[(int)$g['id']]=$g;

function bireysel_hafta_adi($hafta){return (int)$hafta.'. Hafta';}
function bireysel_hafta_degeri($satir){
    if(!empty($satir['hafta_no']))return (int)$satir['hafta_no'];
    return preg_match('/(\d+)/',(string)($satir['aciklama']??''),$m)?(int)$m[1]:0;
}
function bireysel_grup_fiksturu_olustur($pdo,$ligId,$grup){
    $say=$pdo->prepare('SELECT COUNT(*) FROM bireysel_fikstur WHERE lig_id=? AND grup_id=?');$say->execute([$ligId,$grup['id']]);
    if((int)$say->fetchColumn()>0)return false;
    $ekle=$pdo->prepare('INSERT INTO bireysel_fikstur(lig_id,grup_id,hafta_no,tarih,yer,aciklama) VALUES(?,?,?,?,?,?)');
    for($hafta=1;$hafta<=10;$hafta++){
        $ekle->execute([$ligId,$grup['id'],$hafta,date('Y-m-d',strtotime('+'.($hafta-1).' week')),$grup['atis_alani']?:'Atış alanı belirtilmemiş',bireysel_hafta_adi($hafta)]);
    }
    return true;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['csrf']??'')){flash_set('hata','Güvenlik doğrulaması başarısız.');}
    else{
        $islem=$_POST['islem']??'';
        if($islem==='tumunu_olustur'){
            $olusan=0;foreach($gruplar as $grup)if(bireysel_grup_fiksturu_olustur($pdo,$ligId,$grup))$olusan++;
            flash_set($olusan?'basari':'hata',$olusan?$olusan.' grup için 10 haftalık fikstür oluşturuldu.':'Tüm grupların fikstürü zaten oluşturulmuş.');
        }elseif($islem==='tumunu_sil'){
            $pdo->prepare('DELETE FROM bireysel_fikstur WHERE lig_id=?')->execute([$ligId]);flash_set('basari','Bu lige ait tüm bireysel fikstürler silindi.');
        }elseif($islem==='grup_olustur'){
            $grup=$grupHaritasi[(int)($_POST['grup_id']??0)]??null;
            if(!$grup)flash_set('hata','Grup bulunamadı.');
            elseif(bireysel_grup_fiksturu_olustur($pdo,$ligId,$grup))flash_set('basari',e($grup['grup_adi']).' için 10 haftalık fikstür oluşturuldu.');
            else flash_set('hata','Bu grup için fikstür zaten var.');
        }elseif($islem==='grup_fikstur_sil'){
            $grupId=(int)($_POST['grup_id']??0);$pdo->prepare('DELETE FROM bireysel_fikstur WHERE lig_id=? AND grup_id=?')->execute([$ligId,$grupId]);flash_set('basari','Gruba ait fikstürler silindi.');
        }elseif($islem==='ekle'){
            $grup=$grupHaritasi[(int)($_POST['grup_id']??0)]??null;$hafta=(int)($_POST['hafta_no']??0);$tarih=$_POST['tarih']??'';$yer=trim($_POST['yer']??'');
            if(!$grup||$hafta<1||$hafta>10||!$tarih||!$yer)flash_set('hata','Grup, hafta, atış tarihi ve atış alanı zorunludur.');
            else{$pdo->prepare('INSERT INTO bireysel_fikstur(lig_id,grup_id,hafta_no,tarih,yer,aciklama) VALUES(?,?,?,?,?,?)')->execute([$ligId,$grup['id'],$hafta,$tarih,$yer,bireysel_hafta_adi($hafta)]);flash_set('basari','Manuel atış planı eklendi.');}
        }elseif($islem==='fikstur_duzenle'){
            $id=(int)($_POST['id']??0);$hafta=(int)($_POST['hafta_no']??0);$tarih=$_POST['tarih']??'';$yer=trim($_POST['yer']??'');
            if($hafta<1||$hafta>10||!$tarih||!$yer)flash_set('hata','Hafta, atış tarihi ve atış alanı zorunludur.');
            else{$pdo->prepare('UPDATE bireysel_fikstur SET hafta_no=?,tarih=?,yer=?,aciklama=? WHERE id=? AND lig_id=?')->execute([$hafta,$tarih,$yer,bireysel_hafta_adi($hafta),$id,$ligId]);flash_set('basari','Atış planı güncellendi.');}
        }elseif($islem==='fikstur_sil'){
            $pdo->prepare('DELETE FROM bireysel_fikstur WHERE id=? AND lig_id=?')->execute([(int)($_POST['id']??0),$ligId]);flash_set('basari','Atış planı silindi.');
        }
    }
    redirect(BASE_URL.'/admin/bireysel-fikstur.php?lig_id='.$ligId);
}

$duzenle=null;if(($_GET['islem']??'')==='duzenle'){$q=$pdo->prepare('SELECT * FROM bireysel_fikstur WHERE id=? AND lig_id=?');$q->execute([(int)($_GET['id']??0),$ligId]);$duzenle=$q->fetch();}
$q=$pdo->prepare('SELECT f.*,g.grup_adi,g.bolge_adi,g.kategori_adi FROM bireysel_fikstur f JOIN gruplar g ON g.id=f.grup_id WHERE f.lig_id=? ORDER BY COALESCE(f.hafta_no,99),f.tarih,g.bolge_adi,g.kategori_adi');$q->execute([$ligId]);$liste=$q->fetchAll();
ob_start();
?>
<div class="toolbar">
    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/gruplar-ve-fikstur.php">← Gruplar ve Fikstür</a>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="islem" value="tumunu_olustur"><button class="btn btn-primary">Tüm Gruplarda Oluştur</button></form>
    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/bireysel-fikstur.php?lig_id=<?= $ligId ?>&islem=ekle">Ekle</a>
    <form method="post" class="inline-form" onsubmit="return confirm('Bu lige ait tüm fikstürler silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="islem" value="tumunu_sil"><button class="btn btn-danger">Tümünü Sil</button></form>
</div>

<?php if(($_GET['islem']??'')==='ekle'): ?>
<section class="card"><div class="card-head"><h2>Manuel Atış Planı Ekle</h2></div>
    <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="islem" value="ekle">
        <label>Grup *<select name="grup_id" required><option value="">Seçin</option><?php foreach($gruplar as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['bolge_adi']?:$g['grup_adi']) ?> · <?= e($g['kategori_adi']) ?></option><?php endforeach; ?></select></label>
        <label>Hafta *<select name="hafta_no" required><?php for($h=1;$h<=10;$h++): ?><option value="<?= $h ?>"><?= bireysel_hafta_adi($h) ?></option><?php endfor; ?></select></label>
        <label>Atış Tarihi *<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
        <label>Atış Alanı *<input type="text" name="yer" required></label>
        <div><button class="btn btn-primary">Ekle</button> <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/bireysel-fikstur.php?lig_id=<?= $ligId ?>">Vazgeç</a></div>
    </form>
</section>
<?php endif; ?>

<?php if($duzenle): ?>
<section class="card"><div class="card-head"><h2>Atış Planını Düzenle</h2></div>
    <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="islem" value="fikstur_duzenle"><input type="hidden" name="id" value="<?= (int)$duzenle['id'] ?>">
        <label>Hafta *<select name="hafta_no" required><?php for($h=1;$h<=10;$h++): ?><option value="<?= $h ?>" <?= bireysel_hafta_degeri($duzenle)===$h?'selected':'' ?>><?= bireysel_hafta_adi($h) ?></option><?php endfor; ?></select></label>
        <label>Atış Tarihi *<input type="date" name="tarih" value="<?= e($duzenle['tarih']) ?>" required></label>
        <label>Atış Alanı *<input type="text" name="yer" value="<?= e($duzenle['yer']) ?>" required></label>
        <div><button class="btn btn-primary">Kaydet</button> <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/bireysel-fikstur.php?lig_id=<?= $ligId ?>">Vazgeç</a></div>
    </form>
</section>
<?php endif; ?>

<section class="card"><div class="card-head"><h2><?= e($lig['lig_adi']) ?> Grupları</h2><span class="badge">Bireysel Lig</span></div>
    <p class="muted">Her grup-kategori için 10 haftalık atış planı ayrı oluşturulur.</p>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Grup Adı</th><th>Kategori</th><th>İşlem</th></tr></thead><tbody>
    <?php foreach($gruplar as $g): ?><tr><td><?= e($g['bolge_adi']?:$g['grup_adi']) ?></td><td><?= e($g['kategori_adi']?:'—') ?></td><td class="actions">
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="grup_id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="islem" value="grup_olustur"><button class="btn btn-sm btn-primary">Oluştur</button></form>
        <a class="btn btn-sm" href="<?= BASE_URL ?>/admin/gruplar-ve-fikstur.php?islem=duzenle&id=<?= (int)$g['id'] ?>">Düzenle</a>
        <form method="post" class="inline-form" onsubmit="return confirm('Bu grubun fikstürleri silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="grup_id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="islem" value="grup_fikstur_sil"><button class="btn btn-sm btn-danger">Sil</button></form>
    </td></tr><?php endforeach; ?>
    <?php if(!$gruplar): ?><tr><td colspan="3" class="muted">Bu lig için henüz bireysel grup oluşturulmamış.</td></tr><?php endif; ?></tbody></table></div>
</section>

<section class="card"><div class="card-head"><h2>Oluşturulan Fikstürler</h2><span class="badge"><?= count($liste) ?> atış planı</span></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Grup Adı</th><th>Kategori</th><th>Hafta</th><th>Atış Tarihi</th><th>Atış Alanı</th><th>İşlem</th></tr></thead><tbody>
    <?php foreach($liste as $f): $hafta=bireysel_hafta_degeri($f); ?><tr><td><?= e($f['bolge_adi']?:$f['grup_adi']) ?></td><td><?= e($f['kategori_adi']?:'—') ?></td><td><?= $hafta?bireysel_hafta_adi($hafta):'—' ?></td><td><?= tr_tarih($f['tarih']) ?></td><td><?= e($f['yer']) ?></td><td class="actions"><a class="btn btn-sm" href="<?= BASE_URL ?>/admin/bireysel-fikstur.php?lig_id=<?= $ligId ?>&islem=duzenle&id=<?= (int)$f['id'] ?>">Düzenle</a><form method="post" class="inline-form" onsubmit="return confirm('Bu atış planı silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="lig_id" value="<?= $ligId ?>"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="islem" value="fikstur_sil"><button class="btn btn-sm btn-danger">Sil</button></form></td></tr><?php endforeach; ?>
    <?php if(!$liste): ?><tr><td colspan="6" class="muted">Henüz oluşturulmuş bireysel fikstür bulunmuyor.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php
$admin_baslik='Bireysel Lig Fikstürü';$admin_aktif='ligler';$admin_geri_url=BASE_URL.'/admin/gruplar-ve-fikstur.php';$admin_geri_ad='Gruplar ve Fikstür';$admin_icerik=ob_get_clean();require __DIR__.'/partials/layout.php';
