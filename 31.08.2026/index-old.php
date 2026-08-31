<?php
/**
 * ANA SAYFA
 * - Haftanın En İyi Takımları (Takımlar Puan Durumu, DESC)
 * - Haftanın En İyi Sporcuları (Sporcular Puan Durumu, DESC)
 * - Duyurular / Haberler / Yönetmelikler (son eklenen)
 */
$sayfa_baslik = 'Ana Sayfa';
$aktif = 'anasayfa';
require_once __DIR__ . '/includes/header.php';

// Haftanın en iyi takımları (toplam_set DESC, toplam_puan DESC)
$en_iyi_takimlar = $pdo->query("
    SELECT t.*, g.grup_adi
    FROM takimlar t
    JOIN gruplar g ON g.id = t.grup_id
    ORDER BY t.toplam_set DESC, t.toplam_puan DESC, t.kazanilan_mac DESC
    LIMIT 10
")->fetchAll();

// Haftanın en iyi sporcuları (toplam_puan DESC)
$en_iyi_sporcular = $pdo->query("
    SELECT s.*, t.takim_adi, g.grup_adi
    FROM sporcular s
    LEFT JOIN takimlar t ON t.id = s.takim_id
    LEFT JOIN gruplar g ON g.id = t.grup_id
    WHERE s.atis_sayisi > 0
    ORDER BY s.toplam_puan DESC, s.atis_sayisi ASC
    LIMIT 10
")->fetchAll();

// Son duyurular
$duyurular = $pdo->query("SELECT * FROM duyurular WHERE yayinda=1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Son haberler
$haberler = $pdo->query("SELECT * FROM haberler WHERE yayinda=1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Son yönetmelikler
$yonetmelikler = $pdo->query("SELECT * FROM yonetmelikler WHERE yayinda=1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-inner">
        <h1>Okçuluk Amatör Ligi</h1>
        <p class="hero-sub"><?= e(LIG_SEZON) ?> Sezonu · <?= e(TAKIM_BASINA_SPORCU) ?> sporcu · <?= e(OK_SAYISI) ?> ok · <?= e(SET_SAYISI) ?> set</p>
        <p class="hero-text">
            5 set üzerinden oynanan karşılaşmalarda en çok seti kazanan takım galip gelir.
            Puan durumu <strong>kazanılan set sayısına</strong> göre sıralanır;
            eşitlik halinde <strong>averaj puana</strong> (toplam atış puanı) bakılır.
        </p>
    </div>
</section>

<!-- Haftanın En İyi Takımları -->
<section class="card">
    <div class="card-head">
        <h2>🏆 Haftanın En İyi Takımları</h2>
        <small>Veriler: <em>takimlar.toplam_set</em> (yüksekten düşüğe)</small>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Takım</th>
                    <th>Grup</th>
                    <th>Set</th>
                    <th>Averaj</th>
                    <th>Maç</th>
                    <th>G</th>
                    <th>M</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($en_iyi_takimlar)): ?>
                <tr><td colspan="8" class="muted">Henüz maç oynanmadı.</td></tr>
            <?php else: foreach ($en_iyi_takimlar as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="<?= BASE_URL ?>/takim.php?id=<?= (int)$t['id'] ?>"><?= e($t['takim_adi']) ?></a></td>
                    <td><?= e($t['grup_adi']) ?></td>
                    <td><strong><?= (int)$t['toplam_set'] ?></strong></td>
                    <td><?= (int)$t['toplam_puan'] ?></td>
                    <td><?= (int)$t['oynanan_mac'] ?></td>
                    <td class="ok"><?= (int)$t['kazanilan_mac'] ?></td>
                    <td class="no"><?= (int)$t['kaybedilen_mac'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Haftanın En İyi Sporcuları -->
<section class="card">
    <div class="card-head">
        <h2>🎯 Haftanın En İyi Sporcuları</h2>
        <small>Veriler: <em>sporcular.toplam_puan</em> (yüksekten düşüğe)</small>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Sporcu</th>
                    <th>Takım</th>
                    <th>Toplam Puan</th>
                    <th>Atış</th>
                    <th>Maç</th>
                    <th>Ortalama</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($en_iyi_sporcular)): ?>
                <tr><td colspan="7" class="muted">Henüz atış verisi girilmedi.</td></tr>
            <?php else: foreach ($en_iyi_sporcular as $i => $s):
                $ort = $s['atis_sayisi']>0 ? round($s['toplam_puan']/$s['atis_sayisi'],2) : 0;
            ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><a href="<?= BASE_URL ?>/sporcu.php?id=<?= (int)$s['id'] ?>"><?= e($s['ad'].' '.$s['soyad']) ?></a></td>
                    <td><?= e($s['takim_adi'] ?? '-') ?></td>
                    <td><strong><?= (int)$s['toplam_puan'] ?></strong></td>
                    <td><?= (int)$s['atis_sayisi'] ?></td>
                    <td><?= (int)$s['oynanan_mac'] ?></td>
                    <td><?= $ort ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="grid-3">
    <!-- DUYURULAR -->
    <section class="card">
        <div class="card-head"><h2>📢 Duyurular</h2>
            <a href="<?= BASE_URL ?>/duyurular.php" class="link-more">Tümü →</a>
        </div>
        <?php if (empty($duyurular)): ?>
            <p class="muted">Henüz duyuru eklenmedi.</p>
        <?php else: foreach ($duyurular as $d): ?>
            <article class="news-item">
                <?php if (!empty($d['gorsel'])): ?>
                    <img src="<?= UPLOAD_URL ?>/<?= e($d['gorsel']) ?>" alt="">
                <?php endif; ?>
                <h3><?= e($d['baslik']) ?></h3>
                <small><?= tr_tarih_saat($d['created_at']) ?></small>
                <div class="news-content"><?= guvenli_html($d['icerik']) ?></div>
            </article>
        <?php endforeach; endif; ?>
    </section>

    <!-- HABERLER -->
    <section class="card">
        <div class="card-head"><h2>📰 Haberler</h2>
            <a href="<?= BASE_URL ?>/haberler.php" class="link-more">Tümü →</a>
        </div>
        <?php if (empty($haberler)): ?>
            <p class="muted">Henüz haber eklenmedi.</p>
        <?php else: foreach ($haberler as $h): ?>
            <article class="news-item">
                <?php if (!empty($h['gorsel'])): ?>
                    <img src="<?= UPLOAD_URL ?>/<?= e($h['gorsel']) ?>" alt="">
                <?php endif; ?>
                <h3><?= e($h['baslik']) ?></h3>
                <small><?= tr_tarih_saat($h['created_at']) ?></small>
                <p><?= e($h['ozet'] ?: mb_substr(strip_tags($h['icerik']), 0, 140)) ?>...</p>
            </article>
        <?php endforeach; endif; ?>
    </section>

    <!-- YÖNETMELİKLER -->
    <section class="card">
        <div class="card-head"><h2>📜 Yönetmelikler</h2>
            <a href="<?= BASE_URL ?>/yonetmelik.php" class="link-more">Tümü →</a>
        </div>
        <?php if (empty($yonetmelikler)): ?>
            <p class="muted">Henüz yönetmelik eklenmedi.</p>
        <?php else: foreach ($yonetmelikler as $y): ?>
            <article class="news-item">
                <h3><?= e($y['baslik']) ?></h3>
                <small><?= tr_tarih_saat($y['created_at']) ?></small>
            </article>
        <?php endforeach; endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
