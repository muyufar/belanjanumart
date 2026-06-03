# Deploy Laravel di Hostinger (belanja.numart.id)

## Gejala

- **403 Forbidden** di `/` → web root salah atau tidak ada `index.php` / rewrite.
- **404 skateboard Hostinger** di `/index.php` → file Laravel tidak ada di folder yang dilayani.

## Struktur yang benar

```
domains/belanja.numart.id/public_html/   ← sering ini = web root
├── .env
├── .htaccess          ← dari root repo (arahkan ke public/)
├── index.php          ← dari root repo (fallback)
├── app/
├── vendor/            ← wajib: composer install
├── public/
│   ├── .htaccess
│   └── index.php      ← entry point Laravel
└── ...
```

## Opsi A — Ubah document root (paling disarankan)

1. hPanel → **Websites** → **belanja.numart.id** → **Manage**
2. **Advanced** → **Website root**
3. Set ke folder **`public`** di dalam project, contoh:
   - `.../public_html/public` jika repo di-clone ke `public_html`
   - `.../belanjanumart/public` jika repo di folder sebelahnya
4. Simpan, tunggu 2 menit, buka `https://belanja.numart.id/`

## Opsi B — Repo di `public_html` (tanpa ubah document root)

1. Clone / pull GitHub ke `public_html`:
   ```bash
   cd ~/domains/belanja.numart.id/public_html
   git pull origin main
   ```
2. Pastikan ada file **`.htaccess`** dan **`index.php`** di `public_html` (root repo, bukan hanya di `public/`).
3. SSH:
   ```bash
   cd ~/domains/belanja.numart.id/public_html
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   chmod -R 775 storage bootstrap/cache
   find storage bootstrap/cache -type d -exec chmod 775 {} \;
   ```
4. PHP di hPanel: **8.2** atau **8.3**.

## Opsi C — Document root tetap `public_html`, app di luar

```bash
cd ~/domains/belanja.numart.id
git clone https://github.com/muyufar/belanjanumart.git app
cd app
composer install --no-dev
cp .env.example .env
# edit .env
php artisan key:generate
php artisan migrate --force
```

Lalu di File Manager: **kosongkan** `public_html`, salin **semua isi** folder `app/public/` ke `public_html/`.

Edit `public_html/index.php` baris require:

```php
require __DIR__.'/../app/vendor/autoload.php';
// ...
$app = require_once __DIR__.'/../app/bootstrap/app.php';
```

`.env` tetap di `domains/belanja.numart.id/app/.env`.

## .env production

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://belanja.numart.id
NUMART_ASSET_URL=https://demopos.numartmagelang.com/posgit
```

## Cek berhasil

| URL | Harapan |
|-----|---------|
| `https://belanja.numart.id/` | UI marketplace atau error Laravel (bukan 403/404 Hostinger) |
| `https://belanja.numart.id/index.php` | Sama (boleh redirect ke `/`) |

## Masih 403?

- File Manager: apakah `vendor/` ada? (tanpa ini PHP bisa gagal)
- Permission folder `storage`, `bootstrap/cache` → **775**
- Nonaktifkan sementara **Hotlink protection** / **Access Manager** di hPanel
- Cek **Error log** di hPanel → **Advanced** → **Error pages** / **Logs**
