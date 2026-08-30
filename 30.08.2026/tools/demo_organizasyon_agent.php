<?php
/**
 * Demo organizasyon agentı
 * Çalıştırma: C:\xampp\php\php.exe tools\demo_organizasyon_agent.php
 * Uyarı: Mevcut lig, grup, takım, sporcu, maç ve turnuva verilerini siler.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli' && !defined('DEMO_KURULUM_AGENT')) { http_response_code(403); exit("Bu agent yalnızca komut satırından çalıştırılır.\n"); }
mt_srand(20260827);

function agent_log($text) { if (!defined('DEMO_KURULUM_AGENT')) echo "[agent] $text\n"; }
function agent_oklar() { $oklar=[]; for($i=0;$i<7;$i++) $oklar[]=mt_rand(0,3); return $oklar; }
function agent_atlet($oklar) { return array_sum($oklar); } // 7 ok × en fazla 3 puan = en fazla 21
function agent_foto($hedef, $urls, $fallbackEtiket) {
    static $indirmeOnbellegi=[];
    foreach ($urls as $url) {
        if (isset($indirmeOnbellegi[$url])) { file_put_contents($hedef,$indirmeOnbellegi[$url]); return basename($hedef); }
        $veri = false;
        if (function_exists('curl_init')) {
            $c=curl_init($url); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_USERAGENT=>'OkculukLigiDemoAgent/1.0']); $veri=curl_exec($c); $kod=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
            if ($kod!==200) $veri=false;
        }
        if ($veri && strlen($veri)>5000) { $indirmeOnbellegi[$url]=$veri; file_put_contents($hedef,$veri); return basename($hedef); }
    }
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="100%" height="100%" fill="#163d2d"/><circle cx="300" cy="260" r="118" fill="#c58b2d"/><text x="300" y="470" text-anchor="middle" font-size="42" fill="white" font-family="Arial">'.htmlspecialchars($fallbackEtiket,ENT_QUOTES,'UTF-8').'</text></svg>';
    $svgHedef=preg_replace('/\.[^.]+$/','.svg',$hedef); file_put_contents($svgHedef,$svg); return basename($svgHedef);
}
function agent_logo($hedef,$ad,$renk) {
    $harf=mb_strtoupper(mb_substr($ad,0,1,'UTF-8'),'UTF-8');
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420"><rect width="420" height="420" rx="42" fill="#163d2d"/><circle cx="210" cy="210" r="158" fill="none" stroke="'.$renk.'" stroke-width="18"/><path d="M85 250 L210 70 L335 250" fill="none" stroke="'.$renk.'" stroke-width="18"/><text x="210" y="315" text-anchor="middle" fill="white" font-size="115" font-family="Georgia" font-weight="bold">'.$harf.'</text></svg>';
    file_put_contents($hedef,$svg); return basename($hedef);
}
function agent_set_oyna($pdo,$macId,$setNo,$evSporcular,$depSporcular,$tur='lig') {
    $evTop=0;$depTop=0;$kayit=[];
    foreach (['ev'=>$evSporcular,'dep'=>$depSporcular] as $taraf=>$sporcular) foreach($sporcular as $sporcu) { $ok=agent_oklar();$puan=agent_atlet($ok);$kayit[$taraf][]=[$sporcu,$ok,$puan]; if($taraf==='ev')$evTop+=$puan;else $depTop+=$puan; }
    if($evTop===$depTop){ // Eşitlik bozma ayrı tutulur; normal atış/averaja eklenmez.
        $evEB=mt_rand(9,21);$depEB=mt_rand(9,21);if($evEB===$depEB)$depEB=$depEB===21?20:$depEB+1;$kazanan=$evEB>$depEB?'ev':'dep';
        if($tur==='lig')$pdo->prepare("INSERT INTO esitlik_bozma_atislari(kaynak,mac_id,set_no,tur_no,ev_puan,dep_puan) VALUES('lig',?,?,?,?,?)")->execute([$macId,$setNo,1,$evEB,$depEB]);
        else $pdo->prepare("INSERT INTO esitlik_bozma_atislari(kaynak,turnuva_mac_id,set_no,tur_no,ev_puan,dep_puan) VALUES('turnuva',?,?,?,?,?)")->execute([$macId,$setNo,1,$evEB,$depEB]);
    } else $kazanan=$evTop>$depTop?'ev':'dep';
    if($tur==='lig'){
        $ins=$pdo->prepare('INSERT INTO sporcu_set_atislari(mac_id,set_no,sporcu_id,takim_id,ok1,ok2,ok3,ok4,ok5,ok6,ok7,set_toplam) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($kayit as $taraf=>$satirlar)foreach($satirlar as [$s,$ok,$puan])$ins->execute([$macId,$setNo,$s['id'],$s['takim_id'],$ok[0],$ok[1],$ok[2],$ok[3],$ok[4],$ok[5],$ok[6],$puan]);
        $pdo->prepare('INSERT INTO mac_setleri(mac_id,set_no,ev_sahibi_set_puani,deplasman_set_puani,tamamlandi,kazanan) VALUES(?,?,?,?,1,?)')->execute([$macId,$setNo,$evTop,$depTop,$kazanan]);
    } else {
        $ins=$pdo->prepare('INSERT INTO turnuva_sporcu_set_atislari(turnuva_mac_id,set_no,taraf,sporcu_id,ok1,ok2,ok3,ok4,ok5,ok6,ok7,set_toplam) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($kayit as $taraf=>$satirlar)foreach($satirlar as [$s,$ok,$puan])$ins->execute([$macId,$setNo,$taraf,$s['id'],$ok[0],$ok[1],$ok[2],$ok[3],$ok[4],$ok[5],$ok[6],$puan]);
        $pdo->prepare('INSERT INTO turnuva_mac_setleri(turnuva_mac_id,set_no,puan1,puan2,tamamlandi,kazanan) VALUES(?,?,?,?,1,?)')->execute([$macId,$setNo,$evTop,$depTop,$kazanan]);
    }
    return [$kazanan,$evTop,$depTop];
}
function agent_mac_oyna($pdo,$grupId,$ev,$dep,$hafta,$tarih,$hakemId,$sporcular) {
    $pdo->prepare("INSERT INTO maclar(grup_id,ev_sahibi_id,deplasman_id,hafta,tarih,saat,yer,hakem_id,durum) VALUES(?,?,?,?,?,'14:00:00','Geleneksel Okçuluk Sahası',?,'oynandi')")->execute([$grupId,$ev,$dep,$hafta,$tarih,$hakemId]);$id=(int)$pdo->lastInsertId();$set=[0,0];$puan=[0,0];
    for($no=1;$no<=5;$no++){[$kazanan,$evP,$depP]=agent_set_oyna($pdo,$id,$no,$sporcular[$ev],$sporcular[$dep]);$set[$kazanan==='ev'?0:1]++;$puan[0]+=$evP;$puan[1]+=$depP;}
    $pdo->prepare('UPDATE maclar SET ev_sahibi_set=?,deplasman_set=?,ev_sahibi_puan=?,deplasman_puan=? WHERE id=?')->execute([$set[0],$set[1],$puan[0],$puan[1],$id]); return $id;
}
function agent_turnuva_mac($pdo,$turnuvaId,$turNo,$sira,$ev,$dep,$tur,$sporcular,$tarih,$tamamla=true) {
    $pdo->prepare('INSERT INTO turnuva_eslesmeleri(turnuva_id,tur_no,eslesme_no,katilimci1_id,katilimci2_id,durum) VALUES(?,?,?,?,?,?)')->execute([$turnuvaId,$turNo,$sira,$ev,$dep,$tamamla?'tamamlandi':'planlandi']);$eslesme=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO turnuva_maclari(turnuva_id,eslesme_id,katilimci1_id,katilimci2_id,tur_no,tarih,saat,yer,durum) VALUES(?,?,?,?,?,?,'15:00:00','Geleneksel Okçuluk Sahası',?)")->execute([$turnuvaId,$eslesme,$ev,$dep,$turNo,$tarih,$tamamla?'oynandi':'planlandi']);$mac=(int)$pdo->lastInsertId(); if(!$tamamla)return $mac;
    $set=[0,0];for($no=1;$no<=5;$no++){[$kazanan]=$tur==='takim'?agent_set_oyna($pdo,$mac,$no,$sporcular[$ev],$sporcular[$dep],'turnuva'):agent_set_oyna($pdo,$mac,$no,[$sporcular[$ev]],[$sporcular[$dep]],'turnuva');$set[$kazanan==='ev'?0:1]++;}
    $kazanan=$set[0]>$set[1]?$ev:$dep;$pdo->prepare('UPDATE turnuva_maclari SET puan1=?,puan2=? WHERE id=?')->execute([$set[0],$set[1],$mac]);$pdo->prepare('UPDATE turnuva_eslesmeleri SET kazanan_id=? WHERE id=?')->execute([$kazanan,$eslesme]);return $kazanan;
}

agent_log('Eski organizasyon verileri temizleniyor.');
$pdo->beginTransaction();
try {
    foreach(['esitlik_bozma_atislari','turnuva_sporcu_set_atislari','turnuva_mac_setleri','turnuva_maclari','turnuva_eslesmeleri','turnuva_katilimcilari','bireysel_fikstur','bireysel_lig_kayitlari','sporcu_set_atislari','mac_setleri','maclar','sezon_sonuclari'] as $tablo) $pdo->exec("DELETE FROM $tablo");
    $pdo->exec("DELETE FROM favoriler WHERE tur IN ('takim','sporcu')");$pdo->exec('DELETE FROM yetkili');$pdo->exec('DELETE FROM sporcular');$pdo->exec('DELETE FROM takimlar');$pdo->exec('DELETE FROM gruplar');$pdo->exec('DELETE FROM turnuvalar');$pdo->exec('DELETE FROM ligler');
    $pdo->commit();
} catch(Throwable $e) { $pdo->rollBack(); throw $e; }
foreach([UPLOAD_DIR.'/sporcular',UPLOAD_DIR.'/takimlar'] as $klasor){if(!is_dir($klasor))mkdir($klasor,0755,true);foreach(glob($klasor.'/*')?:[] as $dosya)@unlink($dosya);}

$sezon='2026-2027';$pdo->prepare("INSERT INTO sezonlar(sezon_adi,durum) VALUES(?,'aktif') ON DUPLICATE KEY UPDATE durum='aktif',resmi_tarih=NULL")->execute([$sezon]);$sezonId=(int)$pdo->prepare('SELECT id FROM sezonlar WHERE sezon_adi=?')->execute([$sezon]);$st=$pdo->prepare('SELECT id FROM sezonlar WHERE sezon_adi=?');$st->execute([$sezon]);$sezonId=(int)$st->fetchColumn();
$ligIns=$pdo->prepare('INSERT INTO ligler(lig_adi,tur,sezon,sezon_id,aciklama,aktif) VALUES(?,?,?,?,?,1)');$ligIns->execute(['Geleneksel Okçuluk Takım Ligi','takim',$sezon,$sezonId,'Bölgeler arası 16 kulübün mücadele ettiği takım ligi.']);$takimLig=(int)$pdo->lastInsertId();$ligIns->execute(['Bireysel Bölge Ligleri','bireysel',$sezon,$sezonId,'Sporcuların yaş kategorileri ve bölgelerine göre bireysel yarıştığı lig.']);$bireyselLig=(int)$pdo->lastInsertId();
$grupIns=$pdo->prepare('INSERT INTO gruplar(lig_id,grup_adi,bolge_adi,kategori_adi,aciklama,sezon) VALUES(?,?,?,?,?,?)');$bolgeler=['Marmara Bölgesi','İç Anadolu Bölgesi','Ege Bölgesi','Karadeniz Bölgesi'];$takimGruplari=[];foreach($bolgeler as $bolge){$grupIns->execute([$takimLig,$bolge.' Takım Grubu',null,null,$bolge.' kulüpleri',$sezon]);$takimGruplari[]=(int)$pdo->lastInsertId();}
$bireyselGruplar=[];foreach(['Marmara Bölgesi','İç Anadolu Bölgesi'] as $bolge)foreach(['Minik','Yıldız','Gençlik','Büyük'] as $kategori){$grupIns->execute([$bireyselLig,$bolge.' > '.$kategori,$bolge,$kategori,$kategori.' sporcuları',$sezon]);$bireyselGruplar[]=['id'=>(int)$pdo->lastInsertId(),'kategori'=>$kategori,'bolge'=>$bolge];}

$fotoKaynaklari=['https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=600&q=85','https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=600&q=85','https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=600&q=85','https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=600&q=85'];
$takimAdlari=['Nilüfer Okçuluk SK','Mudanya Yay SK','Balıkesir Hedef SK','Bandırma Yay Spor','Ankara Bozkır SK','Eskişehir Porsuk SK','Kütahya Nişan SK','Konya Selçuk SK','İzmir Kemeraltı SK','Manisa Sipahi SK','Aydın Zeybek SK','Muğla Akıncı SK','Samsun Kızılırmak SK','Trabzon Sürmene SK','Ordu Fatsa SK','Rize Çay Yay SK'];$renkler=['#c58b2d','#6c9a8b','#b54b35','#6b6bb2'];
$takimIns=$pdo->prepare('INSERT INTO takimlar(grup_id,takim_adi,kisa_ad,logo,aciklama,sehir,kurulus_yili) VALUES(?,?,?,?,?,?,?)');$sporcuIns=$pdo->prepare('INSERT INTO sporcular(takim_id,ad,soyad,dogum_tarihi,cinsiyet,kategori,lisans_no,telefon,email,adres,foto) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
$adlar=['Arda','Deniz','Efe','Elif','Mert','Zeynep','Bora','Asya','Kaan','Duru','Yusuf','İpek','Emir','Sude','Can','Nehir','Berk','Ada','Umut','Lale'];$soyadlar=['Yıldırım','Kaya','Demir','Acar','Çelik','Şahin','Arslan','Koç','Aydın','Bulut','Aksoy','Öztürk','Korkmaz','Kaplan','Yalçın','Eren'];$takimlar=[];$takimSporcular=[];
foreach($takimAdlari as $i=>$ad){$grup=$takimGruplari[intdiv($i,4)];$logo=agent_logo(UPLOAD_DIR.'/takimlar/takim-'.($i+1).'.svg',$ad,$renkler[$i%4]);$takimIns->execute([$grup,$ad,'TOK'.($i+1),$logo,$ad.'; geleneksel okçuluk kültürünü genç sporcularla yaşatan bölge kulübüdür.',$bolgeler[intdiv($i,4)],2008+($i%12)]);$tid=(int)$pdo->lastInsertId();$takimlar[]=$tid;$takimSporcular[$tid]=[];for($j=0;$j<5;$j++){$isim=$adlar[($i*5+$j)%count($adlar)];$soy=$soyadlar[($i*3+$j)%count($soyadlar)];$foto=agent_foto(UPLOAD_DIR.'/sporcular/club-'.($i*5+$j+1).'.jpg',[$fotoKaynaklari[($i+$j)%4]],$isim);$kategori=['Minik','Yıldız','Gençlik','Büyük','Veteran'][$j];$sporcuIns->execute([$tid,$isim,$soy,'200'.($j+1).'-0'.(($j%8)+1).'-15',$j%2?'K':'E',$kategori,'CL-'.str_pad((string)($i*5+$j+1),4,'0',STR_PAD_LEFT),'05550000000','sporcu'.($i*5+$j+1).'@demo.local',$ad.' / '.$bolgeler[intdiv($i,4)],$foto]);$takimSporcular[$tid][]=['id'=>(int)$pdo->lastInsertId(),'takim_id'=>$tid];}}
$yetkiliEkle=$pdo->prepare("INSERT INTO yetkili(user_id,takim_id,ad,soyad,telefon,email,pozisyon) SELECT id,?,?,?,?,?,? FROM users WHERE kullanici_adi=? LIMIT 1");
$yetkiliEkle->execute([$takimlar[0],'Demo','Yönetici','05550000001','yetkili1@demo.local','Yönetici','yetkili1']);
$yetkiliEkle->execute([$takimlar[1],'Demo','Antrenör','05550000002','yetkili2@demo.local','Antrenör','yetkili2']);
$pdo->prepare('UPDATE takimlar SET yonetici_user_id=(SELECT id FROM users WHERE kullanici_adi=? LIMIT 1) WHERE id=?')->execute(['yetkili1',$takimlar[0]]);

$bagimsiz=[];foreach($bireyselGruplar as $g){for($i=0;$i<3;$i++){$isim=$adlar[(count($bagimsiz)+$i+4)%count($adlar)];$soy=$soyadlar[(count($bagimsiz)+$i+7)%count($soyadlar)];$foto=agent_foto(UPLOAD_DIR.'/sporcular/ind-'.(count($bagimsiz)+1).'.jpg',[$fotoKaynaklari[(count($bagimsiz)+$i)%4]],$isim);$sporcuIns->execute([null,$isim,$soy,'200'.($i+2).'-0'.(($i%8)+1).'-12',$i%2?'E':'K',$g['kategori'],'IND-'.str_pad((string)(count($bagimsiz)+1),4,'0',STR_PAD_LEFT),'05551111111','bireysel'.(count($bagimsiz)+1).'@demo.local',$g['bolge'],$foto]);$sid=(int)$pdo->lastInsertId();$bagimsiz[]=['id'=>$sid,'takim_id'=>null,'kategori'=>$g['kategori']];$pdo->prepare('INSERT INTO bireysel_lig_kayitlari(lig_id,grup_id,sporcu_id,toplam_puan,atis_sayisi) VALUES(?,?,?,?,?)')->execute([$bireyselLig,$g['id'],$sid,mt_rand(210,420),21]);}}

agent_log('Üç haftalık takım ligi simüle ediliyor.');$hakemId=(int)$pdo->query('SELECT id FROM hakemler ORDER BY id LIMIT 1')->fetchColumn();$program=[[[0,1],[2,3]],[[0,2],[1,3]],[[0,3],[1,2]]];foreach($takimGruplari as $gi=>$grup){$dizi=array_slice($takimlar,$gi*4,4);foreach($program as $hafta=>$eslesmeler)foreach($eslesmeler as [$a,$b])agent_mac_oyna($pdo,$grup,$dizi[$a],$dizi[$b],$hafta+1,'2026-10-'.str_pad((string)(3+$hafta*7+$gi),2,'0',STR_PAD_LEFT),$hakemId,$takimSporcular);}
foreach($takimlar as $tid)takim_istatistiklerini_yenile($pdo,$tid);
$pdo->exec("UPDATE sporcular s LEFT JOIN (SELECT sporcu_id,SUM(set_toplam) puan,COUNT(*)*7 ok FROM sporcu_set_atislari GROUP BY sporcu_id) a ON a.sporcu_id=s.id SET s.toplam_puan=COALESCE(a.puan,s.toplam_puan),s.atis_sayisi=COALESCE(a.ok,s.atis_sayisi),s.oynanan_mac=COALESCE((SELECT COUNT(DISTINCT mac_id) FROM sporcu_set_atislari z WHERE z.sporcu_id=s.id),0)");
foreach($bireyselGruplar as $i=>$g)$pdo->prepare("INSERT INTO bireysel_fikstur(lig_id,grup_id,tarih,saat,yer,aciklama) VALUES(?,?,?,'10:00:00',?,'1. ayak atış programı')")->execute([$bireyselLig,$g['id'],'2026-10-'.str_pad((string)(5+$i),2,'0',STR_PAD_LEFT),$g['bolge'].' Geleneksel Okçuluk Sahası']);

agent_log('Takım ve bireysel turnuvaları yarı finale kadar tamamlanıyor.');$turnuvaIns=$pdo->prepare('INSERT INTO turnuvalar(turnuva_adi,tur,kontenjan,sezon_id,aciklama,durum) VALUES(?,?,?,?,?,?)');$turnuvaIns->execute(['Geleneksel Okçuluk Takım Turnuvası','takim','16',$sezonId,'16 kulübün eleme usulü mücadele ettiği takım turnuvası.','eslesme_hazir']);$takimTurnuva=(int)$pdo->lastInsertId();$turnuvaIns->execute(['Bireysel Bölge Okçuluk Turnuvası','bireysel','16',$sezonId,'16 bağımsız sporcunun eleme usulü turnuvası.','eslesme_hazir']);$bireyselTurnuva=(int)$pdo->lastInsertId();
foreach([[$takimTurnuva,$takimlar],[$bireyselTurnuva,array_column(array_slice($bagimsiz,0,16),'id')]] as [$turnuva,$katilimcilar]){$ins=$pdo->prepare('INSERT INTO turnuva_katilimcilari(turnuva_id,hedef_id,sira) VALUES(?,?,?)');foreach($katilimcilar as $sira=>$hedef)$ins->execute([$turnuva,$hedef,$sira+1]);$kazananlar=[];for($i=0;$i<16;$i+=2)$kazananlar[]=agent_turnuva_mac($pdo,$turnuva,1,intdiv($i,2)+1,$katilimcilar[$i],$katilimcilar[$i+1],$turnuva===$takimTurnuva?'takim':'bireysel',$turnuva===$takimTurnuva?$takimSporcular:array_column($bagimsiz,null,'id'),'2026-11-01');$ceyrek=[];for($i=0;$i<8;$i+=2)$ceyrek[]=agent_turnuva_mac($pdo,$turnuva,2,intdiv($i,2)+1,$kazananlar[$i],$kazananlar[$i+1],$turnuva===$takimTurnuva?'takim':'bireysel',$turnuva===$takimTurnuva?$takimSporcular:array_column($bagimsiz,null,'id'),'2026-11-08');$finalistler=[];for($i=0;$i<4;$i+=2)$finalistler[]=agent_turnuva_mac($pdo,$turnuva,3,intdiv($i,2)+1,$ceyrek[$i],$ceyrek[$i+1],$turnuva===$takimTurnuva?'takim':'bireysel',$turnuva===$takimTurnuva?$takimSporcular:array_column($bagimsiz,null,'id'),'2026-11-15');agent_turnuva_mac($pdo,$turnuva,4,1,$finalistler[0],$finalistler[1],$turnuva===$takimTurnuva?'takim':'bireysel',$turnuva===$takimTurnuva?$takimSporcular:array_column($bagimsiz,null,'id'),'2026-11-22',false);}
agent_log('Tamamlandı: 16 takım, '.(count($takimlar)*5).' kulüp sporcusu, '.count($bagimsiz).' bağımsız sporcu, 2 lig ve 2 turnuva oluşturuldu.');
