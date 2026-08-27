# Belanja Numart (Marketplace)

Web marketplace mobile-first terintegrasi dengan POS **Numart** dan pembayaran **BRI Virtual Account (BRIVA API)**.

## Persyaratan

- PHP 8.2+
- Composer
- MySQL (database Numart yang sama dengan POS)
- Kredensial BRI API (sandbox atau production)

## Instalasi

```bash
cd c:\laragon\www\belanja.numart.id
composer install
cp .env.example .env   # jika belum ada
php artisan key:generate
```

Buat database MySQL marketplace (sekali):

```sql
CREATE DATABASE belanja_numart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
```

## Konfigurasi `.env`

```env
APP_URL=http://belanja.numart.id.test

# Database marketplace (MySQL — auth, order, session, cache)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=belanja_numart
DB_USERNAME=root
DB_PASSWORD=

# Database POS Numart — samakan dengan aksi/koneksi.php
NUMART_DB_HOST=127.0.0.1
NUMART_DB_PORT=3306
NUMART_DB_DATABASE=nama_database_numart
NUMART_DB_USERNAME=root
NUMART_DB_PASSWORD=

# URL asset gambar produk di POS
NUMART_ASSET_URL=http://numart.test

# BRI API (dari portal developers.bri.co.id)
BRI_SANDBOX=true
BRI_MOCK=true
BRI_CLIENT_ID=
BRI_CLIENT_SECRET=
BRI_INSTITUTION_CODE=
BRI_BRIVA_NO=
BRI_WEBHOOK_SECRET=rahasia-webhook-anda

# Set BRI_MOCK=false setelah kredensial valid

MARKETPLACE_ADMIN_EMAILS=admin@example.com
NUMART_MARKETPLACE_KASIR_ID=1

# Radius km cabang
BRANCH_DUKUN_RADIUS_KM=12
BRANCH_TEGALREJO_RADIUS_KM=12
```

## Fitur customer & katalog

1. **Stok** — hanya produk yang punya stok &gt; 0 di gudang atau cabang toko (`MARKETPLACE_STOCK_BRANCH_IDS`, default `0,1,5`).
2. **Login member** — nomor kartu (`customer_kartu`) saja, tanpa password/WA/Fonnte.
3. **Katalog** — produk & stok hanya cabang pendaftaran member (`customer_cabang`).
4. **Harga** — retail → `barang_harga_grosir_1`, grosir → `barang_harga_grosir_2`; harga umum dicoret jika lebih mahal.
5. **Minimal belanja** — retail Rp 500.000, grosir Rp 1.000.000 (`MARKETPLACE_MIN_ORDER_*`).
6. **Checkout** — COD (member terverifikasi) atau transfer (QRIS & WA dari tabel `toko` Numart per `toko_cabang`, fallback `.env`).
7. **Verifikasi** — login pertama upload KTP (+ foto usaha untuk grosir); admin setujui di `/admin/verifikasi-member`.

### Setup WA aktivasi

Di `numart/aksi/marketplace-config.php` dan `.env` Laravel, set secret yang **sama**:

```env
NUMART_WA_API_URL=http://numart.test/api/marketplace-wa-send.php
NUMART_WA_API_SECRET=secret-panjang-acak
```

Pastikan `api/no.js` Fonnte di POS sudah berisi token.

### Setup diskon online

Jalankan sekali di MySQL Numart: `numart/db/marketplace_diskon.sql`, lalu kelola di menu **Diskon Online** (`marketplace-diskon.php`).

## Alur bisnis (tahap 1)

1. Katalog membaca `barang` cabang gudang (`barang_cabang = 0`), dengan filter stok agregat cabang.
2. Harga: tamu = umum, user terdaftar = retail (`barang_harga_grosir_1`).
3. Checkout memilih cabang: Numart Dukun (1) / Tegalrejo (5) jika dalam radius + stok cukup, else gudang (0).
4. Order pending → VA BRI dibuat via API.
5. Setelah bayar (webhook atau tombol cek) → `invoice` + `penjualan` ditulis ke Numart.

## Webhook BRI

Daftarkan URL di portal BRI:

```
POST https://belanja.numart.id/api/webhooks/bri/payment
Header: X-Webhook-Secret: {BRI_WEBHOOK_SECRET}
```

Sesuaikan payload dengan format notifikasi yang BRI kirim ke merchant Anda.

## Admin

Buat user Laravel lalu set `MARKETPLACE_ADMIN_EMAILS` ke email user tersebut.

```
/admin/pesanan
```

## Development

```bash
php artisan serve
```

Mode `BRI_MOCK=true`: VA dummy + tombol "Sudah bayar" langsung lunas tanpa hit API.

## Struktur penting

| Path | Fungsi |
|------|--------|
| `app/Services/FulfillmentService.php` | Routing stok cabang |
| `app/Services/Bri/BriPaymentService.php` | OAuth + create VA |
| `app/Services/NumartOrderSyncService.php` | Sinkron invoice POS |
| `config/marketplace.php` | Cabang & ongkir |
