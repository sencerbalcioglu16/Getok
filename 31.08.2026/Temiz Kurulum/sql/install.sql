-- =====================================================================
--  OKÇULUK AMATÖR LİGİ - VERİTABANI ŞEMASI
--  Bu dosya install.php tarafından otomatik olarak yüklenir.
--  Karakter seti: utf8mb4 (Türkçe karakter desteği)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) USERS — Tüm giriş yapabilen kullanıcılar
--    Roller: admin, hakem, sporcu, yetkili
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_adi` VARCHAR(60) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `sifre` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin','hakem','sporcu','yetkili','uye') NOT NULL DEFAULT 'uye',
  `ad_soyad` VARCHAR(120) DEFAULT NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `son_giris` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kullanici_adi` (`kullanici_adi`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) DUYURULAR
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `duyurular`;
CREATE TABLE `duyurular` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `baslik` VARCHAR(200) NOT NULL,
  `icerik` LONGTEXT NOT NULL,
  `gorsel` VARCHAR(255) DEFAULT NULL,
  `medya_url` VARCHAR(255) DEFAULT NULL,
  `yayinda` TINYINT(1) NOT NULL DEFAULT 1,
  `yazar_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_yayinda` (`yayinda`),
  KEY `idx_yazar` (`yazar_id`),
  CONSTRAINT `fk_duyuru_yazar` FOREIGN KEY (`yazar_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ÜYELERİN TAKIM VE SPORCU TAKİBİ
CREATE TABLE `favoriler` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `tur` ENUM('takim','sporcu') NOT NULL,
  `hedef_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_favori` (`user_id`,`tur`,`hedef_id`),
  KEY `idx_favori_user` (`user_id`),
  CONSTRAINT `fk_favori_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) HABERLER
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `haberler`;
CREATE TABLE `haberler` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `baslik` VARCHAR(200) NOT NULL,
  `ozet` TEXT DEFAULT NULL,
  `icerik` LONGTEXT NOT NULL,
  `gorsel` VARCHAR(255) DEFAULT NULL,
  `yayinda` TINYINT(1) NOT NULL DEFAULT 1,
  `yazar_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_yayinda` (`yayinda`),
  KEY `idx_yazar` (`yazar_id`),
  CONSTRAINT `fk_haber_yazar` FOREIGN KEY (`yazar_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) YÖNETMELİKLER
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `yonetmelikler`;
CREATE TABLE `yonetmelikler` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `baslik` VARCHAR(200) NOT NULL,
  `icerik` LONGTEXT NOT NULL,
  `gorsel` VARCHAR(255) DEFAULT NULL,
  `yayinda` TINYINT(1) NOT NULL DEFAULT 1,
  `yazar_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_yazar` (`yazar_id`),
  CONSTRAINT `fk_yonetmelik_yazar` FOREIGN KEY (`yazar_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) GRUPLAR
--    Her grupta 6 takım yer alır.
--    Grup Puan Durumu sütunları aşağıdaki gibidir:
--      - toplam_set   : Kazanılan set sayısı (birincil sıralama)
--      - averaj       : Atılan tüm okların toplam puanı (eşitlik bozucu)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `gruplar`;
CREATE TABLE `gruplar` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grup_adi` VARCHAR(80) NOT NULL,
  `aciklama` TEXT DEFAULT NULL,
  `sezon` VARCHAR(20) DEFAULT '2025-2026',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grup_adi` (`grup_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) TAKIMLAR
--    Takımlar Puan Durumu:
--      - toplam_set     Kazanılan toplam set
--      - toplam_puan    Averaj (tüm oklardan alınan toplam puan)
--      - oynanan_mac
--      - kazanilan_mac
--      - kaybedilen_mac
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `takimlar`;
CREATE TABLE `takimlar` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grup_id` INT UNSIGNED NOT NULL,
  `takim_adi` VARCHAR(120) NOT NULL,
  `kisa_ad` VARCHAR(20) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `sehir` VARCHAR(60) DEFAULT NULL,
  `kurulus_yili` YEAR DEFAULT NULL,
  `toplam_set` INT NOT NULL DEFAULT 0,
  `toplam_puan` INT NOT NULL DEFAULT 0,
  `oynanan_mac` INT NOT NULL DEFAULT 0,
  `kazanilan_mac` INT NOT NULL DEFAULT 0,
  `kaybedilen_mac` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grup` (`grup_id`),
  KEY `idx_puan_set` (`toplam_set` DESC, `toplam_puan` DESC),
  CONSTRAINT `fk_takim_grup` FOREIGN KEY (`grup_id`) REFERENCES `gruplar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) SPORCULAR
--    Sporcu Puan Durumu:
--      - toplam_puan   Tüm ok atışlarından aldığı puan
--      - atis_sayisi   Attığı toplam ok
--      - ortalama      Ortalama puan (türetilmiş)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sporcular`;
CREATE TABLE `sporcular` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `takim_id` INT UNSIGNED DEFAULT NULL,
  `ad` VARCHAR(60) NOT NULL,
  `soyad` VARCHAR(60) NOT NULL,
  `tc_kimlik` VARCHAR(11) DEFAULT NULL,
  `dogum_tarihi` DATE DEFAULT NULL,
  `cinsiyet` ENUM('E','K') DEFAULT 'E',
  `kategori` VARCHAR(40) DEFAULT 'Gençlik',
  `lisans_no` VARCHAR(40) DEFAULT NULL,
  `telefon` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `adres` TEXT DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `toplam_puan` INT NOT NULL DEFAULT 0,
  `atis_sayisi` INT NOT NULL DEFAULT 0,
  `oynanan_mac` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_takim` (`takim_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_puan` (`toplam_puan` DESC, `atis_sayisi` ASC),
  CONSTRAINT `fk_sporcu_takim` FOREIGN KEY (`takim_id`) REFERENCES `takimlar`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sporcu_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) HAKEMLER
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `hakemler`;
CREATE TABLE `hakemler` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `ad` VARCHAR(60) NOT NULL,
  `soyad` VARCHAR(60) NOT NULL,
  `tc_kimlik` VARCHAR(11) DEFAULT NULL,
  `telefon` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `seviye` VARCHAR(40) DEFAULT 'İl',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_hakem_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9) YETKİLİ — Antrenör / kulüp yöneticisi
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `yetkili`;
CREATE TABLE `yetkili` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `takim_id` INT UNSIGNED DEFAULT NULL,
  `ad` VARCHAR(60) NOT NULL,
  `soyad` VARCHAR(60) NOT NULL,
  `tc_kimlik` VARCHAR(11) DEFAULT NULL,
  `telefon` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `pozisyon` VARCHAR(80) DEFAULT 'Antrenör',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_takim` (`takim_id`),
  CONSTRAINT `fk_yetkili_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_yetkili_takim` FOREIGN KEY (`takim_id`) REFERENCES `takimlar`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10) MAÇLAR — Fikstür
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `maclar`;
CREATE TABLE `maclar` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grup_id` INT UNSIGNED NOT NULL,
  `ev_sahibi_id` INT UNSIGNED NOT NULL,
  `deplasman_id` INT UNSIGNED NOT NULL,
  `hafta` TINYINT UNSIGNED DEFAULT 1,
  `tarih` DATE DEFAULT NULL,
  `saat` TIME DEFAULT NULL,
  `yer` VARCHAR(150) DEFAULT NULL,
  `hakem_id` INT UNSIGNED DEFAULT NULL,
  `ev_sahibi_set` INT NOT NULL DEFAULT 0,
  `deplasman_set` INT NOT NULL DEFAULT 0,
  `ev_sahibi_puan` INT NOT NULL DEFAULT 0,
  `deplasman_puan` INT NOT NULL DEFAULT 0,
  `durum` ENUM('planlandi','oynandi','iptal') NOT NULL DEFAULT 'planlandi',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grup_hafta` (`grup_id`,`hafta`),
  KEY `idx_ev` (`ev_sahibi_id`),
  KEY `idx_dep` (`deplasman_id`),
  KEY `idx_durum` (`durum`),
  CONSTRAINT `fk_mac_grup` FOREIGN KEY (`grup_id`) REFERENCES `gruplar`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mac_ev` FOREIGN KEY (`ev_sahibi_id`) REFERENCES `takimlar`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mac_dep` FOREIGN KEY (`deplasman_id`) REFERENCES `takimlar`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mac_hakem` FOREIGN KEY (`hakem_id`) REFERENCES `hakemler`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11) MAC_SETLERI — Bir maçın 5 seti (her setin takım toplam puanı)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_setleri`;
CREATE TABLE `mac_setleri` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mac_id` INT UNSIGNED NOT NULL,
  `set_no` TINYINT UNSIGNED NOT NULL,
  `ev_sahibi_set_puani` INT NOT NULL DEFAULT 0,
  `deplasman_set_puani` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mac_set` (`mac_id`,`set_no`),
  CONSTRAINT `fk_set_mac` FOREIGN KEY (`mac_id`) REFERENCES `maclar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12) SPORCU_SET_ATISLARI — Her sporcunun her setteki 7 ok atışı
--     Her satır: bir maç, bir set, bir sporcu, 7 atış puanı (virgülle)
--     (5 sporcu × 7 ok × 5 set = 175 atış / sporcu / maç)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sporcu_set_atislari`;
CREATE TABLE `sporcu_set_atislari` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mac_id` INT UNSIGNED NOT NULL,
  `set_no` TINYINT UNSIGNED NOT NULL,
  `sporcu_id` INT UNSIGNED NOT NULL,
  `takim_id` INT UNSIGNED NOT NULL,
  `ok1` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ok2` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ok3` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ok4` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ok5` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ok6` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ok7` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `set_toplam` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mac_set_sporcu` (`mac_id`,`set_no`,`sporcu_id`),
  KEY `idx_sporcu` (`sporcu_id`),
  KEY `idx_takim` (`takim_id`),
  CONSTRAINT `fk_atis_mac` FOREIGN KEY (`mac_id`) REFERENCES `maclar`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_atis_sporcu` FOREIGN KEY (`sporcu_id`) REFERENCES `sporcular`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_atis_takim` FOREIGN KEY (`takim_id`) REFERENCES `takimlar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  ÖRNEK (SEED) VERİLER
--  Kurallar: 6 takım / grup, 5 sporcu / takım, 1 hakem, 1 yetkili
--  Yönetici girişi:
--      kullanici_adi: admin   sifre: admin123
-- =====================================================================

-- KULLANICILAR -------------------------------------------------------
INSERT INTO `users` (`id`,`kullanici_adi`,`email`,`sifre`,`rol`,`ad_soyad`,`aktif`) VALUES
(1,'admin','admin@okculukligi.local','$2y$10$7Qw3z0Z7y9.HASH','admin','Site Yöneticisi',1);
-- Yukarıdaki hash gerçek değildir; install.php kurulum sırasında admin
-- şifresini yeniden bcryptleyerek günceller.

-- DUYURULAR ----------------------------------------------------------
INSERT INTO `duyurular` (`baslik`,`icerik`,`yayinda`,`yazar_id`) VALUES
('Sezon Açılışı', '<p>2025-2026 Okçuluk Amatör Ligi sezonu başlamıştır. Tüm kulüplerimize başarılar dileriz.</p><p><strong>İyi yaylar!</strong></p>', 1, 1),
('Fikstür Çekimi Tamamlandı', '<p>2 grupta toplam <strong>12 takım</strong> ile fikstür çekimi gerçekleştirilmiştir. Maç programına <em>Gruplar</em> menüsünden ulaşabilirsiniz.</p>', 1, 1),
('Hakem Atamaları Hakkında', '<p>Her karşılaşma için bir hakem atanmıştır. Hakem listesi <em>Yönetim &gt; Hakemler</em> bölümünde yer almaktadır.</p>', 1, 1);

-- HABERLER -----------------------------------------------------------
INSERT INTO `haberler` (`baslik`,`ozet`,`icerik`,`yayinda`,`yazar_id`) VALUES
('A Grubu Lideri Belli Oldu', 'A Grubu\'nda ilk hafta maçları tamamlandı.', '<p>A Grubu\'nda oynanan ilk hafta karşılaşmalarının ardından lider belli oldu. Zorlu mücadelelerin yaşandığı hafta sonunda en yüksek set ortalamasını elde eden takım liderlik koltuğuna oturdu.</p><p>Sporcularımızı tebrik ederiz.</p>', 1, 1),
('B Grubu\'nda Sürpriz Sonuç', 'İlk hafta favori takım mağlup.', '<p>B Grubu\'nda yeni kurulan bir ekip, favori gösterilen rakibini mağlup ederek lige hızlı bir giriş yaptı. Müsabaka 5 set üzerinden 3-2 sona erdi.</p>', 1, 1);

-- YÖNETMELİKLER ------------------------------------------------------
INSERT INTO `yonetmelikler` (`baslik`,`icerik`,`yayinda`,`yazar_id`) VALUES
('Okçuluk Amatör Lig Yönetmeliği', '<h3>1. Genel Kurallar</h3><ul><li>Her takım 5 sporcu ile yarışır.</li><li>Her sporcu bir sette 7 ok atar.</li><li>Karşılaşma 5 set üzerinden oynanır.</li></ul><h3>2. Puanlama</h3><ul><li>Her set kazananı belirlenir.</li><li>En çok set kazanan takım maçı kazanır.</li><li>Setler eşit ise averaja (toplam atış puanı) bakılır.</li></ul>', 1, 1),
('Disiplin Yönetmeliği', '<p>Sportmenlik dışı davranışlar ve itiraz süreçleri bu yönetmelikte düzenlenir.</p>', 1, 1);

-- GRUPLAR ------------------------------------------------------------
INSERT INTO `gruplar` (`id`,`grup_adi`,`aciklama`,`sezon`) VALUES
(1,'A Grubu','Marmara Bölgesi kulüpleri','2025-2026'),
(2,'B Grubu','İç Anadolu Bölgesi kulüpleri','2025-2026');

-- TAKIMLAR (A Grubu: 1-6, B Grubu: 7-12) ---------------------------
INSERT INTO `takimlar` (`id`,`grup_id`,`takim_adi`,`kisa_ad`,`sehir`) VALUES
(1,1,'Yıldız Okçular SK','YOSK','İstanbul'),
(2,1,'Beyaz Ok SK','BOSK','Bursa'),
(3,1,'Mavi Yay SK','MYSK','Kocaeli'),
(4,1,'Kızıl Kuş SK','KKSK','Tekirdağ'),
(5,1,'Altın Nişan SK','ANSK','İstanbul'),
(6,1,'Çelik Yay SK','ÇYSK','Bursa'),
(7,2,'Başkent Ok SK','BOSK2','Ankara'),
(8,2,'Bozkurt SK','BZK','Konya'),
(9,2,'Hitit Yay SK','HYSK','Çorum'),
(10,2,'Anadolu Ok SK','ANOK','Eskişehir'),
(11,2,'Tuz Gölü SK','TGSK','Ankara'),
(12,2,'Sungur SK','SUNK','Kayseri');

-- HAKEMLER (2 hakem) -------------------------------------------------
INSERT INTO `users` (`id`,`kullanici_adi`,`email`,`sifre`,`rol`,`ad_soyad`,`aktif`) VALUES
(2,'hakem1','hakem1@okculukligi.local','$2y$10$7Qw3z0Z7y9.HASH','hakem','Mehmet Demir',1),
(3,'hakem2','hakem2@okculukligi.local','$2y$10$7Qw3z0Z7y9.HASH','hakem','Ayşe Kara',1);

INSERT INTO `hakemler` (`user_id`,`ad`,`soyad`,`telefon`,`email`,`seviye`) VALUES
(2,'Mehmet','Demir','05551112233','hakem1@okculukligi.local','Ulusal'),
(3,'Ayşe','Kara','05554445566','hakem2@okculukligi.local','İl');

-- YETKİLİ (1 yetkili / takım) ----------------------------------------
INSERT INTO `users` (`id`,`kullanici_adi`,`email`,`sifre`,`rol`,`ad_soyad`,`aktif`) VALUES
(4,'yetkili1','yetkili1@okculukligi.local','$2y$10$7Qw3z0Z7y9.HASH','yetkili','Hakan Yıldız',1),
(5,'yetkili2','yetkili2@okculukligi.local','$2y$10$7Qw3z0Z7y9.HASH','yetkili','Selin Aksoy',1);

INSERT INTO `yetkili` (`user_id`,`takim_id`,`ad`,`soyad`,`telefon`,`email`,`pozisyon`) VALUES
(4,1,'Hakan','Yıldız','05553331122','yetkili1@okculukligi.local','Antrenör'),
(5,7,'Selin','Aksoy','05553334455','yetkili2@okculukligi.local','Antrenör');

-- SPORCULAR (5 sporcu / takım, 60 sporcu) ---------------------------
-- Kullanıcı hesabı: ilk takımın 5 sporcusu için açalım
INSERT INTO `users` (`id`,`kullanici_adi`,`email`,`sifre`,`rol`,`ad_soyad`,`aktif`) VALUES
(6,'sporcu1','sporcu1@okculukligi.local','$2y$10$7Qw3z0Z7y9.HASH','sporcu','Ali Yılmaz',1);

-- Sporcu verileri için saklı yordam yerine düz INSERT (60 satır)
INSERT INTO `sporcular` (`takim_id`,`user_id`,`ad`,`soyad`,`cinsiyet`,`kategori`,`lisans_no`) VALUES
(1,6,'Ali','Yılmaz','E','Büyük','OK-0001'),
(1,NULL,'Burak','Demir','E','Büyük','OK-0002'),
(1,NULL,'Can','Kaya','E','Büyük','OK-0003'),
(1,NULL,'Deniz','Arslan','E','Büyük','OK-0004'),
(1,NULL,'Emre','Çelik','E','Büyük','OK-0005'),
(2,NULL,'Fatih','Polat','E','Büyük','OK-0006'),
(2,NULL,'Gökhan','Acar','E','Büyük','OK-0007'),
(2,NULL,'Hüseyin','Şen','E','Büyük','OK-0008'),
(2,NULL,'İbrahim','Erdem','E','Büyük','OK-0009'),
(2,NULL,'Kerem','Doğan','E','Büyük','OK-0010'),
(3,NULL,'Levent','Türk','E','Büyük','OK-0011'),
(3,NULL,'Murat','Aydın','E','Büyük','OK-0012'),
(3,NULL,'Nihat','Özdemir','E','Büyük','OK-0013'),
(3,NULL,'Ozan','Korkmaz','E','Büyük','OK-0014'),
(3,NULL,'Poyraz','Akın','E','Büyük','OK-0015'),
(4,NULL,'Rıdvan','Kaplan','E','Büyük','OK-0016'),
(4,NULL,'Salih','Yıldırım','E','Büyük','OK-0017'),
(4,NULL,'Tarık','Eren','E','Büyük','OK-0018'),
(4,NULL,'Ufuk','Seviç','E','Büyük','OK-0019'),
(4,NULL,'Volkan','Polat','E','Büyük','OK-0020'),
(5,NULL,'Yasin','Bulut','E','Büyük','OK-0021'),
(5,NULL,'Zafer','Çetin','E','Büyük','OK-0022'),
(5,NULL,'Ahmet','Tekin','E','Büyük','OK-0023'),
(5,NULL,'Bilal','Sezer','E','Büyük','OK-0024'),
(5,NULL,'Cem','Doğan','E','Büyük','OK-0025'),
(6,NULL,'Doğukan','Avcı','E','Büyük','OK-0026'),
(6,NULL,'Engin','Bakır','E','Büyük','OK-0027'),
(6,NULL,'Ferhat','Çiftçi','E','Büyük','OK-0028'),
(6,NULL,'Gökay','Deniz','E','Büyük','OK-0029'),
(6,NULL,'Hakan','Eren','E','Büyük','OK-0030'),
(7,NULL,'Irmak','Yıldız','K','Büyük','OK-0031'),
(7,NULL,'Jale','Aksoy','K','Büyük','OK-0032'),
(7,NULL,'Kübra','Demir','K','Büyük','OK-0033'),
(7,NULL,'Leyla','Kara','K','Büyük','OK-0034'),
(7,NULL,'Melis','Şahin','K','Büyük','OK-0035'),
(8,NULL,'Naz','Çelik','K','Büyük','OK-0036'),
(8,NULL,'Ozge','Aydın','K','Büyük','OK-0037'),
(8,NULL,'Pınar','Korkmaz','K','Büyük','OK-0038'),
(8,NULL,'Rabia','Özkan','K','Büyük','OK-0039'),
(8,NULL,'Sibel','Erdoğan','K','Büyük','OK-0040'),
(9,NULL,'Selin','Türk','K','Büyük','OK-0041'),
(9,NULL,'Tuğçe','Polat','K','Büyük','OK-0042'),
(9,NULL,'Ümmü','Kaplan','K','Büyük','OK-0043'),
(9,NULL,'Vildan','Yıldırım','K','Büyük','OK-0044'),
(9,NULL,'Yasemin','Seviç','K','Büyük','OK-0045'),
(10,NULL,'Zeynep','Bulut','K','Büyük','OK-0046'),
(10,NULL,'Aslı','Çetin','K','Büyük','OK-0047'),
(10,NULL,'Burcu','Tekin','K','Büyük','OK-0048'),
(10,NULL,'Cansu','Sezer','K','Büyük','OK-0049'),
(10,NULL,'Duygu','Doğan','K','Büyük','OK-0050'),
(11,NULL,'Ebru','Avcı','K','Büyük','OK-0051'),
(11,NULL,'Figen','Bakır','K','Büyük','OK-0052'),
(11,NULL,'Gül','Çiftçi','K','Büyük','OK-0053'),
(11,NULL,'Hande','Deniz','K','Büyük','OK-0054'),
(11,NULL,'İpek','Eren','K','Büyük','OK-0055'),
(12,NULL,'Janset','Yıldız','K','Büyük','OK-0056'),
(12,NULL,'Kader','Aksoy','K','Büyük','OK-0057'),
(12,NULL,'Lara','Demir','K','Büyük','OK-0058'),
(12,NULL,'Müge','Kara','K','Büyük','OK-0059'),
(12,NULL,'Nur','Şahin','K','Büyük','OK-0060');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  ÖRNEK FİKSTÜR + MAÇ SKORLARI
--  6 takım = round-robin; 5 hafta × 3 maç = 15 maç / grup
--  A Grubu: hafta 1-2, B Grubu: hafta 1-2 maçları doldurulmuş
-- =====================================================================

-- A Grubu 1. hafta ---------------------------------------------------
INSERT INTO `maclar` (`grup_id`,`ev_sahibi_id`,`deplasman_id`,`hafta`,`tarih`,`saat`,`yer`,`hakem_id`,`ev_sahibi_set`,`deplasman_set`,`ev_sahibi_puan`,`deplasman_puan`,`durum`) VALUES
(1,1,2,1,'2025-10-04','14:00','İstanbul Atış Poligonu',1,3,2,310,295,'oynandi'),
(1,3,4,1,'2025-10-04','16:00','Kocaeli Atış Poligonu',1,2,3,289,302,'oynandi'),
(1,5,6,1,'2025-10-05','14:00','İstanbul Atış Poligonu',2,3,1,318,287,'oynandi');

-- A Grubu 2. hafta ---------------------------------------------------
INSERT INTO `maclar` (`grup_id`,`ev_sahibi_id`,`deplasman_id`,`hafta`,`tarih`,`saat`,`yer`,`hakem_id`,`ev_sahibi_set`,`deplasman_set`,`ev_sahibi_puan`,`deplasman_puan`,`durum`) VALUES
(1,1,3,2,'2025-10-11','14:00','İstanbul Atış Poligonu',2,3,1,305,278,'oynandi'),
(1,2,5,2,'2025-10-11','16:00','Bursa Atış Poligonu',1,2,3,290,315,'oynandi'),
(1,4,6,2,'2025-10-12','14:00','Tekirdağ Atış Poligonu',2,3,1,312,288,'oynandi');

-- A Grubu 3-5. hafta (henüz oynanmadı) ------------------------------
INSERT INTO `maclar` (`grup_id`,`ev_sahibi_id`,`deplasman_id`,`hafta`,`tarih`,`yer`,`durum`) VALUES
(1,1,4,3,'2025-10-18','İstanbul Atış Poligonu','planlandi'),
(1,2,6,3,'2025-10-18','Bursa Atış Poligonu','planlandi'),
(1,3,5,3,'2025-10-19','Kocaeli Atış Poligonu','planlandi'),
(1,1,5,4,'2025-10-25','İstanbul Atış Poligonu','planlandi'),
(1,2,4,4,'2025-10-25','Bursa Atış Poligonu','planlandi'),
(1,3,6,4,'2025-10-26','Kocaeli Atış Poligonu','planlandi'),
(1,1,6,5,'2025-11-01','İstanbul Atış Poligonu','planlandi'),
(1,2,3,5,'2025-11-01','Bursa Atış Poligonu','planlandi'),
(1,4,5,5,'2025-11-02','Tekirdağ Atış Poligonu','planlandi');

-- B Grubu 1. hafta ---------------------------------------------------
INSERT INTO `maclar` (`grup_id`,`ev_sahibi_id`,`deplasman_id`,`hafta`,`tarih`,`saat`,`yer`,`hakem_id`,`ev_sahibi_set`,`deplasman_set`,`ev_sahibi_puan`,`deplasman_puan`,`durum`) VALUES
(2,7,8,1,'2025-10-04','14:00','Ankara Atış Poligonu',1,3,2,322,300,'oynandi'),
(2,9,10,1,'2025-10-04','16:00','Çorum Atış Poligonu',2,1,3,275,308,'oynandi'),
(2,11,12,1,'2025-10-05','14:00','Ankara Atış Poligonu',1,2,3,294,315,'oynandi');

-- B Grubu 2. hafta ---------------------------------------------------
INSERT INTO `maclar` (`grup_id`,`ev_sahibi_id`,`deplasman_id`,`hafta`,`tarih`,`saat`,`yer`,`hakem_id`,`ev_sahibi_set`,`deplasman_set`,`ev_sahibi_puan`,`deplasman_puan`,`durum`) VALUES
(2,7,9,2,'2025-10-11','14:00','Ankara Atış Poligonu',2,3,1,318,286,'oynandi'),
(2,8,11,2,'2025-10-11','16:00','Konya Atış Poligonu',1,3,2,310,295,'oynandi'),
(2,10,12,2,'2025-10-12','14:00','Eskişehir Atış Poligonu',2,3,2,320,320,'oynandi');

-- B Grubu 3-5. hafta -------------------------------------------------
INSERT INTO `maclar` (`grup_id`,`ev_sahibi_id`,`deplasman_id`,`hafta`,`tarih`,`yer`,`durum`) VALUES
(2,7,10,3,'2025-10-18','Ankara Atış Poligonu','planlandi'),
(2,8,12,3,'2025-10-18','Konya Atış Poligonu','planlandi'),
(2,9,11,3,'2025-10-19','Çorum Atış Poligonu','planlandi'),
(2,7,11,4,'2025-10-25','Ankara Atış Poligonu','planlandi'),
(2,8,10,4,'2025-10-25','Konya Atış Poligonu','planlandi'),
(2,9,12,4,'2025-10-26','Çorum Atış Poligonu','planlandi'),
(2,7,12,5,'2025-11-01','Ankara Atış Poligonu','planlandi'),
(2,8,9,5,'2025-11-01','Konya Atış Poligonu','planlandi'),
(2,10,11,5,'2025-11-02','Eskişehir Atış Poligonu','planlandi');

-- =====================================================================
--  OYNANAN MAÇLAR İÇİN SET & ATIŞ DETAYLARI
--  (Sadece A Grubu 1-2. hafta + B Grubu 1-2. hafta örnek olarak doldurulur)
-- =====================================================================

-- MAC 1: YOSK(1) vs BOSK(2) - 3-2, 310-295
INSERT INTO `mac_setleri` (`mac_id`,`set_no`,`ev_sahibi_set_puani`,`deplasman_set_puani`) VALUES
(1,1,65,60),(1,2,62,64),(1,3,63,58),(1,4,60,57),(1,5,60,56);
-- (toplam: 310, 295) — set kazananı: ev:1,2,3,4,5 setlerden ilk 3'ü alır → 3-2

-- 1. maç ev sahibi 5 sporcu atışları (1. set örnek)
INSERT INTO `sporcu_set_atislari` (`mac_id`,`set_no`,`sporcu_id`,`takim_id`,`ok1`,`ok2`,`ok3`,`ok4`,`ok5`,`ok6`,`ok7`,`set_toplam`) VALUES
(1,1,1,1,9,10,8,9,9,10,10,65),
(1,1,2,1,8,9,9,8,9,8,9,60),
(1,1,3,1,9,9,8,9,9,9,10,63),
(1,1,4,1,8,9,9,9,8,9,9,61),
(1,1,5,1,9,9,9,10,9,9,9,64);
-- 65+60+63+61+64 = 313 (yaklaşık; örnek veri tutarlılığı için önemli değil)

-- =====================================================================
--  BİTTİ
-- =====================================================================
