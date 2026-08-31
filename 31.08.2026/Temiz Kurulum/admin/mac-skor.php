<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
/**
 * HAKEM SKOR GİRİŞ SAYFASI
 * - 2 takım × 5 sporcu × 5 set × 7 ok matris
 * - JS ile otomatik set toplamı
 * - Kaydedince: sporcu_set_atislari + mac_setleri + istatistikler güncellenir
 */
$u = kullanici_bilgi();
if (!in_array($u['rol'], ['admin','hakem'], true)) { http_response_code(403); die('Yetkisiz'); }

$mac_id = (int)($_GET['mac_id'] ?? $_POST['mac_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['kaydet']) || isset($_POST['set_kaydet']))) {
    if (!csrf_check($_POST['csrf'] ?? '')) { flash_set('hata','CSRF'); redirect(BASE_URL.'/admin/mac-skor.php'); }
    $mac_id = (int)($_POST['mac_id']); $tamamlananSet = isset($_POST['set_kaydet']) ? max(1,min(SET_SAYISI,(int)($_POST['aktif_set'] ?? 1))) : 0;
    $ev_sporcular = $_POST['ev_sporcu']     ?? [];   // [i] = id
    $dep_sporcular = $_POST['dep_sporcu']   ?? [];
    $ev_atislar    = $_POST['ev_atis']      ?? [];   // [i][set][ok] = puan
    $dep_atislar   = $_POST['dep_atis']     ?? [];

    $mac = $pdo->prepare("SELECT * FROM maclar WHERE id=?"); $mac->execute([$mac_id]); $m = $mac->fetch();
    if (!$m) { flash_set('hata','Maç bulunamadı.'); redirect(BASE_URL.'/admin/mac-skor.php'); }

    $pdo->beginTransaction();
    try {
        // Seçili set tamamlanıyorsa yalnızca o set güncellenir; maç bitiminde tüm kayıt yenilenir.
        if ($tamamlananSet) {
            $pdo->prepare("DELETE FROM sporcu_set_atislari WHERE mac_id=? AND set_no=?")->execute([$mac_id,$tamamlananSet]);
            $pdo->prepare("DELETE FROM mac_setleri WHERE mac_id=? AND set_no=?")->execute([$mac_id,$tamamlananSet]);
            $ilkSet=$sonSet=$tamamlananSet;
        } else { $pdo->prepare("DELETE FROM sporcu_set_atislari WHERE mac_id=?")->execute([$mac_id]); $pdo->prepare("DELETE FROM mac_setleri WHERE mac_id=?")->execute([$mac_id]); $ilkSet=1; $sonSet=SET_SAYISI; }

        // Yeni atışları ekle
        $insert = $pdo->prepare("INSERT INTO sporcu_set_atislari
            (mac_id,set_no,sporcu_id,takim_id,ok1,ok2,ok3,ok4,ok5,ok6,ok7,set_toplam)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");

        $ev_set_toplamlari = array_fill(1, SET_SAYISI, 0);
        $dep_set_toplamlari = array_fill(1, SET_SAYISI, 0);

        // Ev sahibi
        foreach ($ev_sporcular as $i => $sporcu_id) {
            $sporcu_id = (int)$sporcu_id;
            if ($sporcu_id <= 0) continue;
            for ($set = $ilkSet; $set <= $sonSet; $set++) {
                $toplam = 0;
                $row = [];
                for ($ok = 1; $ok <= OK_SAYISI; $ok++) {
                    $v = ok_puan($ev_atislar[$i][$set][$ok] ?? 0);
                    $row[] = $v;
                    $toplam += $v;
                }
                $insert->execute(array_merge([$mac_id, $set, $sporcu_id, $m['ev_sahibi_id']], $row, [$toplam]));
                $ev_set_toplamlari[$set] += $toplam;
            }
        }
        // Deplasman
        foreach ($dep_sporcular as $i => $sporcu_id) {
            $sporcu_id = (int)$sporcu_id;
            if ($sporcu_id <= 0) continue;
            for ($set = $ilkSet; $set <= $sonSet; $set++) {
                $toplam = 0;
                $row = [];
                for ($ok = 1; $ok <= OK_SAYISI; $ok++) {
                    $v = ok_puan($dep_atislar[$i][$set][$ok] ?? 0);
                    $row[] = $v;
                    $toplam += $v;
                }
                $insert->execute(array_merge([$mac_id, $set, $sporcu_id, $m['deplasman_id']], $row, [$toplam]));
                $dep_set_toplamlari[$set] += $toplam;
            }
        }

        // Setleri kaydet
        $setIns = $pdo->prepare("INSERT INTO mac_setleri (mac_id,set_no,ev_sahibi_set_puani,deplasman_set_puani,tamamlandi) VALUES (?,?,?,?,?)");
        for ($set = $ilkSet; $set <= $sonSet; $set++) {
            $setIns->execute([$mac_id, $set, $ev_set_toplamlari[$set], $dep_set_toplamlari[$set], $tamamlananSet ? 1 : 1]);
        }

        $pdo->commit();

        if (!$tamamlananSet) { mac_istatistik_guncelle($pdo, $mac_id); flash_set('basari', 'Maç skorları kaydedildi ve maç tamamlandı.'); }
        else flash_set('basari', $tamamlananSet.'. set tamamlandı; canlı maç ekranına yansıtıldı.');
    } catch (Exception $ex) {
        $pdo->rollBack();
        flash_set('hata', 'Kayıt hatası: ' . $ex->getMessage());
    }
    redirect(BASE_URL.'/admin/mac-skor.php?mac_id='.$mac_id);
}

// Maç listesi (henüz skor girilmemişler önce)
$filtre_grup = (int)($_GET['grup_id'] ?? 0);
$filtre_durum = $_GET['durum'] ?? 'planlandi';
if (!in_array($filtre_durum, ['hepsi','planlandi','oynandi'], true)) $filtre_durum = 'planlandi';
$grup_secimleri = $pdo->query('SELECT id,grup_adi FROM gruplar ORDER BY grup_adi')->fetchAll();
$mac_st = $pdo->prepare("
    SELECT m.*, t1.takim_adi AS ev, t2.takim_adi AS dep, g.grup_adi
    FROM maclar m
    JOIN takimlar t1 ON t1.id = m.ev_sahibi_id
    JOIN takimlar t2 ON t2.id = m.deplasman_id
    JOIN gruplar  g  ON g.id  = m.grup_id
    WHERE (:grup_id = 0 OR m.grup_id = :grup_id2)
      AND (:durum = 'hepsi' OR m.durum = :durum2)
    ORDER BY (m.durum = 'oynandi'), m.tarih, m.saat, m.id
");
$mac_st->execute(['grup_id'=>$filtre_grup,'grup_id2'=>$filtre_grup,'durum'=>$filtre_durum,'durum2'=>$filtre_durum]);
$maclar = $mac_st->fetchAll();

$mac = null; $ev_sporcular = []; $dep_sporcular = [];
$mevcut_atislar = [];   // [takim_tip][sporcu_idx][set_no][ok_no] = puan
$mevcut_setler  = [];

if ($mac_id > 0) {
    $st = $pdo->prepare("SELECT * FROM maclar WHERE id = ?"); $st->execute([$mac_id]); $mac = $st->fetch();
    if ($mac) {
        $ev_sporcular = $pdo->prepare("SELECT * FROM sporcular WHERE takim_id = ? ORDER BY ad, soyad");
        $ev_sporcular->execute([$mac['ev_sahibi_id']]);
        $ev_sporcular = $ev_sporcular->fetchAll();
        // Eğer 5'ten az ise, boş slotlar ekle
        while (count($ev_sporcular) < TAKIM_BASINA_SPORCU) $ev_sporcular[] = null;

        $dep_sporcular = $pdo->prepare("SELECT * FROM sporcular WHERE takim_id = ? ORDER BY ad, soyad");
        $dep_sporcular->execute([$mac['deplasman_id']]);
        $dep_sporcular = $dep_sporcular->fetchAll();
        while (count($dep_sporcular) < TAKIM_BASINA_SPORCU) $dep_sporcular[] = null;

        $atisSt = $pdo->prepare("SELECT * FROM sporcu_set_atislari WHERE mac_id = ?");
        $atisSt->execute([$mac_id]);
        foreach ($atisSt->fetchAll() as $a) {
            $tip = ((int)$a['takim_id'] === (int)$mac['ev_sahibi_id']) ? 'ev' : 'dep';
            $sporcular_ref = ($tip === 'ev') ? $ev_sporcular : $dep_sporcular;
            $idx = -1;
            foreach ($sporcular_ref as $k => $s) if ($s && (int)$s['id'] === (int)$a['sporcu_id']) { $idx = $k; break; }
            if ($idx < 0) continue;
            for ($ok = 1; $ok <= OK_SAYISI; $ok++) {
                $mevcut_atislar[$tip][$idx][(int)$a['set_no']][$ok] = (int)$a['ok'.$ok];
            }
        }
        $setSt = $pdo->prepare("SELECT * FROM mac_setleri WHERE mac_id = ? ORDER BY set_no");
        $setSt->execute([$mac_id]);
        foreach ($setSt->fetchAll() as $s) $mevcut_setler[(int)$s['set_no']] = $s;
    }
}

ob_start();
?>
<?php if (!$mac): ?>
<div class="card">
    <div class="card-head"><h2>✍️ Skor Girişi — Maç Seç</h2></div>
    <form method="get" class="skor-filtre">
        <label>Grup<select name="grup_id"><option value="0">Tüm gruplar</option><?php foreach ($grup_secimleri as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $filtre_grup === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['grup_adi']) ?></option><?php endforeach; ?></select></label>
        <label>Durum<select name="durum"><option value="planlandi" <?= $filtre_durum==='planlandi'?'selected':'' ?>>Skor bekleyen</option><option value="oynandi" <?= $filtre_durum==='oynandi'?'selected':'' ?>>Tamamlanan</option><option value="hepsi" <?= $filtre_durum==='hepsi'?'selected':'' ?>>Tümü</option></select></label>
        <button class="btn btn-primary">Listele</button>
    </form>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Grup</th><th>Hafta</th><th>Tarih</th><th>Ev</th><th>Skor</th><th>Dep</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($maclar as $m): ?>
            <tr>
                <td><?= e($m['grup_adi']) ?></td>
                <td><?= (int)$m['hafta'] ?></td>
                <td><?= tr_tarih($m['tarih']) ?></td>
                <td><strong><?= e($m['ev']) ?></strong></td>
                <td>
                    <?php if ($m['durum']==='oynandi'): ?>
                        <?= (int)$m['ev_sahibi_set'] ?> - <?= (int)$m['deplasman_set'] ?>
                    <?php else: ?><span class="muted">vs</span><?php endif; ?>
                </td>
                <td><?= e($m['dep']) ?></td>
                <td>
                    <?php if ($m['durum']==='oynandi'): ?><span class="badge badge-ok">Oynandı</span>
                    <?php elseif ($m['durum']==='iptal'): ?><span class="badge badge-no">İptal</span>
                    <?php else: ?><span class="badge">Planlandı</span><?php endif; ?>
                </td>
                <td><a href="?mac_id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-primary">Skor Gir</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php else:
    // Sporcu seçim listeleri (her takım için)
    $ev_takim = $pdo->prepare("SELECT * FROM takimlar WHERE id=?"); $ev_takim->execute([$mac['ev_sahibi_id']]); $ev_takim = $ev_takim->fetch();
    $dep_takim = $pdo->prepare("SELECT * FROM takimlar WHERE id=?"); $dep_takim->execute([$mac['deplasman_id']]); $dep_takim = $dep_takim->fetch();
    $ev_adaylar = $pdo->prepare("SELECT * FROM sporcular WHERE takim_id = ? ORDER BY ad, soyad"); $ev_adaylar->execute([$mac['ev_sahibi_id']]); $ev_adaylar = $ev_adaylar->fetchAll();
    $dep_adaylar = $pdo->prepare("SELECT * FROM sporcular WHERE takim_id = ? ORDER BY ad, soyad"); $dep_adaylar->execute([$mac['deplasman_id']]); $dep_adaylar = $dep_adaylar->fetchAll();
?>
<form method="post" id="skorForm" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="mac_id" value="<?= (int)$mac['id'] ?>">
    <input type="hidden" name="kaydet" value="1">

    <div class="card">
        <div class="card-head">
            <h2>📅 <?= e($ev_takim['takim_adi']) ?> <span class="muted">vs</span> <?= e($dep_takim['takim_adi']) ?></h2>
            <small>Hafta <?= (int)$mac['hafta'] ?> · <?= tr_tarih($mac['tarih']) ?> <?= tr_saat($mac['saat']) ?> · <?= e($mac['yer'] ?? '-') ?></small>
        </div>

        <p class="muted">
            Her takım <strong><?= TAKIM_BASINA_SPORCU ?> sporcu</strong> ile yarışır.
            Her sporcu <strong><?= OK_SAYISI ?> ok</strong> atar.
            Karşılaşma <strong><?= SET_SAYISI ?> set</strong> üzerinden oynanır.
            Set kazananı, o setteki 5 sporcunun 7'şer okunun toplam puanıdır.
        </p>
        <div class="skor-filtre"><label>Giriş yapılacak set<select id="setFiltre" name="aktif_set"><?php for($s=1;$s<=SET_SAYISI;$s++): ?><option value="<?= $s ?>" <?= $s===1?'selected':'' ?>><?= $s ?>. Set<?= !empty($mevcut_setler[$s]['tamamlandi'])?' (tamamlandı)':'' ?></option><?php endfor; ?></select></label><span class="muted">Seçili set dışındaki alanlar korunur ve düzenlemeye kapatılır.</span></div>

        <div class="score-grid">
            <!-- EV SAHİBİ -->
            <div class="score-team score-team-ev">
                <h3>🏠 <?= e($ev_takim['takim_adi']) ?> <small>(Ev Sahibi)</small></h3>
                <table class="score-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Sporcu</th>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <th colspan="<?= OK_SAYISI+1 ?>" class="set-head">Set <?= $s ?></th>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <?php for ($o=1; $o<=OK_SAYISI; $o++): ?>
                                    <th class="ok-head">O<?= $o ?></th>
                                <?php endfor; ?>
                                <th class="top-head">Σ</th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php for ($i=0; $i<TAKIM_BASINA_SPORCU; $i++):
                        $mevcut = $ev_sporcular[$i] ?? null;
                    ?>
                        <tr>
                            <td>
                                <select name="ev_sporcu[<?= $i ?>]" class="sporcu-select">
                                    <option value="">— Boş —</option>
                                    <?php foreach ($ev_adaylar as $sa):
                                        $sel = ($mevcut && (int)$mevcut['id']===(int)$sa['id'])?'selected':''; ?>
                                        <option value="<?= (int)$sa['id'] ?>" <?= $sel ?>>
                                            <?= e($sa['ad'].' '.$sa['soyad']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <?php for ($o=1; $o<=OK_SAYISI; $o++):
                                    $val = $mevcut_atislar['ev'][$i][$s][$o] ?? 0;
                                ?>
                                    <td><input type="number" min="0" max="<?= MAKS_OK_PUAN ?>" step="1"
                                        class="ok-input ev-<?= $i ?>-<?= $s ?>"
                                        name="ev_atis[<?= $i ?>][<?= $s ?>][<?= $o ?>]"
                                        value="<?= (int)$val ?>" data-sporcu="<?= $i ?>" data-set="<?= $s ?>" data-takim="ev"></td>
                                <?php endfor; ?>
                                <?php
                                    $sporcu_set_toplami = 0;
                                    for ($oo=1; $oo<=OK_SAYISI; $oo++) {
                                        $sporcu_set_toplami += (int)($mevcut_atislar['ev'][$i][$s][$oo] ?? 0);
                                    }
                                ?>
                                <td class="set-total ev-total-<?= $i ?>-<?= $s ?>" data-set="<?= $s ?>" data-takim="ev"><?= $sporcu_set_toplami ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                        <tr class="team-total-row">
                            <td><strong>Takım Toplam</strong></td>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <td colspan="<?= OK_SAYISI ?>" style="background:#eef;border:0"></td>
                                <td class="team-set-total ev-team-<?= $s ?>" data-set="<?= $s ?>" data-takim="ev">
                                    <?= (int)($mevcut_setler[$s]['ev_sahibi_set_puani'] ?? 0) ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- DEPLASMAN -->
            <div class="score-team score-team-dep">
                <h3>✈️ <?= e($dep_takim['takim_adi']) ?> <small>(Deplasman)</small></h3>
                <table class="score-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Sporcu</th>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <th colspan="<?= OK_SAYISI+1 ?>" class="set-head">Set <?= $s ?></th>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <?php for ($o=1; $o<=OK_SAYISI; $o++): ?>
                                    <th class="ok-head">O<?= $o ?></th>
                                <?php endfor; ?>
                                <th class="top-head">Σ</th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php for ($i=0; $i<TAKIM_BASINA_SPORCU; $i++):
                        $mevcut = $dep_sporcular[$i] ?? null;
                    ?>
                        <tr>
                            <td>
                                <select name="dep_sporcu[<?= $i ?>]" class="sporcu-select">
                                    <option value="">— Boş —</option>
                                    <?php foreach ($dep_adaylar as $sa):
                                        $sel = ($mevcut && (int)$mevcut['id']===(int)$sa['id'])?'selected':''; ?>
                                        <option value="<?= (int)$sa['id'] ?>" <?= $sel ?>>
                                            <?= e($sa['ad'].' '.$sa['soyad']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <?php for ($o=1; $o<=OK_SAYISI; $o++):
                                    $val = $mevcut_atislar['dep'][$i][$s][$o] ?? 0;
                                ?>
                                    <td><input type="number" min="0" max="<?= MAKS_OK_PUAN ?>" step="1"
                                        class="ok-input dep-<?= $i ?>-<?= $s ?>"
                                        name="dep_atis[<?= $i ?>][<?= $s ?>][<?= $o ?>]"
                                        value="<?= (int)$val ?>" data-sporcu="<?= $i ?>" data-set="<?= $s ?>" data-takim="dep"></td>
                                <?php endfor; ?>
                                <?php
                                    $sporcu_set_toplami = 0;
                                    for ($oo=1; $oo<=OK_SAYISI; $oo++) {
                                        $sporcu_set_toplami += (int)($mevcut_atislar['dep'][$i][$s][$oo] ?? 0);
                                    }
                                ?>
                                <td class="set-total dep-total-<?= $i ?>-<?= $s ?>" data-set="<?= $s ?>" data-takim="dep"><?= $sporcu_set_toplami ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                        <tr class="team-total-row">
                            <td><strong>Takım Toplam</strong></td>
                            <?php for ($s=1; $s<=SET_SAYISI; $s++): ?>
                                <td colspan="<?= OK_SAYISI ?>" style="background:#eef;border:0"></td>
                                <td class="team-set-total dep-team-<?= $s ?>" data-set="<?= $s ?>" data-takim="dep">
                                    <?= (int)($mevcut_setler[$s]['deplasman_set_puani'] ?? 0) ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="set-summary">
            <h3>📊 Set Özeti</h3>
            <table class="set-summary-table">
                <thead><tr><th>Set</th><th><?= e($ev_takim['takim_adi']) ?></th><th><?= e($dep_takim['takim_adi']) ?></th><th>Set Skoru</th></tr></thead>
                <tbody>
                <?php for ($s=1; $s<=SET_SAYISI; $s++): $evP=$mevcut_setler[$s]['ev_sahibi_set_puani']??0; $depP=$mevcut_setler[$s]['deplasman_set_puani']??0; ?>
                    <tr>
                        <td><strong>Set <?= $s ?></strong></td>
                        <td><span class="ev-team-<?= $s ?>-summary"><?= (int)$evP ?></span></td>
                        <td><span class="dep-team-<?= $s ?>-summary"><?= (int)$depP ?></span></td>
                        <td>
                            <span class="ev-set-count-<?= $s ?>"></span> -
                            <span class="dep-set-count-<?= $s ?>"></span>
                        </td>
                    </tr>
                <?php endfor; ?>
                <tr class="grand-row">
                    <td><strong>Toplam Set</strong></td>
                    <td colspan="2"></td>
                    <td><strong><span id="evTotalSet">0</span> - <span id="depTotalSet">0</span></strong></td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <button type="submit" name="set_kaydet" value="1" class="btn btn-outline" onclick="return confirm('Seçili setin iki takım puanı kaydedilsin mi?')">✓ Seçili Seti Tamamla</button>
            <button type="submit" name="kaydet" value="1" class="btn btn-primary btn-lg">💾 Skorları Kaydet ve Maçı Tamamla</button>
            <a href="<?= BASE_URL ?>/admin/mac-skor.php" class="btn btn-outline">İptal</a>
        </div>
    </div>
</form>

<script>
(function(){
    const SET_SAYISI = <?= (int)SET_SAYISI ?>;
    const OK_SAYISI  = <?= (int)OK_SAYISI ?>;
    const SPORCU     = <?= (int)TAKIM_BASINA_SPORCU ?>;
    const MAKS       = <?= (int)MAKS_OK_PUAN ?>;

    function recompute(){
        // Sporcu set toplamları + takım set toplamları
        for (let takim of ['ev','dep']) {
            for (let s = 1; s <= SET_SAYISI; s++) {
                let teamSum = 0;
                for (let i = 0; i < SPORCU; i++) {
                    let spSum = 0;
                    for (let o = 1; o <= OK_SAYISI; o++) {
                        const inp = document.querySelector('.ok-input.'+takim+'-'+i+'-'+s);
                        if (!inp) continue;
                        let v = parseInt(inp.value || 0, 10);
                        if (isNaN(v) || v < 0) v = 0;
                        if (v > MAKS) v = MAKS;
                        if (parseInt(inp.value,10) !== v) inp.value = v;
                        spSum += v;
                    }
                    const cell = document.querySelector('.'+takim+'-total-'+i+'-'+s);
                    if (cell) cell.textContent = spSum;
                    teamSum += spSum;
                }
                const tcell = document.querySelector('.'+takim+'-team-'+s);
                if (tcell) tcell.textContent = teamSum;
                const sumA = document.querySelector('.'+takim+'-team-'+s+'-summary');
                if (sumA) sumA.textContent = teamSum;
            }
        }
        // Set kazananı ve toplam set
        let evTotalSet = 0, depTotalSet = 0;
        for (let s = 1; s <= SET_SAYISI; s++) {
            const ev = parseInt((document.querySelector('.ev-team-'+s)?.textContent) || 0, 10);
            const dp = parseInt((document.querySelector('.dep-team-'+s)?.textContent) || 0, 10);
            const evCell = document.querySelector('.ev-set-count-'+s);
            const dpCell = document.querySelector('.dep-set-count-'+s);
            if (evCell) evCell.textContent = ev > dp ? 1 : 0;
            if (dpCell) dpCell.textContent = dp > ev ? 1 : 0;
            if (ev > dp) evTotalSet++;
            if (dp > ev) depTotalSet++;
        }
        const et = document.getElementById('evTotalSet'); if (et) et.textContent = evTotalSet;
        const dt = document.getElementById('depTotalSet'); if (dt) dt.textContent = depTotalSet;
    }

    document.querySelectorAll('.ok-input').forEach(el => {
        el.addEventListener('input', recompute);
        el.addEventListener('change', recompute);
    });
    document.querySelectorAll('.sporcu-select').forEach(el => {
        el.addEventListener('change', recompute);
    });
    const setFiltre=document.getElementById('setFiltre');
    function aktifSetiGoster(){
        const aktif=parseInt(setFiltre?.value||'1',10);
        document.querySelectorAll('.ok-input').forEach(el=>{const acik=parseInt(el.dataset.set,10)===aktif; el.disabled=!acik; el.closest('td').style.opacity=acik?'1':'.28';});
        document.querySelectorAll('.set-total,.team-set-total').forEach(el=>{el.style.opacity=parseInt(el.dataset.set,10)===aktif?'1':'.35';});
    }
    if(setFiltre) setFiltre.addEventListener('change',aktifSetiGoster);
    document.getElementById('skorForm').addEventListener('submit',function(e){if(e.submitter && e.submitter.name==='kaydet') document.querySelectorAll('.ok-input').forEach(el=>el.disabled=false);});
    recompute();
    aktifSetiGoster();
})();
</script>
<?php endif; ?>

<?php
$admin_baslik = 'Skor Girişi';
$admin_aktif  = 'skor';
$admin_icerik = ob_get_clean();
require __DIR__ . '/partials/layout.php';
