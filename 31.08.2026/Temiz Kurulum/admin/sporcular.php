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
zorunlu_rol('admin','yetkili');
$u = kullanici_bilgi();

$islem = $_GET['islem'] ?? 'liste';
$id    = (int)($_GET['id'] ?? 0);

function bireysel_lig_kayitlarini_guncelle($pdo, $sporcu_id, $secimler) {
    $pdo->prepare('DELETE FROM bireysel_lig_kayitlari WHERE sporcu_id=?')->execute([$sporcu_id]);
    $ins=$pdo->prepare('INSERT INTO bireysel_lig_kayitlari(lig_id,grup_id,sporcu_id) VALUES(?,?,?)');
    foreach ($secimler as $lig_id=>$grup_id) if ((int)$grup_id>0) $ins->execute([(int)$lig_id,(int)$grup_id,$sporcu_id]);
}

// Yetkili ise kısıtı uygula
$yetkili_takim_id = null;
if ($u['rol'] === 'yetkili') {
    $yt = $pdo->prepare("SELECT takim_id FROM yetkili WHERE user_id = ?");
    $yt->execute([$u['id']]);
    $yetkili_takim_id = (int)$yt->fetchColumn();
    if (!$yetkili_takim_id) {
        flash_set('hata','Hesabınıza henüz bir takım atanmamış. Yönetici ile iletişime geçin.');
        redirect(BASE_URL.'/admin/');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/sporcular.php'); }
    $ad          = trim($_POST['ad']         ?? '');
    $soyad       = trim($_POST['soyad']      ?? '');
    $tc_kimlik   = trim($_POST['tc_kimlik']  ?? '');
    $dogum_tarihi= $_POST['dogum_tarihi']     ?? null;
    $cinsiyet    = $_POST['cinsiyet']         ?? 'E';
    $kategori    = trim($_POST['kategori']   ?? 'Gençlik');
    $lisans_no   = trim($_POST['lisans_no']  ?? '');
    $telefon     = trim($_POST['telefon']    ?? '');
    $email       = trim($_POST['email']      ?? '');
    $adres       = trim($_POST['adres']      ?? '');
    $takim_id    = (int)($_POST['takim_id']  ?? 0);
    $bireysel_secimler = $_POST['bireysel_grup'] ?? [];

    // yetkili ise takım_id'yi zorla kendi takımı yap
    if ($yetkili_takim_id) $takim_id = $yetkili_takim_id;

    if ($ad === '' || $soyad === '') {
        flash_set('hata','Ad ve soyad zorunludur.');
        redirect(BASE_URL.'/admin/sporcular.php?islem='.($_POST['islem']??'ekle').'&id='.(int)($_POST['id']??0));
    }

    $foto = gorsel_yukle('foto', 'sporcular', $_POST['mevcut_foto'] ?? null);

    if ($_POST['islem'] === 'ekle') {
        $pdo->prepare("INSERT INTO sporcular
            (takim_id,ad,soyad,tc_kimlik,dogum_tarihi,cinsiyet,kategori,lisans_no,telefon,email,adres,foto)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$takim_id ?: null, $ad, $soyad, $tc_kimlik, $dogum_tarihi ?: null, $cinsiyet,
                       $kategori, $lisans_no, $telefon, $email, $adres, $foto]);
        bireysel_lig_kayitlarini_guncelle($pdo, (int)$pdo->lastInsertId(), $bireysel_secimler);
        flash_set('basari','Sporcu eklendi.');
    } elseif ($_POST['islem'] === 'duzenle') {
        // yetkili kendi takımı dışındaki sporcuyu düzenleyemesin
        $row_id = (int)$_POST['id'];
        if ($yetkili_takim_id) {
            $check = $pdo->prepare("SELECT takim_id FROM sporcular WHERE id = ?");
            $check->execute([$row_id]);
            $cur = $check->fetchColumn();
            if ((int)$cur !== $yetkili_takim_id) {
                flash_set('hata','Bu sporcuyu düzenleme yetkiniz yok.');
                redirect(BASE_URL.'/admin/sporcular.php');
            }
        }
        $pdo->prepare("UPDATE sporcular SET
            takim_id=?, ad=?, soyad=?, tc_kimlik=?, dogum_tarihi=?, cinsiyet=?,
            kategori=?, lisans_no=?, telefon=?, email=?, adres=?, foto=? WHERE id=?")
            ->execute([$takim_id ?: null, $ad, $soyad, $tc_kimlik, $dogum_tarihi ?: null, $cinsiyet,
                       $kategori, $lisans_no, $telefon, $email, $adres, $foto, $row_id]);
        bireysel_lig_kayitlarini_guncelle($pdo, $row_id, $bireysel_secimler);
        flash_set('basari','Sporcu güncellendi.');
    }
    redirect(BASE_URL.'/admin/sporcular.php');
}

if ($islem === 'sil' && $id > 0) {
    if ($u['rol'] !== 'admin') { flash_set('hata','Silme yetkisi sadece yöneticidedir.'); redirect(BASE_URL.'/admin/sporcular.php'); }
    $pdo->prepare("DELETE FROM sporcular WHERE id=?")->execute([$id]);
    flash_set('basari','Sporcu silindi.');
    redirect(BASE_URL.'/admin/sporcular.php');
}

$duzenlenen = null;
if ($islem === 'duzenle' && $id > 0) {
    $st = $pdo->prepare("SELECT * FROM sporcular WHERE id=?");
    $st->execute([$id]);
    $duzenlenen = $st->fetch();
    if ($yetkili_takim_id && $duzenlenen && (int)$duzenlenen['takim_id'] !== $yetkili_takim_id) {
        flash_set('hata','Bu sporcuyu düzenleme yetkiniz yok.');
        redirect(BASE_URL.'/admin/sporcular.php');
    }
}

$takimlar = $pdo->query("
    SELECT t.*, g.grup_adi FROM takimlar t JOIN gruplar g ON g.id=t.grup_id JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' ORDER BY l.lig_adi,g.grup_adi, t.takim_adi
")->fetchAll();
$bireysel_ligler=$pdo->query("SELECT * FROM ligler WHERE tur='bireysel' AND aktif=1 ORDER BY lig_adi")->fetchAll();
$mevcut_bireysel=[];
if($duzenlenen){$bk=$pdo->prepare('SELECT lig_id,grup_id FROM bireysel_lig_kayitlari WHERE sporcu_id=?');$bk->execute([$duzenlenen['id']]);foreach($bk->fetchAll() as $k)$mevcut_bireysel[$k['lig_id']]=$k['grup_id'];}

if ($yetkili_takim_id) {
    $sql = "SELECT s.*, t.takim_adi FROM sporcular s LEFT JOIN takimlar t ON t.id=s.takim_id
            WHERE s.takim_id = ? ORDER BY s.ad, s.soyad";
    $st = $pdo->prepare($sql);
    $st->execute([$yetkili_takim_id]);
} else {
    $sql = "SELECT s.*, t.takim_adi FROM sporcular s LEFT JOIN takimlar t ON t.id=s.takim_id
            ORDER BY s.toplam_puan DESC, s.atis_sayisi ASC";
    $st = $pdo->query($sql);
}
$liste = $st->fetchAll();

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
        <label>Doğum Tarihi<input type="date" name="dogum_tarihi" value="<?= e($duzenlenen['dogum_tarihi'] ?? '') ?>"></label>
        <label>Cinsiyet
            <select name="cinsiyet">
                <option value="E" <?= ($duzenlenen['cinsiyet']??'E')==='E'?'selected':'' ?>>Erkek</option>
                <option value="K" <?= ($duzenlenen['cinsiyet']??'')==='K'?'selected':'' ?>>Kadın</option>
            </select>
        </label>
    </div>
    <div class="grid-2">
        <label>Kategori
            <select name="kategori">
                <?php foreach (['Minik','Yıldız','Gençlik','Büyük','Veteran'] as $k): ?>
                    <option value="<?= $k ?>" <?= ($duzenlenen['kategori']??'Gençlik')===$k?'selected':'' ?>><?= $k ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Lisans No<input type="text" name="lisans_no" value="<?= e($duzenlenen['lisans_no'] ?? '') ?>"></label>
    </div>
    <div class="grid-2">
        <label>Takım
            <select name="takim_id" <?= $yetkili_takim_id?'disabled':'' ?>>
                <option value="">— Atanmadı —</option>
                <?php foreach ($takimlar as $tk): ?>
                    <option value="<?= (int)$tk['id'] ?>"
                        <?= ((int)($duzenlenen['takim_id'] ?? $yetkili_takim_id) === (int)$tk['id'])?'selected':'' ?>>
                        <?= e($tk['grup_adi'].' / '.$tk['takim_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($yetkili_takim_id): ?><input type="hidden" name="takim_id" value="<?= $yetkili_takim_id ?>"><?php endif; ?>
        </label>
        <label>Telefon<input type="text" name="telefon" value="<?= e($duzenlenen['telefon'] ?? '') ?>"></label>
    </div>
    <section class="card"><div class="card-head"><h2>Bireysel Lig Katılımı</h2></div><p class="muted">Takım ligindeki katılım takım seçimiyle belirlenir. Sporcu, aşağıdaki bireysel liglere aynı anda kaydedilebilir.</p><?php foreach($bireysel_ligler as $bl): $gs=$pdo->prepare('SELECT id,grup_adi FROM gruplar WHERE lig_id=? ORDER BY grup_adi');$gs->execute([$bl['id']]);$bgruplar=$gs->fetchAll(); ?><label><?= e($bl['lig_adi']) ?><select name="bireysel_grup[<?= (int)$bl['id'] ?>]"><option value="0">Bu ligde yarışmıyor</option><?php foreach($bgruplar as $bg): ?><option value="<?= (int)$bg['id'] ?>" <?= (int)($mevcut_bireysel[$bl['id']]??0)===(int)$bg['id']?'selected':'' ?>><?= e($bg['grup_adi']) ?></option><?php endforeach; ?></select></label><?php endforeach; ?></section>
    <div class="grid-2">
        <label>E-posta<input type="email" name="email" value="<?= e($duzenlenen['email'] ?? '') ?>"></label>
        <label>Foto<input type="file" name="foto" accept="image/*"></label>
    </div>
    <label>Adres<textarea name="adres" rows="2"><?= e($duzenlenen['adres'] ?? '') ?></textarea></label>
    <?php if ($duzenlenen && $duzenlenen['foto']): ?>
        <p>Mevcut foto: <img src="<?= UPLOAD_URL ?>/<?= e($duzenlenen['foto']) ?>" class="thumb"></p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn btn-primary"><?= $islem==='ekle'?'Ekle':'Güncelle' ?></button>
        <a href="<?= BASE_URL ?>/admin/sporcular.php" class="btn btn-outline">İptal</a>
    </div>
</form>
<?php else: ?>
<div class="toolbar">
    <a href="?islem=ekle" class="btn btn-primary">+ Yeni Sporcu</a>
    <?php if ($yetkili_takim_id): ?><span class="muted">Sadece kendi takımınızın sporcularını yönetebilirsiniz.</span><?php endif; ?>
</div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Foto</th><th>Ad Soyad</th><th>Takım</th><th>Kategori</th><th>Toplam Puan</th><th>Atış</th><th></th></tr></thead>
<tbody>
<?php foreach ($liste as $s): ?>
    <tr>
        <td><?php if ($s['foto']): ?><img src="<?= UPLOAD_URL ?>/<?= e($s['foto']) ?>" class="thumb-sm"><?php else: ?>-<?php endif; ?></td>
        <td><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$s['id'] ?>"><?= e($s['ad'].' '.$s['soyad']) ?></a></td>
        <td><?= e($s['takim_adi'] ?? '-') ?></td>
        <td><?= e($s['kategori'] ?? '-') ?></td>
        <td><?= (int)$s['toplam_puan'] ?></td>
        <td><?= (int)$s['atis_sayisi'] ?></td>
        <td class="actions">
            <a href="?islem=duzenle&id=<?= (int)$s['id'] ?>" class="btn btn-sm">Düzenle</a>
            <?php if ($u['rol']==='admin'): ?>
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
