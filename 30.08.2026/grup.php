<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.

/**
 * Grup detayı: fikstür (hafta hafta maçlar)
 */
$sayfa_baslik = 'Grup Detayı';
$aktif = 'gruplar';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$g = $pdo->prepare("SELECT * FROM gruplar WHERE id = ?");
$g->execute([$id]);
$grup = $g->fetch();
if (!$grup) { echo '<div class="flash flash-hata">Grup bulunamadı.</div>'; require_once __DIR__.'/includes/footer.php'; exit; }

$takimlar = $pdo->prepare("SELECT * FROM takimlar WHERE grup_id = ? ORDER BY takim_adi");
$takimlar->execute([$id]);
$takimlar = $takimlar->fetchAll();
$tMap = [];
foreach ($takimlar as $t) $tMap[(int)$t['id']] = $t;

$maclar = $pdo->prepare("
    SELECT m.*, h.ad AS hakem_ad, h.soyad AS hakem_soyad
    FROM maclar m
    LEFT JOIN hakemler h ON h.id = m.hakem_id
    WHERE m.grup_id = ?
    ORDER BY m.hafta, m.tarih, m.saat, m.id
");
$maclar->execute([$id]);
$haftalar = [];
foreach ($maclar->fetchAll() as $m) {
    $haftalar[(int)$m['hafta']][] = $m;
}
?>
<main class="main-content">
<h1><?= e($grup['grup_adi']) ?> · Fikstür</h1>
<p class="muted"><?= e($grup['aciklama']) ?> · <?= e($grup['sezon']) ?> Sezonu</p>

<section class="card">
    <div class="card-head"><h2>📊 Grup Puan Durumu</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Takım</th><th>O</th><th>G</th><th>M</th>
                    <th>Set</th><th>Averaj</th>
                </tr>
            </thead>
            <tbody>
            <?php
            usort($takimlar, function($a,$b){
                if ($a['toplam_set'] != $b['toplam_set']) return $b['toplam_set'] <=> $a['toplam_set'];
                return $b['toplam_puan'] <=> $a['toplam_puan'];
            });
            foreach ($takimlar as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="<?= BASE_URL ?>/takim.php?id=<?= (int)$t['id'] ?>"><?= e($t['takim_adi']) ?></a></td>
                    <td><?= (int)$t['oynanan_mac'] ?></td>
                    <td class="ok"><?= (int)$t['kazanilan_mac'] ?></td>
                    <td class="no"><?= (int)$t['kaybedilen_mac'] ?></td>
                    <td><strong><?= (int)$t['toplam_set'] ?></strong></td>
                    <td><?= (int)$t['toplam_puan'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($haftalar as $hafta => $maclar): ?>
<section class="card">
    <div class="card-head"><h2>📅 <?= (int)$hafta ?>. Hafta</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tarih</th><th>Saat</th><th>Ev Sahibi</th>
                    <th>Skor</th><th>Deplasman</th><th>Yer</th><th>Hakem</th><th>Durum</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($maclar as $m): ?>
                <tr>
                    <td><?= tr_tarih($m['tarih']) ?></td>
                    <td><?= tr_saat($m['saat']) ?></td>
                    <td><strong><?= e($tMap[$m['ev_sahibi_id']]['takim_adi'] ?? '-') ?></strong></td>
                    <td>
                        <?php if ($m['durum'] === 'oynandi'): ?>
                            <span class="score"><?= (int)$m['ev_sahibi_set'] ?> - <?= (int)$m['deplasman_set'] ?></span>
                            <small>(<?= (int)$m['ev_sahibi_puan'] ?> - <?= (int)$m['deplasman_puan'] ?>)</small>
                        <?php else: ?>
                            <span class="muted">vs</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($tMap[$m['deplasman_id']]['takim_adi'] ?? '-') ?></td>
                    <td><?= e($m['yer'] ?? '-') ?></td>
                    <td><?= e(trim(($m['hakem_ad'] ?? '').' '.($m['hakem_soyad'] ?? '')) ?: '-') ?></td>
                    <td>
                        <?php if ($m['durum'] === 'oynandi'): ?>
                            <span class="badge badge-ok">Oynandı</span>
                        <?php elseif ($m['durum'] === 'iptal'): ?>
                            <span class="badge badge-no">İptal</span>
                        <?php else: ?>
                            <span class="badge">Planlandı</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>
</main>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
