<?php

namespace Tests\Feature;

use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockHold;
use App\Models\User;
use App\Services\AffiliateCommissionService;
use App\Services\CatalogService;
use App\Services\CheckoutService;
use App\Services\MarketplaceSettingsService;
use App\Services\NumartCustomerService;
use App\Services\NumartErpClient;
use App\Services\NumartOrderSyncService;
use App\Services\NumartTokoService;
use App\Services\OrderTrackingService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Jalur NUMART_SOURCE=erp diuji dengan ERP palsu (Http::fake). Koneksi DB `numart` (POS lama) sengaja diarahkan ke
 * berkas yang tidak ada: query apa pun ke POS lama di jalur erp akan langsung gagal.
 */
class NumartErpAdapterTest extends TestCase
{
    private bool $erpRejects = false;

    use RefreshDatabase;

    private const URL = 'http://erp.test';

    private const SECRET = 'rahasia-uji';

    /** @var list<array{method:string, path:string, query:array, body:array}> */
    private array $calls = [];

    private array $customers = [];

    /** ERP palsu: gagalkan reservasi stok (uji pelepasan penawaran ongkir). */
    private bool $failReserve = false;

    /** ERP palsu: kampanye ongkir cabang aktif & wajib (O-5). */
    private bool $shippingRequired = false;

    /** ERP palsu: akurasi GPS yang tersimpan di permintaan ongkir. */
    private ?float $quoteAccuracy = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'marketplace.numart_source' => 'erp',
            'services.numart_erp' => array_merge(config('services.numart_erp'), [
                'url' => self::URL, 'client_id' => 'nmc_uji', 'secret' => self::SECRET, 'branch_map' => '', 'cache_seconds' => 0,
            ]),
            'database.connections.numart' => ['driver' => 'sqlite', 'database' => storage_path('tidak-ada/pos-lama.sqlite'), 'prefix' => ''],
            'marketplace.stock_branch_ids' => [0, 1, 5],
        ]);
        $this->customers = [
            77 => $this->erpCustomer(77, 'Siti', '6281234567890', 'NUBLJ00000077', 'retail', 2),
        ];
        Http::fake(fn (HttpRequest $r) => $this->fakeErp($r));
    }

    /* ---------------- ERP palsu ---------------- */

    private function fakeErp(HttpRequest $r)
    {
        if ($this->erpRejects) {
            return Http::response(["error" => ["code" => "INVALID_SIGNATURE", "message" => "Autentikasi integrasi gagal."]], 401);
        }
        $parts = parse_url($r->url());
        $path = $parts['path'];
        parse_str($parts['query'] ?? '', $query);
        $pathWithQuery = $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
        $this->assertSame(NumartErpClient::sign(self::SECRET, $r->header('X-Timestamp')[0], $r->method(), $pathWithQuery, $r->body()), $r->header('X-Signature')[0], 'HMAC salah untuk '.$pathWithQuery);
        $this->assertSame('nmc_uji', $r->header('X-Client-Id')[0]);
        $body = json_decode($r->body() ?: '[]', true) ?: [];
        $this->calls[] = ['method' => $r->method(), 'path' => $path, 'query' => $query, 'body' => $body];
        $p = '/api/v1/integrasi';

        return match (true) {
            $path === "$p/cabang" => Http::response(['data' => [
                ['cabang_id' => 1, 'kode' => 'GDG', 'nama' => 'NU Grosir (Gudang)', 'telepon' => '0293-1', 'whatsapp' => '081111', 'qris' => 'http://erp.test/storage/qris/gdg.png'],
                ['cabang_id' => 2, 'kode' => 'DKN', 'nama' => 'NUMART Dukun', 'telepon' => null, 'whatsapp' => '082222', 'qris' => null],
                ['cabang_id' => 6, 'kode' => 'TGR', 'nama' => 'NUMART Tegalrejo', 'telepon' => null, 'whatsapp' => null, 'qris' => null],
            ]]),
            $path === "$p/katalog" => Http::response(['data' => $this->catalog($query)]),
            $path === "$p/kategori" => Http::response(['data' => [['id' => 9, 'nama' => 'Sembako', 'jumlah_barang' => 2]]]),
            $path === "$p/pengaturan" => Http::response(['data' => ['pengaturan' => ['min_order_retail' => '250000', 'min_order_grosir' => '750000'],
                'diskon' => [['barang_id' => 501, 'kode' => 'BRS5', 'cabang_id' => null, 'tipe' => 'persen', 'nilai' => '10.00', 'mulai' => null, 'selesai' => null]]]]),
            $path === "$p/pelanggan" && $r->method() === 'GET' => Http::response(['data' => array_values(array_filter($this->customers,
                fn ($c) => (isset($query['kartu']) && $c['kartu'] === $query['kartu']) || (isset($query['hp']) && $c['hp'] === $query['hp'])))]),
            $path === "$p/pelanggan" && $r->method() === 'POST' => Http::response(['data' => $this->erpCustomer(90, $body['nama'], $body['hp'], 'NUBLJ00000090', 'retail', 1)], 201),
            (bool) preg_match('#/pelanggan/(\d+)$#', $path, $m) => isset($this->customers[(int) $m[1]])
                ? Http::response(['data' => $this->customers[(int) $m[1]]])
                : Http::response(['error' => ['code' => 'NOT_FOUND', 'message' => 'Pelanggan tidak ditemukan.']], 404),
            (bool) preg_match('#/pelanggan/(\d+)/referral$#', $path, $m) => ($body['kode'] ?? '') === 'KDR-ERP001'
                ? Http::response(['data' => ($this->customers[(int) $m[1]] = ['kader' => ['kader_id' => 1, 'pelanggan_id' => 88, 'kode' => 'KDR-ERP001', 'aktif' => true], 'jenis_mitra' => 'referral'] + $this->customers[(int) $m[1]])])
                : Http::response(['error' => ['code' => 'VALIDATION', 'message' => 'Kode referral tidak valid atau kader nonaktif.']], 422),
            (bool) preg_match('#/pelanggan/(\d+)/riwayat$#', $path) => Http::response(['data' => [
                ['nota' => 'GDG-2611-0001', 'waktu' => '2026-11-10 10:00:00', 'total' => '120000.00', 'jenis_bayar' => 'nontunai', 'status' => 'selesai', 'online' => true, 'cabang' => 'GDG', 'nama_cabang' => 'Gudang'],
            ]]),
            $path === "$p/ongkir/status" => Http::response(['data' => ['aktif' => $this->shippingRequired, 'wajib' => $this->shippingRequired, 'berakhir' => '2026-12-11 00:00:00', 'layanan' => ['direct', 'scheduled', 'manual']]]),
            $path === "$p/ongkir/permintaan" && $r->method() === 'GET' => Http::response(['data' => [$this->erpQuote()]]),
            $path === "$p/ongkir/permintaan" && $r->method() === 'POST' => Http::response(['data' => ['id' => 7, 'status' => 'pending'] + $this->erpQuote()]),
            str_ends_with($path, '/pakai') && $r->method() === 'POST' => Http::response(['data' => ['status' => 'consumed', 'pesanan' => $body['pesanan']] + $this->erpQuote()]),
            str_ends_with($path, '/pakai') && $r->method() === 'DELETE' => Http::response(['data' => ['status' => 'approved'] + $this->erpQuote()]),
            (bool) preg_match('#/ongkir/permintaan/\d+$#', $path) && $r->method() === 'DELETE' => Http::response(['data' => ['status' => 'cancelled'] + $this->erpQuote()]),
            str_ends_with($path, '/reservasi') && $r->method() === 'PUT' && $this->failReserve => Http::response(['error' => ['code' => 'STOCK_INSUFFICIENT', 'message' => 'Stok tidak cukup.']], 422),
            str_ends_with($path, '/reservasi') && $r->method() === 'PUT' => Http::response(['data' => ['nomor_pesanan' => basename(dirname($path))]], 201),
            str_ends_with($path, '/reservasi') && $r->method() === 'DELETE' => Http::response(['data' => ['dilepas' => 1]]),
            str_ends_with($path, '/pembayaran') && $r->method() === 'PUT' => Http::response(['data' => ['id' => 5, 'nomor_pesanan' => basename(dirname($path)), 'status' => 'menunggu']], 201),
            str_ends_with($path, '/penuhi') => Http::response(['data' => ['nomor_pesanan' => basename(dirname($path)), 'nota' => 'GDG-2611-0042', 'grand_total' => $body['total_mitra'] ?? null]], 201),
            str_ends_with($path, '/pengiriman') => Http::response(['data' => ['status' => 'dikirim', 'status_belanja' => 'out_for_delivery', 'kurir' => 'Budi', 'catatan' => null, 'riwayat' => []]]),
            default => Http::response(['error' => ['code' => 'NOT_FOUND', 'message' => 'rute palsu tidak ada: '.$path]], 404),
        };
    }

    private function catalog(array $q): array
    {
        $tierPrice = ['umum' => ['BRS5' => '70000', 'MNYK' => '20000'], 'retail' => ['BRS5' => '65000', 'MNYK' => '18000'], 'grosir' => ['BRS5' => '60000', 'MNYK' => '17000']];
        $all = [
            ['id' => 501, 'kode' => 'BRS5', 'nama' => 'Beras 5 kg', 'kategori_id' => 9, 'kategori' => 'Sembako', 'gambar' => 'http://erp.test/storage/b.png', 'dibuat' => '2026-11-01 00:00:00', 'stok_tersedia_dasar' => '12.000'],
            ['id' => 502, 'kode' => 'MNYK', 'nama' => 'Minyak 1 L', 'kategori_id' => 9, 'kategori' => 'Sembako', 'gambar' => null, 'dibuat' => '2026-11-02 00:00:00', 'stok_tersedia_dasar' => '0.000'],
        ];
        $items = array_values(array_filter($all, function ($b) use ($q) {
            return (empty($q['kode']) || in_array($b['kode'], explode(',', $q['kode']), true))
                && (empty($q['id']) || in_array((string) $b['id'], explode(',', $q['id']), true))
                && (empty($q['hanya_ada_stok']) || (float) $b['stok_tersedia_dasar'] > 0);
        }));
        $tier = $q['tipe_harga'];

        return ['halaman' => (int) ($q['halaman'] ?? 1), 'total' => count($items), 'barang' => array_map(fn ($b) => $b + ['stok_gabungan' => true, 'satuan' => [
            ['level' => 1, 'nama' => 'PCS', 'isi' => '1', 'barcode' => null, 'harga' => $tierPrice[$tier][$b['kode']]],
            ['level' => 2, 'nama' => 'KARTON', 'isi' => '10', 'barcode' => null, 'harga' => null],
        ]], $items)];
    }

    private function erpCustomer(int $id, string $name, string $hp, string $kartu, string $tier, int $home): array
    {
        return ['pelanggan_id' => $id, 'nama' => $name, 'hp' => $hp, 'email' => null, 'alamat' => 'Muntilan', 'kartu' => $kartu, 'tipe_harga' => $tier,
            'cabang_asal_id' => $home, 'cabang_asal' => null, 'aktif' => true, 'poin' => 14,
            'verifikasi' => ['status' => 'none', 'diajukan_pada' => null, 'diputuskan_pada' => null, 'catatan' => null, 'ada_foto_warung' => false],
            'jenis_mitra' => null, 'nama_warung' => null, 'kader' => null, 'kode_kader' => null];   // ERP dengan mitra kader (2026-11-20)
    }

    /** Penawaran ongkir ERP (O-5): tarif 12.000 − subsidi 2.000 (dihitung ERP dari margin) = 10.000; waktu UTC. */
    private function erpQuote(): array
    {
        return ['id' => 7, 'status' => 'approved', 'siap' => true, 'sidik' => str_repeat('a', 64), 'pesanan' => null, 'berlaku_sampai' => gmdate('Y-m-d H:i:s', time() + 3600),
            'permintaan' => ['nama' => 'Pembeli', 'hp' => '081200000000', 'alamat' => 'Jl. Uji', 'metode_bayar' => 'transfer', 'layanan' => 'direct', 'lat' => -7.6, 'lng' => 110.2, 'akurasi' => $this->quoteAccuracy],
            'keranjang' => ['baris' => [['kode' => 'BRS5', 'nama' => 'Beras 5 kg', 'qty' => 2, 'isi' => 1, 'harga' => 63000]], 'subtotal' => 126000],
            'snapshot' => ['route' => ['service' => 'direct', 'start' => gmdate('Y-m-d H:i:s', time() + 86400), 'end' => gmdate('Y-m-d H:i:s', time() + 93600)],
                'money' => ['fare' => 12000, 'subsidy' => 2000, 'customer_fee' => 10000]]];
    }

    private function callsTo(string $suffix, ?string $method = null): array
    {
        return array_values(array_filter($this->calls, fn ($c) => str_ends_with($c['path'], $suffix) && (! $method || $c['method'] === $method)));
    }

    /* ---------------- Katalog, stok, pengaturan, toko ---------------- */

    public function test_catalog_reads_erp_with_pos_branch_mapping_and_member_compare_price(): void
    {
        // memberCabang null → stok gabungan marketplace.stock_branch_ids (POS 0,1,5 → ERP 1,2,6)
        $page = app(CatalogService::class)->paginateProducts(0, 1, null, 9, 20, 'page', null, null, null);

        $this->assertSame(2, $page->total());
        $beras = $page->getCollection()->firstWhere('barang_kode', 'BRS5');
        $this->assertSame(501, $beras->barang_id);                  // id barang ERP
        $this->assertSame(58500, $beras->price);                    // retail 65.000 − diskon online ERP 10%
        $this->assertTrue($beras->has_discount);
        $this->assertSame(12.0, $beras->stock);
        $this->assertNull($beras->barang_harga_beli);              // HPP tidak dibuka ERP (O-5)

        $first = $this->callsTo('/katalog')[0]['query'];
        $this->assertSame(['1', 'retail', '9', 'nama', '1,2,6'], [$first['cabang_id'], $first['tipe_harga'], $first['kategori_id'], $first['urut'], $first['stok_cabang_ids']]);
        $this->assertSame('umum', $this->callsTo('/katalog')[1]['query']['tipe_harga']);   // harga umum untuk harga coret member

        $this->assertSame('Sembako', app(CatalogService::class)->categoriesFromPusat()->first()->kategori_nama);
        $this->assertSame('1', $this->callsTo('/kategori')[0]['query']['cabang_id']);
        $this->assertNull(app(CatalogService::class)->productByKode(0, 'TIDAKADA', 0));
        $this->assertFalse(app(CatalogService::class)->hasStockForKode('MNYK'));
    }

    public function test_settings_and_branch_profile_come_from_erp(): void
    {
        $this->assertSame(['retail' => 250000, 'grosir' => 750000], app(MarketplaceSettingsService::class)->minOrderAmounts());
        $toko = app(NumartTokoService::class);
        $this->assertSame('NU Grosir (Gudang)', $toko->branchLabel(0));
        $this->assertSame('http://erp.test/storage/qris/gdg.png', $toko->qrisUrlForCabang(0));
        $this->assertSame('6282222', $toko->whatsAppForCabang(1));
    }

    /* ---------------- Member (O-2) ---------------- */

    public function test_member_login_by_card_links_legacy_account_instead_of_duplicating(): void
    {
        $legacy = User::factory()->create(['numart_customer_id' => 3121, 'member_card' => 'NUBLJ00000077', 'phone' => '6281234567890']);
        $svc = app(NumartCustomerService::class);

        $customer = $svc->findByKartu('nublj00000077');
        $user = $svc->loginOrCreateUserFromCustomer($customer);

        $this->assertSame($legacy->id, $user->id);
        $this->assertSame(77, (int) $user->erp_customer_id);
        $this->assertSame(3121, (int) $user->numart_customer_id);   // id POS lama tidak ditimpa (rollback)
        $this->assertSame(1, (int) $user->price_tier);
        $this->assertSame(1, (int) $user->member_cabang);           // cabang ERP 2 (DKN) → POS 1
        $this->assertSame(1, User::count());

        $this->assertSame(14, $svc->customerPoints(77));
        $this->assertSame('GDG-2611-0001', $svc->purchaseHistory(77)->first()->penjualan_invoice);
    }

    public function test_login_page_signs_in_erp_member_by_card(): void
    {
        $this->post('/masuk', ['card_number' => 'nublj00000077'])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame(77, (int) auth()->user()->erp_customer_id);
    }

    public function test_login_shows_friendly_error_instead_of_500_when_erp_rejects(): void
    {
        $this->erpRejects = true;   // klien ditolak ERP (ID/secret salah) — sebelumnya halaman 500

        $this->from('/masuk')->post('/masuk', ['card_number' => 'NUBLJ00000077'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors(['card_number' => 'Layanan member sedang bermasalah, silakan coba lagi beberapa saat lagi.']);

        $this->assertGuest();
    }

    public function test_login_works_when_erp_has_duplicate_member_with_same_phone(): void
    {
        // Data nyata ERP: satu orang punya dua kartu (NUDKNG… & "139") dengan HP sama beda format (62… / 08…).
        $this->customers[3681] = $this->erpCustomer(3681, 'Yusuf', '6282340715548', 'NUDKNG0000111', 'grosir', 2);
        $this->customers[5742] = $this->erpCustomer(5742, 'Yusuf', '082340715548', '139', 'retail', 6);
        $other = User::factory()->create(['erp_customer_id' => 5742, 'member_card' => '139', 'phone' => '6282340715548']);

        $this->post('/masuk', ['card_number' => 'NUDKNG0000111'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertAuthenticated();
        $user = auth()->user();
        $this->assertNotSame($other->id, $user->id);
        $this->assertSame(3681, (int) $user->erp_customer_id);
        $this->assertSame('NUDKNG0000111', $user->member_card);
        $this->assertNull($user->phone);                                    // HP sudah dipakai akun kartu 139
        $this->assertSame('6282340715548', $other->fresh()->phone);

        // Login ulang: akun & email buatan tetap sama (tidak dibuat ulang tiap login).
        $email = $user->email;
        auth()->logout();
        $this->post('/masuk', ['card_number' => 'NUDKNG0000111'])->assertSessionHasNoErrors();
        $this->assertSame($user->id, auth()->id());
        $this->assertSame($email, auth()->user()->email);
        $this->assertSame(2, User::count());
    }

    public function test_register_new_member_goes_to_erp(): void
    {
        $c = app(NumartCustomerService::class)->createCustomerInNumart('Budi', '0812 9999 0000', 'Salam');

        $this->assertSame(90, $c->customer_id);
        $this->assertSame('NUBLJ00000090', $c->customer_kartu);
        $this->assertSame('6281299990000', $this->callsTo('/pelanggan', 'POST')[0]['body']['hp']);
    }

    /* ---------------- Checkout → lunas → kirim ---------------- */

    public function test_checkout_reserves_in_erp_instead_of_local_stock_hold(): void
    {
        $user = User::factory()->create(['price_tier' => 0]);
        $order = app(CheckoutService::class)->placeOrder([
            'name' => 'Pembeli', 'phone' => '081200000000', 'address' => 'Jl. Uji', 'payment_method' => 'transfer',
            'customer_lat' => -7.6, 'customer_lng' => 110.2, 'gps_valid' => '1',
        ], [['barang_id' => 501, 'barang_kode' => 'BRS5', 'qty' => 2]], $user);

        $this->assertSame('erp', $order->numart_source);
        $this->assertSame(126000, (int) $order->subtotal);           // 70.000 − 10% = 63.000 × 2
        $this->assertSame(0, StockHold::count());
        $reserve = $this->callsTo('/reservasi', 'PUT')[0];
        $this->assertSame("/api/v1/integrasi/pesanan/{$order->order_number}/reservasi", $reserve['path']);
        $this->assertSame(['cabang_id' => 1, 'berlaku_menit' => 120, 'baris' => [['barang_id' => 501, 'level' => 1, 'qty' => '2']]], $reserve['body']);
    }

    public function test_paid_order_is_fulfilled_in_erp_with_discount_and_delivery_address(): void
    {
        $order = $this->order(['numart_source' => 'erp']);

        $nota = app(NumartOrderSyncService::class)->syncPaidOrder($order);

        $this->assertSame('GDG-2611-0042', $nota);
        $order->refresh();
        $this->assertSame(['GDG-2611-0042', 'processing', 'preparing', 'erp'], [$order->numart_invoice, $order->status, $order->tracking_status, $order->numart_source]);
        $body = $this->callsTo('/penuhi')[0]['body'];
        $this->assertSame('umum', $body['tipe_harga']);
        $this->assertSame('nontunai', $body['metode_bayar']);
        $this->assertSame('14000', $body['diskon']);                 // ERP 140.000 − dibayar 126.000
        $this->assertSame('126000', $body['total_mitra']);
        $this->assertSame(['penerima' => 'Pembeli', 'hp' => '081200000000', 'alamat' => 'Jl. Uji', 'catatan' => 'Pesanan online '.$order->order_number], $body['pengiriman']);
        $this->assertArrayNotHasKey('pelanggan_id', $body);
        $this->assertCount(0, $this->callsTo('/reservasi'));         // sudah direservasi saat checkout

        // idempoten lokal: panggilan ulang tidak memanggil ERP lagi
        app(NumartOrderSyncService::class)->syncPaidOrder($order->fresh());
        $this->assertCount(1, $this->callsTo('/penuhi'));
    }

    public function test_transition_order_from_pos_era_is_reserved_before_fulfill_and_tier_mismatch_drops_customer(): void
    {
        $user = User::factory()->create(['erp_customer_id' => 77, 'price_tier' => 2]);   // ERP: retail, pesanan: grosir
        $order = $this->order(['numart_source' => null, 'user_id' => $user->id, 'price_tier' => 2, 'subtotal' => 120000, 'grand_total' => 120000], 60000);

        app(NumartOrderSyncService::class)->syncPaidOrder($order);

        $this->assertSame([['barang_id' => 501, 'level' => 1, 'qty' => '2']], $this->callsTo('/reservasi', 'PUT')[0]['body']['baris']);
        $this->assertArrayNotHasKey('pelanggan_id', $this->callsTo('/penuhi')[0]['body']);
        $this->assertSame('0', $this->callsTo('/penuhi')[0]['body']['diskon']);
    }

    public function test_tracking_reads_erp_delivery(): void
    {
        $order = $this->order(['numart_source' => 'erp', 'numart_invoice' => 'GDG-2611-0042', 'status' => 'processing', 'tracking_status' => 'preparing']);

        $order = app(OrderTrackingService::class)->syncFromNumartInvoice($order);

        $this->assertSame('out_for_delivery', $order->tracking_status);
        $this->assertSame('Kurir: Budi', $order->tracking_note);
    }

    public function test_erp_orders_leave_commission_to_erp_and_kader_admin_points_to_erp(): void
    {
        $kader = User::factory()->create(['marketplace_role' => User::ROLE_AFFILIATE_KADER, 'affiliate_active' => true, 'affiliate_code' => 'KDR-UJI']);
        $buyer = User::factory()->create(['affiliate_kader_id' => $kader->id, 'partner_kind' => 'prioritas']);
        $this->assertNull(app(AffiliateCommissionService::class)->accrueForOrder($this->order(['numart_source' => 'erp', 'user_id' => $buyer->id, 'status' => 'processing'])));
        $this->assertSame(0, AffiliateCommission::count(), 'komisi pesanan ERP dijurnal ERP, bukan di toko');

        $admin = User::factory()->create(['marketplace_role' => User::ROLE_BACKOFFICE]);
        $this->actingAs($admin)->get('/backoffice/affiliate/kader')->assertOk()->assertSee('Backoffice NUMART ERP');
        $this->actingAs($admin)->post('/backoffice/affiliate/kader', ['member_card' => 'NUBLJ00000077'])->assertSessionHas('error');
        $this->actingAs($admin)->post('/backoffice/affiliate/komisi', ['warung' => 1, 'prioritas' => 2, 'referral' => 1, 'basis' => 'subtotal',
            'margin_share_min' => 0.5, 'margin_share_max' => 1.5])->assertSessionHas('error');
        $this->actingAs($kader)->get('/mitra-kader')->assertOk()->assertSee('Kader Saya');
    }

    public function test_login_copies_kader_relation_from_erp_and_referral_code_goes_to_erp(): void
    {
        $this->customers[88] = ['kode_kader' => 'KDR-ERP001'] + $this->erpCustomer(88, 'Kader Siti', '6281200000088', 'NUBLJ00000088', 'retail', 2);
        $this->customers[77] = ['kader' => ['kader_id' => 1, 'pelanggan_id' => 88, 'kode' => 'KDR-ERP001', 'aktif' => true], 'jenis_mitra' => 'warung', 'nama_warung' => 'Warung Siti']
            + $this->customers[77];

        // Kader login → peran & kode dari ERP
        $this->post('/masuk', ['card_number' => 'NUBLJ00000088'])->assertSessionHasNoErrors();
        $kader = auth()->user();
        $this->assertSame([User::ROLE_AFFILIATE_KADER, 'KDR-ERP001', true], [$kader->marketplace_role, $kader->affiliate_code, (bool) $kader->affiliate_active]);
        auth()->logout();

        // Mitra login → tertaut ke akun toko kader (untuk checkout atas nama mitra)
        $this->post('/masuk', ['card_number' => 'NUBLJ00000077'])->assertSessionHasNoErrors();
        $this->assertSame([$kader->id, 'warung', 'Warung Siti'], [auth()->user()->affiliate_kader_id, auth()->user()->partner_kind, auth()->user()->warung_name]);
        auth()->logout();

        // Member lain memakai kode referral → diteruskan ke ERP
        $this->customers[79] = $this->erpCustomer(79, 'Budi', '6281200000079', 'NUBLJ00000079', 'umum', 1);
        $this->post('/masuk', ['card_number' => 'NUBLJ00000079'])->assertSessionHasNoErrors();
        $this->post('/profil/kode-referral', ['referral_code' => 'KDR-SALAH'])->assertSessionHas('error', 'Kode referral tidak valid atau kader nonaktif.');
        $this->post('/profil/kode-referral', ['referral_code' => 'kdr-erp001'])->assertSessionHas('success');
        $this->assertSame([$kader->id, 'referral'], [auth()->user()->fresh()->affiliate_kader_id, auth()->user()->fresh()->partner_kind]);
        $this->assertSame('KDR-ERP001', array_slice($this->callsTo('/referral', 'POST'), -1)[0]['body']['kode']);
    }

    public function test_older_erp_without_kader_fields_keeps_local_kader_link(): void
    {
        $kader = User::factory()->create(['marketplace_role' => User::ROLE_AFFILIATE_KADER, 'affiliate_code' => 'KDR-LOKAL1']);
        User::factory()->create(['erp_customer_id' => 77, 'member_card' => 'NUBLJ00000077', 'affiliate_kader_id' => $kader->id, 'partner_kind' => 'warung']);
        $this->customers[77] = array_diff_key($this->customers[77], array_flip(['jenis_mitra', 'nama_warung', 'kader', 'kode_kader']));   // ERP versi lama

        $this->post('/masuk', ['card_number' => 'NUBLJ00000077'])->assertSessionHasNoErrors();
        $this->assertSame([$kader->id, 'warung'], [auth()->user()->affiliate_kader_id, auth()->user()->partner_kind], 'urutan rilis aman: ikatan lokal tidak terhapus');
    }

    public function test_export_for_erp_contains_kaders_mitra_and_rates(): void
    {
        $kader = User::factory()->create(['marketplace_role' => User::ROLE_AFFILIATE_KADER, 'affiliate_code' => 'KDR-LAMA01', 'member_card' => 'NUBLJ00000011', 'erp_customer_id' => 11]);
        User::factory()->create(['affiliate_kader_id' => $kader->id, 'partner_kind' => 'warung', 'member_card' => 'NUBLJ00000012', 'warung_name' => 'Warung A']);
        \App\Models\AffiliateReferral::create(['affiliate_user_id' => $kader->id, 'member_card' => 'NUBLJ00000013', 'partner_kind' => 'prioritas',
            'customer_name' => 'Toko B', 'customer_phone' => '6281200000013']);
        $file = tempnam(sys_get_temp_dir(), 'kdr');

        $this->artisan('affiliate:ekspor-erp', ['file' => $file])->assertSuccessful();
        $d = json_decode(file_get_contents($file), true);
        $this->assertSame([['KDR-LAMA01', 11]], array_map(fn ($k) => [$k['kode'], $k['pelanggan_id']], $d['kader']));
        $this->assertSame([['NUBLJ00000012', 'warung', 'NUBLJ00000011'], ['NUBLJ00000013', 'prioritas', 'NUBLJ00000011']],
            array_map(fn ($m) => [$m['kartu'], $m['jenis'], $m['kader_kartu']], $d['mitra']));
        $this->assertArrayHasKey('referral', $d['tarif']);
    }

    public function test_admin_cancel_releases_erp_reservation(): void
    {
        $order = $this->order(['numart_source' => 'erp']);

        app(\App\Services\OrderPaymentService::class)->cancel($order);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertCount(1, $this->callsTo('/reservasi', 'DELETE'));
    }

    public function test_shipping_quote_goes_to_erp_and_checkout_consumes_it_with_order_number(): void
    {
        $this->shippingRequired = true;
        $user = User::factory()->create(['price_tier' => 0]);
        $svc = app(ShippingService::class);
        $this->assertTrue($svc->required(0), 'status ongkir dari ERP (cabang POS 0 → ERP 1)');
        $input = ['name' => 'Pembeli', 'phone' => '081200000000', 'address' => 'Jl. Uji', 'payment_method' => 'transfer', 'shipping_service' => 'direct',
            'customer_lat' => -7.6, 'customer_lng' => 110.2, 'gps_valid' => '1'];
        $ctx = $svc->context([['barang_kode' => 'BRS5', 'qty' => 2]], $user, $input);
        $this->assertSame(7, $svc->request($user, 0, $ctx));
        $req = $this->callsTo('/ongkir/permintaan', 'POST')[0]['body'];
        $this->assertSame([1, (string) $user->id, $ctx['fingerprint'], [['kode' => 'BRS5', 'qty' => 2, 'harga' => 63000]]], [$req['cabang_id'], $req['pelanggan_ref'], $req['sidik'], $req['baris']]);
        $this->assertArrayNotHasKey('hpp', $req, 'web belanja tidak mengirim/menerima HPP');

        $q = $svc->quotes($user, 0)->first();   // bentuk baris shipping_quotes lama → tampilan checkout tidak berubah
        $this->assertSame([7, 'approved', 126000, 10000], [$q->id, $q->status, json_decode($q->request_json, true)['basket']['subtotal'], json_decode($q->snapshot_json, true)['money']['customer_fee']]);

        $order = app(CheckoutService::class)->placeOrder($input + ['shipping_quote_id' => 7], [['barang_id' => 501, 'barang_kode' => 'BRS5', 'qty' => 2]], $user);
        $use = $this->callsTo('/ongkir/permintaan/7/pakai', 'POST')[0]['body'];
        $this->assertSame([(string) $user->id, $ctx['fingerprint'], $order->order_number], [$use['pelanggan_ref'], $use['sidik'], $use['pesanan']]);
        $this->assertSame([10000, 136000, 2000, 7], [(int) $order->shipping_fee, (int) $order->grand_total, $order->shipping_snapshot['money']['subsidy'], $order->shipping_snapshot['erp_quote_id']]);
        $this->assertNull($order->shipping_quote_id);

        $svc->cancel(7, $user);
        $this->assertCount(1, $this->callsTo('/ongkir/permintaan/7', 'DELETE'));
    }

    /** Regresi E2E 2026-11-12: akurasi GPS ikut sidik; tanpa dikembalikan dari ERP penawaran tidak pernah "siap" di checkout. */
    public function test_quote_request_round_trips_gps_accuracy_so_checkout_fingerprint_matches(): void
    {
        $this->shippingRequired = true;
        $this->quoteAccuracy = 25.0;
        $user = User::factory()->create(['price_tier' => 0]);
        $svc = app(ShippingService::class);
        $cart = [['barang_kode' => 'BRS5', 'qty' => 2]];
        $ctx = $svc->context($cart, $user, ['name' => 'Pembeli', 'phone' => '081200000000', 'address' => 'Jl. Uji', 'payment_method' => 'transfer',
            'shipping_service' => 'direct', 'customer_lat' => '-7.6000', 'customer_lng' => '110.2000', 'gps_accuracy' => '25', 'gps_valid' => '1']);

        $saved = json_decode($svc->quotes($user, 0)->first()->request_json, true);   // seperti CheckoutController::create
        $again = $svc->context($cart, $user, $saved + ['shipping_service' => $saved['service']]);

        $this->assertEquals(25, $saved['gps_accuracy']);
        $this->assertSame($ctx['fingerprint'], $again['fingerprint']);
    }

    public function test_failed_order_releases_consumed_erp_shipping_quote(): void
    {
        $this->failReserve = true;
        $this->shippingRequired = true;
        $user = User::factory()->create(['price_tier' => 0]);
        try {
            app(CheckoutService::class)->placeOrder(['name' => 'Pembeli', 'phone' => '081200000000', 'address' => 'Jl. Uji', 'payment_method' => 'transfer', 'shipping_service' => 'direct',
                'customer_lat' => -7.6, 'customer_lng' => 110.2, 'gps_valid' => '1', 'shipping_quote_id' => 7], [['barang_id' => 501, 'barang_kode' => 'BRS5', 'qty' => 2]], $user);
            $this->fail('reservasi gagal harus menggagalkan pesanan');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Stok', $e->getMessage());
        }
        $this->assertSame(0, Order::count());
        $consume = $this->callsTo('/ongkir/permintaan/7/pakai', 'POST')[0]['body']['pesanan'];
        $release = $this->callsTo('/ongkir/permintaan/7/pakai', 'DELETE')[0];
        $this->assertSame($consume, $release['query']['pesanan'], 'penawaran dilepas untuk nomor pesanan yang gagal');
    }

    public function test_shipping_context_marks_cost_unknown_on_erp(): void
    {
        $user = User::factory()->create(['price_tier' => 0]);
        $ctx = app(ShippingService::class)->context([['barang_kode' => 'BRS5', 'qty' => 1]], $user, [
            'name' => 'A', 'phone' => '0812', 'address' => 'B', 'payment_method' => 'transfer', 'shipping_service' => 'direct', 'customer_lat' => -7.6, 'customer_lng' => 110.2,
        ]);

        $this->assertFalse($ctx['basket']['cost_known']);   // O-5: margin dihitung ERP saat menerbitkan penawaran
        $this->assertSame(0, $ctx['basket']['cost']);
    }

    /* ---------------- Webhook ERP ---------------- */

    public function test_webhook_updates_tracking_idempotently(): void
    {
        $order = $this->order(['numart_source' => 'erp', 'numart_invoice' => 'GDG-2611-0042', 'status' => 'processing', 'tracking_status' => 'preparing']);
        $payload = ['id' => 55, 'event' => 'pengiriman.diperbarui', 'data' => ['nomor_pesanan' => $order->order_number, 'nota' => 'GDG-2611-0042',
            'status' => 'antre', 'status_belanja' => 'queued_for_delivery', 'gagal' => false, 'kurir' => 'Budi', 'catatan' => 'Kurir: Budi', 'waktu' => now()->toIso8601String()]];

        $this->webhook($payload, '55')->assertOk()->assertJson(['message' => 'tracking queued_for_delivery']);
        $this->assertSame('queued_for_delivery', $order->fresh()->tracking_status);

        $order->fresh()->update(['tracking_status' => 'preparing']);
        $this->webhook($payload, '55')->assertOk()->assertJson(['duplikat' => true]);
        $this->assertSame('preparing', $order->fresh()->tracking_status);   // event yang sama tidak diproses ulang
    }

    public function test_proof_upload_and_cod_checkout_go_to_erp_verification_queue(): void
    {
        $order = $this->order(['numart_source' => 'erp', 'status' => 'pending_transfer']);
        app(CheckoutService::class)->submitPaymentProof($order, 'payments/proofs/bukti.jpg');

        $call = $this->callsTo('/pembayaran', 'PUT')[0];
        $this->assertSame("/api/v1/integrasi/pesanan/{$order->order_number}/pembayaran", $call['path']);
        $this->assertSame(['transfer', '126000', 'Pembeli', '081200000000'], [$call['body']['metode'], $call['body']['total'], $call['body']['pembeli'], $call['body']['hp']]);
        $this->assertStringEndsWith('/storage/payments/proofs/bukti.jpg', $call['body']['bukti_url']);
        $this->assertStringStartsWith('http', $call['body']['bukti_url']);
        $this->assertSame(['umum', 'nontunai', '126000', '14000'], [$call['body']['nota']['tipe_harga'], $call['body']['nota']['metode_bayar'], $call['body']['nota']['total_mitra'], $call['body']['nota']['diskon']]);
        $this->assertCount(0, $this->callsTo('/penuhi'), 'nota baru dibuat setelah toko menerima di ERP');

        $user = User::factory()->create(['price_tier' => 0]);
        $user->forceFill(['member_verification_status' => 'approved'])->save();   // COD = member terverifikasi
        try {
            $cod = app(CheckoutService::class)->placeOrder([
                'name' => 'Pembeli COD', 'phone' => '081200000001', 'address' => 'Jl. Uji', 'payment_method' => 'cod',
                'customer_lat' => -7.6, 'customer_lng' => 110.2, 'gps_valid' => '1',
            ], [['barang_id' => 501, 'barang_kode' => 'BRS5', 'qty' => 1]], $user);
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->markTestIncomplete('COD tidak tersedia untuk pengguna uji: '.$e->getMessage());
        }
        $codCall = collect($this->callsTo('/pembayaran', 'PUT'))->firstWhere('path', "/api/v1/integrasi/pesanan/{$cod->order_number}/pembayaran");
        $this->assertSame('cod', $codCall['body']['metode']);
        $this->assertArrayNotHasKey('bukti_url', $codCall['body']);
        $this->assertSame('tunai', $codCall['body']['nota']['metode_bayar']);
    }

    public function test_rejected_payment_webhook_reopens_upload_or_cancels_cod(): void
    {
        $order = $this->order(['numart_source' => 'erp', 'status' => 'proof_submitted', 'payment_proof_path' => 'payments/proofs/a.jpg']);
        $this->webhook(['id' => 70, 'event' => 'pembayaran.ditolak', 'data' => ['nomor_pesanan' => $order->order_number, 'metode' => 'transfer', 'alasan' => 'Uang belum masuk']], '70')->assertOk();
        $order->refresh();
        $this->assertSame(['pending_transfer', null], [$order->status, $order->payment_proof_path]);
        $this->assertStringContainsString('Uang belum masuk', $order->tracking_note);

        $cod = $this->order(['numart_source' => 'erp', 'status' => 'pending_cod', 'payment_method' => 'cod']);
        $this->webhook(['id' => 71, 'event' => 'pembayaran.ditolak', 'data' => ['nomor_pesanan' => $cod->order_number, 'metode' => 'cod', 'alasan' => 'Alamat palsu']], '71')->assertOk();
        $this->assertSame('cancelled', $cod->fresh()->status);
        $this->assertCount(1, $this->callsTo('/reservasi', 'DELETE'));

        // sudah jadi nota → penolakan terlambat diabaikan
        $paid = $this->order(['numart_source' => 'erp', 'status' => 'processing', 'numart_invoice' => 'GDG-1']);
        $this->webhook(['id' => 72, 'event' => 'pembayaran.ditolak', 'data' => ['nomor_pesanan' => $paid->order_number, 'metode' => 'transfer']], '72')->assertOk();
        $this->assertSame('processing', $paid->fresh()->status);
    }

    public function test_webhook_rejects_bad_signature_and_handles_fulfilled_and_cancelled(): void
    {
        $order = $this->order(['numart_source' => 'erp', 'status' => 'proof_submitted']);
        $data = ['id' => 60, 'event' => 'pesanan.dipenuhi', 'data' => ['nomor_pesanan' => $order->order_number, 'nota' => 'GDG-2611-0050', 'total' => '126000.00', 'ref_pembayaran' => null]];

        $this->webhook($data, '60', 'salah')->assertStatus(401);
        $this->webhook($data, '60')->assertOk();
        $this->assertSame(['GDG-2611-0050', 'processing'], [$order->fresh()->numart_invoice, $order->fresh()->status]);

        $this->webhook(['id' => 61, 'event' => 'pesanan.dibatalkan', 'data' => ['nomor_pesanan' => $order->order_number, 'nota' => 'GDG-2611-0050', 'alasan' => 'stok rusak']], '61')->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertStringContainsString('stok rusak', $order->fresh()->tracking_note);

        $this->webhook(['id' => 62, 'event' => 'pesanan.dipenuhi', 'data' => ['nomor_pesanan' => 'MP-TIDAKADA']], '62')->assertOk()->assertJson(['message' => 'pesanan tidak dikenal — diabaikan']);
    }

    /* ---------------- bantuan ---------------- */

    private function webhook(array $payload, string $eventId, ?string $secret = null)
    {
        $body = json_encode($payload);
        $ts = (string) time();

        return $this->call('POST', '/api/webhooks/numart-erp', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CLIENT_ID' => 'nmc_uji', 'HTTP_X_TIMESTAMP' => $ts, 'HTTP_X_EVENT_ID' => $eventId,
            'HTTP_X_SIGNATURE' => NumartErpClient::sign($secret ?? self::SECRET, $ts, 'POST', '/api/webhooks/numart-erp', $body),
        ], $body);
    }

    private function order(array $attrs = [], int $unitPrice = 63000): Order
    {
        $order = Order::create($attrs + [
            'order_number' => 'MP-'.strtoupper(\Illuminate\Support\Str::random(10)),
            'price_tier' => 0, 'fulfillment_cabang' => 0, 'customer_name' => 'Pembeli', 'customer_phone' => '081200000000',
            'customer_address' => 'Jl. Uji', 'subtotal' => 126000, 'shipping_fee' => 0, 'discount' => 0, 'grand_total' => 126000,
            'payment_method' => 'transfer', 'status' => 'proof_submitted', 'tracking_status' => 'awaiting_payment',
        ]);
        OrderItem::create(['order_id' => $order->id, 'barang_id' => 501, 'barang_kode' => 'BRS5', 'barang_nama' => 'Beras 5 kg', 'qty' => 2,
            'unit_price' => $unitPrice, 'line_total' => 2 * $unitPrice, 'harga_beli' => 0, 'satuan_id' => 0, 'konversi_isi' => 1]);

        return $order->fresh();
    }
}
