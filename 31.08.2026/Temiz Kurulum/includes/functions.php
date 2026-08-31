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

// ---- Yeni sürüm şema uyumluluğu (mevcut kurulumları da günceller) ----
if (!function_exists('uyumluluk_guncelle')) {
    function uyumluluk_guncelle($pdo) {
        static $calisti = false;
        if ($calisti) return;
        $calisti = true;
        try {
            $rol = $pdo->query("SHOW COLUMNS FROM users LIKE 'rol'")->fetch();
            if ($rol && strpos($rol['Type'], "'uye'") === false) {
                $pdo->exec("ALTER TABLE users MODIFY rol ENUM('admin','hakem','sporcu','yetkili','uye') NOT NULL DEFAULT 'uye'");
            }
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
        } catch (PDOException $e) { /* eski veya sınırlı kurulumlarda siteyi durdurma */ }
    }
}

if (isset($pdo) && $pdo instanceof PDO) uyumluluk_guncelle($pdo);

if (!function_exists('coklu_lig_yukselt')) {
    function coklu_lig_yukselt($pdo) {
        static $calisti = false; if ($calisti) return; $calisti = true;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ligler (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lig_adi VARCHAR(150) NOT NULL, tur ENUM('takim','bireysel') NOT NULL DEFAULT 'takim', sezon VARCHAR(30) DEFAULT NULL, aciklama TEXT DEFAULT NULL, aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sezonlar (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sezon_adi VARCHAR(30) NOT NULL UNIQUE, durum ENUM('aktif','resmilesti') NOT NULL DEFAULT 'aktif', resmi_tarih DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_icerikleri (anahtar VARCHAR(80) PRIMARY KEY, baslik VARCHAR(200) NOT NULL, icerik LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bireysel_lig_kayitlari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lig_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL, sporcu_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_bireysel_kayit(lig_id,sporcu_id), KEY idx_bl_grup(grup_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bireysel_fikstur (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lig_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL, tarih DATE NOT NULL, saat TIME DEFAULT NULL, yer VARCHAR(160) NOT NULL, aciklama VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_bf_lig_grup(lig_id,grup_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS sezon_sonuclari (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sezon_id INT UNSIGNED NOT NULL, lig_id INT UNSIGNED NOT NULL, grup_id INT UNSIGNED NOT NULL, tur ENUM('takim','bireysel') NOT NULL, sira INT UNSIGNED NOT NULL, hedef_id INT UNSIGNED NULL, ad VARCHAR(200) NOT NULL, oynanan INT NOT NULL DEFAULT 0, galibiyet INT NOT NULL DEFAULT 0, maglubiyet INT NOT NULL DEFAULT 0, toplam_set INT NOT NULL DEFAULT 0, toplam_puan INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_sezon_sonuc (sezon_id,lig_id,grup_id,tur,sira), KEY idx_arsiv_filtre (sezon_id,lig_id,grup_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $col = $pdo->query("SHOW COLUMNS FROM gruplar LIKE 'lig_id'")->fetchAll();
            if (!$col) $pdo->exec("ALTER TABLE gruplar ADD lig_id INT UNSIGNED NULL AFTER id");
            $sezonCol = $pdo->query("SHOW COLUMNS FROM ligler LIKE 'sezon_id'")->fetchAll();
            if (!$sezonCol) $pdo->exec("ALTER TABLE ligler ADD sezon_id INT UNSIGNED NULL AFTER id");
            foreach ([defined('LIG_SEZON')?LIG_SEZON:'2025-2026','2026-2027'] as $sezonAdi) $pdo->prepare("INSERT IGNORE INTO sezonlar(sezon_adi) VALUES(?)")->execute([$sezonAdi]);
            $pdo->exec("UPDATE ligler l JOIN sezonlar s ON s.sezon_adi=l.sezon SET l.sezon_id=s.id WHERE l.sezon_id IS NULL AND l.sezon IS NOT NULL");
            $ana = $pdo->query("SELECT id FROM ligler WHERE tur='takim' ORDER BY id LIMIT 1")->fetchColumn();
            if (!$ana) { $pdo->prepare("INSERT INTO ligler (lig_adi,tur,sezon,sezon_id,aciklama) VALUES (?,?,?,?,?)")->execute(['Geleneksel Okçuluk Takım Ligleri','takim',defined('LIG_SEZON')?LIG_SEZON:null,$pdo->query("SELECT id FROM sezonlar WHERE sezon_adi=".$pdo->quote(defined('LIG_SEZON')?LIG_SEZON:'2025-2026'))->fetchColumn(),'Mevcut takım karşılaşmalarının yer aldığı ana lig.']); $ana=(int)$pdo->lastInsertId(); }
            $pdo->prepare("UPDATE gruplar SET lig_id=? WHERE lig_id IS NULL")->execute([$ana]);
            $bireysel = $pdo->query("SELECT id FROM ligler WHERE tur='bireysel' ORDER BY id LIMIT 1")->fetchColumn();
            if (!$bireysel) {
                $pdo->prepare("INSERT INTO ligler (lig_adi,tur,sezon,sezon_id,aciklama) VALUES (?,?,?,?,?)")->execute(['Bireysel Okçuluk Bölge Ligleri','bireysel',defined('LIG_SEZON')?LIG_SEZON:null,$pdo->query("SELECT id FROM sezonlar WHERE sezon_adi=".$pdo->quote(defined('LIG_SEZON')?LIG_SEZON:'2025-2026'))->fetchColumn(),'Sporcuların bölge gruplarında bireysel olarak yarıştığı lig.']);
                $bireysel=(int)$pdo->lastInsertId();
                foreach (['Marmara Bölgesi','İç Anadolu Bölgesi'] as $bolge) $pdo->prepare("INSERT INTO gruplar (lig_id,grup_adi,aciklama,sezon) VALUES (?,?,?,?)")->execute([$bireysel,$bolge,'Bireysel lig bölgesi',defined('LIG_SEZON')?LIG_SEZON:null]);
            }
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
                        $kayitlar = $pdo->prepare('SELECT id,takim_adi,oynanan_mac,kazanilan_mac,kaybedilen_mac,toplam_set,toplam_puan FROM takimlar WHERE grup_id=? ORDER BY toplam_puan DESC, kazanilan_mac DESC, toplam_set DESC, takim_adi');
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
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return '<input type="hidden" name="csrf" value="' . $token . '">';
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
        $st = $pdo->prepare("SELECT set_no, ev_sahibi_set_puani, deplasman_set_puani FROM mac_setleri WHERE mac_id = ? ORDER BY set_no");
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
            if ($s['ev_sahibi_set_puani'] > $s['deplasman_set_puani']) $ev_toplam_set++;
            elseif ($s['deplasman_set_puani'] > $s['ev_sahibi_set_puani']) $dep_toplam_set++;
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


    }
}
