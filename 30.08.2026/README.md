# Geleneksel Türk Okçuluğu Bölge Ligleri

Geleneksel Türk okçuluğu organizasyonları için geliştirilmiş, PHP ve MySQL tabanlı bir lig ve turnuva yönetim sistemidir. Takım ligleri, **Bireysel Bölge Ligleri**, takım turnuvaları ve bireysel turnuvalar; tek bir yönetim panelinden yönetilebilir.

Sistem; fikstür, karşılaşma ve set puanı girişi, puan durumları, sezon arşivi, sporcu/takım kartları, duyurular ve kullanıcı takibi gibi ihtiyaçları kapsar.

## Öne çıkan özellikler

- Takım Ligi, Bireysel Bölge Ligleri, takım turnuvası ve bireysel turnuva yönetimi
- Sezon bazlı organizasyonlar, grup yapısı ve resmî sonuç arşivi
- Takım liglerinde set puanı ve atış puanı bazlı puan durumu
- Bireysel organizasyonlarda takımdan bağımsız sporcu puan ve sıralaması
- 16 veya 32 katılımcılı turnuvalar için dinamik eşleşme ağacı
- Planlanan ve tamamlanan karşılaşmaların fikstür ve sonuç ekranları
- Hakem için adım adım set bazlı skor girişi
- Eşitlik bozma atışları ile set galibini belirleme
- Sporcu ve takım profilleri, fotoğraf/logo yükleme ve favoriye alma
- Duyuru, haber, yönetmelik ve kurumsal sayfalar için HTML editörü
- Mobil uyumlu, sağdan açılır ana menü
- Rol bazlı yönetim paneli: Yönetici, Hakem, Yetkili, Sporcu ve Üye

## Karşılaşma ve puanlama kuralları

Takım karşılaşmalarında bir takım sette beş sporcu ile yer alır. Her sporcu, bir sette **7 ok** atar ve bu yedi okun toplam puanı tek değer olarak kaydedilir. Bir sporcunun set puanı en fazla **21** olabilir.

- Karşılaşmalar setler üzerinden sonuçlanır.
- Bir set eşit bitemez. Eşitlikte, iki taraf için eşitlik bozma atışları kaydedilir ve galip belirlenene kadar devam edilir.
- Eşitlik bozma puanları set galibini belirler; takımın sezonluk atış averajına eklenmez.
- Takım Ligi grup sıralaması önce set puanına, sonra atış puanına göre yapılır.
- Bireysel Bölge Ligleri ve bireysel turnuvalar, takım organizasyonlarından bağımsız istatistik tutar.

## Gereksinimler

- PHP 7.4 veya üzeri
- MySQL 5.7+ ya da MariaDB 10.3+
- Apache veya PHP destekli eşdeğer bir web sunucusu
- `pdo_mysql` PHP eklentisi
- `assets/uploads/` klasörüne yazma izni

XAMPP ile yerel kullanım için proje klasörünü `htdocs` altına koymanız yeterlidir.

## Kurulum

1. Proje dosyalarını web sunucunuzun yayın klasörüne yükleyin.
2. Hosting panelinden boş bir MySQL veritabanı ve buna yetkili bir kullanıcı oluşturun.
3. Tarayıcıdan `https://alanadiniz.com/proje-klasoru/install.php` adresini açın.
4. **Mevcut veritabanına kur** seçeneğini kullanın; bu seçenek hosting hesabınızda veritabanı oluşturma yetkisi gerektirmez.
5. Veritabanı ve ilk yönetici hesabı bilgilerini girerek kurulumu tamamlayın.

Kurulum tamamlandığında `config/config.php`, `config/database.php` ve `config/install.lock` otomatik oluşturulur. Bu dosyalar ortamınıza özeldir; depoda veya temiz kurulum paketinde bulunmamalıdır.

> Kurulum işlemi hedef veritabanındaki sistem tablolarını yeniden oluşturabilir. Canlı bir kurulum üzerinde işlem yapmadan önce mutlaka yedek alın.

### Demo kurulum paketi

`okculuk-ligi-demo-kurulum.zip` paketi, kurulum sırasında örnek organizasyonlar, takımlar, sporcular, görseller ve sonuçlanmış karşılaşmalar üretir. Normal paket temiz kurulum içindir.

Kurulum ekranında belirlediğiniz yönetici kullanıcı adı ve parolası kullanılır. Demo içerikte ek örnek hesaplar da oluşturulabilir; canlı kullanıma geçmeden önce tüm örnek hesapları gözden geçirip parolalarını değiştirin.

## Roller ve erişimler

| Rol | Yetkiler |
| --- | --- |
| Yönetici | Tüm organizasyonları, içerikleri, kullanıcıları ve sistem ayarlarını yönetir. |
| Hakem | Kendisine tanımlı alanlarda karşılaşmaları görür ve set skorlarını girer. |
| Yetkili – Yönetici | En fazla iki takım oluşturabilir; kendi takımlarını ve sporcularını yönetebilir. |
| Yetkili – Antrenör | Yetki tanımına uygun kendi takımının sporcu işlemlerini yürütür. |
| Sporcu | Kendi hesabı ve profil bilgilerine erişir. |
| Üye | Takım ve sporcuları favorilerine ekleyip takip eder. |

Yönetim panelindeki menüler giriş yapan kullanıcının rolüne göre otomatik sınırlandırılır.

## Temel kullanım akışı

1. Yönetici panelinden sezonu oluşturun veya aktif sezonu seçin.
2. Lig ya da turnuva oluşturun; türünü takım veya bireysel olarak belirleyin.
3. Lig gruplarını tanımlayın. Bireysel Bölge Liglerinde bölge ve sporcu kategorisini eşleştirin.
4. Takım, sporcu, hakem ve yetkili kayıtlarını ekleyin.
5. Katılımcıları ilgili organizasyonlara kaydedin.
6. Ligler için fikstürü, turnuvalar için eşleşme ağacını oluşturun.
7. Hakem hesabıyla set sonuçlarını girin.
8. Sonuçları, puan durumunu, sporcu/takım kartlarını ve sezon arşivini siteden takip edin.

## Dizin yapısı

```text
okculuk-ligi/
├── admin/                  # Rol bazlı yönetim paneli
├── assets/                 # CSS, JavaScript, logo ve yüklenen görseller
├── config/                 # Kurulumdan sonra oluşan ortam yapılandırması
├── includes/               # Ortak fonksiyonlar, header, footer ve sidebar
├── tools/                  # Demo kurulum ve yardımcı araçlar
├── install.php             # Kurulum sihirbazı
├── index.php               # Ana sayfa
├── ligler.php              # Lig listesi
├── turnuvalar.php          # Turnuva listesi
├── fikstur.php             # Fikstür ve filtreleme ekranı
├── sonuclar.php            # Karşılaşma sonuçları
├── takimlar.php            # Takım listesi ve filtreler
├── sporcular.php           # Sporcu listesi ve filtreler
└── README.md
```

## Önemli sayfalar

| Alan | Açıklama |
| --- | --- |
| `admin/ligler.php` | Sezon ve lig yönetimi |
| `admin/turnuvalar.php` | Turnuva, katılımcı ve eşleşme yönetimi |
| `admin/gruplar.php` | Grup, kategori ve fikstür işlemleri |
| `admin/maclar.php` | Karşılaşmaları filtreleme ve planlama |
| `admin/mac-skor.php` | Takım karşılaşmaları için set puanı girişi |
| `admin/turnuva-skor.php` | Turnuva karşılaşmalarının skor işlemleri |
| `admin/sporcular.php` | Sporcu, katılım ve giriş bilgileri |
| `admin/uyeler.php` | Üye hesapları yönetimi |
| `arsiv.php` | Resmileşmiş sezon sonuçları |

## Güvenlik ve canlı ortam notları

- Kurulum tamamlandıktan sonra `install.lock` dosyasını silmeyin.
- Yönetici ve örnek kullanıcı parolalarını ilk fırsatta değiştirin.
- `assets/uploads/` yalnızca uygulamanın yazabildiği kadar izinle yapılandırılmalıdır; herkese açık yazma izni vermeyin.
- Canlıya almadan önce HTTPS kullanın, veritabanını düzenli yedekleyin ve PHP hata gösterimini kapatın.
- `config/` altındaki veritabanı bilgilerini GitHub’a yüklemeyin.

## Sorun giderme

| Sorun | Kontrol edilmesi gerekenler |
| --- | --- |
| Veritabanı bağlantısı kurulamıyor | `config/database.php` bilgileri, veritabanı kullanıcısının yetkileri ve `pdo_mysql` eklentisi |
| Kurulum tekrar açılmıyor | `config/install.lock` dosyası kurulumun tamamlandığını gösterir; canlı veriler varken silmeyin |
| Görseller görünmüyor | `assets/uploads/` yazma izni, dosya yolu ve sunucunun dosya erişim izinleri |
| Mobil menü sorunlu | Tarayıcı önbelleğini temizleyin; güncel `assets/css/mobile-menu.css` ve `assets/js/mobile-menu.js` dosyalarının yüklendiğini doğrulayın |
| Türkçe karakterler bozuk | Veritabanı ve bağlantının `utf8mb4` kullandığını doğrulayın |

## Lisans

Bu depo için henüz ayrı bir lisans metni eklenmemiştir. GitHub’da yayımlamadan önce kullanım ve dağıtım koşullarınızı tanımlayan bir `LICENSE` dosyası eklemeniz önerilir.
