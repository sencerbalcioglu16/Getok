<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.


$sayfa_baslik = 'Haberler';
$aktif = 'haberler';
require_once __DIR__ . '/includes/header.php';
$liste = $pdo->query("SELECT * FROM haberler WHERE yayinda=1 ORDER BY created_at DESC")->fetchAll();
?>
<main class="main-content">
<h1>Haberler</h1>
<?php if (empty($liste)): ?>
    <p class="muted">Henüz haber yok.</p>
<?php else: foreach ($liste as $h): ?>
    <article class="card news-item">
        <?php if ($h['gorsel']): ?><img src="<?= UPLOAD_URL ?>/<?= e($h['gorsel']) ?>" alt=""><?php endif; ?>
        <h2><?= e($h['baslik']) ?></h2>
        <small><?= tr_tarih_saat($h['created_at']) ?></small>
        <?php if ($h['ozet']): ?><p><strong><?= e($h['ozet']) ?></strong></p><?php endif; ?>
        <div class="news-content"><?= guvenli_html($h['icerik']) ?></div>
    </article>
<?php endforeach; endif; ?>
</main>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
