# btDocs Lisans Paneli

Framework'süz, session tabanlı basit PHP lisans yönetim paneli.

## Gereksinimler

- PHP 8.1+ (PDO MySQL)
- MySQL 8 / MariaDB
- Composer **gerekmez** (Sprint C0)

## Kurulum

1. IIS/Apache document root'u **proje köküne** verin (subdomain doğrudan buraya gelsin).
2. `config/.env.example` dosyasını `config/.env` olarak kopyalayın ve DB bilgilerini doldurun.
3. Migration:

```bash
php database/migrate.php
```

4. Admin kullanıcısı:

```bash
php seed_admin.php
```

Varsayılan: `admin` / `Ozt129103@`

5. Panele gidin: `/admin/login.php`

## Yapı

```
/
  index.php                 → login veya dashboard yönlendirme
  admin/                    → session korumalı yönetim sayfaları
  api/license-verify.php    → public API (api_key)
  includes/                 → db, auth, helpers (web'den engelli)
  config/                   → config.php + .env (web'den engelli)
  database/migrations/      → SQL şema
  seed_admin.php
```

## API

```
GET /api/license-verify.php?api_key=...
```

Örnek yanıt (geçerli):

```json
{"valid":true,"bitis_tarihi":"2027-01-01","kalan_gun":120}
```

## Lisans hatırlatma maili (C1)

1. `config/.env` içinde SMTP alanlarını doldurun.
2. Panelden: **Hatırlatmalar** → **Şimdi çalıştır**
3. Cron (günlük):

```bash
0 9 * * * php /path/to/check_license_expiry.php
```

Eşikler: `LICENSE_REMINDER_DAYS=30,14,7,3,1` — aynı eşik tekrar gönderilmez.

## Güvenlik notu

`web.config` (IIS) ve klasör `.htaccess` dosyaları `includes/`, `config/`, `database/` erişimini engeller. Document root'u bu proje kökü ise bu kurallar aktif olmalıdır.
