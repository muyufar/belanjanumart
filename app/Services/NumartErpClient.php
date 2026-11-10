<?php

/**
 * Adaptor untuk repo belanjanumart (Laravel 12) — salin ke app/Services/NumartErpClient.php.
 * Menggantikan NumartOrderSyncService yang menulis langsung ke tabel POS lama (temuan F-16):
 * sekarang semua lewat API NUMART ERP yang menegakkan harga, stok, idempotensi, dan jurnal.
 *
 * .env belanjanumart:
 *   NUMART_ERP_URL=https://erp.numart.id        (lokal: http://127.0.0.1:8077)
 *   NUMART_ERP_CLIENT_ID=nmc_xxx                 (dari `php artisan integrasi:buat-klien` di ERP)
 *   NUMART_ERP_CLIENT_SECRET=xxx
 * config/services.php:
 *   'numart_erp' => ['url' => env('NUMART_ERP_URL'), 'client_id' => env('NUMART_ERP_CLIENT_ID'), 'secret' => env('NUMART_ERP_CLIENT_SECRET')],
 *
 * Pemetaan alur belanjanumart → ERP:
 *   CheckoutService::placeOrder        → reserve($order->order_number, $cabang, $lines)        (ganti StockHold lokal)
 *   konfirmasi bayar / webhook BRI     → fulfill($order->order_number, [...])                    (ganti syncPaidOrder)
 *   pesanan batal / kedaluwarsa        → release($order->order_number)
 *   katalog & stok                     → catalog($cabang, $tier, $q)                             (ganti query langsung ke tabel barang)
 * Semua panggilan aman diulang (nomor pesanan = kunci idempotensi).
 */

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NumartErpClient
{
    public function __construct(
        private ?string $baseUrl = null,
        private ?string $clientId = null,
        private ?string $secret = null,
    ) {
        $this->baseUrl ??= rtrim((string) config('services.numart_erp.url'), '/');
        $this->clientId ??= (string) config('services.numart_erp.client_id');
        $this->secret ??= (string) config('services.numart_erp.secret');
    }

    /**
     * Katalog — ganti CatalogService (tabel barang/kategori/penjualan POS lama).
     * $extra: kategori_id, kode ("a,b"), id ("1,2"), urut (id|nama|terbaru|terlaris), terlaris_hari (7),
     *         stok_cabang_ids ("0,1,5" → id ERP; stok dijumlah, = marketplace.stock_branch_ids), hanya_ada_stok (1), per_halaman (≤ 100).
     */
    public function catalog(int $branchId, string $tier, ?string $q = null, int $page = 1, array $extra = []): array
    {
        return $this->send('GET', '/api/v1/integrasi/katalog?'.http_build_query(array_filter(['cabang_id' => $branchId, 'tipe_harga' => $tier, 'q' => $q, 'halaman' => $page] + $extra,
            fn ($v) => $v !== null && $v !== '')))['data'];
    }

    /** Kategori yang punya barang aktif di cabang tampilan — ganti CatalogService::categories / categoriesFromPusat. */
    public function categories(int $branchId): array
    {
        return $this->send('GET', '/api/v1/integrasi/kategori?cabang_id='.$branchId)['data'];
    }

    /** @param list<array{barang_id:int, level:int, qty:string}> $lines */
    public function reserve(string $orderNo, int $branchId, array $lines, int $ttlMinutes = 60): array
    {
        return $this->send('PUT', '/api/v1/integrasi/pesanan/'.rawurlencode($orderNo).'/reservasi', ['cabang_id' => $branchId, 'berlaku_menit' => $ttlMinutes, 'baris' => $lines])['data'];
    }

    public function release(string $orderNo): array
    {
        return $this->send('DELETE', '/api/v1/integrasi/pesanan/'.rawurlencode($orderNo).'/reservasi')['data'];
    }

    /**
     * @param  array{tipe_harga:string, metode_bayar:string, pelanggan_id?:int|null, ref_pembayaran?:string|null, ongkir?:string, diskon?:string, total_mitra?:string}  $data
     * @return array data nota (nota, grand_total, baris)
     */
    public function fulfill(string $orderNo, array $data): array
    {
        return $this->send('POST', '/api/v1/integrasi/pesanan/'.rawurlencode($orderNo).'/penuhi', $data)['data'];
    }

    /**
     * Status pengiriman & linimasa (O-1) — ganti OrderTrackingService::syncFromNumartInvoice (invoice_kurir POS lama).
     * `status_belanja` sudah memakai kunci OrderTrackingService (preparing/queued_for_delivery/out_for_delivery/delivered).
     * Perubahan juga dikirim lewat webhook `pengiriman.diperbarui`. Alamat kirim dikirim di fulfill(): ['pengiriman' => ['penerima','hp','alamat','catatan']].
     */
    public function delivery(string $orderNo): array
    {
        return $this->send('GET', '/api/v1/integrasi/pesanan/'.rawurlencode($orderNo).'/pengiriman')['data'];
    }

    /** Profil, WA, & URL QRIS cabang (O-3) — ganti NumartTokoService (tabel `toko`). */
    public function branches(): array
    {
        return $this->send('GET', '/api/v1/integrasi/cabang')['data'];
    }

    /** Minimal belanja & diskon online aktif hari ini (O-4) — ganti MarketplaceSettingsService & MarketplaceDiscountService. */
    public function settings(?int $branchId = null): array
    {
        return $this->send('GET', '/api/v1/integrasi/pengaturan'.($branchId ? '?cabang_id='.$branchId : ''))['data'];
    }

    /* ---- Member (O-2) — ganti NumartCustomerService (tabel `customer` & `invoice`) ---- */

    /** @param array{hp?:string, kartu?:string, id?:int} $by satu kriteria @return list<array> */
    public function findCustomers(array $by): array
    {
        return $this->send('GET', '/api/v1/integrasi/pelanggan?'.http_build_query($by))['data'];
    }

    public function customer(int $id): array
    {
        return $this->send('GET', '/api/v1/integrasi/pelanggan/'.$id)['data'];
    }

    /** Daftar member baru (tipe retail, kartu NUBLJ…). HP sudah terdaftar → RuntimeException 422 dengan pelanggan_id di pesan error. */
    public function registerCustomer(string $name, string $phone, ?string $email = null, ?string $address = null): array
    {
        return $this->send('POST', '/api/v1/integrasi/pelanggan', array_filter(['nama' => $name, 'hp' => $phone, 'email' => $email, 'alamat' => $address]))['data'];
    }

    /** @param array{nama?:string, email?:string, alamat?:string} $data */
    public function updateCustomer(int $id, array $data): array
    {
        return $this->send('PATCH', '/api/v1/integrasi/pelanggan/'.$id, $data)['data'];
    }

    public function customerHistory(int $id, int $limit = 30): array
    {
        return $this->send('GET', '/api/v1/integrasi/pelanggan/'.$id.'/riwayat?batas='.$limit)['data'];
    }

    /** Ajukan verifikasi member/warung: isi berkas foto (biner), dikirim base64 agar ikut ditandatangani HMAC. Status → pending. */
    public function submitVerification(int $id, string $ktpBinary, ?string $businessPhotoBinary = null): array
    {
        return $this->send('POST', '/api/v1/integrasi/pelanggan/'.$id.'/verifikasi', array_filter(['ktp_base64' => base64_encode($ktpBinary),
            'foto_warung_base64' => $businessPhotoBinary !== null ? base64_encode($businessPhotoBinary) : null]))['data'];
    }

    /* ---- Ongkir (O-5) — ganti mesin lokal Nugrosir\Shipping (shipping_campaigns/quotes/trips). Subsidi dihitung ERP dari margin; HPP tidak dikirim. ---- */

    /** @return array{aktif:bool, wajib:bool, berakhir:string|null, layanan:list<string>} wajib = checkout harus memakai penawaran ongkir */
    public function shippingStatus(int $branchId): array
    {
        return $this->send('GET', '/api/v1/integrasi/ongkir/status?cabang_id='.$branchId)['data'];
    }

    /** @return list<array> 10 permintaan terbaru pelanggan (id, status, siap, sidik, permintaan, keranjang, berlaku_sampai (UTC), snapshot{route,money}) */
    public function shippingQuotes(int $branchId, string $customerRef): array
    {
        return $this->send('GET', '/api/v1/integrasi/ongkir/permintaan?'.http_build_query(['cabang_id' => $branchId, 'pelanggan_ref' => $customerRef]))['data'];
    }

    /**
     * @param  array{cabang_id:int, pelanggan_ref:string, sidik:string, nama:string, hp:string, alamat:string, metode_bayar:string, layanan:string, lat:float, lng:float, akurasi?:float|null,
     *               baris:list<array{kode:string, qty:int, harga:int|string}>}  $data  sidik sama & masih aktif → permintaan yang sama
     */
    public function requestShipping(array $data): array
    {
        return $this->send('POST', '/api/v1/integrasi/ongkir/permintaan', $data)['data'];
    }

    public function cancelShipping(int $id, string $customerRef): array
    {
        return $this->send('DELETE', '/api/v1/integrasi/ongkir/permintaan/'.$id.'?pelanggan_ref='.rawurlencode($customerRef))['data'];
    }

    /** Dipanggil saat membuat pesanan; idempoten per nomor pesanan. Pesanan gagal dibuat → releaseShipping(). */
    public function consumeShipping(int $id, string $customerRef, string $fingerprint, string $orderNo): array
    {
        return $this->send('POST', '/api/v1/integrasi/ongkir/permintaan/'.$id.'/pakai', ['pelanggan_ref' => $customerRef, 'sidik' => $fingerprint, 'pesanan' => $orderNo])['data'];
    }

    public function releaseShipping(int $id, string $orderNo): array
    {
        return $this->send('DELETE', '/api/v1/integrasi/ongkir/permintaan/'.$id.'/pakai?pesanan='.rawurlencode($orderNo))['data'];
    }

    /** Verifikasi webhook dari ERP (route POST /api/webhooks/numart-erp di belanjanumart). Penerima wajib idempoten pakai X-Event-Id. */
    public static function verifyWebhook(string $secret, string $timestamp, string $path, string $body, string $signature): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, 'POST', $path, $body), strtolower($signature));
    }

    public static function sign(string $secret, string $timestamp, string $method, string $pathWithQuery, string $body): string
    {
        return hash_hmac('sha256', $timestamp."\n".strtoupper($method)."\n".$pathWithQuery."\n".$body, $secret);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $pathWithQuery, ?array $payload = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $ts = (string) time();
        $res = Http::timeout(15)->retry(2, 500, throw: false)->withHeaders([
            'Accept' => 'application/json', 'X-Client-Id' => $this->clientId, 'X-Timestamp' => $ts,
            'X-Signature' => self::sign((string) $this->secret, $ts, $method, $pathWithQuery, $body),
        ])->withBody($body, 'application/json')->send($method, $this->baseUrl.$pathWithQuery);

        return $this->decode($res);
    }

    private function decode(Response $res): array
    {
        $json = $res->json() ?? [];
        if (! $res->successful()) {
            $e = $json['error'] ?? ['code' => 'HTTP_'.$res->status(), 'message' => $res->body()];
            throw new RuntimeException("[{$e['code']}] {$e['message']}", $res->status());
        }

        return $json;
    }
}
