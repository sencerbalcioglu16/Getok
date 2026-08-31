<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/**
 * Admin Panosu
 */
$u = kullanici_bilgi();
if (!in_array(($u['rol'] ?? ''), ['admin','yonetici'], true)) {
    $hedef = ['yetkili'=>'sporcular.php','hakem'=>'maclar.php','sporcu'=>'profil.php'][$u['rol'] ?? ''] ?? 'profil.php';
    if (($u['rol'] ?? '') === 'yetkili') {
        $kontrol=$pdo->prepare('SELECT takim_id FROM yetkili WHERE user_id=?');$kontrol->execute([$u['id']]);
        if (!(int)$kontrol->fetchColumn()) { flash_set('hata','Hesabınıza henüz bir takım atanmamış. Yönetici ile iletişime geçin.'); $hedef='profil.php'; }
    }
    redirect(BASE_URL.'/admin/'.$hedef);
}

$sayilar = [
    'duyurular'  => (int)$pdo->query("SELECT COUNT(*) FROM duyurular")->fetchColumn(),
    'haberler'   => (int)$pdo->query("SELECT COUNT(*) FROM haberler")->fetchColumn(),
    'yonetmelik' => (int)$pdo->query("SELECT COUNT(*) FROM yonetmelikler")->fetchColumn(),
    'gruplar'    => (int)$pdo->query("SELECT COUNT(*) FROM gruplar")->fetchColumn(),
    'takimlar'   => (int)$pdo->query("SELECT COUNT(*) FROM takimlar")->fetchColumn(),
    'sporcular'  => (int)$pdo->query("SELECT COUNT(*) FROM sporcular")->fetchColumn(),
    'hakemler'   => (int)$pdo->query("SELECT COUNT(*) FROM hakemler")->fetchColumn(),
    'yetkili'    => (int)$pdo->query("SELECT COUNT(*) FROM yetkili")->fetchColumn(),
    'maclar'     => (int)$pdo->query("SELECT COUNT(*) FROM maclar")->fetchColumn(),
    'oynanan'    => (int)$pdo->query("SELECT COUNT(*) FROM maclar WHERE durum='oynandi'")->fetchColumn(),
    'planlandi'  => (int)$pdo->query("SELECT COUNT(*) FROM maclar WHERE durum='planlandi'")->fetchColumn(),
];

// Son maçlar
$son_maclar = $pdo->query("
    SELECT m.*, t1.takim_adi AS ev, t2.takim_adi AS dep
    FROM maclar m
    JOIN takimlar t1 ON t1.id = m.ev_sahibi_id
    JOIN takimlar t2 ON t2.id = m.deplasman_id
    ORDER BY m.id DESC LIMIT 6
")->fetchAll();

ob_start();
?>
<div class="kpi-grid">
    <a href="<?= BASE_URL ?>/admin/duyurular.php" class="kpi-tile kpi-blue">
        <span>Duyurular</span><strong><?= $sayilar['duyurular'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/haberler.php" class="kpi-tile kpi-blue">
        <span>Haberler</span><strong><?= $sayilar['haberler'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/yonetmelikler.php" class="kpi-tile kpi-blue">
        <span>Yönetmelikler</span><strong><?= $sayilar['yonetmelik'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/gruplar-ve-fikstur.php" class="kpi-tile kpi-purple">
        <span>Gruplar</span><strong><?= $sayilar['gruplar'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/takimlar.php" class="kpi-tile kpi-purple">
        <span>Takımlar</span><strong><?= $sayilar['takimlar'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/sporcular.php" class="kpi-tile kpi-purple">
        <span>Sporcular</span><strong><?= $sayilar['sporcular'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/hakemler.php" class="kpi-tile kpi-green">
        <span>Hakemler</span><strong><?= $sayilar['hakemler'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/yetkili.php" class="kpi-tile kpi-green">
        <span>Yetkililer</span><strong><?= $sayilar['yetkili'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/maclar.php" class="kpi-tile kpi-orange">
        <span>Toplam Maç</span><strong><?= $sayilar['maclar'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/maclar.php?durum=oynandi" class="kpi-tile kpi-orange">
        <span>Oynanan</span><strong><?= $sayilar['oynanan'] ?></strong>
    </a>
    <a href="<?= BASE_URL ?>/admin/maclar.php?durum=planlandi" class="kpi-tile kpi-orange">
        <span>Planlanan</span><strong><?= $sayilar['planlandi'] ?></strong>
    </a>
</div>

<section class="card">
    <div class="card-head"><h2>📅 Son Maçlar</h2>
        <a href="<?= BASE_URL ?>/admin/maclar.php" class="link-more">Tümü →</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Tarih</th><th>Ev</th><th>Skor</th><th>Dep</th><th>Durum</th></tr></thead>
            <tbody>
            <?php foreach ($son_maclar as $m): ?>
                <tr>
                    <td><?= tr_tarih($m['tarih']) ?></td>
                    <td><?= e($m['ev']) ?></td>
                    <td>
                        <?php if ($m['durum']==='oynandi'): ?>
                            <?= (int)$m['ev_sahibi_set'] ?> - <?= (int)$m['deplasman_set'] ?>
                        <?php else: ?><span class="muted">vs</span><?php endif; ?>
                    </td>
                    <td><?= e($m['dep']) ?></td>
                    <td>
                        <?php if ($m['durum']==='oynandi'): ?><span class="badge badge-ok">Oynandı</span>
                        <?php elseif ($m['durum']==='iptal'): ?><span class="badge badge-no">İptal</span>
                        <?php else: ?><span class="badge">Planlandı</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
$admin_baslik = 'Yönetim Panosu';
$admin_aktif  = 'dashboard';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
