# Geleneksel Türk Okçuluğu Bölge Ligleri

Geleneksel Türk okçuluğu için geliştirilmiş, PHP ve MySQL tabanlı açık kaynaklı lig ve turnuva yönetim sistemidir. Takım Ligleri, Bireysel Bölge Ligleri, takım turnuvaları ve bireysel turnuvalar aynı uygulama içinde yönetilir.

Sistem; sezon, grup, fikstür, karşılaşma, puan durumu, turnuva ağacı, sporcu ve takım kartları, kurumsal içerikler, duyurular ve rol bazlı yönetim panelini kapsar.

## Öne çıkan özellikler

- Takım Ligi ve Bireysel Bölge Ligleri yönetimi
- Takım ve bireysel turnuvalar için 16/32 katılımcılı eşleşme ağacı
- A–C ve B–D sıralamasına göre otomatik ilk tur eşleşmeleri
- Sezon oluşturma, aktif sezon yönetimi, sezon kapanışı ve resmî sonuç arşivi
- Takım grupları ile bireysel bölge-kategori gruplarının bağımsız yönetimi
- Takım ligleri için çift devreli otomatik fikstür
- Bireysel ligler için grup bazlı, 10 haftalık atış fikstürü
- Set bazlı karşılaşma skoru, eşitlik bozma ve canlı karşılaşma detayları
- Bireysel atışlarda sporcu başına haftalık 0–21 puan girişi
- Takım ve sporcu kartları, profil fotoğrafı ve takım logosu yükleme
- Favori takım ve sporcu takibi
- Lig, grup, kategori ve sonuç filtreleri
- Puan durumu tablolarında sütun bazlı artan/azalan sıralama
- Duyuru, haber, yönetmelik ve kurumsal içerik yönetimi
- Mobil uyumlu ana menü ve yönetim paneli

## Organizasyon türleri

| Tür | Katılımcı | Temel yapı |
| --- | --- | --- |
| Takım Ligi | Takımlar | Grup, haftalık fikstür ve puan durumu |
| Bireysel Bölge Ligleri | Sporcular | Bölge + kategori grubu, haftalık atış puanı |
| Takım Turnuvası | Takımlar | 16 veya 32 katılımcı, eleme ağacı |
| Bireysel Turnuva | Sporcular | 16 veya 32 katılımcı, eleme ağacı |

## Kategoriler ve puanlama

### Sporcu kategorileri

Sporcu kaydında kategori, doğum tarihi ve cinsiyete göre otomatik hesaplanır:

| Koşul | Kategori |
| --- | --- |
| 8–11 yaş | Minikler |
| 12–15 yaş | Yıldızlar |
| 16–17 yaş | Gençler |
| 18 yaş ve üzeri erkek | Yetişkin |
| 18 yaş ve üzeri kadın | Kadınlar |

Bireysel lig grupları yalnızca aynı kategorideki sporcuları kabul eder. Örneğin “Marmara Bölgesi > Kadınlar” grubuna yalnızca Kadınlar kategorisindeki sporcular kaydedilebilir.

### Karşılaşma ve atış kuralları

- Bir sporcu, bir sette 7 ok atar.
- Bir sporcunun tek set puanı en fazla **21** olabilir.
- Takım karşılaşmalarında set galibi, iki takımın o setteki sporcu puanlarının toplamıyla belirlenir.
- Set eşit bitemez. Eşitlikte iki taraf için ayrı eşitlik bozma atışı girilir; galip belirlenene kadar devam edilir.
- Eşitlik bozma puanları yalnızca set galibini belirler, sezonluk atış averajına eklenmez.
- Takım Ligi sıralaması önce set puanına, ardından atış puanına göre yapılır.
- Bireysel Bölge Ligleri, takım organizasyonlarından bağımsız toplam puan ve atış sayısı tutar.

## Gereksinimler

- PHP 8.0 veya üzeri
- MySQL 5.7+ ya da MariaDB 10.3+
- Apache, Nginx veya PHP destekli eşdeğer bir web sunucusu
- PHP `pdo_mysql` eklentisi
- `assets/uploads/` klasörü için uygulamanın yazma izni

XAMPP kullanıyorsanız proje klasörünü `htdocs` altına yerleştirmeniz yeterlidir.

## Kurulum

1. Paketi web sunucunuzun yayın klasörüne çıkarın.
2. Hosting panelinizden boş bir MySQL veritabanı ve bu veritabanına yetkili kullanıcı oluşturun.
3. Tarayıcıdan `https://alanadiniz.com/proje-klasoru/install.php` adresini açın.
4. Veritabanı bilgilerini ve ilk tam yönetici hesabını girin.
5. Kurulum tamamlandıktan sonra giriş yapıp sezon ve organizasyonları oluşturun.

Kurulum, yeni veritabanı oluşturmaya çalışmaz; yalnızca önceden oluşturulmuş bir veritabanına bağlanır. Kurulum sonunda aşağıdaki ortam dosyaları oluşturulur:

```text
config/config.php
config/database.php
config/install.lock
```

Bu dosyalar sunucuya özeldir. Temiz kurulum paketine ve Git deposuna eklenmemelidir.

> Uyarı: `install.php`, seçtiğiniz veritabanındaki uygulama tablolarını oluşturur veya yeniden düzenler. Canlı veritabanında işlem yapmadan önce yedek alın.

## Paket türleri

| Paket | Kullanım amacı |
| --- | --- |
| `okculuk-ligi-kurulum.zip` | Temiz kurulum; demo veri içermez. |
| `okculuk-ligi-demo-kurulum.zip` | Kurulum sonrası örnek sezon, ligler, takımlar, sporcular, görseller ve sonuçlanmış karşılaşmalar üretir. |

Demo paketi yalnızca deneme ve sunum içindir. Canlı kullanıma geçmeden önce örnek hesapları, parolaları ve içerikleri gözden geçirin.

## Roller ve yetkiler

| Rol | Erişim kapsamı |
| --- | --- |
| Tam Yönetici | Sistemin tüm alanları, içerikler, kullanıcılar ve sınırlı yönetici hesapları |
| Sınırlı Yönetici | Organizasyon Yönetimi ve Katılımcılar alanları; duyurular, haberler, yönetmelikler ve kurumsal sayfalara erişemez |
| Hakem | Karşılaşmalar, Skor Girişi ve profil |
| Takım Yetkilisi – Yönetici | En fazla iki kendi takımını ve kendi takım sporcularını yönetir |
| Takım Yetkilisi – Antrenör | Kendi takımı için sporcu işlemleri yapar |
| Sporcu | Kendi profil ve hesap bilgileri |
| Üye | Profil, favori takım ve sporcu takibi |

Sınırlı yönetici hesapları yalnızca tam yönetici tarafından **Yöneticiler** ekranından oluşturulur.

## Yönetim akışı

1. **Ligler ve Sezon** sayfasından sezonu oluşturun veya aktif sezonu seçin.
2. Takım Ligi ya da Bireysel Bölge Ligleri oluşturun.
3. **Gruplar ve Fikstür** ekranından grup ekleyin.
   - Takım grubu: Bölge adı ve Takım Ligi seçimi
   - Bireysel grup: Bölge adı, Bireysel Lig, kategori ve atış alanı
4. Takımları, sporcuları, hakemleri ve takım yetkililerini ekleyin.
5. Sporcuları uygun bireysel grup veya turnuvalara kaydedin.
6. Lig fikstürünü ya da turnuva ağacını oluşturun.
7. Skor Girişi ekranından set ya da bireysel haftalık atış puanlarını girin.
8. Sonuçları, puan tablolarını, kartları ve sezon arşivini siteden takip edin.

## Önemli sayfalar

| Sayfa | Açıklama |
| --- | --- |
| `admin/ligler.php` | Sezon, lig ve kadro dönemi yönetimi |
| `admin/gruplar-ve-fikstur.php` | Takım/bireysel grup ve lig fikstürü yönetimi |
| `admin/turnuvalar.php` | Turnuva oluşturma, katılımcı yerleşimi ve ağaç işlemleri |
| `admin/maclar.php` | Karşılaşma ve atış planı yönetimi |
| `admin/mac-skor.php` | Takım karşılaşması ve bireysel lig skor giriş seçimi |
| `admin/bireysel-skor.php` | Bireysel grup haftalık atış puanı girişi |
| `admin/sporcular.php` | Sporcu, kategori, organizasyon ve hesap yönetimi |
| `admin/takimlar.php` | Takım ve logo yönetimi |
| `admin/yoneticiler.php` | Sınırlı yönetici hesapları (yalnızca tam yönetici) |
| `admin/sayfalar.php` | Hakkımızda, İletişim ve Destekleyenler içerikleri |
| `sonuclar.php` | Lig ve turnuva sonuçları, lig/grup/kategori filtreleri |
| `arsiv.php` | Resmileştirilmiş sezon sonuçları |

`admin/gruplar.php` eski adresi korunur ancak otomatik olarak `admin/gruplar-ve-fikstur.php` sayfasına yönlenir.

## Dizin yapısı

```text
okculuk-ligi/
├── admin/                  # Rol bazlı yönetim paneli
├── assets/                 # Stil, betik, logo ve yüklenen görseller
├── config/                 # Kurulum sonrası oluşan sunucu yapılandırması
├── includes/               # Ortak fonksiyonlar ve sayfa parçaları
├── sql/                    # Başlangıç veritabanı şeması
├── tools/                  # Demo kurulum aracı ve yardımcı dosyalar
├── install.php             # Kurulum sihirbazı
├── index.php               # Ana sayfa
├── fikstur.php             # Fikstür ekranı
├── sonuclar.php            # Karşılaşma sonuçları
├── takimlar.php            # Takım listesi
├── sporcular.php           # Sporcu listesi
└── README.md
```

## Güvenlik ve canlı ortam notları

- Kurulumdan sonra `config/install.lock` dosyasını silmeyin.
- İlk yönetici ve demo hesaplarının parolalarını değiştirin.
- `assets/uploads/` klasörünü yalnızca uygulamanın yazabileceği izinlerle yapılandırın.
- Canlı ortamda HTTPS kullanın.
- Veritabanını düzenli olarak yedekleyin.
- PHP hata gösterimini canlı ortamda kapatın.
- `config/` altındaki veritabanı bilgilerini asla GitHub’a yüklemeyin.

## Sorun giderme

| Sorun | Kontrol edilmesi gerekenler |
| --- | --- |
| Veritabanı bağlantısı kurulamıyor | Veritabanı bilgileri, kullanıcı yetkileri ve `pdo_mysql` eklentisi |
| Kurulum tekrar açılmıyor | `config/install.lock` dosyası; canlı veriler varken silmeyin |
| Görseller görünmüyor | `assets/uploads/` izinleri, dosya yolu ve sunucu erişim izinleri |
| Bireysel sporcu grubu seçilemiyor | Sporcu kategorisi ile grup kategorisinin aynı olduğunu kontrol edin |
| Mobil menü sorunlu | Tarayıcı önbelleğini temizleyin ve güncel stil/betik dosyalarının yüklendiğini doğrulayın |
| Türkçe karakterler bozuk | Veritabanı ve bağlantının `utf8mb4` kullandığını doğrulayın |

## Lisans

Bu proje, [GNU General Public License v3.0](LICENSE) ile lisanslanmıştır.

Telif hakkı © 2026 **Sencer BALCIOĞLU**. Sistemin ilk sürümü Sencer BALCIOĞLU tarafından geliştirilmiştir.

GPL-3.0; sistemin kullanılmasına, incelenmesine, değiştirilmesine, dağıtılmasına ve ücretli hizmet kapsamında sunulmasına izin verir. Projeyi veya değiştirilmiş bir sürümünü dağıtanlar, ilgili kaynak kodunu da GPL-3.0 koşullarıyla erişilebilir tutmalıdır.
