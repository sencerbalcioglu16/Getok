<?php

// === 1) ÖNCE KONFİGÜRASYONLARI YÜKLE ===
require_once __DIR__ . '/config/config.php';   // BASE_URL, LIG_SEZON, vs.
require_once __DIR__ . '/config/database.php'; // PDO bağlantısı ($pdo)
require_once __DIR__ . '/includes/functions.php'; // e(), tr_tarih_saat(), vs.

$sayfa_baslik = 'Sporcu Detayı';
$aktif = 'sporcular';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("
    SELECT s.*, t.takim_adi, g.grup_adi
    FROM sporcular s
    LEFT JOIN takimlar t ON t.id = s.takim_id
    LEFT JOIN gruplar  g ON g.id = t.grup_id
    WHERE s.id = ?
");
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { echo '<div class="flash flash-hata">Sporcu bulunamadı.</div>'; require_once __DIR__.'/includes/footer.php'; exit; }
$favori = false;
if (giris_yapmis()) { $fs=$pdo->prepare("SELECT id FROM favoriler WHERE user_id=? AND tur='sporcu' AND hedef_id=?"); $fs->execute([kullanici_bilgi()['id'],$id]); $favori=(bool)$fs->fetch(); }

$ort = $s['atis_sayisi']>0 ? round($s['toplam_puan']/$s['atis_sayisi'],2) : 0;
?>
<h1><?= e($s['ad'].' '.$s['soyad']) ?></h1>
<p class="muted">
    <?= e($s['takim_adi'] ?? '—') ?>
    <?php if ($s['grup_adi']): ?> · <?= e($s['grup_adi']) ?><?php endif; ?>
    <?php if ($s['kategori']): ?> · <?= e($s['kategori']) ?><?php endif; ?>
</p>
<section class="follow-card">
    <div class="follow-card-icon">🎯</div>
    <div class="follow-card-copy"><span class="eyebrow">Sporcu takibi</span><h2><?= $favori ? 'Bu sporcuyu takip ediyorsunuz' : 'Bu sporcuyu takip edin' ?></h2><p>Favorilerinize ekleyerek maç ve performans bilgilerine hızlıca ulaşın.</p></div>
    <?php if (giris_yapmis()): ?><form method="post" action="<?= BASE_URL ?>/favori.php" class="follow-card-action"><?= csrf_field() ?><input type="hidden" name="tur" value="sporcu"><input type="hidden" name="hedef_id" value="<?= $id ?>"><input type="hidden" name="islem" value="<?= $favori?'kaldir':'ekle' ?>"><input type="hidden" name="donus" value="<?= e(BASE_URL.'/sporcu.php?id='.$id) ?>"><button class="btn <?= $favori?'btn-outline':'btn-primary' ?>"><?= $favori?'★ Takibi bırak':'☆ Favorilere ekle' ?></button></form><?php else: ?><a href="<?= BASE_URL ?>/login.php" class="btn btn-outline follow-card-action">☆ Takip etmek için giriş yapın</a><?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>🎯 Sporcu Puan Durumu</h2></div>
    <div class="kpis">
        <div class="kpi kpi-primary"><span>Toplam Puan</span><strong><?= (int)$s['toplam_puan'] ?></strong></div>
        <div class="kpi"><span>Atış Sayısı</span><strong><?= (int)$s['atis_sayisi'] ?></strong></div>
        <div class="kpi"><span>Ortalama (puan/ok)</span><strong><?= $ort ?></strong></div>
        <div class="kpi"><span>Oynanan Maç</span><strong><?= (int)$s['oynanan_mac'] ?></strong></div>
    </div>
</section>

<section class="card">
    <div class="card-head"><h2>📝 Bilgiler</h2></div>
    <dl class="info-list">
        <dt>TC Kimlik</dt><dd><?= e($s['tc_kimlik'] ?? '-') ?></dd>
        <dt>Doğum Tarihi</dt><dd><?= tr_tarih($s['dogum_tarihi']) ?></dd>
        <dt>Cinsiyet</dt><dd><?= $s['cinsiyet']==='E'?'Erkek':($s['cinsiyet']==='K'?'Kadın':'-') ?></dd>
        <dt>Lisans No</dt><dd><?= e($s['lisans_no'] ?? '-') ?></dd>
        <dt>Telefon</dt><dd><?= e($s['telefon'] ?? '-') ?></dd>
        <dt>E-posta</dt><dd><?= e($s['email'] ?? '-') ?></dd>
    </dl>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
