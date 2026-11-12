<?php

namespace App\Services\Erp;

use App\Services\NumartErpClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Ongkir lewat NUMART ERP (O-5, keputusan pemilik 2026-11-11): tarif dari jarak & waktu, dibagi per pesanan searah; subsidi toko dihitung ERP dari
 * margin (HPP tidak pernah dikirim ke web ini). Admin ongkir (verifikasi rute, penawaran, tutup perjalanan) ada di Backoffice ERP → Pengiriman Online.
 * Permintaan dikembalikan dalam bentuk baris `shipping_quotes` lama (request_json/snapshot_json/expires_at UTC) agar tampilan checkout tidak berubah.
 */
class ErpShipping
{
    public function __construct(private NumartErpClient $erp, private ErpBranchMap $branches) {}

    public function required(int $posCabang): bool
    {
        $erp = $this->branches->toErp($posCabang);

        return (bool) Cache::remember('numart_erp.ongkir.'.$erp, 30, fn () => $this->call(fn () => $this->erp->shippingStatus($erp)))['wajib'];
    }

    /** @return Collection<int, object> */
    public function quotes(int $userId, int $posCabang): Collection
    {
        $erp = $this->branches->toErp($posCabang);

        return collect($this->call(fn () => $this->erp->shippingQuotes($erp, (string) $userId)))->map(fn ($q) => $this->row($q, $posCabang));
    }

    /** @param array $context hasil ShippingService::context() */
    public function request(int $userId, int $posCabang, array $context): int
    {
        return (int) $this->call(fn () => $this->erp->requestShipping([
            'cabang_id' => $this->branches->toErp($posCabang), 'pelanggan_ref' => (string) $userId, 'sidik' => $context['fingerprint'],
            'nama' => $context['name'], 'hp' => $context['phone'], 'alamat' => $context['address'], 'metode_bayar' => $context['payment_method'], 'layanan' => $context['service'],
            'lat' => $context['customer_lat'], 'lng' => $context['customer_lng'], 'akurasi' => $context['gps_accuracy'],
            'baris' => array_map(fn ($l) => ['kode' => $l['code'], 'qty' => $l['qty'], 'harga' => $l['price']], $context['basket']['lines']),
        ]))['id'];
    }

    public function cancel(int $id, int $userId): void
    {
        $this->call(fn () => $this->erp->cancelShipping($id, (string) $userId));
    }

    /** @return array snapshot {route{service,start,end}, money{fare,subsidy,customer_fee}} untuk orders.shipping_snapshot */
    public function consume(int $id, int $userId, string $fingerprint, string $orderNo): array
    {
        return $this->call(fn () => $this->erp->consumeShipping($id, (string) $userId, $fingerprint, $orderNo))['snapshot'];
    }

    public function release(int $id, string $orderNo): void
    {
        $this->call(fn () => $this->erp->releaseShipping($id, $orderNo));
    }

    private function row(array $q, int $posCabang): object
    {
        $r = $q['permintaan'];

        return (object) ['id' => $q['id'], 'status' => $q['status'], 'branch_id' => $posCabang, 'fingerprint' => $q['sidik'], 'expires_at' => $q['berlaku_sampai'],
            'request_json' => json_encode(['name' => $r['nama'], 'phone' => $r['hp'], 'address' => $r['alamat'], 'payment_method' => $r['metode_bayar'], 'service' => $r['layanan'],
                // akurasi GPS ikut sidik (ShippingService::context) → wajib dikembalikan, kalau tidak penawaran tidak pernah "siap" di checkout
                'customer_lat' => $r['lat'], 'customer_lng' => $r['lng'], 'gps_accuracy' => isset($r['akurasi']) ? (float) $r['akurasi'] : null, 'basket' => ['subtotal' => $q['keranjang']['subtotal'],
                    'lines' => array_map(fn ($l) => ['code' => $l['kode'], 'name' => $l['nama'], 'qty' => $l['qty'], 'conversion' => $l['isi'], 'price' => $l['harga']], $q['keranjang']['baris'])]]),
            'snapshot_json' => $q['snapshot'] ? json_encode($q['snapshot']) : null];
    }

    /** Penolakan bisnis ERP (422/409) → \DomainException berpesan Indonesia seperti mesin lama; gangguan lain dilempar apa adanya. */
    private function call(\Closure $fn): array
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            if (in_array($e->getCode(), [409, 422], true)) {
                throw new \DomainException(preg_replace('/^\[[A-Z_]+\]\s*/', '', $e->getMessage()));
            }
            throw $e;
        }
    }
}
