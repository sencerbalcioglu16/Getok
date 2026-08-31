<?php
/**
 * Fikstür - Sadece Planlanmış Maçlar, Grup ve Hafta Filtreli (Hafta Zorunlu)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$sayfa_baslik = 'Fikstür';
$aktif = 'fikstur';
require_once __DIR__ . '/includes/header.php';


// Grupları çek
$gruplar = $pdo->query("SELECT id, grup_adi FROM gruplar ORDER BY grup_adi")->fetchAll();
if (empty($gruplar)) {
    echo '<p>Henüz grup tanımlanmamış.</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Seçili grup (GET) - geçerli değilse ilk grubu al
$grup_id = isset($_GET['grup']) ? (int)$_GET['grup'] : 0;
$gecerli_grup_ids = array_column($gruplar, 'id');
if (!in_array($grup_id, $gecerli_grup_ids)) {
    $grup_id = $gecerli_grup_ids[0];
}

// Seçili gruba ait planlanmış maçların haftalarını çek
$haftalar = $pdo->prepare("
    SELECT DISTINCT hafta 
    FROM maclar 
    WHERE durum = 'planlandi' AND grup_id = ? 
    ORDER BY hafta
");
$haftalar->execute([$grup_id]);
$hafta_list = $haftalar->fetchAll(PDO::FETCH_COLUMN);

// Seçili hafta (GET) - geçerli değilse ilk haftayı al
$hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : 0;
if (!in_array($hafta, $hafta_list) && !empty($hafta_list)) {
    $hafta = $hafta_list[0];
}

// Sorgu: sadece planlanmış maçlar, grup ve hafta zorunlu
$sql = "
    SELECT m.*, 
           t1.takim_adi AS ev_adi, 
           t2.takim_adi AS dep_adi, 
           g.grup_adi,
           h.ad AS hakem_ad, h.soyad AS hakem_soyad
    FROM maclar m
    JOIN takimlar t1 ON t1.id = m.ev_sahibi_id
    JOIN takimlar t2 ON t2.id = m.deplasman_id
    JOIN gruplar g ON g.id = m.grup_id
    LEFT JOIN hakemler h ON h.id = m.hakem_id
    WHERE m.durum = 'planlandi' AND m.grup_id = ? AND m.hafta = ?
";

$params = [$grup_id, $hafta];
$sql .= " ORDER BY m.tarih ASC, m.saat ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maclar = $stmt->fetchAll();
} catch (PDOException $e) {
    $maclar = [];
}
?>
<main class="main-content">
<div class="container">

    <h1>📅 Fikstür</h1>
    
    <!-- Filtre Formu -->
    <form method="get" class="form" style="margin-bottom:20px;">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <label style="display:flex; flex-direction:column; gap:4px;">
                Grup
                <select name="grup" onchange="this.form.submit()">
                    <?php foreach ($gruplar as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= $grup_id === (int)$g['id'] ? 'selected' : '' ?>>
                            <?= e($g['grup_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($hafta_list)): ?>
                <label style="display:flex; flex-direction:column; gap:4px;">
                    Hafta
                    <select name="hafta" onchange="this.form.submit()">
                        <?php foreach ($hafta_list as $h): ?>
                            <option value="<?= (int)$h ?>" <?= $hafta === (int)$h ? 'selected' : '' ?>>
                                <?= (int)$h ?>. Hafta
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <span style="color:#718096;padding:8px 0;">Bu gruba ait planlanmış maç yok.</span>
            <?php endif; ?>
            <noscript>
                <button class="btn btn-primary">Filtrele</button>
            </noscript>
        </div>
    </form>

    <?php if (empty($maclar)): ?>
        <p>Seçilen haftaya ait planlanmış maç bulunmuyor.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Grup</th>
                        <th>Hafta</th>
                        <th>Tarih / Saat</th>
                        <th>Ev Sahibi</th>
                        <th>Deplasman</th>
                        <th>Yer</th>
                        <th>Hakem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($maclar as $m): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/mac.php?id=<?= (int)$m['id'] ?>"><?= e($m['grup_adi']) ?></a></td>
                        <td><?= (int)$m['hafta'] ?></td>
                        <td><?= tr_tarih($m['tarih']) ?> <?= tr_saat($m['saat']) ?></td>
                        <td><a href="<?= BASE_URL ?>/mac.php?id=<?= (int)$m['id'] ?>"><strong><?= e($m['ev_adi']) ?></strong></a></td>
                        <td><a href="<?= BASE_URL ?>/mac.php?id=<?= (int)$m['id'] ?>"><?= e($m['dep_adi']) ?></a></td>
                        <td><?= e($m['yer'] ?? '-') ?></td>
                        <td><?= e(trim(($m['hakem_ad']??'').' '.($m['hakem_soyad']??'')) ?: '-') ?></td>
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
