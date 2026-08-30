<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
zorunlu_rol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','Güvenlik doğrulaması başarısız.'); redirect(BASE_URL.'/admin/ligler.php'); }
    $islem = $_POST['islem'] ?? 'lig_olustur';
    try {
        if ($islem === 'kadro_penceresi') {
            $durum = ($_POST['sporcu_kayit_acik'] ?? '0') === '1' ? '1' : '0';
            $pdo->prepare("INSERT INTO sistem_ayarlari(anahtar,deger) VALUES('sporcu_kayit_acik',?) ON DUPLICATE KEY UPDATE deger=VALUES(deger)")->execute([$durum]);
            flash_set('basari', $durum==='1' ? 'Takım yetkilileri için sporcu ekleme ve çıkarma dönemi açıldı.' : 'Takım yetkilileri için sporcu ekleme ve çıkarma dönemi kapatıldı.');
        } elseif ($islem === 'sezon_olustur') {
            $sezonAdi = trim($_POST['sezon_adi'] ?? '');
            if (!preg_match('/^(20\d{2})-(20\d{2})$/', $sezonAdi, $m) || (int)$m[2] !== (int)$m[1] + 1) throw new RuntimeException('Sezon biçimi 2026-2027 şeklinde olmalıdır.');
            $pdo->prepare('INSERT INTO sezonlar(sezon_adi) VALUES(?)')->execute([$sezonAdi]);
            flash_set('basari', $sezonAdi.' sezonu oluşturuldu.');
        } elseif ($islem === 'sezon_resmilestir') {
            if (empty($_POST['onay'])) throw new RuntimeException('Resmileştirme onayı gereklidir.');
            sezon_sonuclarini_resmilestir($pdo, (int)($_POST['sezon_id'] ?? 0));
            flash_set('basari','Sezon sonuçları donduruldu ve Arşiv’e resmî olarak eklendi.');
        } else {
            $ad = trim($_POST['lig_adi'] ?? ''); $tur = $_POST['tur'] ?? 'takim'; $grupMetni = trim($_POST['gruplar'] ?? ''); $sezonId = (int)($_POST['sezon_id'] ?? 0);
            if ($ad === '' || $grupMetni === '' || !in_array($tur,['takim','bireysel'],true) || !$sezonId) throw new RuntimeException('Lig adı, türü, sezon ve en az bir grup zorunludur.');
            $sorgu = $pdo->prepare("SELECT * FROM sezonlar WHERE id=? AND durum='aktif'"); $sorgu->execute([$sezonId]); $sezon = $sorgu->fetch();
            if (!$sezon) throw new RuntimeException('Yalnızca aktif bir sezona yeni lig eklenebilir.');
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO ligler(lig_adi,tur,sezon,sezon_id,aciklama) VALUES(?,?,?,?,?)')->execute([$ad,$tur,$sezon['sezon_adi'],$sezonId,trim($_POST['aciklama'] ?? '')]);
            $ligId = (int)$pdo->lastInsertId();
            foreach (preg_split('/[\r\n]+/', $grupMetni) as $grup) {
                $grup=trim($grup); if ($grup === '') continue;
                $bolge=null; $kategori=null;
                if ($tur==='bireysel') {
                    [$bolge,$kategori]=array_pad(array_map('trim',explode('>', $grup, 2)),2,'');
                    if ($bolge==='' || $kategori==='') throw new RuntimeException('Bireysel Bölge Ligleri için her satırı “Bölge > Kategori” biçiminde girin.');
                    $grup=$bolge.' > '.$kategori;
                }
                $pdo->prepare('INSERT INTO gruplar(lig_id,grup_adi,bolge_adi,kategori_adi,aciklama,sezon) VALUES(?,?,?,?,?,?)')->execute([$ligId,$grup,$bolge,$kategori,'',$sezon['sezon_adi']]);
            }
            $pdo->commit(); flash_set('basari','Lig ve grupları oluşturuldu.');
        }
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); flash_set('hata',$e->getMessage()); }
    redirect(BASE_URL.'/admin/ligler.php');
}
$sezonlar = $pdo->query('SELECT * FROM sezonlar ORDER BY sezon_adi DESC')->fetchAll();
$aktifSezonlar = array_values(array_filter($sezonlar, fn($s) => $s['durum'] === 'aktif'));
$kadroAcik = ayar_al($pdo, 'sporcu_kayit_acik', '1') === '1';
$ligler = $pdo->query('SELECT l.*, s.durum sezon_durumu, COUNT(g.id) grup_sayisi FROM ligler l LEFT JOIN sezonlar s ON s.id=l.sezon_id LEFT JOIN gruplar g ON g.lig_id=l.id GROUP BY l.id ORDER BY l.created_at DESC')->fetchAll();
ob_start(); ?>
<div class="card"><div class="card-head"><h2>Sezon Yönetimi</h2><span class="badge">Sonuçlar kapanışta Arşiv’e kaydedilir</span></div><div class="grid-2"><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="islem" value="sezon_olustur"><label>Yeni sezon<input name="sezon_adi" required placeholder="2026-2027" pattern="20[0-9]{2}-20[0-9]{2}"></label><div class="form-actions"><button class="btn btn-primary">Sezon Oluştur</button></div></form><div class="notice"><strong>Sezonu resmileştirme</strong><br><small>Bu işlem puan tablolarını ve katılımcı adlarını dondurur. Sonuçlar Arşiv sayfasından görüntülenir.</small></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Sezon</th><th>Durum</th><th>Resmî tarih</th><th>İşlem</th></tr></thead><tbody><?php foreach($sezonlar as $s): ?><tr><td><strong><?= e($s['sezon_adi']) ?></strong></td><td><span class="badge <?= $s['durum']==='aktif'?'badge-success':'' ?>"><?= $s['durum']==='aktif'?'Aktif':'Resmileşti' ?></span></td><td><?= $s['resmi_tarih']?tr_tarih_saat($s['resmi_tarih']):'-' ?></td><td><?php if($s['durum']==='aktif'): ?><form method="post" onsubmit="return confirm('Bu sezonun sonuçları dondurulacak. Devam edilsin mi?')"><?= csrf_field() ?><input type="hidden" name="islem" value="sezon_resmilestir"><input type="hidden" name="sezon_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="onay" value="1"><button class="btn btn-sm">Sezonu Resmileştir</button></form><?php else: ?><span class="muted">Arşivde</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-head"><h2>Yeni Lig Oluştur</h2></div><?php if(!$aktifSezonlar): ?><p>Yeni lig eklemek için önce aktif bir sezon oluşturun.</p><?php else: ?><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="islem" value="lig_olustur"><div class="grid-2"><label>Lig adı *<input name="lig_adi" required placeholder="Bireysel Okçuluk Bölge Ligleri"></label><label>Lig türü *<select name="tur"><option value="takim">Takım Ligi</option><option value="bireysel">Bireysel Lig</option></select></label></div><div class="grid-2"><label>Sezon *<select name="sezon_id" required><?php foreach($aktifSezonlar as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['sezon_adi']) ?></option><?php endforeach; ?></select></label><label>Grup / bölge adları *<textarea name="gruplar" required placeholder="Marmara Bölgesi&#10;İç Anadolu Bölgesi"></textarea></label></div><label>Açıklama<textarea name="aciklama" rows="3"></textarea></label><div class="form-actions"><button class="btn btn-primary">Lig Oluştur</button></div></form><?php endif; ?></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Lig</th><th>Tür</th><th>Sezon</th><th>Grup</th><th>Yönetim</th></tr></thead><tbody><?php foreach($ligler as $l): ?><tr><td><strong><?= e($l['lig_adi']) ?></strong><br><small><?= e($l['aciklama']) ?></small></td><td><?= $l['tur']==='bireysel'?'Bireysel Lig':'Takım Ligi' ?></td><td><?= e($l['sezon']) ?></td><td><?= (int)$l['grup_sayisi'] ?></td><td><?php if($l['tur']==='bireysel' && $l['sezon_durumu']==='aktif'): ?><a class="btn btn-sm" href="<?= BASE_URL ?>/admin/bireysel-katilim.php?lig_id=<?= (int)$l['id'] ?>">Sporcu kayıtları</a> <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/admin/bireysel-fikstur.php?lig_id=<?= (int)$l['id'] ?>">Fikstür</a><?php elseif($l['sezon_durumu']==='resmilesti'): ?><span class="muted">Arşivde</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card"><div class="card-head"><h2>Takım Kadro Dönemi</h2><span class="badge <?= $kadroAcik?'badge-success':'' ?>"><?= $kadroAcik?'Açık':'Kapalı' ?></span></div><p class="muted">Bu ayar yalnızca takım yetkililerinin kendi kadrolarına sporcu ekleme, düzenleme ve takımdan çıkarma işlemlerini etkiler.</p><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="islem" value="kadro_penceresi"><input type="hidden" name="sporcu_kayit_acik" value="<?= $kadroAcik?'0':'1' ?>"><button class="btn <?= $kadroAcik?'btn-danger':'btn-primary' ?>"><?= $kadroAcik?'Dönemi Kapat':'Dönemi Aç' ?></button></form></div>
<div class="card"><div class="card-head"><h2>Şampiyona / Turnuva</h2><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/turnuvalar.php">Turnuvaları Yönet</a></div><p class="muted">Takım veya bireysel, 16 ya da 32 katılımcılı eleme turnuvaları oluşturabilir; A–C ve B–D düzeninde ilk tur eşleşmelerini hazırlayabilirsiniz.</p></div>
<?php $admin_baslik='Lig Yönetimi'; $admin_aktif='ligler'; $admin_icerik=ob_get_clean(); require __DIR__.'/partials/layout.php'; ?>
