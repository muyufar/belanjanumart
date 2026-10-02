<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CatalogService;
use App\Services\CheckoutService;
use App\Services\MemberContextService;
use App\Services\PricingService;
use App\Services\ShippingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nugrosir\Shipping;
use Tests\TestCase;

class ShippingPilotTest extends TestCase
{
    use RefreshDatabase;

    private Shipping $engine;
    private User $customer;
    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        require_once app_path('Support/NugrosirShipping.php');
        $this->engine = new Shipping(DB::connection()->getPdo());
        $this->customer = User::factory()->create();
        DB::table('shipping_campaigns')->where('id', 1)->update(['enabled' => true,
            'ends_at' => gmdate('Y-m-d H:i:s', time() + 30 * 86400),
            'store_json' => json_encode(['lat' => -7.6, 'lng' => 110.2, 'address' => 'Toko uji'])]);
        $this->data = ['fingerprint' => hash('sha256', 'basket'), 'name' => 'Pembeli', 'phone' => '08123456789',
            'address' => 'Alamat uji Muntilan', 'service' => 'direct', 'payment_method' => 'transfer',
            'basket' => ['subtotal' => 100000, 'cost' => 91000, 'cost_known' => true, 'lines' => []]];
    }

    private function route(string $service = 'direct', float $km = 6, int $minutes = 28): array
    {
        $start = (new \DateTimeImmutable('tomorrow 11:00', new \DateTimeZone('Asia/Jakarta')))->setTimezone(new \DateTimeZone('UTC'));
        return ['service' => $service, 'km' => $km, 'minutes' => $minutes, 'note' => 'Rute diverifikasi',
            'start' => $start->format('Y-m-d H:i:s'), 'end' => $start->modify('+2 hours')->format('Y-m-d H:i:s'), 'manual_pay' => 0];
    }

    private function request(?array $data = null): int
    {
        return $this->engine->request($this->customer->id, 0, $data ?? $this->data);
    }

    private function approve(int $id, float $distance = 3, float $weight = 2): int
    {
        return $this->engine->approve([$id], $this->route('direct', 2 * $distance, (int) ceil(10 + 6 * $distance)),
            [$id => ['km' => $distance, 'kg' => $weight, 'lat' => -7.6, 'lng' => 110.2]], 'pos:1', 0);
    }

    public function test_direct_and_batch_pay_match_agreed_examples(): void
    {
        $p = Shipping::policy();
        foreach ([1 => 8000, 2 => 9000, 3 => 12000, 4 => 15000, 5 => 17000] as $d => $pay) {
            $this->assertSame($pay, Shipping::payout(2 * $d, 10 + 6 * $d, 1, $p));
        }
        $this->assertSame(14000, Shipping::payout(6, 35, 2, $p));
        $this->assertSame(18000, Shipping::payout(8, 45, 3, $p));
        $this->assertSame(24000, Shipping::payout(12, 55, 3, $p));
        $this->assertSame(17000, array_sum(Shipping::split(17000, 3)));
    }

    public function test_promo_preserves_courier_and_minimum_contribution_after_qris(): void
    {
        $m = Shipping::money($this->data, 8000, Shipping::policy(), 300000);
        $this->assertSame(2000, $m['subsidy']);
        $this->assertSame(6000, $m['customer_fee']);
        $this->assertSame(8000, $m['courier_allocation']);
        $this->assertSame(742, $m['payment']);
        $this->assertSame(3258, $m['contribution']);
        $d = $this->data;
        $d['basket']['cost'] = 96000;
        $this->assertSame(0, Shipping::money($d, 8000, Shipping::policy(), 300000)['subsidy']);
        $d['basket']['cost_known'] = false;
        $this->assertNull(Shipping::money($d, 8000, Shipping::policy(), 300000)['contribution']);
        $this->assertSame(0, Shipping::money($d, 8000, Shipping::policy(), 300000)['subsidy']);
        $this->assertSame(100, Shipping::money($this->data, 8000, Shipping::policy(), 100)['subsidy']);
        $this->assertSame(0, Shipping::payment(100000, 'transfer', Shipping::policy()));
        $this->assertSame(701, Shipping::payment(100001, 'transfer', Shipping::policy()));
        $this->assertSame(0, Shipping::payment(500000, 'transfer', array_replace(Shipping::policy(), ['payment_category' => 'umi'])));
    }

    public function test_five_km_is_allowed_but_more_requires_manual_quote(): void
    {
        $id = $this->request();
        $this->approve($id, 5);
        $d = $this->data;
        $d['fingerprint'] = hash('sha256', 'different');
        $next = $this->request($d);
        $this->expectException(\DomainException::class);
        $this->approve($next, 5.001);
    }

    public function test_overweight_is_rejected(): void
    {
        $id = $this->request();
        $this->expectException(\DomainException::class);
        $this->approve($id, 3, 10.01);
    }

    public function test_missing_coordinates_are_not_priced_as_zero_distance(): void
    {
        $id = $this->request();
        $this->expectException(\DomainException::class);
        $this->engine->approve([$id], $this->route(), [$id => ['km' => 3, 'kg' => 2]], 'pos:1', 0);
    }

    public function test_heavy_long_distance_can_only_use_explicit_manual_offer(): void
    {
        $id = $this->request();
        $route = $this->route('manual', 14, 60);
        $route['manual_pay'] = 45000;
        $trip = $this->engine->approve([$id], $route, [$id => ['km' => 7, 'kg' => 30, 'lat' => -7.6, 'lng' => 110.2]], 'pos:1', 0);
        $this->assertSame(45000, (int) DB::table('shipping_trips')->where('id', $trip)->value('agreed_pay'));
    }

    public function test_expired_offer_cannot_checkout(): void
    {
        $id = $this->request();
        $this->approve($id);
        DB::table('shipping_quotes')->where('id', $id)->update(['expires_at' => '2020-01-01 00:00:00']);
        $this->expectException(\DomainException::class);
        $this->engine->consume($id, $this->customer->id, 0, $this->data['fingerprint']);
    }

    public function test_http_cancel_enforces_ownership_and_guest_authentication(): void
    {
        $id = $this->request();
        $this->post('/checkout/ongkir/'.$id.'/batal')->assertRedirect('/masuk');
        $other = User::factory()->create();
        $this->actingAs($other)->post('/checkout/ongkir/'.$id.'/batal')->assertSessionHas('error');
        $this->assertSame('pending', DB::table('shipping_quotes')->where('id', $id)->value('status'));
        $this->actingAs($this->customer)->post('/checkout/ongkir/'.$id.'/batal')->assertRedirect('/checkout');
        $this->assertSame('cancelled', DB::table('shipping_quotes')->where('id', $id)->value('status'));
    }

    public function test_approved_snapshot_survives_policy_change_and_pause(): void
    {
        $id = $this->request();
        $this->approve($id);
        DB::table('shipping_campaigns')->where('id', 1)->update(['enabled' => false, 'policy_json' => json_encode(array_replace(Shipping::policy(), ['minimum' => 99000]))]);
        $s = $this->engine->consume($id, $this->customer->id, 0, $this->data['fingerprint']);
        $this->assertSame(12000, $s['money']['courier_allocation']);
        $this->assertSame('muntilan-2026-10-v1', $s['policy']['version']);
        $this->assertTrue(app(ShippingService::class)->required(0));
    }

    public function test_other_customer_cannot_consume_quote(): void
    {
        $id = $this->request();
        $this->approve($id);
        $this->expectException(\DomainException::class);
        $this->engine->consume($id, $this->customer->id + 1, 0, $this->data['fingerprint']);
    }

    public function test_changed_cart_address_or_payment_fingerprint_is_rejected(): void
    {
        $id = $this->request();
        $this->approve($id);
        $this->expectException(\DomainException::class);
        $this->engine->consume($id, $this->customer->id, 0, hash('sha256', 'changed'));
    }

    public function test_quote_cannot_be_reused(): void
    {
        $id = $this->request();
        $this->approve($id);
        $this->engine->consume($id, $this->customer->id, 0, $this->data['fingerprint']);
        $this->expectException(\DomainException::class);
        $this->engine->consume($id, $this->customer->id, 0, $this->data['fingerprint']);
    }

    public function test_expired_quote_can_be_replaced_and_releases_promo(): void
    {
        $id = $this->request();
        $this->approve($id);
        DB::table('shipping_quotes')->where('id', $id)->update(['expires_at' => '2020-01-01 00:00:00']);
        $new = $this->request();
        $this->assertNotSame($id, $new);
        $this->assertSame(0, $this->engine->budgets()['promo']);
    }

    public function test_batch_budget_and_total_weight_are_checked(): void
    {
        $ids = $verified = [];
        for ($i = 0; $i < 3; $i++) {
            $data = array_replace($this->data, ['service' => 'scheduled', 'fingerprint' => hash('sha256', 'batch'.$i)]);
            $ids[] = $id = $this->request($data);
            $verified[$id] = ['km' => 3, 'kg' => 3, 'lat' => -7.6, 'lng' => 110.2];
        }
        DB::table('shipping_campaigns')->where('id', 1)->update(['fallback_limit' => 100]);
        try {
            $this->engine->approve($ids, $this->route('scheduled', 8, 45), $verified, 'pos:1', 0);
            $this->fail('Budget exhaustion must reject the trip');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Cadangan', $e->getMessage());
        }
        $this->assertSame(0, DB::table('shipping_trips')->count());
        DB::table('shipping_campaigns')->where('id', 1)->update(['fallback_limit' => 300000]);
        $trip = $this->engine->approve($ids, $this->route('scheduled', 8, 45), $verified, 'pos:1', 0);
        $this->assertSame(18000, (int) DB::table('shipping_trips')->where('id', $trip)->value('agreed_pay'));
        $sum = DB::table('shipping_quotes')->where('trip_id', $trip)->get()->sum(fn ($q) => json_decode($q->snapshot_json, true)['money']['courier_allocation']);
        $this->assertSame(18000, $sum);
        $this->assertSame(18000, $this->engine->budgets()['fallback']);
    }

    public function test_cancellation_after_work_keeps_agreed_pay_and_cannot_settle_twice(): void
    {
        $id = $this->request();
        $trip = $this->approve($id);
        $this->engine->cancelQuote($id, 'customer:'.$this->customer->id, $this->customer->id);
        $this->assertSame(0, $this->engine->budgets()['promo']);
        $actual = ['km' => 1, 'minutes' => 10, 'expenses' => 2000, 'note' => 'Sudah bekerja, pesanan dibatalkan', 'no_work' => false];
        $this->engine->settle($trip, 0, 7, $actual, 'pos:1');
        $this->assertSame(14000, (int) DB::table('shipping_trips')->where('id', $trip)->value('actual_pay'));
        $this->assertSame(14000, (int) DB::table('shipping_quotes')->where('id', $id)->value('actual_allocation'));
        $this->expectException(\DomainException::class);
        $this->engine->settle($trip, 0, 7, $actual, 'pos:1');
    }

    public function test_cancellation_without_work_is_zero_and_releases_reservations(): void
    {
        $id = $this->request();
        $trip = $this->approve($id);
        $this->engine->settle($trip, 0, 0, ['km' => 0, 'minutes' => 0, 'expenses' => 0, 'note' => 'Belum ada pekerjaan', 'no_work' => true], 'pos:1');
        $this->assertSame('void', DB::table('shipping_trips')->where('id', $trip)->value('status'));
        $this->assertSame(['promo' => 0, 'fallback' => 0], $this->engine->budgets());
    }

    public function test_real_checkout_uses_current_hpp_conversion_and_quote_atomically(): void
    {
        config(['database.connections.numart' => config('database.connections.sqlite')]);
        DB::purge('numart');
        Schema::connection('numart')->create('barang', function (Blueprint $t) {
            $t->integer('barang_id'); $t->decimal('barang_harga_beli_rata', 12, 2);
        });
        DB::connection('numart')->table('barang')->insert(['barang_id' => 1, 'barang_harga_beli_rata' => 4550]);
        $product = (object) ['barang_id' => 1, 'barang_kode' => 'TEST', 'barang_nama' => 'Barang uji', 'price' => 10000,
            'barang_stock' => 100, 'satuan_isi_1' => 2, 'barang_harga_beli' => 4000, 'satuan_id' => 1];
        $this->mock(CatalogService::class, fn ($m) => $m->shouldReceive('productByKode')->andReturn($product));
        $this->mock(PricingService::class, fn ($m) => $m->shouldReceive('tierForUser')->andReturn(0));
        $this->mock(MemberContextService::class, function ($m) {
            $m->shouldReceive('memberCabangId')->andReturn(0);
            $m->shouldReceive('branchLabel')->andReturn('Nugrosir');
            $m->shouldReceive('minOrderAmount')->andReturn(50000);
        });
        $cart = [['barang_id' => 1, 'barang_kode' => 'TEST', 'qty' => 10]];
        $input = ['name' => 'Pembeli', 'phone' => '08123456789', 'address' => 'Alamat Muntilan', 'payment_method' => 'transfer', 'shipping_service' => 'direct'];
        $context = app(ShippingService::class)->context($cart, $this->customer, $input);
        $this->assertSame(91000, $context['basket']['cost']);
        $id = $this->request($context);
        $this->approve($id, 1);
        $order = app(CheckoutService::class)->placeOrder($input + ['shipping_quote_id' => $id], $cart, $this->customer);
        $this->assertSame(6000, $order->shipping_fee);
        $this->assertSame(106000, $order->grand_total);
        $this->assertSame(9100, $order->items[0]->harga_beli);
        $this->assertSame($id, $order->shipping_quote_id);
        $this->assertSame($order->id, (int) DB::table('shipping_quotes')->where('id', $id)->value('order_id'));
        $this->assertSame(20, (int) DB::table('stock_holds')->where('order_id', $order->id)->value('qty_pcs'));
        // A failure after quote consumption must restore the reservation and not create an order.
        $next = $this->request($context);
        $this->approve($next, 1);
        \App\Models\Order::creating(function () { throw new \RuntimeException('Simulated insert failure'); });
        try {
            app(CheckoutService::class)->placeOrder($input + ['shipping_quote_id' => $next], $cart, $this->customer);
            $this->fail('Order insertion must fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated insert failure', $e->getMessage());
        } finally {
            \App\Models\Order::flushEventListeners();
        }
        $this->assertSame('approved', DB::table('shipping_quotes')->where('id', $next)->value('status'));
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('stock_holds')->count());
        DB::disconnect('numart');
    }
}
