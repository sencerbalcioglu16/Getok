<?php
/**
 * Fikstür - Sadece Planlanmış Maçlar, Grup Filtreli
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$sayfa_baslik = 'Fikstür';
$aktif = 'fikstur';
require_once __DIR__ . '/includes/header.php';

// Seçili grup ID'si (GET)
$grup_id = isset($_GET['grup']) ? (int)$_GET['grup'] : 0;

// Grupları çek (filtreleme için)
$gruplar = $pdo->query("SELECT id, grup_adi FROM gruplar ORDER BY grup_adi")->fetchAll();

// Sorgu: sadece planlanmış maçlar, isteğe bağlı grup filtresi
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
    WHERE m.durum = 'planlandi'
";

$params = [];
if ($grup_id > 0) {
    $sql .= " AND m.grup_id = ?";
    $params[] = $grup_id;
}

$sql .= " ORDER BY m.tarih ASC, m.saat ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maclar = $stmt->fetchAll();
} catch (PDOException $e) {
    $maclar = [];
}
?>
<div class="container">
    <h1>📅 Fikstür</h1>
    
    <!-- Filtre Formu -->
    <form method="get" class="form" style="margin-bottom:20px;">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <label style="display:flex; flex-direction:column; gap:4px;">
                Grup Seç
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

    <?php if (empty($maclar)): ?>
        <p>Bu gruba ait planlanmış maç bulunmuyor.</p>
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
                        <td><?= e($m['grup_adi']) ?></td>
                        <td><?= (int)$m['hafta'] ?></td>
                        <td><?= tr_tarih($m['tarih']) ?> <?= tr_saat($m['saat']) ?></td>
                        <td><strong><?= e($m['ev_adi']) ?></strong></td>
                        <td><?= e($m['dep_adi']) ?></td>
                        <td><?= e($m['yer'] ?? '-') ?></td>
                        <td><?= e(trim(($m['hakem_ad']??'').' '.($m['hakem_soyad']??'')) ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>