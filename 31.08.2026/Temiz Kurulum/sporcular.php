<?php
/**
 * Sporcular Listesi - Grup ve Takım Filtreli, Sıralama Seçenekli
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$sayfa_baslik = 'Sporcular';
$aktif = 'sporcular';
require_once __DIR__ . '/includes/header.php';

// Filtre ve sıralama parametreleri
$grup_id = isset($_GET['grup']) ? (int)$_GET['grup'] : 0;
$takim_id = isset($_GET['takim']) ? (int)$_GET['takim'] : 0;
$sirala = isset($_GET['sirala']) ? $_GET['sirala'] : 'soyad';
$yön = isset($_GET['yön']) && $_GET['yön'] === 'desc' ? 'DESC' : 'ASC';

// İzin verilen sıralama sütunları (whitelist)
$izinli_sirala = [
    'ad'           => 'Ad',
    'soyad'        => 'Soyad',
    'takim_adi'    => 'Takım',
    'grup_adi'     => 'Grup',
    'toplam_puan'  => 'Toplam Puan',
    'atis_sayisi'  => 'Atış Sayısı',
    'ortalama'     => 'Ortalama'
];
if (!array_key_exists($sirala, $izinli_sirala)) {
    $sirala = 'soyad';
}

// Grupları çek (filtreleme için)
$gruplar = $pdo->query("SELECT id, grup_adi FROM gruplar ORDER BY grup_adi")->fetchAll();

// Takımları çek (tümü, sonra grup seçimine göre filtreleme yapılacak)
$takim_sql = "SELECT id, takim_adi, grup_id FROM takimlar ORDER BY takim_adi";
$takimlar = $pdo->query($takim_sql)->fetchAll();

// Eğer grup seçiliyse, takım listesini o gruba göre filtrele
$takim_filtreli = $takimlar;
if ($grup_id > 0) {
    $takim_filtreli = array_filter($takimlar, function($t) use ($grup_id) {
        return $t['grup_id'] == $grup_id;
    });
}

// Sporcu sorgusu
$sql = "
    SELECT s.*, 
           t.takim_adi, 
           g.grup_adi,
           COALESCE(t.takim_adi, 'Takım Yok') AS takim_gosterim
    FROM sporcular s
    LEFT JOIN takimlar t ON t.id = s.takim_id
    LEFT JOIN gruplar g ON g.id = t.grup_id
    WHERE 1=1
";
$params = [];

if ($grup_id > 0) {
    $sql .= " AND (t.grup_id = ? OR (s.takim_id IS NULL AND ? = 0))";
    $params[] = $grup_id;
    $params[] = $grup_id; // takımı olmayanlar için grup filtresini görmezden gelmek istiyorsak, ama şimdilik sadece o gruba ait takımları göster
    // Daha doğrusu: takımı olmayanlar her durumda gelsin istiyorsak:
    // $sql .= " AND (t.grup_id = ? OR s.takim_id IS NULL)";
    // ama bu durumda "Tüm Gruplar" seçeneğinde takımı olmayanlar gelir, grup seçildiğinde de gelir. Karar verelim.
    // Kullanıcı grup seçtiğinde sadece o grubun takımlarındaki sporcuları görmek isteyebilir, takımı olmayanları da göstermek isteyebilir.
    // Daha basit: grup filtrelemesinde takımı olmayanları dahil etme, çünkü grupları yok.
    // O yüzden yukarıdaki gibi bırakalım, sadece t.grup_id = ? ile filtreleyelim.
    // Eğer takımı olmayanlar da gelsin istenirse ayrıca koşul eklenebilir.
}

if ($takim_id > 0) {
    $sql .= " AND s.takim_id = ?";
    $params[] = $takim_id;
}

// Sıralama
switch ($sirala) {
    case 'ortalama':
        $sql .= " ORDER BY (s.toplam_puan / NULLIF(s.atis_sayisi, 0)) " . $yön;
        break;
    case 'takim_adi':
        $sql .= " ORDER BY t.takim_adi " . $yön . ", s.soyad ASC";
        break;
    case 'grup_adi':
        $sql .= " ORDER BY g.grup_adi " . $yön . ", s.soyad ASC";
        break;
    default:
        $sql .= " ORDER BY s." . $sirala . " " . $yön;
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sporcular = $stmt->fetchAll();

// Sıralama bağlantıları için yardımcı fonksiyon
function sirala_link($sutun, $mevcut, $yön, $grup_id, $takim_id) {
    $yeni_yön = 'asc';
    if ($sutun === $mevcut) {
        $yeni_yön = $yön === 'ASC' ? 'desc' : 'asc';
    }
    return "?grup=" . $grup_id . "&takim=" . $takim_id . "&sirala=" . $sutun . "&yön=" . $yeni_yön;
}
?>
<main class="main-content">
<div class="container">
    <h1>Sporcular</h1>

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
            <label style="display:flex; flex-direction:column; gap:4px;">
                Takım
                <select name="takim" onchange="this.form.submit()">
                    <option value="0">— Tüm Takımlar —</option>
                    <?php foreach ($takim_filtreli as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $takim_id === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['takim_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript>
                <button class="btn btn-primary">Filtrele</button>
            </noscript>
        </div>
    </form>

    <?php if (empty($sporcular)): ?>
        <p>Henüz sporcu bulunmuyor.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><a href="<?= sirala_link('ad', $sirala, $yön, $grup_id, $takim_id) ?>">Ad <?= $sirala==='ad' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('soyad', $sirala, $yön, $grup_id, $takim_id) ?>">Soyad <?= $sirala==='soyad' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('takim_adi', $sirala, $yön, $grup_id, $takim_id) ?>">Takım <?= $sirala==='takim_adi' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('grup_adi', $sirala, $yön, $grup_id, $takim_id) ?>">Grup <?= $sirala==='grup_adi' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('toplam_puan', $sirala, $yön, $grup_id, $takim_id) ?>">Puan <?= $sirala==='toplam_puan' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('atis_sayisi', $sirala, $yön, $grup_id, $takim_id) ?>">Atış <?= $sirala==='atis_sayisi' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                        <th><a href="<?= sirala_link('ortalama', $sirala, $yön, $grup_id, $takim_id) ?>">Ort. <?= $sirala==='ortalama' ? ($yön==='ASC' ? '↑' : '↓') : '' ?></a></th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($sporcular as $s): 
                    $ort = $s['atis_sayisi'] > 0 ? round($s['toplam_puan'] / $s['atis_sayisi'], 2) : 0;
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$s['id'] ?>"><?= e($s['ad']) ?></a></td>
                        <td><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$s['id'] ?>"><?= e($s['soyad']) ?></a></td>
                        <td><?= e($s['takim_gosterim'] ?? '-') ?></td>
                        <td><?= e($s['grup_adi'] ?? '-') ?></td>
                        <td><?= (int)$s['toplam_puan'] ?></td>
                        <td><?= (int)$s['atis_sayisi'] ?></td>
                        <td><?= $ort ?></td>
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
