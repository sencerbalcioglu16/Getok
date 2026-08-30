<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.


$sayfa_baslik = 'Duyurular';
$aktif = 'duyurular';
require_once __DIR__ . '/includes/header.php';
$liste = $pdo->query("SELECT * FROM duyurular WHERE yayinda=1 ORDER BY created_at DESC")->fetchAll();
?>
<main class="main-content">
<h1>Duyurular</h1>
<?php if (empty($liste)): ?>
    <p class="muted">Henüz duyuru yok.</p>
<?php else: foreach ($liste as $d): ?>
    <article class="card news-item">
        <?php if ($d['gorsel']): ?><img src="<?= UPLOAD_URL ?>/<?= e($d['gorsel']) ?>" alt=""><?php endif; ?>
        <h2><?= e($d['baslik']) ?></h2>
        <small><?= tr_tarih_saat($d['created_at']) ?></small>
        <div class="news-content"><?= guvenli_html($d['icerik']) ?></div>
        <?php if (!empty($d['medya_url'])): ?><p><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e($d['medya_url']) ?>">▶ Videoyu izle</a></p><?php endif; ?>
    </article>
<?php endforeach; endif; ?>
</main>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
