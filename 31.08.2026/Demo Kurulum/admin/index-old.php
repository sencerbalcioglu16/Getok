<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/**
 * Admin Panosu
 */
$u = kullanici_bilgi();

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
    <div class="kpi-tile kpi-blue"><span>Duyurular</span><strong><?= $sayilar['duyurular'] ?></strong></div>
    <div class="kpi-tile kpi-blue"><span>Haberler</span><strong><?= $sayilar['haberler'] ?></strong></div>
    <div class="kpi-tile kpi-blue"><span>Yönetmelikler</span><strong><?= $sayilar['yonetmelik'] ?></strong></div>
    <div class="kpi-tile kpi-purple"><span>Gruplar</span><strong><?= $sayilar['gruplar'] ?></strong></div>
    <div class="kpi-tile kpi-purple"><span>Takımlar</span><strong><?= $sayilar['takimlar'] ?></strong></div>
    <div class="kpi-tile kpi-purple"><span>Sporcular</span><strong><?= $sayilar['sporcular'] ?></strong></div>
    <div class="kpi-tile kpi-green"><span>Hakemler</span><strong><?= $sayilar['hakemler'] ?></strong></div>
    <div class="kpi-tile kpi-green"><span>Yetkililer</span><strong><?= $sayilar['yetkili'] ?></strong></div>
    <div class="kpi-tile kpi-orange"><span>Toplam Maç</span><strong><?= $sayilar['maclar'] ?></strong></div>
    <div class="kpi-tile kpi-orange"><span>Oynanan</span><strong><?= $sayilar['oynanan'] ?></strong></div>
    <div class="kpi-tile kpi-orange"><span>Planlanan</span><strong><?= $sayilar['planlandi'] ?></strong></div>
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
