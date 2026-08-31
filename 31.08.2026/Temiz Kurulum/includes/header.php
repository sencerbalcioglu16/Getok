<?php
if (!isset($sayfa_baslik)) $sayfa_baslik = LIG_ADI;
$aktif = $aktif ?? '';
$u = kullanici_bilgi();
$ligler_menu = $pdo->query("SELECT l.id,l.lig_adi FROM ligler l JOIN sezonlar s ON s.id=l.sezon_id WHERE l.aktif=1 AND s.durum='aktif' ORDER BY l.lig_adi")->fetchAll();
$turnuvalar_menu = $pdo->query("SELECT t.id,t.turnuva_adi FROM turnuvalar t JOIN sezonlar s ON s.id=t.sezon_id WHERE s.durum='aktif' ORDER BY t.turnuva_adi")->fetchAll();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($sayfa_baslik) ?> — <?= e(LIG_ADI) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.ico" sizes="any">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/follow-card.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/multi-league.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/account.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mobile-menu.css">
    <script src="<?= BASE_URL ?>/assets/js/mobile-menu.js" defer></script>
</head>
<body>
<div class="site-shell">
    <div class="utility-bar"><span><?= e(LIG_SEZON) ?> Sezonu</span><span></span></div>
    <header class="site-header">
        <a href="<?= BASE_URL ?>/index.php" class="brand"><img src="<?= BASE_URL ?>/assets/images/getok-logo.png" alt="Geleneksel Türk Okçuluğu Bölge Ligleri logosu" class="brand-logo"><span class="brand-text">Geleneksel Türk<br>Okçuluğu Bölge Ligleri</span></a>
        <button type="button" class="mobile-menu-toggle" aria-label="Menüyü aç" aria-controls="anaMenu" aria-expanded="false"><span></span><span></span><span></span></button>
        <nav class="mainnav" id="anaMenu" aria-label="Ana menü">
            <details class="lig-menu"><summary class="<?= in_array($aktif,['ligler','turnuvalar'],true)?'active':'' ?>">Ligler ve Turnuvalar</summary><div>
                <span class="menu-heading">Ligler</span>
                <?php foreach($ligler_menu as $ligMenu): ?><a href="<?= BASE_URL ?>/lig.php?id=<?= (int)$ligMenu['id'] ?>"><?= e($ligMenu['lig_adi']) ?></a><?php endforeach; ?>
                <a href="<?= BASE_URL ?>/ligler.php">Tüm Ligler</a>
                <span class="menu-heading">Turnuvalar</span>
                <?php foreach($turnuvalar_menu as $turnuvaMenu): ?><a href="<?= BASE_URL ?>/turnuva.php?id=<?= (int)$turnuvaMenu['id'] ?>">🏆 <?= e($turnuvaMenu['turnuva_adi']) ?></a><?php endforeach; ?>
                <a href="<?= BASE_URL ?>/turnuvalar.php">Tüm Turnuvalar</a><a href="<?= BASE_URL ?>/arsiv.php">Arşiv</a>
            </div></details>
            <a href="<?= BASE_URL ?>/sonuclar.php" class="<?= $aktif==='sonuclar'?'active':'' ?>">Karşılaşma Sonuçları</a>
            <a href="<?= BASE_URL ?>/fikstur.php" class="<?= $aktif==='fikstur'?'active':'' ?>">Fikstür</a>
            <a href="<?= BASE_URL ?>/takimlar.php" class="<?= $aktif==='takimlar'?'active':'' ?>">Takımlar</a>
            <a href="<?= BASE_URL ?>/sporcular.php" class="<?= $aktif==='sporcular'?'active':'' ?>">Sporcular</a>
            <a href="<?= BASE_URL ?>/duyurular.php" class="<?= $aktif==='duyurular'?'active':'' ?>">Duyurular</a>
            <div class="mobile-nav-actions"><?php if($u): ?><a href="<?= BASE_URL ?>/favorilerim.php">★ Favorilerim</a><a href="<?= hesap_sayfasi_url() ?>">Hesabım</a><?php else: ?><a href="<?= BASE_URL ?>/login.php">Giriş yap</a><?php endif; ?></div>
        </nav>
        <div class="top-actions"><?php if($u): ?><a href="<?= BASE_URL ?>/favorilerim.php" class="btn btn-ghost">★ Favorilerim</a><a href="<?= hesap_sayfasi_url() ?>" class="btn btn-primary">Hesabım</a><?php else: ?><a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">Giriş yap</a><?php endif; ?></div>
    </header>
    <div class="mobile-menu-overlay" aria-hidden="true"></div>
    <?php if ($flash = flash_get()): ?><div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <div class="main-layout">
