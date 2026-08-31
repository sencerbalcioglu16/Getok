<?php
require_once __DIR__.'/../../includes/functions.php';
$flash=flash_get();$u=kullanici_bilgi();$rol=$u['rol']??'';
$admin_icerik=str_replace(['Bireysel Okçuluk Bölge Ligleri','Grup / bölge adları','Marmara Bölgesi&#10;İç Anadolu Bölgesi'],['Bireysel Bölge Ligleri','Grup veya bölge-kategori bilgisi','Bursa - Balıkesir Bölgesi > Minikler Grubu'],$admin_icerik??'');
$admin_baslik=str_replace('Şampiyona / Turnuva','Turnuvalar',$admin_baslik??'');
$admin_geri_url=$admin_geri_url??BASE_URL.'/admin/';$admin_geri_ad=$admin_geri_ad??'Yönetim Panosu';
$admin_icerik=str_replace(['Yeni Şampiyona / Turnuva','Şampiyona / Turnuva','Geleneksel Okçuluk Türkiye Şampiyonası'],['Yeni Turnuva','Turnuvalar','Geleneksel Okçuluk Türkiye Turnuvası'],$admin_icerik??'');
$menu=[
 'admin'=>[
  ['dashboard','📊','Pano','/admin/'],
  ['ligler','🏆','Ligler ve Sezon','/admin/ligler.php'],
  ['gruplar','↳','Gruplar ve Fikstür','/admin/gruplar-ve-fikstur.php'],
  ['turnuvalar','🏆','Turnuvalar','/admin/turnuvalar.php'],
  ['maclar','📅','Karşılaşmalar','/admin/maclar.php'],
  ['skor','✍️','Skor Girişi','/admin/mac-skor.php'],
  ['takimlar','🏛️','Takımlar','/admin/takimlar.php'],
  ['sporcular','🏹','Sporcular','/admin/sporcular.php'],
  ['hakemler','⚖️','Hakemler','/admin/hakemler.php'],
  ['yetkili','👤','Yetkililer','/admin/yetkili.php'],
  ['yoneticiler','🛡️','Yöneticiler','/admin/yoneticiler.php'],
  ['uyeler','👥','Üyeler','/admin/uyeler.php'],
  ['duyurular','📢','Duyurular','/admin/duyurular.php'],
  ['haberler','📰','Haberler','/admin/haberler.php'],
  ['yonetmelik','📜','Yönetmelikler','/admin/yonetmelikler.php'],
  ['sayfalar','🏢','Kurumsal Sayfalar','/admin/sayfalar.php'],
  ['profil','👤','Profilim','/admin/profil.php']
 ],
 'yonetici'=>[
  ['dashboard','📊','Pano','/admin/'],
  ['ligler','🏆','Ligler ve Sezon','/admin/ligler.php'],
  ['gruplar','↳','Gruplar ve Fikstür','/admin/gruplar-ve-fikstur.php'],
  ['turnuvalar','🏆','Turnuvalar','/admin/turnuvalar.php'],
  ['maclar','📅','Karşılaşmalar','/admin/maclar.php'],
  ['skor','✍️','Skor Girişi','/admin/mac-skor.php'],
  ['takimlar','🏛️','Takımlar','/admin/takimlar.php'],
  ['sporcular','🏹','Sporcular','/admin/sporcular.php'],
  ['hakemler','⚖️','Hakemler','/admin/hakemler.php'],
  ['yetkili','👤','Yetkililer','/admin/yetkili.php'],
  ['uyeler','👥','Üyeler','/admin/uyeler.php'],
  ['profil','👤','Profilim','/admin/profil.php']
 ],
 'yetkili'=>[['sporcular','🏹','Sporcular','/admin/sporcular.php'],['profil','👤','Profilim','/admin/profil.php']],
 'hakem'=>[['maclar','📅','Karşılaşmalar','/admin/maclar.php'],['skor','✍️','Skor Girişi','/admin/mac-skor.php'],['profil','👤','Profilim','/admin/profil.php']],
 'sporcu'=>[['profil','👤','Profilim','/admin/profil.php']]
];
$izinli=array_column($menu[$rol]??[],0);
if(in_array($rol,['admin','yonetici'],true))$izinli[]='gruplar';
if($rol==='yetkili'){$p=$pdo->prepare("SELECT pozisyon FROM yetkili WHERE user_id=?");$p->execute([$u['id']]);if($p->fetchColumn()==='Yönetici'){$menu['yetkili']=array_merge([['takimlar','🏛️','Takımlarım','/admin/takimlar.php']],$menu['yetkili']);$izinli=array_column($menu['yetkili'],0);}}
if(!in_array($admin_aktif??'', $izinli, true)){http_response_code(403);exit('Bu sayfaya erişim yetkiniz bulunmuyor.');}
$bolumler=['dashboard'=>'GENEL','ligler'=>'ORGANİZASYON YÖNETİMİ','takimlar'=>'KATILIMCILAR','duyurular'=>'İÇERİK VE HESAPLAR'];
?>
<!doctype html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($admin_baslik??'Yönetim') ?> — <?= e(LIG_ADI) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-editor.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/organizasyon-secim.css">
</head><body class="admin-body"><div class="admin-wrapper">
<aside class="admin-sidebar"><div class="sidebar-brand"><a href="<?= BASE_URL ?>/admin/<?= in_array($rol,['admin','yonetici'],true)?'':'profil.php' ?>"><strong>🎯 Yönetim</strong></a></div>
<nav class="sidebar-nav"><ul><?php foreach($menu[$rol]??[] as $m): ?><?php if(in_array($rol,['admin','yonetici'],true)&&isset($bolumler[$m[0]])): ?><li class="sidebar-section"><?= $bolumler[$m[0]] ?></li><?php endif; ?><li<?= $m[0]==='gruplar'?' class="sidebar-sub-link"':'' ?>><a href="<?= BASE_URL.$m[3] ?>" class="<?= ($admin_aktif??'')===$m[0]?'active':'' ?>"><?= $m[1] ?> <?= $m[2] ?></a></li><?php endforeach; ?><li><hr></li><li><a href="<?= BASE_URL ?>/admin/cikis.php">🚪 Çıkış</a></li></ul></nav>
</aside>
<main class="admin-main"><header class="admin-header"><div class="header-left"><a class="btn btn-sm btn-outline" href="<?= e($admin_geri_url) ?>">← <?= e($admin_geri_ad) ?></a><h1><?= e($admin_baslik??'Yönetim Panosu') ?></h1></div><div class="header-right"><span><?= e($u['ad_soyad']??'') ?> (<?= e($rol) ?>)</span></div></header>
<?php if($flash): ?><div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="admin-content"><?= $admin_icerik??'' ?></div><footer class="admin-footer"><small><?= e(LIG_ADI) ?> v<?= e(SURUM) ?> · <?= date('Y') ?></small></footer></main>
</div></body></html>
