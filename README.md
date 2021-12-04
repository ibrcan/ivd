# 🧾 İnternet Vergi Dairesi Sorgulamaları

Bu kütüphane, PHP aracılığıyla İnteraktif Vergi Dairesi Üzerinden Earsiv Fatura Sorgulama, Borç Sorgualama işlemleri yapılabilmektedir. Tamamen ücretsizdir ve aktif olarak geliştirilmektedir. 

### 🚩Kurulum
**Paket Kurulumu:**

    composer require ibrcan/ivd
    
### 📲 Destek ve Sorular 

Eğer bir sorun yaşıyorsanız veya proje hakkında bir sorunuz varsa lütfen [buradan](https://github.com/ibrcan/ivd/issues/new "buradan") bir kayıt oluşturun, yaşadığınız sorunu hep birlikte çözelim.


### 🚩Özellikler

- İnteraktif Vergi Dairesi oturumunu açma ve sonlandırma.
- Adıma Düzenlenmiş Faturaları Görüntüleme
- (Devam Edecek)

### 🚩Örnekler

**Giriş**

Bir client oluşturarak genel yapıyı projemize dahil ediyoruz.
```php
use ibrcan\ivd\interaktif;
$client = new interaktif();
```
Giriş bilgilerinizi chain fonksiyonlarla tanımlayabiliyorsunuz, bu production için geçerlidir. Daha sonra bağlantıyı sağlıyoruz.
```php
// Production environment
$client->setUsername("XXX")->setPassword("YYY");
$client->connect(); //Oturum açma başlatıldı.
```

**Earsiv Fatura Sorgulama**
Faturaların listelenmesi aşağıdaki şekilde yapılıyor:
```php
// Tüm faturaları listele
$client->getInvoicesMeFromAPI("2021-11-28", "2021-12-03");
```

