<?php
/**
 * Admin paneli ortak layout
 *
 * Her admin sayfasının başında:
 *   $admin_baslik = 'Sayfa Adı';
 *   $admin_aktif   = 'duyurular';
 *   $admin_icerik  = '...html...';
 *   require 'partials/layout.php';
 *
 * şeklinde çağrılır.
 */
require_once __DIR__ . '/../../includes/header.php';
zorunlu_rol('admin','hakem','sporcu','yetkili');

$u = kullanici_bilgi();

$menuler = [
    ['key'=>'dashboard', 'icon'=>'📊', 'ad'=>'Pano',        'url'=>'/admin/index.php',     'rol'=>['admin','hakem','sporcu','yetkili']],
    ['key'=>'duyurular', 'icon'=>'📢', 'ad'=>'Duyurular',    'url'=>'/admin/duyurular.php', 'rol'=>['admin']],
    ['key'=>'haberler',  'icon'=>'📰', 'ad'=>'Haberler',     'url'=>'/admin/haberler.php',  'rol'=>['admin']],
    ['key'=>'yonetmelik','icon'=>'📜', 'ad'=>'Yönetmelikler','url'=>'/admin/yonetmelikler.php','rol'=>['admin']],
    ['key'=>'gruplar',   'icon'=>'👥', 'ad'=>'Gruplar',      'url'=>'/admin/gruplar.php',   'rol'=>['admin']],
    ['key'=>'takimlar',  'icon'=>'🏹', 'ad'=>'Takımlar',     'url'=>'/admin/takimlar.php',  'rol'=>['admin','yetkili']],
    ['key'=>'sporcular', 'icon'=>'🎯', 'ad'=>'Sporcular',    'url'=>'/admin/sporcular.php', 'rol'=>['admin','yetkili','sporcu']],
    ['key'=>'yetkili',   'icon'=>'🧑‍💼','ad'=>'Yetkililer',  'url'=>'/admin/yetkili.php',   'rol'=>['admin']],
    ['key'=>'hakemler',  'icon'=>'🧑‍⚖️','ad'=>'Hakemler',    'url'=>'/admin/hakemler.php',  'rol'=>['admin']],
    ['key'=>'maclar',    'icon'=>'📅', 'ad'=>'Maçlar',       'url'=>'/admin/maclar.php',    'rol'=>['admin','hakem']],
    ['key'=>'skor',      'icon'=>'✍️', 'ad'=>'Skor Girişi',  'url'=>'/admin/mac-skor.php',  'rol'=>['admin','hakem']],
    ['key'=>'profil',    'icon'=>'⚙️', 'ad'=>'Profilim',     'url'=>'/admin/profil.php',    'rol'=>['admin','hakem','sporcu','yetkili']],
];
?>
<style>body { background: #f4f6fb; }</style>
<div class="admin-wrap">
    <aside class="sidebar">
        <div class="sidebar-head">
            <span class="brand-icon">🎯</span>
            <strong>Yönetim Paneli</strong>
        </div>
        <nav>
        <?php foreach ($menuler as $m): if (!in_array($u['rol'], $m['rol'], true)) continue; ?>
            <a href="<?= BASE_URL . $m['url'] ?>"
               class="side-link <?= ($admin_aktif ?? '')===$m['key']?'active':'' ?>">
                <span class="ico"><?= $m['icon'] ?></span>
                <span><?= e($m['ad']) ?></span>
            </a>
        <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <a href="<?= BASE_URL ?>/" class="side-link">← Siteye Dön</a>
            <a href="<?= BASE_URL ?>/logout.php" class="side-link danger">Çıkış Yap</a>
        </div>
    </aside>
    <section class="admin-main">
        <header class="admin-topbar">
            <h1><?= e($admin_baslik ?? 'Yönetim') ?></h1>
            <div class="who">
                <?= e($u['ad_soyad'] ?: $u['kullanici_adi']) ?>
                <span class="role-tag role-<?= e($u['rol']) ?>"><?= e($u['rol']) ?></span>
            </div>
        </header>
        <?php $f = flash_get(); if ($f): ?>
            <div class="flash flash-<?= e($f['tip']) ?>"><?= e($f['mesaj']) ?></div>
        <?php endif; ?>
        <div class="admin-content">
            <?= $admin_icerik ?? '' ?>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
