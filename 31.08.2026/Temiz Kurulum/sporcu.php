<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$sporcuSt = $pdo->prepare("SELECT s.*, t.id AS takim_id, t.takim_adi, g.grup_adi, g.bolge_adi, g.kategori_adi,
    l.lig_adi, l.sezon_id, sz.sezon_adi
    FROM sporcular s
    LEFT JOIN takimlar t ON t.id=s.takim_id
    LEFT JOIN gruplar g ON g.id=t.grup_id
    LEFT JOIN ligler l ON l.id=g.lig_id
    LEFT JOIN sezonlar sz ON sz.id=l.sezon_id
    WHERE s.id=?");
$sporcuSt->execute([$id]);
$sporcu = $sporcuSt->fetch();
if (!$sporcu) { http_response_code(404); exit('Sporcu bulunamadı.'); }

$favori = false;
if (giris_yapmis()) {
    $favSt = $pdo->prepare("SELECT id FROM favoriler WHERE user_id=? AND tur='sporcu' AND hedef_id=?");
    $favSt->execute([kullanici_bilgi()['id'], $id]);
    $favori = (bool)$favSt->fetch();
}

$kayitSt = $pdo->prepare("SELECT k.toplam_puan AS puan, l.lig_adi, g.grup_adi, g.bolge_adi, g.kategori_adi
    FROM bireysel_lig_kayitlari k
    JOIN gruplar g ON g.id=k.grup_id
    JOIN ligler l ON l.id=g.lig_id
    WHERE k.sporcu_id=?
    ORDER BY l.lig_adi, g.bolge_adi, g.grup_adi");
$kayitSt->execute([$id]);
$ligKayitlari = $kayitSt->fetchAll();

$atisSql = "SELECT sa.set_no, sa.set_toplam, m.id AS mac_id, m.tarih, m.saat, ev.takim_adi AS ev_sahibi, dep.takim_adi AS deplasman,
    l.lig_adi, sz.sezon_adi
    FROM sporcu_set_atislari sa
    JOIN maclar m ON m.id=sa.mac_id
    LEFT JOIN takimlar ev ON ev.id=m.ev_sahibi_id
    LEFT JOIN takimlar dep ON dep.id=m.deplasman_id
    LEFT JOIN gruplar g ON g.id=m.grup_id
    LEFT JOIN ligler l ON l.id=g.lig_id
    LEFT JOIN sezonlar sz ON sz.id=l.sezon_id
    WHERE sa.sporcu_id=?";
$atisParams = [$id];
if (!empty($sporcu['sezon_id'])) { $atisSql .= " AND l.sezon_id=?"; $atisParams[] = $sporcu['sezon_id']; }
$atisSql .= " ORDER BY m.tarih DESC, m.id DESC, sa.set_no DESC LIMIT 6";
$atisSt = $pdo->prepare($atisSql); $atisSt->execute($atisParams); $sonAtislar = $atisSt->fetchAll();

$turnuvaSt = $pdo->prepare("SELECT tsa.set_no, tsa.set_toplam, tm.id AS mac_id, tm.tarih, tr.turnuva_adi, tr.tur
    FROM turnuva_sporcu_set_atislari tsa
    JOIN turnuva_maclari tm ON tm.id=tsa.turnuva_mac_id
    JOIN turnuvalar tr ON tr.id=tm.turnuva_id
    WHERE tsa.sporcu_id=? AND tr.tur=?
    ORDER BY tm.tarih DESC, tm.id DESC, tsa.set_no DESC LIMIT 1");
$turnuvaSt->execute([$id,'takim']); $sonTakimTurnuvaAtisi = $turnuvaSt->fetch();
$turnuvaSt->execute([$id,'bireysel']); $sonBireyselTurnuvaAtisi = $turnuvaSt->fetch();

$toplamPuan = (int)($sporcu['toplam_puan'] ?? 0);
$atisSayisi = (int)($sporcu['atis_sayisi'] ?? 0);
$ortalama = $atisSayisi > 0 ? number_format($toplamPuan / $atisSayisi, 2, ',', '.') : '0,00';
$adSoyad = trim($sporcu['ad'] . ' ' . $sporcu['soyad']);
require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <section class="sporcu-profil">
        <div class="sporcu-foto">
            <?php if (!empty($sporcu['foto'])): ?><img src="<?= UPLOAD_URL ?>/sporcular/<?= e(basename($sporcu['foto'])) ?>" alt="<?= e($adSoyad) ?>">
            <?php else: ?><span>🏹</span><?php endif; ?>
        </div>
        <div class="sporcu-profil-yazi">
            <h1><?= e($adSoyad) ?></h1>
            <p class="sporcu-kimlik"><?= e($sporcu['kategori'] ?: 'Kategori belirtilmemiş') ?>
                <?php if ($sporcu['takim_id']): ?> · <a href="takim.php?id=<?= (int)$sporcu['takim_id'] ?>"><?= e($sporcu['takim_adi']) ?></a><?php endif; ?>
            </p>
            <?php if ($sporcu['grup_adi']): ?><p class="muted"><?= e($sporcu['lig_adi']) ?> · <?= e($sporcu['bolge_adi'] ?: $sporcu['grup_adi']) ?><?= $sporcu['kategori_adi'] ? ' · ' . e($sporcu['kategori_adi']) : '' ?></p><?php endif; ?>
        </div>
        <?php if (giris_yapmis()): ?>
            <form method="post" action="<?= BASE_URL ?>/favori.php" class="sporcu-takip-form"><?= csrf_field() ?><input type="hidden" name="tur" value="sporcu"><input type="hidden" name="hedef_id" value="<?= $id ?>"><input type="hidden" name="islem" value="<?= $favori ? 'kaldir' : 'ekle' ?>"><input type="hidden" name="donus" value="<?= e(BASE_URL . '/sporcu.php?id=' . $id) ?>"><button class="btn <?= $favori ? 'btn-outline' : 'btn-primary' ?>"><?= $favori ? '★ Takibi bırak' : '☆ Takip et' ?></button></form>
        <?php endif; ?>
    </section>

    <div class="sporcu-kart-duzeni">
        <div>
            <section class="card bilgi-karti">
                <h2>Sporcu Bilgileri</h2>
                <div class="bilgi-listesi">
                    <div><span>Kategori</span><b><?= e($sporcu['kategori'] ?: '-') ?></b></div>
                    <div><span>Takım</span><b><?php if ($sporcu['takim_id']): ?><a href="takim.php?id=<?= (int)$sporcu['takim_id'] ?>"><?= e($sporcu['takim_adi']) ?></a><?php else: ?>Bağımsız sporcu<?php endif; ?></b></div>
                    <div><span>Lisans No</span><b><?= e($sporcu['lisans_no'] ?: '-') ?></b></div>
                    <div><span>Doğum Tarihi</span><b><?= $sporcu['dogum_tarihi'] ? tr_tarih($sporcu['dogum_tarihi']) : '-' ?></b></div>
                </div>
            </section>

            <section class="card bilgi-karti">
                <h2>Bireysel Bölge Ligleri</h2>
                <?php if ($ligKayitlari): ?><div class="lig-kayit-listesi">
                    <?php foreach ($ligKayitlari as $kayit): ?><div>
                        <span><b><?= e($kayit['lig_adi']) ?></b><small><?= e($kayit['bolge_adi'] ?: $kayit['grup_adi']) ?><?= $kayit['kategori_adi'] ? ' · ' . e($kayit['kategori_adi']) : '' ?></small></span>
                        <strong><?= (int)$kayit['puan'] ?> <small>puan</small></strong>
                    </div><?php endforeach; ?>
                </div><?php else: ?><p class="empty-state">Bu sporcu henüz bir Bireysel Bölge Ligine kayıtlı değil.</p><?php endif; ?>
            </section>

            <section class="card bilgi-karti son-atis-karti">
                <div class="card-baslik"><h2>Son Karşılaşmalar</h2><a class="mini-link" href="sporcu-atislar.php?id=<?= $id ?>">Tümünü görüntüle →</a></div>
                <?php if ($sonAtislar): ?><div class="sporcu-atis-listesi">
                    <?php foreach ($sonAtislar as $atis): ?><a href="mac.php?id=<?= (int)$atis['mac_id'] ?>" class="sporcu-atis-satiri">
                        <span><b><?= e($atis['ev_sahibi'] ?: 'Bireysel atış') ?><?= $atis['deplasman'] ? ' – ' . e($atis['deplasman']) : '' ?></b><small><?= tr_tarih($atis['tarih']) ?> · Set <?= (int)$atis['set_no'] ?></small></span>
                        <strong><?= (int)$atis['set_toplam'] ?> <small>puan</small></strong>
                    </a><?php endforeach; ?>
                </div><?php else: ?><p class="empty-state">Bu sezonda kaydedilmiş bir karşılaşma performansı yok.</p><?php endif; ?>
            </section>
        </div>

        <aside class="sporcu-performans">
            <section class="card"><h2>Lig Performansı</h2>
                <div class="performans-satir"><span>Toplam Puan</span><b><?= $toplamPuan ?></b></div>
                <div class="performans-satir"><span>Atış Sayısı</span><b><?= $atisSayisi ?></b></div>
                <div class="performans-satir"><span>Puan Ortalaması</span><b><?= $ortalama ?></b></div>
                <div class="performans-satir"><span>Oynanan Karşılaşma</span><b><?= (int)($sporcu['oynanan_mac'] ?? 0) ?></b></div>
            </section>
            <section class="card"><h2>Turnuva Performansı</h2>
                <?php if ($sonTakimTurnuvaAtisi || $sonBireyselTurnuvaAtisi): ?>
                    <?php if ($sonTakimTurnuvaAtisi): ?><div class="performans-satir"><span>Son Takım Turnuvası</span><b><?= e($sonTakimTurnuvaAtisi['turnuva_adi']) ?></b></div><div class="performans-satir"><span>Son set puanı</span><b><?= (int)$sonTakimTurnuvaAtisi['set_toplam'] ?></b></div><a class="mini-link" href="turnuva-mac.php?id=<?= (int)$sonTakimTurnuvaAtisi['mac_id'] ?>">Takım karşılaşmasını görüntüle →</a><?php endif; ?>
                    <?php if ($sonBireyselTurnuvaAtisi): ?><div class="performans-satir"><span>Son Bireysel Turnuva</span><b><?= e($sonBireyselTurnuvaAtisi['turnuva_adi']) ?></b></div><div class="performans-satir"><span>Son set puanı</span><b><?= (int)$sonBireyselTurnuvaAtisi['set_toplam'] ?></b></div><a class="mini-link" href="turnuva-mac.php?id=<?= (int)$sonBireyselTurnuvaAtisi['mac_id'] ?>">Bireysel karşılaşmayı görüntüle →</a><?php endif; ?>
                <?php else: ?><p class="empty-state">Henüz turnuva performansı yok.</p><?php endif; ?>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/sidebar.php'; require __DIR__ . '/includes/footer.php'; ?>
