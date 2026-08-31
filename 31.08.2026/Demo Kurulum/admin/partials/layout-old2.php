<?php
/**
 * Admin paneli layout'u
 * Değişkenler:
 *   $admin_baslik  - sayfa başlığı
 *   $admin_aktif   - aktif menü öğesi (dashboard, duyurular, haberler, yonetmelik, gruplar, takimlar, sporcular, hakemler, yetkili, maclar, skor, profil)
 *   $admin_icerik  - içerik HTML
 */
$flash = flash_get();
$u = kullanici_bilgi();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($admin_baslik ?? 'Yönetim') ?> — <?= LIG_ADI ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <a href="<?= BASE_URL ?>/admin/"><strong>🎯 Yönetim</strong></a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="<?= BASE_URL ?>/admin/" class="<?= $admin_aktif=='dashboard'?'active':'' ?>">📊 Pano</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/duyurular.php" class="<?= $admin_aktif=='duyurular'?'active':'' ?>">📢 Duyurular</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/haberler.php" class="<?= $admin_aktif=='haberler'?'active':'' ?>">📰 Haberler</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/yonetmelikler.php" class="<?= $admin_aktif=='yonetmelik'?'active':'' ?>">📜 Yönetmelikler</a></li>
                    <li><hr></li>
                    <li><a href="<?= BASE_URL ?>/admin/gruplar-ve-fikstur.php" class="<?= $admin_aktif=='gruplar'?'active':'' ?>">📋 Gruplar</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/takimlar.php" class="<?= $admin_aktif=='takimlar'?'active':'' ?>">🏛️ Takımlar</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/sporcular.php" class="<?= $admin_aktif=='sporcular'?'active':'' ?>">🏹 Sporcular</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/hakemler.php" class="<?= $admin_aktif=='hakemler'?'active':'' ?>">⚖️ Hakemler</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/yetkili.php" class="<?= $admin_aktif=='yetkili'?'active':'' ?>">👤 Yetkililer</a></li>
                    <li><hr></li>
                    <li><a href="<?= BASE_URL ?>/admin/maclar.php" class="<?= $admin_aktif=='maclar'?'active':'' ?>">⚽ Maçlar</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/mac-skor.php" class="<?= $admin_aktif=='skor'?'active':'' ?>">✍️ Skor Girişi</a></li>
                    <li><hr></li>
                    <li><a href="<?= BASE_URL ?>/admin/profil.php" class="<?= $admin_aktif=='profil'?'active':'' ?>">👤 Profilim</a></li>
                    <li><a href="<?= BASE_URL ?>/cikis.php">🚪 Çıkış</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Ana içerik -->
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1><?= e($admin_baslik ?? 'Yönetim Panosu') ?></h1>
                </div>
                <div class="header-right">
                    <span><?= e($u['ad_soyad'] ?? '') ?> (<?= e($u['rol'] ?? '') ?>)</span>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="admin-content">
                <?= $admin_icerik ?? '' ?>
            </div>

            <footer class="admin-footer">
                <small><?= LIG_ADI ?> v<?= SURUM ?> · <?= date('Y') ?></small>
            </footer>
        </main>
    </div>
</body>
</html>
