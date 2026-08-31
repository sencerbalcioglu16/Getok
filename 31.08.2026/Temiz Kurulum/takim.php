<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.

/**
 * Takım detayı: kadro, maçlar
 */
$sayfa_baslik = 'Takım Detayı';
$aktif = 'takimlar';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT t.*, g.grup_adi FROM takimlar t JOIN gruplar g ON g.id=t.grup_id WHERE t.id=?");
$st->execute([$id]);
$t = $st->fetch();
if (!$t) { echo '<div class="flash flash-hata">Takım bulunamadı.</div>'; require_once __DIR__.'/includes/footer.php'; exit; }
$favori = false;
if (giris_yapmis()) { $fs=$pdo->prepare("SELECT id FROM favoriler WHERE user_id=? AND tur='takim' AND hedef_id=?"); $fs->execute([kullanici_bilgi()['id'],$id]); $favori=(bool)$fs->fetch(); }

$sporcular = $pdo->prepare("SELECT * FROM sporcular WHERE takim_id = ? ORDER BY ad, soyad");
$sporcular->execute([$id]);
$sporcular = $sporcular->fetchAll();

$maclar = $pdo->prepare("
    SELECT m.*, tevt.takim_adi AS ev_adi, tdep.takim_adi AS dep_adi
    FROM maclar m
    JOIN takimlar tevt ON tevt.id = m.ev_sahibi_id
    JOIN takimlar tdep ON tdep.id = m.deplasman_id
    WHERE m.ev_sahibi_id = ? OR m.deplasman_id = ?
    ORDER BY m.tarih DESC, m.id DESC
");
$maclar->execute([$id, $id]);
$maclar = $maclar->fetchAll();
?>
<h1><?= e($t['takim_adi']) ?></h1>
<p class="muted">
    <strong><?= e($t['grup_adi']) ?></strong>
    <?php if ($t['sehir']): ?> · <?= e($t['sehir']) ?><?php endif; ?>
    <?php if ($t['kurulus_yili']): ?> · Kuruluş: <?= e($t['kurulus_yili']) ?><?php endif; ?>
</p>
<?php if (giris_yapmis()): ?><form method="post" action="<?= BASE_URL ?>/favori.php" class="favorite-form"><?= csrf_field() ?><input type="hidden" name="tur" value="takim"><input type="hidden" name="hedef_id" value="<?= $id ?>"><input type="hidden" name="islem" value="<?= $favori?'kaldir':'ekle' ?>"><input type="hidden" name="donus" value="<?= e(BASE_URL.'/takim.php?id='.$id) ?>"><button class="btn <?= $favori?'btn-outline':'btn-primary' ?>"><?= $favori?'★ Takibi bırak':'☆ Takip et' ?></button></form><?php else: ?><p><a href="<?= BASE_URL ?>/login.php" class="btn btn-outline">☆ Takip etmek için giriş yapın</a></p><?php endif; ?>

<section class="card">
    <div class="card-head"><h2>📊 Takım Puan Durumu</h2></div>
    <div class="kpis">
        <div class="kpi"><span>Oynanan</span><strong><?= (int)$t['oynanan_mac'] ?></strong></div>
        <div class="kpi"><span>Galibiyet</span><strong><?= (int)$t['kazanilan_mac'] ?></strong></div>
        <div class="kpi"><span>Mağlubiyet</span><strong><?= (int)$t['kaybedilen_mac'] ?></strong></div>
        <div class="kpi kpi-primary"><span>Toplam Set</span><strong><?= (int)$t['toplam_set'] ?></strong></div>
        <div class="kpi"><span>Averaj (Atış Puanı)</span><strong><?= (int)$t['toplam_puan'] ?></strong></div>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>👥 Sporcu Kadrosu (<?= count($sporcular) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Ad Soyad</th><th>Kategori</th>
                    <th>Toplam Puan</th><th>Atış</th><th>Maç</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sporcular)): ?>
                <tr><td colspan="6" class="muted">Kadro boş.</td></tr>
            <?php else: foreach ($sporcular as $i => $s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$s['id'] ?>"><?= e($s['ad'].' '.$s['soyad']) ?></a></td>
                    <td><?= e($s['kategori'] ?? '-') ?></td>
                    <td><?= (int)$s['toplam_puan'] ?></td>
                    <td><?= (int)$s['atis_sayisi'] ?></td>
                    <td><?= (int)$s['oynanan_mac'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>📅 Maçlar</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Tarih</th><th>Ev Sahibi</th><th>Skor</th><th>Deplasman</th><th>Durum</th></tr>
            </thead>
            <tbody>
            <?php foreach ($maclar as $m): ?>
                <tr>
                    <td><?= tr_tarih($m['tarih']) ?></td>
                    <td><strong><?= e($m['ev_adi']) ?></strong></td>
                    <td>
                        <?php if ($m['durum']==='oynandi'): ?>
                            <?= (int)$m['ev_sahibi_set'] ?> - <?= (int)$m['deplasman_set'] ?>
                            <small>(<?= (int)$m['ev_sahibi_puan'] ?> - <?= (int)$m['deplasman_puan'] ?>)</small>
                        <?php else: ?>
                            <span class="muted">vs</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['dep_adi']) ?></td>
                    <td>
                        <?php if ($m['durum']==='oynandi'): ?>
                            <span class="badge badge-ok">Oynandı</span>
                        <?php elseif ($m['durum']==='iptal'): ?>
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
