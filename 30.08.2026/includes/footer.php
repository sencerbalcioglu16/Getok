<?php
$footer_ligler = isset($pdo) ? $pdo->query("SELECT l.id,l.lig_adi FROM ligler l JOIN sezonlar s ON s.id=l.sezon_id WHERE l.aktif=1 AND s.durum='aktif' ORDER BY l.created_at")->fetchAll() : [];
$footer_turnuvalar = isset($pdo) ? $pdo->query("SELECT t.id,t.turnuva_adi FROM turnuvalar t JOIN sezonlar s ON s.id=t.sezon_id WHERE s.durum='aktif' ORDER BY t.created_at DESC")->fetchAll() : [];
?>
</div></div>
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <img src="<?= BASE_URL ?>/assets/images/getok-logo.png" alt="Geleneksel Okçuluk Ligleri logosu" class="footer-logo">
            <div><strong>Geleneksel Okçuluk Ligleri</strong><small>Gelenekten gelen güç, hedefe giden yol.</small></div>
        </div>
        <div class="footer-column footer-lig-menu">
            <strong>Ligler ve Turnuvalar</strong><span class="footer-menu-heading">Ligler</span>
            <?php foreach ($footer_ligler as $fl): ?><a href="<?= BASE_URL ?>/lig.php?id=<?= (int)$fl['id'] ?>"><?= e($fl['lig_adi']) ?></a><?php endforeach; ?>
            <a href="<?= BASE_URL ?>/ligler.php">Tüm Ligler</a><span class="footer-menu-heading">Turnuvalar</span>
            <?php foreach ($footer_turnuvalar as $ft): ?><a href="<?= BASE_URL ?>/turnuva.php?id=<?= (int)$ft['id'] ?>">🏆 <?= e($ft['turnuva_adi']) ?></a><?php endforeach; ?>
            <a href="<?= BASE_URL ?>/turnuvalar.php">Tüm Turnuvalar</a><a href="<?= BASE_URL ?>/arsiv.php">Arşiv</a>
        </div>
        <div class="footer-column">
            <strong>Kurumsal</strong><a href="<?= BASE_URL ?>/hakkimizda.php">Hakkımızda</a><a href="<?= BASE_URL ?>/yonetmelikler.php">Yönetmelikler</a><a href="<?= BASE_URL ?>/iletisim.php">İletişim</a><a href="<?= BASE_URL ?>/destekleyenler.php">Destekleyenler</a>
        </div>
    </div>
    <div class="footer-copy">© <?= date('Y') ?> Geleneksel Okçuluk Ligleri · Geliştirici: Sencer BALCIOĞLU · <a href="<?= BASE_URL ?>/LICENSE" target="_blank" rel="license noopener">GNU GPL v3.0 ile lisanslanmıştır</a></div>
</footer>
</body></html>
