<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
$sporcuSt = $pdo->prepare("SELECT s.*, t.takim_adi FROM sporcular s LEFT JOIN takimlar t ON t.id=s.takim_id WHERE s.id=?");
$sporcuSt->execute([$id]); $sporcu = $sporcuSt->fetch();
if (!$sporcu) { http_response_code(404); exit('Sporcu bulunamadı.'); }
$atisSt = $pdo->prepare("SELECT sa.set_no,sa.set_toplam,m.id AS mac_id,m.tarih,ev.takim_adi AS ev_sahibi,dep.takim_adi AS deplasman,l.lig_adi
    FROM sporcu_set_atislari sa JOIN maclar m ON m.id=sa.mac_id
    LEFT JOIN takimlar ev ON ev.id=m.ev_sahibi_id LEFT JOIN takimlar dep ON dep.id=m.deplasman_id
    LEFT JOIN gruplar g ON g.id=m.grup_id LEFT JOIN ligler l ON l.id=g.lig_id
    WHERE sa.sporcu_id=? ORDER BY m.tarih DESC,m.id DESC,sa.set_no DESC");
$atisSt->execute([$id]); $atislar=$atisSt->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <a class="sonuc-geri-btn" href="sporcu.php?id=<?= $id ?>">← Geri</a>
    <section class="page-heading"><span class="eyebrow">SPORCU PERFORMANSI</span><h1><?= e(trim($sporcu['ad'].' '.$sporcu['soyad'])) ?> · Tüm Karşılaşmalar</h1><p>Kaydedilmiş set atışları, en yeniden eskiye sıralanır.</p></section>
    <section class="card table-card">
        <?php if ($atislar): ?><div class="table-wrap"><table><thead><tr><th>Tarih</th><th>Karşılaşma</th><th>Lig</th><th>Set</th><th>Puan</th></tr></thead><tbody>
            <?php foreach($atislar as $atis): ?><tr><td><?= tr_tarih($atis['tarih']) ?></td><td><a href="mac.php?id=<?= (int)$atis['mac_id'] ?>"><?= e($atis['ev_sahibi'] ?: 'Bireysel atış') ?><?= $atis['deplasman'] ? ' – '.e($atis['deplasman']) : '' ?></a></td><td><?= e($atis['lig_adi'] ?: '-') ?></td><td><?= (int)$atis['set_no'] ?></td><td><b><?= (int)$atis['set_toplam'] ?></b></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><p class="empty-state">Bu sporcu için henüz atış kaydı bulunmuyor.</p><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/sidebar.php'; require __DIR__ . '/includes/footer.php'; ?>
