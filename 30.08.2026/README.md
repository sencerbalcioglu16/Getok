# 🎯 Okçuluk Amatör Ligi Yönetim Sistemi

Kapsamlı bir PHP + MySQL spor ligi yönetim sistemi. 5 sporcu × 7 ok × 5 set
kuralına göre karşılaşma yönetimi, puan durumu, averaj hesabı, fikstür
oluşturma, üyelik ve admin paneli.

## 🚀 Hızlı Kurulum

1. **Dosyaları sunucuya yükle** (XAMPP / WAMP / Linux + Apache + PHP 7.4+ + MySQL).
2. Tarayıcıdan `http://localhost/okculuk-ligi/install.php` adresine git.
3. Veritabanı bilgilerini gir (sunucu, kullanıcı, parola) ve yönetici hesabı oluştur.
4. "Kurulumu Başlat" butonuna tıkla. Sistem otomatik olarak:
   - Veritabanını oluşturur
   - Tüm tabloları kurar
   - Örnek verileri yükler (2 grup, 12 takım, 60 sporcu, maçlar vb.)
   - Admin şifresini bcrypt ile şifreler
5. Ana sayfaya yönlendirilirsin.

## 🔐 Varsayılan Girişler (Kurulum Sonrası)

| Rol      | Kullanıcı Adı | Şifre       |
|----------|----------------|-------------|
| Yönetici | `admin`        | `admin123`  |
| Hakem    | `hakem1`       | `hakem123`  |
| Yetkili  | `yetkili1`     | `yetkili123`|
| Sporcu   | `sporcu1`      | `sporcu123` |

> **ÖNEMLİ:** İlk girişten sonra bu şifreleri değiştirin!

## 📁 Klasör Yapısı

```
Minimax4/
├── install.php              ← Kurulum sihirbazı (ilk çalıştırma)
├── index.php                ← Ana sayfa
├── login.php / register.php / logout.php
├── gruplar.php / grup.php   ← Grup listesi ve fikstür
├── takimlar.php / takim.php ← Takım listesi ve detay
├── sporcular.php / sporcu.php
├── haberler.php / duyurular.php / yonetmelik.php
│
├── admin/                   ← Yönetim paneli
│   ├── partials/layout.php  ← Sidebar + üst bar
│   ├── index.php            ← Pano
│   ├── duyurular.php
│   ├── haberler.php
│   ├── yonetmelikler.php
│   ├── gruplar.php          ← Fikstür oluşturma
│   ├── takimlar.php
│   ├── sporcular.php
│   ├── hakemler.php
│   ├── yetkili.php
│   ├── maclar.php
│   ├── mac-skor.php         ← ⭐ Hakem skor girişi (5×7×5 matris)
│   └── profil.php
│
├── config/
│   ├── config.php           ← Otomatik üretilir
│   ├── database.php         ← Otomatik üretilir
│   └── install.lock         ← Kurulum tamamlandı işareti
│
├── includes/
│   ├── header.php / footer.php
│   └── functions.php        ← Tüm yardımcı fonksiyonlar
│
├── sql/install.sql          ← Şema + örnek veri
│
└── assets/
    ├── css/style.css
    ├── js/main.js
    └── uploads/             ← Yüklenen görseller (klasör)
```

## 🏆 Lig Kuralları (Kodda Uygulanmıştır)

1. Her takım **5 sporcu** ile yarışır.
2. Her sporcu bir sette **7 ok** atar.
3. Karşılaşma **5 set** üzerinden oynanır.
4. Her set kazananı = 5 sporcunun 7'şer okunun toplam puanı.
5. **En çok set kazanan** takım maçı kazanır.
6. Puan durumu sıralaması:
   - **Birincil:** Kazanılan toplam set sayısı
   - **Eşitlik bozucu:** Averaj (tüm atışlardan alınan toplam puan)
7. Bir ok en fazla **10 puan** (Olimpiyat standardı).

## 🗃 Veritabanı Tabloları

| Tablo               | Açıklama |
|---------------------|----------|
| `users`             | Tüm giriş yapabilen hesaplar (admin/hakem/sporcu/yetkili) |
| `duyurular`         | Site duyuruları (görsel + metin editörü) |
| `haberler`          | Haberler (görsel + metin editörü) |
| `yonetmelikler`     | Yönetmelikler (görsel + metin editörü) |
| `gruplar`           | Grup tanımları (her grup 6 takım) |
| `takimlar`          | Takımlar (grup, puan durumu sütunlarıyla) |
| `sporcular`         | Sporcular (puan durumu sütunlarıyla) |
| `hakemler`          | Hakemler |
| `yetkili`           | Antrenör / kulüp yöneticisi |
| `maclar`            | Fikstür ve maç sonuçları |
| `mac_setleri`       | Bir maçın 5 setinin takım toplamları |
| `sporcu_set_atislari` | Her sporcunun her setteki 7 ok atışı |

## 👥 Roller ve Yetkiler

| Rol      | Yapabildikleri |
|----------|----------------|
| **Admin** | Her şey. Tüm CRUD işlemleri. |
| **Hakem** | Maç skorlarını girer (`admin/mac-skor.php`). |
| **Yetkili** | Kendi takımının sporcu listesini oluşturur/düzenler. |
| **Sporcu** | Yalnızca kendi kişisel bilgilerini düzenler. |

Yetki kontrolü her sayfada `zorunlu_rol(...)` ile yapılır. Yetkisiz erişimlerde 403 döner.

## 📊 Puan Durumu Mantığı

**Grup / Takım Puan Durumu:**
```sql
ORDER BY toplam_set DESC, toplam_puan DESC
```

**Sporcu Puan Durumu:**
```sql
ORDER BY toplam_puan DESC, atis_sayisi ASC
```

İstatistikler `mac_istatistik_guncelle()` fonksiyonu tarafından her maç skor
girişinden sonra otomatik olarak yeniden hesaplanır. Tüm takım ve sporcu
kayıtları, sadece durumu `oynandi` olan maçlar üzerinden güncellenir — bu
yüzden planlanmış maçlar istatistikleri etkilemez.

## 🎯 Hakem Skor Girişi

`admin/mac-skor.php` sayfası şu matrisi içerir:

```
                  Set 1      Set 2      Set 3      Set 4      Set 5
                 O1..O7 Σ   O1..O7 Σ   O1..O7 Σ   O1..O7 Σ   O1..O7 Σ
Sporcu 1          [_ _ _ _]  ...
Sporcu 2
Sporcu 3
Sporcu 4
Sporcu 5
─────────────────────────────────────────────────────────────
Takım Toplam         Σ         Σ         Σ         Σ         Σ
```

- 0–10 arası ok puanı girilir, geçersiz değerler otomatik düzeltilir.
- Satır toplamı (sporcu set toplamı), sütun toplamı (takım set toplamı) ve
  set kazananı JavaScript ile canlı hesaplanır.
- "Skorları Kaydet" denilince tüm atışlar veritabanına yazılır ve maç
  istatistikleri otomatik güncellenir.

## 🛠 Teknoloji

- **PHP 7.4+** (PDO + MySQL)
- **MySQL 5.7+ / MariaDB 10.3+** (utf8mb4)
- **Saf CSS + JS** (framework yok, sadece dahili)
- **BCrypt** şifre hash
- **CSRF** koruması tüm POST formlarında
- **Session** tabanlı kimlik doğrulama

## 🔄 Fikstür Oluşturma

Bir grup sayfasında (`admin/gruplar.php`):
1. Grubu oluştur.
2. 6 takımı gruba ekle.
3. "Fikstür Oluştur" butonuna tıkla.
4. Sistem round-robin algoritmasıyla 5 hafta × 3 maç = 15 maç üretir.
5. (Mevcut planlanmış maçlar silinir, oynanmış olanlar korunur.)

## 📝 Gerçek Veriye Geçiş

Sistem production'a hazır. Geçiş için:
1. `config/install.lock` silinirse install tekrar çalışır (verileri siler).
2. **Verileri silmeden gerçek veriye geçmek için:**
   - Admin → Duyurular / Haberler / Yönetmelikler sekmesinden yeni içerik girin.
   - Admin → Gruplar → Yeni grup ekleyin.
   - Admin → Takımlar → Takımları ekleyin.
   - Admin → Sporcular → Sporcu ekleyin, takıma atayın.
   - Admin → Hakemler / Yetkili → Hesap açın.
   - Admin → Maçlar → Manuel maç ekleyin veya "Fikstür Oluştur" ile otomatik üretin.
   - Hakem olarak giriş yapıp `admin/mac-skor.php` üzerinden skor girin.

## 🆘 Sorun Giderme

- **Veritabanı bağlantı hatası:** `config/database.php` dosyasındaki
  bilgileri kontrol edin. `config/install.lock` varsa kurulum yapılmış demektir.
- **Beyaz sayfa:** PHP hata gösterimini açın (`php.ini`'de `display_errors=On`).
- **Görsel yüklenmiyor:** `assets/uploads/` alt klasörlerinin yazma izni
  olduğundan emin olun (Linux: `chmod -R 777 assets/uploads`).
- **Karakterler bozuk:** MySQL `utf8mb4` ve PHP dosyaları UTF-8 (BOM'suz).

## 📄 Lisans

Bu sistem açık kaynak kodludur, eğitim ve amatör spor organizasyonları için
ücretsiz kullanılabilir.
