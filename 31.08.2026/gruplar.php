<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.

/**
 * Tüm Gruplar + Grup Puan Durumu
 */
$sayfa_baslik = 'Gruplar';
$aktif = 'gruplar';
require_once __DIR__ . '/includes/header.php';

$gruplar = $pdo->query("SELECT g.* FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE l.tur='takim' AND l.aktif=1 ORDER BY g.grup_adi")->fetchAll();
?>
<main class="main-content">
<h1>Gruplar ve Grup Puan Durumu</h1>
<p class="muted">Her grupta <?= (int)GRUP_TAKIM_SAYISI ?> takım mücadele eder. Sıralama <strong>kazanılan set sayısına</strong> göredir; eşitlik halinde <strong>averaj puana</strong> bakılır.</p>

<?php foreach ($gruplar as $g):
    $takimlar = $pdo->prepare("
        SELECT * FROM takimlar WHERE grup_id = ?
        ORDER BY toplam_set DESC, toplam_puan DESC, kazanilan_mac DESC
    ");
    $takimlar->execute([$g['id']]);
    $takimlar = $takimlar->fetchAll();
?>
<section class="card">
    <div class="card-head">
        <h2>📋 <?= e($g['grup_adi']) ?></h2>
        <small><?= e($g['aciklama']) ?></small>
        <a href="<?= BASE_URL ?>/grup.php?id=<?= (int)$g['id'] ?>" class="link-more">Fikstür →</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Takım</th>
                    <th>O</th>
                    <th>G</th>
                    <th>M</th>
                    <th>Set</th>
                    <th>Averaj</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($takimlar)): ?>
                <tr><td colspan="7" class="muted">Bu grupta takım yok.</td></tr>
            <?php else: foreach ($takimlar as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="<?= BASE_URL ?>/takim.php?id=<?= (int)$t['id'] ?>"><?= e($t['takim_adi']) ?></a></td>
                    <td><?= (int)$t['oynanan_mac'] ?></td>
                    <td class="ok"><?= (int)$t['kazanilan_mac'] ?></td>
                    <td class="no"><?= (int)$t['kaybedilen_mac'] ?></td>
                    <td><strong><?= (int)$t['toplam_set'] ?></strong></td>
                    <td><?= (int)$t['toplam_puan'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>
</main>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
