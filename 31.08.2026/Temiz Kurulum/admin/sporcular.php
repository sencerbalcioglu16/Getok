<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/**
 * Sporcular yönetimi
 * - admin: tümünü görür ve düzenler
 * - yetkili: yalnızca kendi takımınınkini görür/düzenler
 * - sporcu: yalnızca kendi profilini günceller (profil.php üzerinden)
 */
zorunlu_rol('admin','yonetici','yetkili');
$u = kullanici_bilgi();

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

function bireysel_lig_kayitlarini_guncelle($pdo, $sporcu_id, $secimler, $kategori) {
    $dogrulanmis=[];$kontrol=$pdo->prepare("SELECT g.id FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE g.id=? AND l.id=? AND l.tur='bireysel' AND TRIM(g.kategori_adi)=TRIM(?)");
    foreach ($secimler as $lig_id=>$grup_id) if ((int)$grup_id>0) {$kontrol->execute([(int)$grup_id,(int)$lig_id,$kategori]);if(!$kontrol->fetchColumn())throw new RuntimeException('Sporcu yalnızca kendi kategorisiyle eşleşen bireysel bölge grubuna kaydedilebilir.');$dogrulanmis[]=[(int)$lig_id,(int)$grup_id];}
    $pdo->prepare('DELETE FROM bireysel_lig_kayitlari WHERE sporcu_id=?')->execute([$sporcu_id]);
    $ins=$pdo->prepare('INSERT INTO bireysel_lig_kayitlari(lig_id,grup_id,sporcu_id) VALUES(?,?,?)');
    foreach ($dogrulanmis as [$lig_id,$grup_id]) $ins->execute([$lig_id,$grup_id,$sporcu_id]);
}

function bireysel_turnuva_kayitlarini_dogrula($pdo, $sporcu_id, $secimler) {
    $secimler=array_values(array_unique(array_filter(array_map('intval',(array)$secimler))));
    if (!$secimler) return [];
    $yerler=implode(',',array_fill(0,count($secimler),'?'));
    $s=$pdo->prepare("SELECT id,turnuva_adi,kontenjan FROM turnuvalar WHERE tur='bireysel' AND durum='taslak' AND id IN ($yerler)");
    $s->execute($secimler);$turnuvalar=$s->fetchAll();
    if (count($turnuvalar)!==count($secimler)) throw new RuntimeException('Yalnızca eşleşmesi henüz oluşturulmamış bireysel turnuvalar seçilebilir.');
    $mevcut=$pdo->prepare('SELECT COUNT(*) FROM turnuva_katilimcilari WHERE turnuva_id=? AND hedef_id<>?');
    foreach($turnuvalar as $t){$mevcut->execute([(int)$t['id'],$sporcu_id]);if((int)$mevcut->fetchColumn()>=(int)$t['kontenjan'])throw new RuntimeException($t['turnuva_adi'].' kontenjanı dolu.');}
    return $secimler;
}

function bireysel_turnuva_kayitlarini_guncelle($pdo, $sporcu_id, $secimler) {
    $secimler=bireysel_turnuva_kayitlarini_dogrula($pdo,$sporcu_id,$secimler);
    $taslaklar=$pdo->query("SELECT id FROM turnuvalar WHERE tur='bireysel' AND durum='taslak'")->fetchAll(PDO::FETCH_COLUMN);
    if(!$taslaklar) return;
    $sil=$pdo->prepare('DELETE FROM turnuva_katilimcilari WHERE turnuva_id=? AND hedef_id=?');
    $sira=$pdo->prepare('SELECT COALESCE(MAX(sira),0)+1 FROM turnuva_katilimcilari WHERE turnuva_id=?');
    $ekle=$pdo->prepare('INSERT INTO turnuva_katilimcilari(turnuva_id,hedef_id,sira) VALUES(?,?,?)');
    $var=$pdo->prepare('SELECT id FROM turnuva_katilimcilari WHERE turnuva_id=? AND hedef_id=?');
    foreach($taslaklar as $turnuvaId){$turnuvaId=(int)$turnuvaId;if(!in_array($turnuvaId,$secimler,true)){$sil->execute([$turnuvaId,$sporcu_id]);continue;}$var->execute([$turnuvaId,$sporcu_id]);if(!$var->fetchColumn()){$sira->execute([$turnuvaId]);$ekle->execute([$turnuvaId,$sporcu_id,(int)$sira->fetchColumn()]);}}
}

function takim_organizasyon_kayitlarini_guncelle($pdo, $sporcu_id, $takim_id, $ligSecili, $turnuvaSecimleri) {
    $sil=$pdo->prepare("DELETE FROM sporcu_organizasyon_kayitlari WHERE sporcu_id=? AND tur IN ('takim_ligi','takim_turnuvasi')");
    $ekle=$pdo->prepare('INSERT INTO sporcu_organizasyon_kayitlari(sporcu_id,tur,organizasyon_id,grup_id) VALUES(?,?,?,?)');
    $secimler=array_values(array_unique(array_filter(array_map('intval',(array)$turnuvaSecimleri))));
    $uygun=[];
    if($secimler){if(!$takim_id)throw new RuntimeException('Takım turnuvası seçmek için önce sporcuya bir takım atayın.');$yerler=implode(',',array_fill(0,count($secimler),'?'));
        $q=$pdo->prepare("SELECT t.id FROM turnuvalar t JOIN turnuva_katilimcilari k ON k.turnuva_id=t.id WHERE t.tur='takim' AND k.hedef_id=? AND t.id IN ($yerler)");
        $q->execute(array_merge([$takim_id],$secimler));$uygun=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(count($uygun)!==count($secimler)) throw new RuntimeException('Yalnızca sporcunun takımının katıldığı takım turnuvaları seçilebilir.');}
    $sil->execute([$sporcu_id]);
    if(!$takim_id) return;
    if($ligSecili){$q=$pdo->prepare("SELECT l.id,g.id grup_id FROM takimlar t JOIN gruplar g ON g.id=t.grup_id JOIN ligler l ON l.id=g.lig_id WHERE t.id=? AND l.tur='takim'");$q->execute([$takim_id]);if($satir=$q->fetch())$ekle->execute([$sporcu_id,'takim_ligi',(int)$satir['id'],(int)$satir['grup_id']]);}
    foreach($uygun as $turnuvaId)$ekle->execute([$sporcu_id,'takim_turnuvasi',$turnuvaId,0]);
}

// Yetkili ise kısıtı uygula
$yetkili_takim_id = null;
$yetkili_takim_ids = [];
if ($u['rol'] === 'yetkili') {
    $yt = $pdo->prepare("SELECT DISTINCT takim_id FROM (SELECT takim_id FROM yetkili WHERE user_id=? AND takim_id IS NOT NULL UNION SELECT id FROM takimlar WHERE yonetici_user_id=?) x");
    $yt->execute([$u['id'],$u['id']]);
    $yetkili_takim_ids = array_map('intval', $yt->fetchAll(PDO::FETCH_COLUMN));
    $yetkili_takim_id = $yetkili_takim_ids[0] ?? null;
    if (!$yetkili_takim_ids) {
        flash_set('hata','Hesabınıza henüz bir takım atanmamış. Yönetici ile iletişime geçin.');
        redirect(BASE_URL.'/admin/profil.php');
    }
}
$yetkili_kadro_acik = $u['rol'] !== 'yetkili' || ayar_al($pdo, 'sporcu_kayit_acik', '1') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/sporcular.php'); }
    if (!$yetkili_kadro_acik) { flash_set('hata','Sporcu ekleme ve çıkarma dönemi şu anda kapalı.'); redirect(BASE_URL.'/admin/sporcular.php'); }
    $ad          = trim($_POST['ad']         ?? '');
    $soyad       = trim($_POST['soyad']      ?? '');
    $tc_kimlik   = trim($_POST['tc_kimlik']  ?? '');
    $dogum_tarihi= $_POST['dogum_tarihi']     ?? null;
    $cinsiyet    = $_POST['cinsiyet']         ?? 'E';
    $kategori    = sporcu_kategorisi_belirle($dogum_tarihi, $cinsiyet);
    $lisans_no   = trim($_POST['lisans_no']  ?? '');
    $telefon     = trim($_POST['telefon']    ?? '');
    $email       = trim($_POST['email']      ?? '');
    $adres       = trim($_POST['adres']      ?? '');
    $takim_id    = (int)($_POST['takim_id']  ?? 0);
    $bireysel_secimler = $_POST['bireysel_grup'] ?? [];
    $bireysel_turnuva_secimleri = $_POST['bireysel_turnuva'] ?? [];
    $takim_ligi_secili = !empty($_POST['takim_ligi_secili']);
    $takim_turnuva_secimleri = $_POST['takim_turnuva'] ?? [];

    if ($u['rol']==='yetkili' && !in_array($takim_id,$yetkili_takim_ids,true)) {
        flash_set('hata','Yalnızca kendi takımlarınız için sporcu kaydı yapabilirsiniz.');
        redirect(BASE_URL.'/admin/sporcular.php?islem='.($_POST['islem']??'ekle'));
    }

    if ($ad === '' || $soyad === '' || !$kategori) {
        flash_set('hata','Ad, soyad ve 8 yaşını doldurmuş bir sporcu için doğum tarihi zorunludur.');
        redirect(BASE_URL.'/admin/sporcular.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }

    $grupKontrol=$pdo->prepare("SELECT g.id FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE g.id=? AND l.id=? AND l.tur='bireysel' AND TRIM(g.kategori_adi)=TRIM(?)");
    foreach($bireysel_secimler as $ligSecim=>$grupSecim) if((int)$grupSecim>0){$grupKontrol->execute([(int)$grupSecim,(int)$ligSecim,$kategori]);if(!$grupKontrol->fetchColumn()){flash_set('hata','Sporcu yalnızca kendi kategorisiyle eşleşen bireysel bölge grubuna kaydedilebilir.');redirect(BASE_URL.'/admin/sporcular.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));}}
    try { bireysel_turnuva_kayitlarini_dogrula($pdo, (int)($_POST['id'] ?? 0), $bireysel_turnuva_secimleri); }
    catch (Throwable $e) { flash_set('hata',$e->getMessage()); redirect(BASE_URL.'/admin/sporcular.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0)); }
    $foto = gorsel_yukle('foto', 'sporcular', $_POST['mevcut_foto'] ?? null);

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO sporcular
            (takim_id,ad,soyad,tc_kimlik,dogum_tarihi,cinsiyet,kategori,lisans_no,telefon,email,adres,foto)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$takim_id ?: null, $ad, $soyad, $tc_kimlik, $dogum_tarihi ?: null, $cinsiyet,
                       $kategori, $lisans_no, $telefon, $email, $adres, $foto]);
        $yeni_sporcu_id=(int)$pdo->lastInsertId();
        bireysel_lig_kayitlarini_guncelle($pdo, $yeni_sporcu_id, $bireysel_secimler, $kategori);
        bireysel_turnuva_kayitlarini_guncelle($pdo, $yeni_sporcu_id, $bireysel_turnuva_secimleri);
        takim_organizasyon_kayitlarini_guncelle($pdo, $yeni_sporcu_id, $takim_id, $takim_ligi_secili, $takim_turnuva_secimleri);
        flash_set('basari','Sporcu eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        // yetkili kendi takımı dışındaki sporcuyu düzenleyemesin
        $row_id = (int)$_POST['id'];
        if ($u['rol']==='yetkili') {
            $check = $pdo->prepare("SELECT takim_id FROM sporcular WHERE id = ?");
            $check->execute([$row_id]);
            $cur = $check->fetchColumn();
            if (!in_array((int)$cur,$yetkili_takim_ids,true)) {
                flash_set('hata','Bu sporcuyu düzenleme yetkiniz yok.');
                redirect(BASE_URL.'/admin/sporcular.php');
            }
        }
        $pdo->prepare("UPDATE sporcular SET
            takim_id=?, ad=?, soyad=?, tc_kimlik=?, dogum_tarihi=?, cinsiyet=?,
            kategori=?, lisans_no=?, telefon=?, email=?, adres=?, foto=? WHERE id=?")
            ->execute([$takim_id ?: null, $ad, $soyad, $tc_kimlik, $dogum_tarihi ?: null, $cinsiyet,
                       $kategori, $lisans_no, $telefon, $email, $adres, $foto, $row_id]);
        bireysel_lig_kayitlarini_guncelle($pdo, $row_id, $bireysel_secimler, $kategori);
        bireysel_turnuva_kayitlarini_guncelle($pdo, $row_id, $bireysel_turnuva_secimleri);
        takim_organizasyon_kayitlarini_guncelle($pdo, $row_id, $takim_id, $takim_ligi_secili, $takim_turnuva_secimleri);
        flash_set('basari','Sporcu güncellendi.');
    }
    redirect(BASE_URL.'/admin/sporcular.php');
}

if ($islem === 'takimdan_cikar' && $id > 0 && $yetkili_takim_ids) {
    if (!$yetkili_kadro_acik) { flash_set('hata','Sporcu ekleme ve çıkarma dönemi şu anda kapalı.'); }
    else { $yerler=implode(',',array_fill(0,count($yetkili_takim_ids),'?')); $s=$pdo->prepare("UPDATE sporcular SET takim_id=NULL WHERE id=? AND takim_id IN ($yerler)"); $s->execute(array_merge([$id],$yetkili_takim_ids)); flash_set('basari','Sporcu takım kadrosundan çıkarıldı.'); }
    redirect(BASE_URL.'/admin/sporcular.php');
}
if ($islem === 'sil' && $id > 0) {
    if (!in_array($u['rol'], ['admin','yonetici'], true)) { flash_set('hata','Silme yetkisi yalnızca yönetici rollerindedir.'); redirect(BASE_URL.'/admin/sporcular.php'); }
    $pdo->prepare("DELETE FROM sporcular WHERE id=?")->execute([$id]);
    flash_set('basari','Sporcu silindi.');
    redirect(BASE_URL.'/admin/sporcular.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM sporcular WHERE id=?");
    $st->execute([$id]);
    $duzenlenen = $st->fetch();
    if ($u['rol']==='yetkili' && $duzenlenen && !in_array((int)$duzenlenen['takim_id'],$yetkili_takim_ids,true)) {
        flash_set('hata','Bu sporcuyu düzenleme yetkiniz yok.');
        redirect(BASE_URL.'/admin/sporcular.php');
    }
}

$takimlar = $pdo->query("
    SELECT t.*, g.grup_adi FROM takimlar t JOIN gruplar g ON g.id=t.grup_id JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' ORDER BY l.lig_adi,g.grup_adi, t.takim_adi
")->fetchAll();
$bireysel_ligler=$pdo->query("SELECT * FROM ligler WHERE tur='bireysel' AND aktif=1 ORDER BY lig_adi")->fetchAll();
$bireysel_gruplar=[];
if ($bireysel_ligler) {
    $grupSt=$pdo->prepare('SELECT id,lig_id,grup_adi,bolge_adi,kategori_adi FROM gruplar WHERE lig_id=? ORDER BY bolge_adi,grup_adi');
    foreach ($bireysel_ligler as $bl) { $grupSt->execute([$bl['id']]); $bireysel_gruplar[$bl['id']]=$grupSt->fetchAll(); }
}
$mevcut_bireysel=[];
if($duzenlenen){$bk=$pdo->prepare('SELECT lig_id,grup_id FROM bireysel_lig_kayitlari WHERE sporcu_id=?');$bk->execute([$duzenlenen['id']]);foreach($bk->fetchAll() as $k)$mevcut_bireysel[$k['lig_id']]=$k['grup_id'];}
$bireysel_turnuvalar=$pdo->query("SELECT id,turnuva_adi,kontenjan,durum FROM turnuvalar WHERE tur='bireysel' ORDER BY FIELD(durum,'taslak','eslesme_hazir','tamamlandi'),turnuva_adi")->fetchAll();
$mevcut_bireysel_turnuvalar=[];
if($duzenlenen){$tk=$pdo->prepare('SELECT turnuva_id FROM turnuva_katilimcilari WHERE hedef_id=?');$tk->execute([$duzenlenen['id']]);$mevcut_bireysel_turnuvalar=array_flip(array_map('intval',$tk->fetchAll(PDO::FETCH_COLUMN)));}
$takim_turnuva_adaylari=$pdo->query("SELECT t.id,t.turnuva_adi,t.durum,GROUP_CONCAT(k.hedef_id ORDER BY k.hedef_id) takimlar FROM turnuvalar t JOIN turnuva_katilimcilari k ON k.turnuva_id=t.id WHERE t.tur='takim' GROUP BY t.id ORDER BY t.turnuva_adi")->fetchAll();
$mevcut_takim_ligi=false;$mevcut_takim_turnuvalar=[];
if($duzenlenen){$ok=$pdo->prepare("SELECT tur,organizasyon_id FROM sporcu_organizasyon_kayitlari WHERE sporcu_id=? AND tur IN ('takim_ligi','takim_turnuvasi')");$ok->execute([$duzenlenen['id']]);foreach($ok->fetchAll() as $k){if($k['tur']==='takim_ligi')$mevcut_takim_ligi=true;else $mevcut_takim_turnuvalar[(int)$k['organizasyon_id']]=true;}}

if ($u['rol']==='yetkili') {
    $sql = "SELECT s.*, t.takim_adi FROM sporcular s LEFT JOIN takimlar t ON t.id=s.takim_id
            WHERE s.takim_id IN (".implode(',',array_fill(0,count($yetkili_takim_ids),'?')).") ORDER BY s.ad, s.soyad";
    $st = $pdo->prepare($sql);
    $st->execute($yetkili_takim_ids);
} else {
    $sql = "SELECT s.*, t.takim_adi FROM sporcular s LEFT JOIN takimlar t ON t.id=s.takim_id
            ORDER BY s.toplam_puan DESC, s.atis_sayisi ASC";
    $st = $pdo->query($sql);
}
$liste = $st->fetchAll();
$formKategori=sporcu_kategorisi_belirle($duzenlenen['dogum_tarihi']??null,$duzenlenen['cinsiyet']??'E');

ob_start();
?>
<?php if ($islem === 'ekle' || $islem === 'duzenle'): ?>
<form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="<?= e($islem) ?>">
    <?php if ($duzenlenen): ?><input type="hidden" name="id" value="<?= (int)$duzenlenen['id'] ?>">
        <input type="hidden" name="mevcut_foto" value="<?= e($duzenlenen['foto'] ?? '') ?>"><?php endif; ?>
    <div class="grid-2">
        <label>Ad *<input type="text" name="ad" required value="<?= e($duzenlenen['ad'] ?? '') ?>"></label>
        <label>Soyad *<input type="text" name="soyad" required value="<?= e($duzenlenen['soyad'] ?? '') ?>"></label>
    </div>
    <div class="grid-3">
        <label>TC Kimlik<input type="text" name="tc_kimlik" maxlength="11" value="<?= e($duzenlenen['tc_kimlik'] ?? '') ?>"></label>
        <label>Doğum Tarihi *<input type="date" name="dogum_tarihi" required value="<?= e($duzenlenen['dogum_tarihi'] ?? '') ?>"></label>
        <label>Cinsiyet
            <select name="cinsiyet">
                <option value="E" <?= ($duzenlenen['cinsiyet']??'E')==='E'?'selected':'' ?>>Erkek</option>
                <option value="K" <?= ($duzenlenen['cinsiyet']??'')==='K'?'selected':'' ?>>Kadın</option>
            </select>
        </label>
    </div>
    <div class="grid-2">
        <label>Kategori<input id="kategoriGoster" readonly value="<?= e($formKategori?:'Doğum tarihi ve cinsiyete göre belirlenir') ?>"><input type="hidden" name="kategori" value="<?= e($formKategori??'') ?>"></label>
        <label>Lisans No<input type="text" name="lisans_no" value="<?= e($duzenlenen['lisans_no'] ?? '') ?>"></label>
    </div>
    <div class="grid-2">
        <label>Takım
            <select name="takim_id" required>
                <option value="">— Atanmadı —</option>
                <?php foreach ($takimlar as $tk): ?>
                    <?php if ($u['rol']==='yetkili' && !in_array((int)$tk['id'],$yetkili_takim_ids,true)) continue; ?>
                    <option value="<?= (int)$tk['id'] ?>"
                        <?= ((int)($duzenlenen['takim_id'] ?? 0) === (int)$tk['id'])?'selected':'' ?>>
                        <?= e($tk['grup_adi'].' / '.$tk['takim_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Telefon<input type="text" name="telefon" value="<?= e($duzenlenen['telefon'] ?? '') ?>"></label>
    </div>
    <section class="organizasyon-secim"><label class="org-check" id="takimLigSecim"><input type="checkbox" name="takim_ligi_secili" <?= $mevcut_takim_ligi?'checked':'' ?>><span>Takım Ligi</span></label><small class="takim-lig-durum">Takım seçildiğinde uygun olur</small></section>
    <section class="organizasyon-secim"><label class="org-check"><input type="checkbox" class="org-detay-toggle" data-hedef="bireyselLigDetay" <?= $mevcut_bireysel?'checked':'' ?>><span>Bireysel Lig</span></label><div id="bireyselLigDetay" class="org-detay"><?php foreach($bireysel_ligler as $bl): $bgruplar=$bireysel_gruplar[$bl['id']]??[]; ?><select name="bireysel_grup[<?= (int)$bl['id'] ?>]" class="bireysel-grup-secimi"><option value="0">Grup seçin</option><?php foreach($bgruplar as $bg): ?><option value="<?= (int)$bg['id'] ?>" data-kategori="<?= e($bg['kategori_adi']??'') ?>" <?= (int)($mevcut_bireysel[$bl['id']]??0)===(int)$bg['id']?'selected':'' ?>><?= e(($bg['bolge_adi']?:$bg['grup_adi']).' · '.($bg['kategori_adi']?:'')) ?></option><?php endforeach; ?></select><?php endforeach; ?></div></section>
    <section class="organizasyon-secim"><label class="org-check"><input type="checkbox" class="org-detay-toggle" data-hedef="bireyselTurnuvaDetay" <?= $mevcut_bireysel_turnuvalar?'checked':'' ?>><span>Bireysel Turnuva</span></label><div id="bireyselTurnuvaDetay" class="org-detay"><?php foreach($bireysel_turnuvalar as $turnuva): $kilitli=$turnuva['durum']!=='taslak'; ?><label class="org-mini <?= $kilitli?'is-disabled':'' ?>"><input type="checkbox" name="bireysel_turnuva[]" value="<?= (int)$turnuva['id'] ?>" <?= isset($mevcut_bireysel_turnuvalar[(int)$turnuva['id']])?'checked':'' ?> <?= $kilitli?'disabled':'' ?>><span><?= e($turnuva['turnuva_adi']) ?></span></label><?php endforeach; ?></div></section>
    <section class="organizasyon-secim"><label class="org-check"><input type="checkbox" class="org-detay-toggle" data-hedef="takimTurnuvaDetay" <?= $mevcut_takim_turnuvalar?'checked':'' ?>><span>Takım Turnuvası</span></label><div id="takimTurnuvaDetay" class="org-detay"><?php foreach($takim_turnuva_adaylari as $turnuva): ?><label class="org-mini takim-turnuva-secim" data-takimlar="<?= e($turnuva['takimlar']) ?>"><input type="checkbox" name="takim_turnuva[]" value="<?= (int)$turnuva['id'] ?>" <?= isset($mevcut_takim_turnuvalar[(int)$turnuva['id']])?'checked':'' ?>><span><?= e($turnuva['turnuva_adi']) ?></span></label><?php endforeach; ?></div></section>
    <div class="grid-2">
        <label>E-posta<input type="email" name="email" value="<?= e($duzenlenen['email'] ?? '') ?>"></label>
        <label>Foto<input type="file" name="foto" accept="image/*"></label>
    </div>
    <label>Adres<textarea name="adres" rows="2"><?= e($duzenlenen['adres'] ?? '') ?></textarea></label>
    <?php if ($duzenlenen && $duzenlenen['foto']): ?>
        <p>Mevcut foto: <img src="<?= UPLOAD_URL ?>/sporcular/<?= e(basename($duzenlenen['foto'])) ?>" class="thumb"></p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/sporcular.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<script>document.addEventListener('DOMContentLoaded',()=>{const kategori=document.querySelector('input[name="kategori"]'),kategoriGoster=document.getElementById('kategoriGoster'),dogum=document.querySelector('input[name="dogum_tarihi"]'),cinsiyet=document.querySelector('select[name="cinsiyet"]'),takim=document.querySelector('select[name="takim_id"]'),takimLig=document.querySelector('#takimLigSecim input'),takimLigDurum=document.querySelector('.takim-lig-durum');const filtrele=()=>{document.querySelectorAll('.bireysel-grup-secimi').forEach(sec=>{let uygun=0;[...sec.options].forEach(o=>{if(!o.value)return;const gorunur=o.dataset.kategori===kategori.value;o.hidden=!gorunur;o.disabled=!gorunur;if(gorunur)uygun++;if(!gorunur&&o.selected)sec.value='0'});sec.disabled=uygun===0})};const kategoriGuncelle=()=>{if(!dogum.value){kategori.value='';kategoriGoster.value='Doğum tarihi ve cinsiyete göre belirlenir';filtrele();return}const d=new Date(dogum.value),bugun=new Date(),yas=bugun.getFullYear()-d.getFullYear()-((bugun.getMonth()<d.getMonth()||(bugun.getMonth()===d.getMonth()&&bugun.getDate()<d.getDate()))?1:0);let k=yas>=18?(cinsiyet.value==='K'?'Kadınlar':'Yetişkin'):yas>=16?'Gençler':yas>=12?'Yıldızlar':yas>=8?'Minikler':'';kategori.value=k;kategoriGoster.value=k||'8 yaş altı sporcu kaydedilemez';filtrele()};const takimlariGuncelle=()=>{const id=takim?.value||'';if(takimLig){takimLig.disabled=!id;if(!id)takimLig.checked=false}if(takimLigDurum)takimLigDurum.textContent=id?'Seçilen takımın ligi seçilebilir':'Önce takım seçin';document.querySelectorAll('.takim-turnuva-secim').forEach(kart=>{const uygun=id&&kart.dataset.takimlar.split(',').includes(id),girdi=kart.querySelector('input');girdi.disabled=!uygun;if(!uygun)girdi.checked=false;kart.classList.toggle('is-disabled',!uygun)})};const detaylariGuncelle=()=>document.querySelectorAll('.org-detay-toggle').forEach(t=>{const alan=document.getElementById(t.dataset.hedef);alan.hidden=!t.checked;alan.querySelectorAll('input,select').forEach(i=>i.disabled=!t.checked)});dogum.addEventListener('change',kategoriGuncelle);cinsiyet.addEventListener('change',kategoriGuncelle);takim?.addEventListener('change',takimlariGuncelle);document.querySelectorAll('.org-detay-toggle').forEach(t=>t.addEventListener('change',detaylariGuncelle));kategoriGuncelle();takimlariGuncelle();detaylariGuncelle()});</script>
<?php else: ?>
<div class="toolbar">
    <?php if (!$yetkili_takim_id || $yetkili_kadro_acik): ?><a href="?islem=ekle" class="btn btn-primary">+ Yeni Sporcu</a><?php endif; ?>
    <?php if ($yetkili_takim_id): ?><span class="muted"><?= $yetkili_kadro_acik?'Sadece kendi takımınızın sporcularını yönetebilirsiniz.':'Kadro dönemi kapalı: ekleme, düzenleme ve çıkarma yapılamaz.' ?></span><?php endif; ?>
</div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Foto</th><th>Ad Soyad</th><th>Takım</th><th>Kategori</th><th>Toplam Puan</th><th>Atış</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $s): ?>
    <tr>
        <td><?php if ($s['foto']): ?><img src="<?= UPLOAD_URL ?>/sporcular/<?= e(basename($s['foto'])) ?>" class="thumb-sm"><?php else: ?>-<?php endif; ?></td>
        <td><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$s['id'] ?>"><?= e($s['ad'].' '.$s['soyad']) ?></a></td>
        <td><?= e($s['takim_adi'] ?? '-') ?></td>
        <td><?= e($s['kategori'] ?? '-') ?></td>
        <td><?= (int)$s['toplam_puan'] ?></td>
        <td><?= (int)$s['atis_sayisi'] ?></td>
        <td class="actions">
            <?php if (!$yetkili_takim_id || $yetkili_kadro_acik): ?><a href="?islem=duzenle&id=<?= (int)$s['id'] ?>" class="btn btn-sm">Düzenle</a><?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/sporcu-hesap.php?sporcu_id=<?= (int)$s['id'] ?>" class="btn btn-sm">Giriş Bilgileri</a>
            <?php if ($yetkili_takim_id && $yetkili_kadro_acik): ?><a href="?islem=takimdan_cikar&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sporcu takım kadrosundan çıkarılsın mı?')">Takımdan Çıkar</a><?php endif; ?>
            <?php if (in_array($u['rol'], ['admin','yonetici'], true)): ?>
            <a href="?islem=sil&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silinsin mi?')">Sil</a>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$admin_baslik = 'Sporcular';
$admin_aktif  = 'sporcular';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
