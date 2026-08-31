<?php
/**
 * Yardımcı fonksiyonlar
 */

if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
}

// ---- Güvenli çıktı ----
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}


// ---- Tarih/Saat formatlama ----
if (!function_exists('tr_tarih_saat')) {
    function tr_tarih_saat($datetime) {
        if (empty($datetime)) return '-';
        $d = new DateTime($datetime);
        return $d->format('d.m.Y H:i');
    }
}
if (!function_exists('tr_tarih')) {
    function tr_tarih($tarih) {
        if (empty($tarih)) return '-';
        $d = new DateTime($tarih);
        return $d->format('d.m.Y');
    }
}
if (!function_exists('tr_saat')) {
    function tr_saat($saat) {
        if (empty($saat)) return '-';
        $d = new DateTime($saat);
        return $d->format('H:i');
    }
}

// ---- HTML güvenliği (basit) ----
if (!function_exists('guvenli_html')) {
    function guvenli_html($html) {
        // Gelişmiş temizlik için HTMLPurifier önerilir, basitçe döndürüyoruz
        return $html;
    }
}

if (!function_exists('html_editor_alani')) {
    function html_editor_alani($alan, $id, $icerik = '') {
        $alan = preg_replace('/[^a-zA-Z0-9_]/', '', $alan);
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        ob_start(); ?>
        <div class="html-editor">
            <div class="html-editor-tools">
                <button type="button" data-editor="<?= e($id) ?>" data-cmd="bold"><b>Kalın</b></button>
                <button type="button" data-editor="<?= e($id) ?>" data-cmd="italic"><i>İtalik</i></button>
                <button type="button" data-editor="<?= e($id) ?>" data-cmd="formatBlock" data-value="h2">Başlık</button>
                <button type="button" data-editor="<?= e($id) ?>" data-cmd="insertUnorderedList">• Liste</button>
                <button type="button" data-editor="<?= e($id) ?>" data-cmd="createLink">Bağlantı</button>
                <button type="button" data-editor="<?= e($id) ?>" data-cmd="removeFormat">Temizle</button>
            </div>
            <div id="<?= e($id) ?>" class="html-editor-area" contenteditable="true"><?= guvenli_html($icerik) ?></div>
        </div>
        <textarea name="<?= e($alan) ?>" id="<?= e($id) ?>Source" hidden></textarea>
        <script>(()=>{const e=document.getElementById('<?= e($id) ?>'),s=document.getElementById('<?= e($id) ?>Source'),f=e.closest('form');document.querySelectorAll('[data-editor="<?= e($id) ?>"]').forEach(b=>b.addEventListener('click',()=>{let v=b.dataset.value||null;if(b.dataset.cmd==='createLink'){v=prompt('Bağlantı adresi:','https://');if(!v)return;}document.execCommand(b.dataset.cmd,false,v);e.focus()}));f.addEventListener('submit',()=>s.value=e.innerHTML)})();</script>
        <?php return ob_get_clean();
    }
}

// ---- Kullanıcı oturum bilgileri ----
if (!function_exists('kullanici_bilgi')) {
    function kullanici_bilgi() {
        return $_SESSION['kullanici'] ?? null;
    }
}

if (!function_exists('giris_yapmis')) {
    function giris_yapmis() {
        return isset($_SESSION['kullanici']);
    }
}

// Normal üyeler sitedeki hesap merkezini kullanır; yönetim rolü olanlar
// aynı merkezden ayrıca panele geçebilir.
if (!function_exists('yonetim_paneli_erisimi_var')) {
    function yonetim_paneli_erisimi_var($rol = null) {
        $rol = $rol ?? (kullanici_bilgi()['rol'] ?? '');
        return in_array($rol, ['admin', 'yonetici', 'hakem', 'yetkili', 'sporcu'], true);
    }
}

if (!function_exists('hesap_sayfasi_url')) {
    function hesap_sayfasi_url() {
        return BASE_URL . '/hesabim.php';
    }
}

if (!function_exists('guvenli_donus_url')) {
    function guvenli_donus_url($url, $varsayilan = null) {
        $varsayilan = $varsayilan ?: BASE_URL . '/index.php';
        $url = trim((string)$url);
        if ($url === '') return $varsayilan;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return BASE_URL . $url;
        $temel = parse_url(BASE_URL);
        $hedef = parse_url($url);
        if ($hedef && isset($hedef['host']) && strtolower($hedef['host']) === strtolower($temel['host'] ?? '')) {
            return $url;
        }
        return $varsayilan;
    }
}

if (!function_exists('oturum_ac')) {
    function oturum_ac(array $kullanici) {
        session_regenerate_id(true);
        $_SESSION['kullanici'] = [
            'id' => (int)$kullanici['id'], 'kullanici_adi' => $kullanici['kullanici_adi'],
            'email' => $kullanici['email'], 'rol' => $kullanici['rol'], 'ad_soyad' => $kullanici['ad_soyad'],
        ];
        unset($_SESSION['csrf_token']);
    }
}

if (!function_exists('oturum_kapat')) {
    function oturum_kapat($mesaj = 'Çıkış yaptınız.') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        flash_set('basari', $mesaj);
    }
}

// ---- Yeni sürüm şema uyumluluğu (mevcut kurulumları da günceller) ----
if (!function_exists('uyumluluk_guncelle')) {
    function uyumluluk_guncelle($pdo) {
        static $calisti = false;
        if ($calisti) return;
        $calisti = true;
        try {
            $rol = $pdo->query("SHOW COLUMNS FROM users LIKE 'rol'")->fetch();
            if ($rol && strpos($rol['Type'], "'yonetici'") === false) {
                $pdo->exec("ALTER TABLE users MODIFY rol ENUM('admin','yonetici','hakem','sporcu','yetkili','uye') NOT NULL DEFAULT 'uye'");
            }
            $pdo->exec("UPDATE ligler SET lig_adi='Bireysel Bölge Ligleri' WHERE tur='bireysel' AND lig_adi LIKE 'Bireysel Okçuluk%'");
        } catch (PDOException $e) { }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM duyurular LIKE 'medya_url'")->fetchAll();
            if (!$cols) $pdo->exec("ALTER TABLE duyurular ADD medya_url VARCHAR(255) DEFAULT NULL AFTER gorsel");
        } catch (PDOException $e) { }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM mac_setleri LIKE 'tamamlandi'")->fetchAll();
            if (!$cols) $pdo->exec("ALTER TABLE mac_setleri ADD tamamlandi TINYINT(1) NOT NULL DEFAULT 0 AFTER deplasman_set_puani");
            $pdo->exec("UPDATE mac_setleri ms JOIN maclar m ON m.id=ms.mac_id SET ms.tamamlandi=1 WHERE m.durum='oynandi' AND ms.tamamlandi=0");
        } catch (PDOException $e) { }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS favoriler (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
                tur ENUM('takim','sporcu') NOT NULL, hedef_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id),
                UNIQUE KEY uk_favori (user_id,tur,hedef_id), KEY idx_favori_user (user_id),
                CONSTRAINT fk_favori_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sistem_ayarlari (anahtar VARCHAR(80) PRIMARY KEY, deger VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->prepare("INSERT IGNORE INTO sistem_ayarlari(anahtar,deger) VALUES('sporcu_kayit_acik','1')")->execute();
        } catch (PDOException $e) { /* eski veya sınırlı kurulumlarda siteyi durdurma */ }
    }
}

if (isset($pdo) && $pdo instanceof PDO) uyumluluk_guncelle($pdo);

if (!function_exists('ayar_al')) {
    function ayar_al($pdo, $anahtar, $varsayilan = null) {
        $s = $pdo->prepare('SELECT deger FROM sistem_ayarlari WHERE anahtar=?'); $s->execute([$anahtar]);
        $v = $s->fetchColumn(); return $v === false ? $varsayilan : $v;
    }
}

if (!function_exists('sporcu_kategorisi_belirle')) {
    function sporcu_kategorisi_belirle($dogumTarihi, $cinsiyet = 'E') {
        if (!$dogumTarihi) return null;
        try {$dogum=new DateTime($dogumTarihi);$bugun=new DateTime('today');$yas=$dogum->diff($bugun)->y;} catch (Throwable $e) {return null;}
        if ($yas>=18 && $cinsiyet==='K') return 'Kadınlar';
        if ($yas>=18) return 'Yetişkin';
        if ($yas>=16) return 'Gençler';
        if ($yas>=12) return 'Yıldızlar';
        if ($yas>=8) return 'Minikler';
        return null;
    }
}

if (!function_exists('kurumsal_sayfa_varsayilanlari')) {
    function kurumsal_sayfa_varsayilanlari() {
        return [
            'hakkimizda'=>['Hakkımızda','<h2>Kuruluş ve Amaç</h2><p>Geleneksel Okçuluk Ligleri, geleneksel okçuluğu yaygınlaştırmak ve sporculara düzenli bir rekabet ortamı sunmak amacıyla kurulmuştur.</p><h2>Vizyon ve Misyon</h2><p>Vizyonumuz geleneksel okçuluğu ulusal çapta organize etmektir. Misyonumuz sporcularımıza güvenilir ve şeffaf bir lig deneyimi sunmaktır.</p>'],
            'iletisim'=>['İletişim','<h2>İletişim Bilgileri</h2><p><strong>Adres:</strong> Adres bilgisini buraya ekleyin.</p><p><strong>Telefon:</strong> Telefon numarasını buraya ekleyin.</p><p><strong>E-posta:</strong> E-posta adresini buraya ekleyin.</p><p><strong>Sosyal medya:</strong> Hesap bağlantılarını buraya ekleyin.</p>'],
            'destekleyenler'=>['Destekleyenler','<p>Ligimize destek veren kurum, sponsor ve paydaşları bu alandan ekleyebilirsiniz.</p>']
        ];
    }
}
if (!function_exists('kurumsal_sayfa_al')) {
    function kurumsal_sayfa_al($pdo,$anahtar) {
        $varsayilan=kurumsal_sayfa_varsayilanlari();$st=$pdo->prepare('SELECT baslik,icerik FROM site_icerikleri WHERE anahtar=?');$st->execute([$anahtar]);
        return $st->fetch()?:['baslik'=>$varsayilan[$anahtar][0]??'Kurumsal Sayfa','icerik'=>$varsayilan[$anahtar][1]??''];
    }
}

if (!function_exists('coklu_lig_yukselt')) {
    function coklu_lig_yukselt($pdo) {
        static $calisti = false; if ($calisti) return; $calisti = true;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ligler (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lig_adi VARCHAR(150) NOT NULL, tur ENUM('takim','bireysel') NOT NULL DEFAULT 'takim', sezon VARCHAR(30) DEFAULT NULL, aciklama TEXT DEFAULT NULL, aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sezonlar (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sezon_adi VARCHAR(30) NOT NULL UNIQUE, durum ENUM('aktif','resmilesti') NOT NULL DEFAULT 'aktif', resmi_tarih DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_icerikleri (anahtar VARCHAR(80) PRIMARY KEY, baslik VARCHAR(200) NOT NULL, icerik LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bireysel_lig_kayitlari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lig_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL, sporcu_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_bireysel_kayit(lig_id,sporcu_id), KEY idx_bl_grup(grup_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bireysel_fikstur (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lig_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL, tarih DATE NOT NULL, saat TIME DEFAULT NULL, yer VARCHAR(160) NOT NULL, aciklama VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_bf_lig_grup(lig_id,grup_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            if (!$pdo->query("SHOW COLUMNS FROM bireysel_fikstur LIKE 'hafta_no'")->fetchAll()) $pdo->exec("ALTER TABLE bireysel_fikstur ADD hafta_no INT UNSIGNED NULL AFTER grup_id");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bireysel_fikstur_atislari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, fikstur_id INT UNSIGNED NOT NULL, sporcu_id INT UNSIGNED NOT NULL, puan TINYINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_bfa_fikstur_sporcu(fikstur_id,sporcu_id), KEY idx_bfa_sporcu(sporcu_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            if (!$pdo->query("SHOW COLUMNS FROM gruplar LIKE 'atis_alani'")->fetchAll()) $pdo->exec("ALTER TABLE gruplar ADD atis_alani VARCHAR(160) NULL AFTER kategori_adi");
            $pdo->exec("UPDATE gruplar SET kategori_adi=CASE kategori_adi WHEN 'Minik' THEN 'Minikler' WHEN 'Yıldız' THEN 'Yıldızlar' WHEN 'Gençlik' THEN 'Gençler' WHEN 'Büyük' THEN 'Yetişkin' WHEN 'Veteran' THEN 'Yetişkin' ELSE kategori_adi END WHERE kategori_adi IN ('Minik','Yıldız','Gençlik','Büyük','Veteran')");
            $pdo->exec("UPDATE sporcular SET kategori=CASE WHEN TIMESTAMPDIFF(YEAR,dogum_tarihi,CURDATE())>=18 AND cinsiyet='K' THEN 'Kadınlar' WHEN TIMESTAMPDIFF(YEAR,dogum_tarihi,CURDATE())>=18 THEN 'Yetişkin' WHEN TIMESTAMPDIFF(YEAR,dogum_tarihi,CURDATE())>=16 THEN 'Gençler' WHEN TIMESTAMPDIFF(YEAR,dogum_tarihi,CURDATE())>=12 THEN 'Yıldızlar' ELSE 'Minikler' END WHERE dogum_tarihi IS NOT NULL");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sezon_sonuclari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sezon_id INT UNSIGNED NOT NULL, lig_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL, tur ENUM('takim','bireysel') NOT NULL, sira INT UNSIGNED NOT NULL, hedef_id INT UNSIGNED NULL, ad VARCHAR(200) NOT NULL, oynanan INT NOT NULL DEFAULT 0, galibiyet INT NOT NULL DEFAULT 0, maglubiyet INT NOT NULL DEFAULT 0, toplam_set INT NOT NULL DEFAULT 0, toplam_puan INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_sezon_sonuc (sezon_id,lig_id,grup_id,tur,sira), KEY idx_arsiv_filtre (sezon_id,lig_id,grup_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS turnuvalar (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turnuva_adi VARCHAR(160) NOT NULL, tur ENUM('takim','bireysel') NOT NULL, kontenjan ENUM('16','32') NOT NULL, sezon_id INT UNSIGNED NOT NULL, aciklama TEXT NULL, durum ENUM('taslak','eslesme_hazir','tamamlandi') NOT NULL DEFAULT 'taslak', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS turnuva_katilimcilari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turnuva_id INT UNSIGNED NOT NULL, hedef_id INT UNSIGNED NOT NULL, sira INT UNSIGNED NOT NULL, UNIQUE KEY uk_turnuva_hedef(turnuva_id,hedef_id), UNIQUE KEY uk_turnuva_sira(turnuva_id,sira)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sporcu_organizasyon_kayitlari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sporcu_id INT UNSIGNED NOT NULL, tur ENUM('takim_ligi','bireysel_lig','takim_turnuvasi','bireysel_turnuva') NOT NULL, organizasyon_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_sporcu_organizasyon(sporcu_id,tur,organizasyon_id,grup_id), KEY idx_organizasyon(tur,organizasyon_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach ([['bireysel_lig_kayitlari','toplam_puan INT NOT NULL DEFAULT 0'],['bireysel_lig_kayitlari','atis_sayisi INT NOT NULL DEFAULT 0'],['turnuva_katilimcilari','toplam_puan INT NOT NULL DEFAULT 0'],['turnuva_katilimcilari','atis_sayisi INT NOT NULL DEFAULT 0']] as $ek) { $ad=strtok($ek[1],' '); if (!$pdo->query("SHOW COLUMNS FROM {$ek[0]} LIKE ".$pdo->quote($ad))->fetchAll()) $pdo->exec("ALTER TABLE {$ek[0]} ADD {$ek[1]}"); }
            $pdo->exec("CREATE TABLE IF NOT EXISTS turnuva_eslesmeleri (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turnuva_id INT UNSIGNED NOT NULL, tur_no INT UNSIGNED NOT NULL, eslesme_no INT UNSIGNED NOT NULL, katilimci1_id INT UNSIGNED NULL, katilimci2_id INT UNSIGNED NULL, kazanan_id INT UNSIGNED NULL, durum ENUM('planlandi','tamamlandi') NOT NULL DEFAULT 'planlandi', UNIQUE KEY uk_turnuva_eslesme(turnuva_id,tur_no,eslesme_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS turnuva_maclari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turnuva_id INT UNSIGNED NOT NULL, eslesme_id INT UNSIGNED NOT NULL, katilimci1_id INT UNSIGNED NULL, katilimci2_id INT UNSIGNED NULL, tur_no INT UNSIGNED NOT NULL, durum ENUM('planlandi','oynandi','iptal') NOT NULL DEFAULT 'planlandi', tarih DATE NULL, saat TIME NULL, yer VARCHAR(160) NULL, UNIQUE KEY uk_turnuva_mac_eslesme(eslesme_id), KEY idx_turnuva_mac(turnuva_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("ALTER TABLE turnuva_maclari MODIFY katilimci1_id INT UNSIGNED NULL, MODIFY katilimci2_id INT UNSIGNED NULL");
            foreach (['puan1 INT NOT NULL DEFAULT 0','puan2 INT NOT NULL DEFAULT 0'] as $kolon) { $ad=strtok($kolon,' '); if (!$pdo->query("SHOW COLUMNS FROM turnuva_maclari LIKE ".$pdo->quote($ad))->fetchAll()) $pdo->exec("ALTER TABLE turnuva_maclari ADD $kolon"); }
            $pdo->exec("INSERT IGNORE INTO turnuva_maclari(turnuva_id,eslesme_id,katilimci1_id,katilimci2_id,tur_no) SELECT e.turnuva_id,e.id,k1.hedef_id,k2.hedef_id,e.tur_no FROM turnuva_eslesmeleri e JOIN turnuva_katilimcilari k1 ON k1.id=e.katilimci1_id JOIN turnuva_katilimcilari k2 ON k2.id=e.katilimci2_id");
            $hazirlar=$pdo->query("SELECT id,kontenjan FROM turnuvalar WHERE durum IN ('eslesme_hazir','tamamlandi')")->fetchAll();
            foreach($hazirlar as $hazir){
                $turSayisi=(int)round(log((int)$hazir['kontenjan'],2));
                for($tur=2;$tur<=$turSayisi;$tur++){
                    $adet=(int)$hazir['kontenjan']/(2**$tur);
                    $say=$pdo->prepare('SELECT COUNT(*) FROM turnuva_eslesmeleri WHERE turnuva_id=? AND tur_no=?');$say->execute([$hazir['id'],$tur]);
                    for($no=(int)$say->fetchColumn()+1;$no<=$adet;$no++){
                        $pdo->prepare('INSERT INTO turnuva_eslesmeleri(turnuva_id,tur_no,eslesme_no) VALUES(?,?,?)')->execute([$hazir['id'],$tur,$no]);
                        $eslesmeId=(int)$pdo->lastInsertId();
                        $pdo->prepare('INSERT INTO turnuva_maclari(turnuva_id,eslesme_id,tur_no) VALUES(?,?,?)')->execute([$hazir['id'],$eslesmeId,$tur]);
                    }
                }
            }
            $pdo->exec("CREATE TABLE IF NOT EXISTS turnuva_mac_setleri (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turnuva_mac_id INT UNSIGNED NOT NULL, set_no INT UNSIGNED NOT NULL, puan1 INT NOT NULL DEFAULT 0, puan2 INT NOT NULL DEFAULT 0, tamamlandi TINYINT(1) NOT NULL DEFAULT 0, UNIQUE KEY uk_turnuva_set(turnuva_mac_id,set_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS turnuva_sporcu_set_atislari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turnuva_mac_id INT UNSIGNED NOT NULL, set_no INT UNSIGNED NOT NULL, taraf ENUM('ev','dep') NOT NULL, sporcu_id INT UNSIGNED NOT NULL, ok1 INT NOT NULL DEFAULT 0, ok2 INT NOT NULL DEFAULT 0, ok3 INT NOT NULL DEFAULT 0, ok4 INT NOT NULL DEFAULT 0, ok5 INT NOT NULL DEFAULT 0, ok6 INT NOT NULL DEFAULT 0, ok7 INT NOT NULL DEFAULT 0, set_toplam INT NOT NULL DEFAULT 0, UNIQUE KEY uk_turnuva_sporcu_set(turnuva_mac_id,set_no,taraf,sporcu_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS esitlik_bozma_atislari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kaynak ENUM('lig','turnuva') NOT NULL, mac_id INT UNSIGNED NULL, turnuva_mac_id INT UNSIGNED NULL, set_no INT UNSIGNED NOT NULL, tur_no INT UNSIGNED NOT NULL, ev_puan INT NOT NULL, dep_puan INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_esitlik_lig(mac_id,set_no), KEY idx_esitlik_turnuva(turnuva_mac_id,set_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach ([['mac_setleri','kazanan'],['turnuva_mac_setleri','kazanan']] as $ek) if (!$pdo->query("SHOW COLUMNS FROM {$ek[0]} LIKE 'kazanan'")->fetchAll()) $pdo->exec("ALTER TABLE {$ek[0]} ADD kazanan ENUM('ev','dep') NULL AFTER tamamlandi");
            $col = $pdo->query("SHOW COLUMNS FROM gruplar LIKE 'lig_id'")->fetchAll();
            if (!$col) $pdo->exec("ALTER TABLE gruplar ADD lig_id INT UNSIGNED NULL AFTER id");
            foreach ([['bolge_adi','VARCHAR(160) NULL'],['kategori_adi','VARCHAR(160) NULL']] as $grupKolon) {
                if (!$pdo->query("SHOW COLUMNS FROM gruplar LIKE ".$pdo->quote($grupKolon[0]))->fetchAll()) $pdo->exec("ALTER TABLE gruplar ADD {$grupKolon[0]} {$grupKolon[1]} AFTER grup_adi");
            }
            if (!$pdo->query("SHOW COLUMNS FROM takimlar LIKE 'aciklama'")->fetchAll()) $pdo->exec("ALTER TABLE takimlar ADD aciklama TEXT NULL AFTER logo");
            if (!$pdo->query("SHOW COLUMNS FROM takimlar LIKE 'yonetici_user_id'")->fetchAll()) $pdo->exec("ALTER TABLE takimlar ADD yonetici_user_id INT UNSIGNED NULL AFTER aciklama");
            $pdo->exec("UPDATE takimlar t JOIN yetkili y ON y.takim_id=t.id SET t.yonetici_user_id=y.user_id WHERE t.yonetici_user_id IS NULL AND y.pozisyon='Yönetici'");
            $sezonCol = $pdo->query("SHOW COLUMNS FROM ligler LIKE 'sezon_id'")->fetchAll();
            if (!$sezonCol) $pdo->exec("ALTER TABLE ligler ADD sezon_id INT UNSIGNED NULL AFTER id");
            foreach ([defined('LIG_SEZON')?LIG_SEZON:'2025-2026','2026-2027'] as $sezonAdi) $pdo->prepare("INSERT IGNORE INTO sezonlar(sezon_adi) VALUES(?)")->execute([$sezonAdi]);
            $pdo->exec("UPDATE ligler l JOIN sezonlar s ON s.sezon_adi=l.sezon SET l.sezon_id=s.id WHERE l.sezon_id IS NULL AND l.sezon IS NOT NULL");
            $ana = $pdo->query("SELECT id FROM ligler WHERE tur='takim' ORDER BY id LIMIT 1")->fetchColumn();
            if (!$ana) { $pdo->prepare("INSERT INTO ligler (lig_adi,tur,sezon,sezon_id,aciklama) VALUES (?,?,?,?,?)")->execute(['Geleneksel Okçuluk Takım Ligleri','takim',defined('LIG_SEZON')?LIG_SEZON:null,$pdo->query("SELECT id FROM sezonlar WHERE sezon_adi=".$pdo->quote(defined('LIG_SEZON')?LIG_SEZON:'2025-2026'))->fetchColumn(),'Mevcut takım karşılaşmalarının yer aldığı ana lig.']); $ana=(int)$pdo->lastInsertId(); }
            $pdo->prepare("UPDATE gruplar SET lig_id=? WHERE lig_id IS NULL")->execute([$ana]);
            $bireysel = $pdo->query("SELECT id FROM ligler WHERE tur='bireysel' ORDER BY id LIMIT 1")->fetchColumn();
            if (!$bireysel) {
                $pdo->prepare("INSERT INTO ligler (lig_adi,tur,sezon,sezon_id,aciklama) VALUES (?,?,?,?,?)")->execute(['Bireysel Bölge Ligleri','bireysel',defined('LIG_SEZON')?LIG_SEZON:null,$pdo->query("SELECT id FROM sezonlar WHERE sezon_adi=".$pdo->quote(defined('LIG_SEZON')?LIG_SEZON:'2025-2026'))->fetchColumn(),'Sporcuların bölge ve kategori gruplarında bireysel olarak yarıştığı lig.']);
                $bireysel=(int)$pdo->lastInsertId();
                foreach (['Marmara Bölgesi','İç Anadolu Bölgesi'] as $bolge) foreach (['Minikler','Yıldızlar','Gençler','Yetişkin','Kadınlar'] as $kategori) $pdo->prepare("INSERT INTO gruplar (lig_id,grup_adi,bolge_adi,kategori_adi,aciklama,sezon) VALUES (?,?,?,?,?,?)")->execute([$bireysel,$bolge.' > '.$kategori,$bolge,$kategori,'Bireysel lig bölgesi',defined('LIG_SEZON')?LIG_SEZON:null]);
            }
            /* Eski, kategori tanımı olmayan bireysel bölge gruplarını kullanılabilir kategori gruplarına tamamla. */
            $eskiGruplar=$pdo->prepare("SELECT g.* FROM gruplar g JOIN ligler l ON l.id=g.lig_id WHERE l.id=? AND l.tur='bireysel' AND (g.kategori_adi IS NULL OR TRIM(g.kategori_adi)='')");
            $eskiGruplar->execute([$bireysel]);
            $varMi=$pdo->prepare("SELECT id FROM gruplar WHERE lig_id=? AND TRIM(bolge_adi)=TRIM(?) AND TRIM(kategori_adi)=TRIM(?) LIMIT 1");
            $olustur=$pdo->prepare("INSERT INTO gruplar (lig_id,grup_adi,bolge_adi,kategori_adi,aciklama,sezon) VALUES (?,?,?,?,?,?)");
            foreach($eskiGruplar->fetchAll() as $eskiGrup){$bolge=trim($eskiGrup['bolge_adi']?:$eskiGrup['grup_adi']);if($bolge==='')continue;foreach(['Minikler','Yıldızlar','Gençler','Yetişkin','Kadınlar'] as $kategori){$varMi->execute([$bireysel,$bolge,$kategori]);if(!$varMi->fetchColumn())$olustur->execute([$bireysel,$bolge.' > '.$kategori,$bolge,$kategori,$eskiGrup['aciklama']?:'Bireysel lig bölgesi',$eskiGrup['sezon']]);}}
        } catch (PDOException $e) { }
    }
}
if (isset($pdo) && $pdo instanceof PDO) coklu_lig_yukselt($pdo);

// Sezon kapatılırken tablo, sporcu ve takım adlarıyla birlikte dondurulur.
if (!function_exists('sezon_sonuclarini_resmilestir')) {
    function sezon_sonuclarini_resmilestir($pdo, $sezonId) {
        $sezonId = (int)$sezonId;
        $sezon = $pdo->prepare('SELECT * FROM sezonlar WHERE id=?');
        $sezon->execute([$sezonId]);
        $sezon = $sezon->fetch();
        if (!$sezon) throw new RuntimeException('Sezon bulunamadı.');
        if ($sezon['durum'] === 'resmilesti') throw new RuntimeException('Bu sezon zaten resmileştirilmiş.');
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM sezon_sonuclari WHERE sezon_id=?')->execute([$sezonId]);
            $ligSorgu = $pdo->prepare('SELECT id,tur FROM ligler WHERE sezon_id=?');
            $ligSorgu->execute([$sezonId]);
            $ekle = $pdo->prepare('INSERT INTO sezon_sonuclari (sezon_id,lig_id,grup_id,tur,sira,hedef_id,ad,oynanan,galibiyet,maglubiyet,toplam_set,toplam_puan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($ligSorgu->fetchAll() as $lig) {
                $grupSorgu = $pdo->prepare('SELECT id FROM gruplar WHERE lig_id=?');
                $grupSorgu->execute([(int)$lig['id']]);
                foreach ($grupSorgu->fetchAll() as $grup) {
                    $grupId = (int)$grup['id']; $sira = 0;
                    if ($lig['tur'] === 'takim') {
                        $kayitlar = $pdo->prepare('SELECT id,takim_adi,oynanan_mac,kazanilan_mac,kaybedilen_mac,toplam_set,toplam_puan FROM takimlar WHERE grup_id=? ORDER BY toplam_set DESC, toplam_puan DESC, takim_adi');
                        $kayitlar->execute([$grupId]);
                        foreach ($kayitlar->fetchAll() as $k) $ekle->execute([$sezonId,$lig['id'],$grupId,'takim',++$sira,$k['id'],$k['takim_adi'],$k['oynanan_mac'],$k['kazanilan_mac'],$k['kaybedilen_mac'],$k['toplam_set'],$k['toplam_puan']]);
                    } else {
                        $kayitlar = $pdo->prepare("SELECT k.sporcu_id, CONCAT(s.ad,' ',s.soyad) ad, s.toplam_puan FROM bireysel_lig_kayitlari k JOIN sporcular s ON s.id=k.sporcu_id WHERE k.lig_id=? AND k.grup_id=? ORDER BY s.toplam_puan DESC, s.ad, s.soyad");
                        $kayitlar->execute([(int)$lig['id'],$grupId]);
                        foreach ($kayitlar->fetchAll() as $k) $ekle->execute([$sezonId,$lig['id'],$grupId,'bireysel',++$sira,$k['sporcu_id'],$k['ad'],0,0,0,0,$k['toplam_puan']]);
                    }
                }
            }
            $pdo->prepare("UPDATE sezonlar SET durum='resmilesti', resmi_tarih=NOW() WHERE id=?")->execute([$sezonId]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}

// ---- Rol kontrolü ----
if (!function_exists('zorunlu_rol')) {
    function zorunlu_rol(...$roller) {
        $u = kullanici_bilgi();

        if (!$u) {
            flash_set('hata', 'Lütfen giriş yapın.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        if (!in_array($u['rol'], $roller)) {
            http_response_code(403);
            die('Yetkisiz erişim.');
        }
    }
}

// ---- Flash mesajlar ----
if (!function_exists('flash_set')) {
    function flash_set($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}
if (!function_exists('flash_get')) {
    function flash_get() {
        if (isset($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $f;
        }
        return null;
    }
}

// ---- CSRF ----
if (!function_exists('csrf_field')) {
    function csrf_field() {
        // Aynı ekranda birden fazla form bulunabilir. Her form için oturumdaki
        // tek anahtarı değiştirmek, önce çizilen formu geçersiz kılıyordu.
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf_token'] . '">';
    }
}
if (!function_exists('csrf_check')) {
    function csrf_check($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ---- Şifre işlemleri ----
if (!function_exists('sifre_hash')) {
    function sifre_hash($sifre) {
        return password_hash($sifre, PASSWORD_BCRYPT);
    }
}
if (!function_exists('sifre_dogrula')) {
    function sifre_dogrula($sifre, $hash) {
        return password_verify($sifre, $hash);
    }
}

// ---- Ok puanı doğrulama ----
if (!function_exists('ok_puan')) {
    function ok_puan($deger) {
        $v = (int)$deger;
        if ($v < 0) return 0;
        if ($v > 10) return 10;
        return $v;
    }
}

// ---- Görsel yükleme ----
if (!function_exists('gorsel_yukle')) {
    function gorsel_yukle($input_name, $alt_klasor, $mevcut = null) {
        if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
            return $mevcut;
        }
        $file = $_FILES[$input_name];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','svg'];
        if (!in_array($ext, $allowed)) {
            flash_set('hata', 'Geçersiz dosya formatı.');
            return $mevcut;
        }
        // MIME kontrolü
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'])) {
            flash_set('hata', 'Geçersiz dosya türü.');
            return $mevcut;
        }
        // Boyut kontrolü (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            flash_set('hata', 'Dosya 5MB\'dan büyük.');
            return $mevcut;
        }
        $hedef_dir = UPLOAD_DIR . '/' . $alt_klasor;
        if (!is_dir($hedef_dir)) {
            mkdir($hedef_dir, 0755, true);
        }
        $yeni_ad = uniqid() . '.' . $ext;
        $hedef = $hedef_dir . '/' . $yeni_ad;
        if (move_uploaded_file($file['tmp_name'], $hedef)) {
            // Eski dosyayı sil
            if ($mevcut && file_exists(UPLOAD_DIR . '/' . $alt_klasor . '/' . $mevcut)) {
                @unlink(UPLOAD_DIR . '/' . $alt_klasor . '/' . $mevcut);
            }
            return $yeni_ad;
        }
        return $mevcut;
    }
}

// ---- Maç istatistiklerini güncelle ----
if (!function_exists('mac_istatistik_guncelle')) {
    function mac_istatistik_guncelle($pdo, $mac_id) {
        // 1. Maç bilgilerini al
        $st = $pdo->prepare("SELECT * FROM maclar WHERE id = ?");
        $st->execute([$mac_id]);
        $mac = $st->fetch();
        if (!$mac) return;

        // 2. Set toplamlarını hesapla (mac_setleri'nden)
        $st = $pdo->prepare("SELECT set_no, ev_sahibi_set_puani, deplasman_set_puani, kazanan FROM mac_setleri WHERE mac_id = ? ORDER BY set_no");
        $st->execute([$mac_id]);
        $sets = $st->fetchAll();
        if (count($sets) < SET_SAYISI) return; // eksik set varsa güncelleme

        $ev_toplam_set = 0;
        $dep_toplam_set = 0;
        $ev_toplam_puan = 0;
        $dep_toplam_puan = 0;
        foreach ($sets as $s) {
            $ev_toplam_puan += $s['ev_sahibi_set_puani'];
            $dep_toplam_puan += $s['deplasman_set_puani'];
            if (($s['kazanan'] ?? '') === 'ev' || (empty($s['kazanan']) && $s['ev_sahibi_set_puani'] > $s['deplasman_set_puani'])) $ev_toplam_set++;
            elseif (($s['kazanan'] ?? '') === 'dep' || (empty($s['kazanan']) && $s['deplasman_set_puani'] > $s['ev_sahibi_set_puani'])) $dep_toplam_set++;
        }

        // 3. Mac tablosunu güncelle
        $pdo->prepare("UPDATE maclar SET ev_sahibi_set = ?, deplasman_set = ?, ev_sahibi_puan = ?, deplasman_puan = ?, durum = 'oynandi' WHERE id = ?")
            ->execute([$ev_toplam_set, $dep_toplam_set, $ev_toplam_puan, $dep_toplam_puan, $mac_id]);

        // 4. Takım istatistiklerini güncelle
        $takim_ids = [$mac['ev_sahibi_id'], $mac['deplasman_id']];
        foreach ($takim_ids as $takim_id) {
            // Oynadığı maçları say
            $st = $pdo->prepare("SELECT COUNT(*) FROM maclar WHERE (ev_sahibi_id = ? OR deplasman_id = ?) AND durum = 'oynandi'");
            $st->execute([$takim_id, $takim_id]);
            $oynanan = (int)$st->fetchColumn();

            // Kazandığı maçları say (ev sahibi olarak kazandıkları + deplasman olarak kazandıkları)
            $st = $pdo->prepare("SELECT COUNT(*) FROM maclar WHERE ev_sahibi_id = ? AND ev_sahibi_set > deplasman_set AND durum='oynandi'");
            $st->execute([$takim_id]);
            $kaz_ev = (int)$st->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM maclar WHERE deplasman_id = ? AND deplasman_set > ev_sahibi_set AND durum='oynandi'");
            $st->execute([$takim_id]);
            $kaz_dep = (int)$st->fetchColumn();
            $kazanilan = $kaz_ev + $kaz_dep;

            $kaybedilen = $oynanan - $kazanilan;

            // Toplam set ve puan
            $st = $pdo->prepare("SELECT SUM(ev_sahibi_set) FROM maclar WHERE ev_sahibi_id = ? AND durum='oynandi'");
            $st->execute([$takim_id]);
            $set_ev = (int)$st->fetchColumn();
            $st = $pdo->prepare("SELECT SUM(deplasman_set) FROM maclar WHERE deplasman_id = ? AND durum='oynandi'");
            $st->execute([$takim_id]);
            $set_dep = (int)$st->fetchColumn();
            $toplam_set = $set_ev + $set_dep;

            $st = $pdo->prepare("SELECT SUM(ev_sahibi_puan) FROM maclar WHERE ev_sahibi_id = ? AND durum='oynandi'");
            $st->execute([$takim_id]);
            $puan_ev = (int)$st->fetchColumn();
            $st = $pdo->prepare("SELECT SUM(deplasman_puan) FROM maclar WHERE deplasman_id = ? AND durum='oynandi'");
            $st->execute([$takim_id]);
            $puan_dep = (int)$st->fetchColumn();
            $toplam_puan = $puan_ev + $puan_dep;

            $pdo->prepare("UPDATE takimlar SET
                oynanan_mac = ?, kazanilan_mac = ?, kaybedilen_mac = ?,
                toplam_set = ?, toplam_puan = ?
                WHERE id = ?")
                ->execute([$oynanan, $kazanilan, $kaybedilen, $toplam_set, $toplam_puan, $takim_id]);
        }

        // 5. Sporcu istatistiklerini güncelle
        // Tüm sporcular için mac_id'ye göre atışları topla
        $st = $pdo->prepare("SELECT sporcu_id, SUM(set_toplam) AS toplam_puan, COUNT(*) AS atis_sayisi FROM sporcu_set_atislari WHERE mac_id = ? GROUP BY sporcu_id");
        $st->execute([$mac_id]);
        $sporcu_atislari = $st->fetchAll();
        foreach ($sporcu_atislari as $sa) {
            $sporcu_id = $sa['sporcu_id'];
            $toplam_puan = (int)$sa['toplam_puan'];
            $atis_sayisi = (int)$sa['atis_sayisi'] * 7; // her sette 7 ok, her kayıt bir set
            // Mevcut toplamları güncelle
            $pdo->prepare("UPDATE sporcular SET
                toplam_puan = toplam_puan + ?,
                atis_sayisi = atis_sayisi + ?,
                oynanan_mac = oynanan_mac + 1
                WHERE id = ?")
                ->execute([$toplam_puan, $atis_sayisi, $sporcu_id]);
        }

        foreach ($takim_ids as $takim_id) takim_istatistiklerini_yenile($pdo, $takim_id);


    }
}

if (!function_exists('takim_istatistiklerini_yenile')) {
    function takim_istatistiklerini_yenile($pdo, $takim_id) {
        $macSt=$pdo->prepare("SELECT id,ev_sahibi_id,deplasman_id,ev_sahibi_puan,deplasman_puan FROM maclar WHERE durum='oynandi' AND (ev_sahibi_id=? OR deplasman_id=?)");
        $macSt->execute([$takim_id,$takim_id]);$maclar=$macSt->fetchAll();$oynanan=count($maclar);$galibiyet=0;$maglubiyet=0;$toplamSet=0;$toplamPuan=0;
        $setSt=$pdo->prepare('SELECT ev_sahibi_set_puani,deplasman_set_puani,kazanan FROM mac_setleri WHERE mac_id=?');
        foreach($maclar as $mac){$setSt->execute([$mac['id']]);$evSet=0;$depSet=0;foreach($setSt->fetchAll() as $set){if($set['kazanan']==='ev'||(!$set['kazanan']&&$set['ev_sahibi_set_puani']>$set['deplasman_set_puani']))$evSet++;elseif($set['kazanan']==='dep'||(!$set['kazanan']&&$set['deplasman_set_puani']>$set['ev_sahibi_set_puani']))$depSet++;}if((int)$mac['ev_sahibi_id']===(int)$takim_id){$toplamSet+=$evSet;$toplamPuan+=(int)$mac['ev_sahibi_puan'];if($evSet>$depSet)$galibiyet++;elseif($evSet<$depSet)$maglubiyet++;}else{$toplamSet+=$depSet;$toplamPuan+=(int)$mac['deplasman_puan'];if($depSet>$evSet)$galibiyet++;elseif($depSet<$evSet)$maglubiyet++;}}
        $pdo->prepare('UPDATE takimlar SET oynanan_mac=?,kazanilan_mac=?,kaybedilen_mac=?,toplam_set=?,toplam_puan=? WHERE id=?')->execute([$oynanan,$galibiyet,$maglubiyet,$toplamSet,$toplamPuan,$takim_id]);
    }
}
