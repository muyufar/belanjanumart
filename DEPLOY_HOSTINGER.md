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

## Deployment Git gagal: `.htaccess` / `index.php` would be overwritten

Pesan Hostinger:

```text
pull: error: The following untracked working tree files would be overwritten by merge:
	.htaccess
	index.php
```

**Penyebab:** Di folder deploy sudah ada `.htaccess` dan `index.php` (dibuat manual / upload), tapi **belum** masuk Git. Saat `git pull`, Git menolak menimpa file untracked.

**Perbaikan (pilih satu):**

### Via File Manager hPanel

1. Buka folder deploy Git (biasanya `public_html` atau path yang di-set di menu GIT).
2. **Hapus** file `.htaccess` dan `index.php` di **root folder repo** (bukan di `public/`).
3. Kembali ke **Advanced → GIT** → **Deploy** lagi.

File yang sama akan diambil dari repo GitHub (memang sengaja ada untuk Hostinger).

### Via SSH

```bash
cd /path/ke/folder/deploy   # sesuaikan path di pengaturan GIT Hostinger
rm -f .htaccess index.php
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
```

Lalu **Deploy** ulang dari hPanel jika perlu.

> Jangan hapus `public/.htaccess` dan `public/index.php` — itu entry point Laravel.

## Masih 403?

- File Manager: apakah `vendor/` ada? (tanpa ini PHP bisa gagal)
- Permission folder `storage`, `bootstrap/cache` → **775**
- Nonaktifkan sementara **Hotlink protection** / **Access Manager** di hPanel
- Cek **Error log** di hPanel → **Advanced** → **Error pages** / **Logs**
