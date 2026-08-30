<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.


$sayfa_baslik = 'Yönetmelikler';
$aktif = 'yonetmelik';
require_once __DIR__ . '/includes/header.php';
$liste = $pdo->query("SELECT * FROM yonetmelikler WHERE yayinda=1 ORDER BY created_at DESC")->fetchAll();
?>
<main class="main-content">
<h1>Yönetmelikler</h1>
<?php if (empty($liste)): ?>
    <p class="muted">Henüz yönetmelik yok.</p>
<?php else: foreach ($liste as $y): ?>
    <article class="card news-item">
        <?php if ($y['gorsel']): ?><img src="<?= UPLOAD_URL ?>/<?= e($y['gorsel']) ?>" alt=""><?php endif; ?>
        <h2><?= e($y['baslik']) ?></h2>
        <small><?= tr_tarih_saat($y['created_at']) ?></small>
        <div class="news-content"><?= guvenli_html($y['icerik']) ?></div>
    </article>
<?php endforeach; endif; ?>
</main>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
