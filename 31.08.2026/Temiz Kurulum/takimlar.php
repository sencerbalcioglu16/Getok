<?php
/**
 * Takımlar Listesi - Grup Filtreli ve Sıralama Seçenekli
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$sayfa_baslik = 'Takımlar';
$aktif = 'takimlar';
require_once __DIR__ . '/includes/header.php';

// Filtre ve sıralama parametreleri
$grup_id = isset($_GET['grup']) ? (int)$_GET['grup'] : 0;
$sirala = isset($_GET['sirala']) ? $_GET['sirala'] : 'takim_adi';
$yön = isset($_GET['yön']) && $_GET['yön'] === 'desc' ? 'DESC' : 'ASC';

// İzin verilen sıralama sütunları (whitelist)
$izinli_sirala = [
    'takim_adi'      => 'Takım Adı',
    'toplam_set'     => 'Toplam Set',
    'toplam_puan'    => 'Averaj Puan',
    'kazanilan_mac'  => 'Galibiyet',
    'kaybedilen_mac' => 'Mağlubiyet',
    'grup_adi'       => 'Grup'
];
if (!array_key_exists($sirala, $izinli_sirala)) {
    $sirala = 'takim_adi';
}

// Grupları çek (filtreleme için)
$gruplar = $pdo->query("SELECT g.id, g.grup_adi FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' AND l.aktif=1 ORDER BY g.grup_adi")->fetchAll();

// Takım sorgusu
$sql = "SELECT t.*, g.grup_adi FROM takimlar t JOIN gruplar g ON g.id = t.grup_id JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim'";
$params = [];
if ($grup_id > 0) {
    $sql .= " AND t.grup_id = ?";
    $params[] = $grup_id;
}
$sql .= " ORDER BY " . $sirala . " " . $yön;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$takimlar = $stmt->fetchAll();

// Sıralama bağlantıları için yardımcı fonksiyon
function sirala_link($sutun, $mevcut, $yön, $grup_id) {
    $yeni_yön = 'asc';
    if ($sutun === $mevcut) {
        $yeni_yön = $yön === 'ASC' ? 'desc' : 'asc';
    }
    return "?grup=" . $grup_id . "&sirala=" . $sutun . "&yön=" . $yeni_yön;
}
?>
<main class="main-content">
<div class="container">
    <h1>Takımlar</h1>

    <!-- Filtre Formu -->
    <form method="get" class="form" style="margin-bottom:20px;">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <label style="display:flex; flex-direction:column; gap:4px;">
                Grup
                <select name="grup" onchange="this.form.submit()">
                    <option value="0">— Tüm Gruplar —</option>
                    <?php foreach ($gruplar as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= $grup_id === (int)$g['id'] ? 'selected' : '' ?>>
                            <?= e($g['grup_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript>
                <button class="btn btn-primary">Filtrele</button>
            </noscript>
        </div>
    </form>

    <?php if (empty($takimlar)): ?>
        <p>Henüz takım bulunmuyor.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><a href="<?= sirala_link('takim_adi', $sirala, $yön, $grup_id) ?>">Takım Adı <?= $sirala==='takim_adi' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('grup_adi', $sirala, $yön, $grup_id) ?>">Grup <?= $sirala==='grup_adi' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('toplam_set', $sirala, $yön, $grup_id) ?>">Set <?= $sirala==='toplam_set' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('toplam_puan', $sirala, $yön, $grup_id) ?>">Averaj <?= $sirala==='toplam_puan' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('kazanilan_mac', $sirala, $yön, $grup_id) ?>">Galibiyet <?= $sirala==='kazanilan_mac' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('kaybedilen_mac', $sirala, $yön, $grup_id) ?>">Mağlubiyet <?= $sirala==='kaybedilen_mac' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($takimlar as $t): ?>
                    <tr>
                        <td><strong><?= e($t['takim_adi']) ?></strong></td>
                        <td><?= e($t['grup_adi']) ?></td>
                        <td><?= (int)$t['toplam_set'] ?></td>
                        <td><?= (int)$t['toplam_puan'] ?></td>
                        <td><?= (int)$t['kazanilan_mac'] ?></td>
                        <td><?= (int)$t['kaybedilen_mac'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</main>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
